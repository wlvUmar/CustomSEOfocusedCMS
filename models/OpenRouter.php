<?php
// path: ./models/OpenRouter.php

class OpenRouter {
    private const API_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    public const MODELS = [
        'deepseek/deepseek-chat'          => 'DeepSeek Chat (default, cheap)',
        'openrouter/free'                 => 'Auto: best free model',
        'openai/gpt-oss-120b:free'        => 'GPT-OSS 120B (free)',
        'openai/gpt-oss-20b:free'         => 'GPT-OSS 20B (free, fast)',
        'openai/gpt-4o-mini'              => 'GPT-4o Mini (balanced)',
        'anthropic/claude-3.5-haiku'      => 'Claude Haiku (fast)',
        'google/gemini-2.5-flash'         => 'Gemini 2.5 Flash (cheap)',
        'deepseek/deepseek-r1'            => 'DeepSeek R1 (reasoning)',
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
        $temperature = max(0, min(2, (float)$temperature));

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
                    usleep(500000 + random_int(0, 500000)); // jittered backoff
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
                // Return partial instead of throwing — caller can surface truncated hint (C6).
                $content = trim((string)$content) . "\n\n[Response truncated: hit {$maxTokens}-token output limit. Try a smaller field/section or raise max_tokens.]";
                return $content;
            }

            return trim($content);
        }

        // Unreachable, but keeps static analysis happy.
        throw $lastError ?? new Exception('OpenRouter request failed');
    }

    /**
     * Chat completion with tool-calling support (OpenAI-style `tools`).
     *
     * Used by AI Studio's agent loop. Unlike chat(), this returns the raw
     * assistant message so the caller can see both free text (content) and
     * any tool_calls the model wants to make:
     *
     *   ['content' => '...', 'tool_calls' => [['id'=>.., 'function'=>['name'=>.., 'arguments'=>'{...}']]], 'finish_reason' => 'tool_calls'|'stop']
     *
     * The caller is responsible for appending the assistant message (with its
     * tool_calls) and the resulting `role: tool` messages back into $messages
     * on subsequent turns.
     *
     * @param array  $messages    OpenAI-format messages (system/user/assistant/tool)
     * @param string $model       OpenRouter model slug (see MODELS)
     * @param array  $tools       Tool definitions ([['type'=>'function','function'=>['name'=>..,'description'=>..,'parameters'=>..]], ...])
     * @param float  $temperature
     * @param int    $maxTokens   Output token budget per model call
     * @param int    $retries     Extra attempts on transient failures (network, 429, 5xx)
     * @return array              Raw assistant message (content + tool_calls + usage)
     * @throws Exception          On missing key, network, or API errors
     */
    public static function chatWithTools(
        array $messages,
        string $model,
        array $tools,
        float $temperature = 0.5,
        int $maxTokens = 8192,
        int $retries = 1
    ): array {
        $apiKey = self::getApiKey();
        if ($apiKey === '') {
            throw new Exception('OpenRouter API key is not configured. Add OPENROUTER_API_KEY to .env');
        }

        if (!isset(self::MODELS[$model])) {
            $model = 'deepseek/deepseek-chat';
        }

        $maxTokens = max(256, min($maxTokens, 32000));
        $temperature = max(0, min(2, (float)$temperature));

        $payload = json_encode([
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            'tools'       => $tools,
            'tool_choice' => 'auto',
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
                    'X-Title: CMS AI Studio',
                ],
                // Multi-turn tool loops make one call at a time; give each a
                // comfortable window instead of racing a shorter timeout.
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
                    usleep(500000 + random_int(0, 500000)); // jittered backoff
                    continue;
                }
                throw $lastError;
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new Exception('OpenRouter returned an invalid response');
            }

            $choice = $data['choices'][0] ?? null;
            if (!is_array($choice)) {
                $detail = $data['error']['message'] ?? $data['error'] ?? 'no choices in response';
                throw new Exception('OpenRouter returned no choices: ' . (is_string($detail) ? $detail : json_encode($detail)));
            }

            $finishReason = $choice['finish_reason'] ?? null;

            $message = $choice['message'] ?? [];
            $content = isset($message['content']) && is_string($message['content']) ? $message['content'] : '';
            $toolCalls = $message['tool_calls'] ?? null;
            if (!is_array($toolCalls)) {
                $toolCalls = null;
            }

            if ($content === '' && empty($toolCalls)) {
                $detail = $data['error']['message'] ?? $data['error'] ?? 'empty completion';
                throw new Exception('OpenRouter returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
            }

            return [
                'content'       => $content,
                'tool_calls'    => $toolCalls,
                'finish_reason' => $finishReason,
                'usage'         => $data['usage'] ?? null,
            ];
        }

        // Unreachable, but keeps static analysis happy.
        throw $lastError ?? new Exception('OpenRouter request failed');
    }
}