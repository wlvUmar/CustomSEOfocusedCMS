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
     * @param array  $messages  [['role' => 'system'|'user'|'assistant', 'content' => '...'], ...]
     * @param string $model     OpenRouter model slug (e.g. deepseek/deepseek-chat)
     * @param float  $temperature
     * @return string           Generated assistant text
     * @throws Exception        On missing key, network or API errors
     */
    public static function chat(array $messages, string $model, float $temperature = 0.7): string {
        $apiKey = self::getApiKey();
        if ($apiKey === '') {
            throw new Exception('OpenRouter API key is not configured. Add OPENROUTER_API_KEY to .env');
        }

        if (!isset(self::MODELS[$model])) {
            $model = 'deepseek/deepseek-chat';
        }

        $payload = json_encode([
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => 4096,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('OpenRouter network error: ' . $error);
        }

        if ($httpCode === 401) {
            throw new Exception('OpenRouter API key is invalid or unauthorized');
        }
        if ($httpCode === 429) {
            throw new Exception('OpenRouter rate limit exceeded. Please try again in a moment');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception('OpenRouter API error (HTTP ' . $httpCode . '): ' . mb_substr($response, 0, 500));
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception('OpenRouter returned an invalid response');
        }

        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            $detail = $data['error']['message'] ?? $data['error'] ?? 'empty completion';
            throw new Exception('OpenRouter returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
        }

        return trim($content);
    }
}
