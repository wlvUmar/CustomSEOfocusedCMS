<?php
// path: ./models/OpenRouter.php

class OpenRouter {
    private const API_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    public const MODELS = [
        'deepseek/deepseek-chat'          => 'DeepSeek Chat (fast, cheap)',
        'openai/gpt-4o-mini'              => 'GPT-4o Mini (balanced)',
        'anthropic/claude-3.5-haiku'      => 'Claude Haiku (fast)',
        'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B (open)',
    ];

    public static function getApiKey(): string {
        return defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : (getenv('OPENROUTER_API_KEY') ?: '');
    }

    /**
     * Send a chat completion request to OpenRouter.
     *
     * @param array  $messages   [['role' => 'system'|'user'|'assistant', 'content' => '...'], ...]
     * @param string $model      OpenRouter model slug (e.g. deepseek/deepseek-chat)
     * @param float  $temperature
     * @param int    $maxTokens  Output token budget. Callers editing large HTML
     *                           fields in 'full' mode should pass a larger value —
     *                           4096 is easy to exceed on a full page rewrite, and
     *                           a truncated response silently breaks JSON parsing
     *                           downstream (looks like "nothing happened").
     * @param int    $retries    Number of extra attempts on transient failures
     *                           (network errors, 429, 5xx). 0 = no retry.
     * @return string            Generated assistant text
     * @throws Exception         On missing key, network, or API errors
     */
    public static function chat(
        array $messages,
        string $model,
        float $temperature = 0.7,
        int $maxTokens = 4096,
        int $retries = 1
    ): string {
        $apiKey = self::getApiKey();
        if ($apiKey === '') {
            throw new Exception('OpenRouter API key is not configured. Add OPENROUTER_API_KEY to .env');
        }

        if (!isset(self::MODELS[$model])) {
            $model = 'deepseek/deepseek-chat';
        }

        $maxTokens = max(256, min($maxTokens, 32000));

        $payload = json_encode([
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new Exception('Failed to encode request payload as JSON: ' . json_last_error_msg());
        }

        $attempt = 0;
        $lastError = null;

        while ($attempt <= $retries) {
            $attempt++;

            $ch = curl_init(self::API_ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Bearer ' . $apiKey,
                    'HTTP-Referer: ' . (defined('BASE_URL') ? BASE_URL : ''),
                    'X-Title: CMS Page Editor',
                ],
                // Larger completions take longer; give big rewrites room to finish
                // instead of racing a 60s timeout.
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $transient = false;

            if ($error) {
                $lastError = new Exception('OpenRouter network error: ' . $error);
                $transient = true;
            } elseif ($httpCode === 401) {
                throw new Exception('OpenRouter API key is invalid or unauthorized');
            } elseif ($httpCode === 429) {
                $lastError = new Exception('OpenRouter rate limit exceeded. Please try again in a moment');
                $transient = true;
            } elseif ($httpCode >= 500) {
                $lastError = new Exception('OpenRouter API error (HTTP ' . $httpCode . '): ' . mb_substr((string)$response, 0, 500));
                $transient = true;
            } elseif ($httpCode < 200 || $httpCode >= 300) {
                throw new Exception('OpenRouter API error (HTTP ' . $httpCode . '): ' . mb_substr((string)$response, 0, 500));
            }

            if ($transient) {
                if ($attempt <= $retries) {
                    usleep(500000); // brief backoff before retrying
                    continue;
                }
                throw $lastError;
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new Exception('OpenRouter returned an invalid response');
            }

            $choice = $data['choices'][0] ?? null;
            $content = $choice['message']['content'] ?? null;
            $finishReason = $choice['finish_reason'] ?? null;

            if (!is_string($content) || trim($content) === '') {
                $detail = $data['error']['message'] ?? $data['error'] ?? 'empty completion';
                throw new Exception('OpenRouter returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
            }

            if ($finishReason === 'length') {
                throw new Exception(
                    'OpenRouter response was cut off because it hit the ' . $maxTokens . '-token output limit. '
                    . 'Try a smaller field/section, a more specific prompt, or raise max_tokens for this request.'
                );
            }

            return trim($content);
        }

        // Unreachable, but keeps static analysis happy.
        throw $lastError ?? new Exception('OpenRouter request failed');
    }
}