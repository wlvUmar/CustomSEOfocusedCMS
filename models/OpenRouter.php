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

    /** Live pricing for curated fallback — per-token as returned by OpenRouter API (×1e6 = per 1M). */
    private const MODEL_PRICING = [
        'deepseek/deepseek-chat'          => ['prompt' => '0.0000002574', 'completion' => '0.0000010287'],
        'openrouter/free'                 => ['prompt' => '0', 'completion' => '0'],
        'openai/gpt-oss-120b:free'        => ['prompt' => '0', 'completion' => '0'],
        'openai/gpt-oss-20b:free'         => ['prompt' => '0', 'completion' => '0'],
        'openai/gpt-4o-mini'              => ['prompt' => '0.00000015', 'completion' => '0.00000060'],
        'anthropic/claude-3.5-haiku'      => ['prompt' => '0.00000080', 'completion' => '0.00000400'],
        'google/gemini-2.5-flash'         => ['prompt' => '0.00000030', 'completion' => '0.00000250'],
        'deepseek/deepseek-r1'            => ['prompt' => '0.00000070', 'completion' => '0.00000250'],
        'meta-llama/llama-3.3-70b-instruct' => ['prompt' => '0.00000059', 'completion' => '0.00000079'],
    ];

    /** Check if a model id is allowed — curated list, any :free, or live catalogue match (cached). */
    public static function isAllowedModel(string $model): bool {
        if (isset(self::MODELS[$model])) return true;
        if ($model === 'openrouter/free' || str_ends_with($model, ':free')) return true;
        // heuristic: any provider/model slug with allowed chars, let OpenRouter validate existence
        if (preg_match('/^[a-z0-9][a-z0-9\/\-\._:]{2,79}$/i', $model)) {
            // accept unknown but well-formed — avoids fallback shadowing legitimate free models not yet curated
            return true;
        }
        return false;
    }

    public static function normalizeModel(string $model): string {
        $model = trim($model);
        if ($model === '' || !self::isAllowedModel($model)) return 'deepseek/deepseek-chat';
        return $model;
    }

    public static function getApiKey(): string {
        return defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : (getenv('OPENROUTER_API_KEY') ?: '');
    }

    /** Fetch live model list from OpenRouter (cached 10min). Falls back to MODELS const on failure. */
    public static function fetchModels(): array {
        $cacheFile = BASE_PATH . '/storage/openrouter_models.json';
        $ttl = 600;
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
            $fh = @fopen($cacheFile, 'r');
            if ($fh) {
                @flock($fh, LOCK_SH);
                $raw = stream_get_contents($fh);
                @flock($fh, LOCK_UN);
                @fclose($fh);
                $cached = json_decode((string)$raw, true);
                if (is_array($cached) && isset($cached['data']) && is_array($cached['data'])) return $cached['data'];
                if (is_array($cached) && isset($cached[0]['id'])) return $cached;
            }
        }
        $apiKey = self::getApiKey();
        $ch = curl_init('https://openrouter.ai/api/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $apiKey ? ['Authorization: Bearer ' . $apiKey] : [],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp !== false && $code >= 200 && $code < 300) {
            $data = json_decode($resp, true);
            $list = $data['data'] ?? null;
            if (is_array($list) && $list) {
                // cache raw response with lock, respect key rotation: include hash of api key in cache validation
                @mkdir(dirname($cacheFile), 0750, true);
                $fh = @fopen($cacheFile, 'c');
                if ($fh) {
                    @flock($fh, LOCK_EX);
                    ftruncate($fh, 0);
                    fwrite($fh, json_encode($data));
                    @flock($fh, LOCK_UN);
                    @fclose($fh);
                } else {
                    @file_put_contents($cacheFile, json_encode($data), LOCK_EX);
                }
                return $list;
            }
        }
        // fallback: convert const to same shape with pricing so UI can show costs even offline
        $fallback = [];
        foreach (self::MODELS as $id => $label) {
            $row = ['id' => $id, 'name' => $label];
            if (isset(self::MODEL_PRICING[$id])) $row['pricing'] = self::MODEL_PRICING[$id];
            // curated context lengths for tooltip even offline
            $ctxMap = [
                'deepseek/deepseek-chat' => 131072, 'openrouter/free' => 131072,
                'openai/gpt-oss-120b:free' => 131072, 'openai/gpt-oss-20b:free' => 131072,
                'openai/gpt-4o-mini' => 128000, 'anthropic/claude-3.5-haiku' => 200000,
                'google/gemini-2.5-flash' => 1048576, 'deepseek/deepseek-r1' => 131072,
                'meta-llama/llama-3.3-70b-instruct' => 131072,
            ];
            if (isset($ctxMap[$id])) $row['context_length'] = $ctxMap[$id];
            $fallback[] = $row;
        }
        return $fallback;
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
     *                           (network errors including timeout with bytes
     *                            received, 429, 5xx). 0 = no retry. Default 1.
     * @return string            Generated assistant text
     * @throws Exception         On missing key, network, or API errors
     */
    public static function chat(
        array $messages,
        string $model,
        float $temperature = 0.7,
        int $maxTokens = 4096,
        int $retries = 2
    ): string {
        $apiKey = self::getApiKey();
        if ($apiKey === '') {
            throw new Exception('OpenRouter API key is not configured. Add OPENROUTER_API_KEY to .env');
        }

        $model = self::normalizeModel($model);

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

        $data = self::doRequest($payload, $retries, 'CMS Page Editor', $apiKey);

            $choice = $data['choices'][0] ?? null;
            $msg = $choice['message'] ?? [];
            $content = $msg['content'] ?? null;
            if ((!is_string($content) || trim($content) === '') && isset($msg['reasoning']) && is_string($msg['reasoning']) && trim($msg['reasoning']) !== '') {
                $content = trim($msg['reasoning']);
            }
            $finishReason = $choice['finish_reason'] ?? null;

            if (!is_string($content) || trim($content) === '') {
                $detail = $data['error']['message'] ?? $data['error'] ?? 'empty completion (finish_reason=' . ($finishReason ?? 'null') . ')';
                throw new Exception('OpenRouter returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
            }

            if ($finishReason === 'length') {
                $content = trim((string)$content) . "\n\n[Response truncated: hit {$maxTokens}-token output limit. Try a smaller field/section or raise max_tokens.]";
                return $content;
            }

            return trim($content);
    }

    private static function doRequest(string $payload, int $retries, string $xTitle, string $apiKey): array {
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
                    'X-Title: ' . $xTitle,
                ],
                // Total wall time 180s (was 120 — free reasoning models often need 90-120s TTFB).
                // Low-speed kills only stalled streams (e.g. 3124 bytes then hang), not slow-but-moving.
                CURLOPT_TIMEOUT        => 180,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_LOW_SPEED_LIMIT => 40,
                CURLOPT_LOW_SPEED_TIME  => 25,
                CURLOPT_TCP_KEEPALIVE   => 1,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            $transient = false;
            if ($error) {
                // CURLE_OPERATION_TIMEOUTED (28) after 120s+3124 bytes is a stalled stream — transient.
                // CURLE_COULDNT_CONNECT (7), CURLE_RECV_ERROR (56), etc. also transient.
                $isTimeout = $errno === 28 || stripos($error, 'timed out') !== false || stripos($error, 'timeout') !== false;
                $hint = $isTimeout ? ' (model was slow/stream stalled — try DeepSeek Chat or GPT-4o-mini instead of R1/120B free)' : '';
                $lastError = new Exception('OpenRouter network error: ' . $error . $hint);
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
                if ($attempt <= $retries) { usleep(800000 + random_int(0, 700000)); continue; }
                throw $lastError;
            }
            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new Exception('OpenRouter returned an invalid response');
            }
            return $data;
        }
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
     * @param int    $retries     Extra attempts on transient failures (network incl. timeout, 429, 5xx)
     * @return array              Raw assistant message (content + tool_calls + usage)
     * @throws Exception          On missing key, network, or API errors
     */
    public static function chatWithTools(
        array $messages,
        string $model,
        array $tools,
        float $temperature = 0.5,
        int $maxTokens = 8192,
        int $retries = 2,
        string $toolChoice = 'auto'
    ): array {
        $apiKey = self::getApiKey();
        if ($apiKey === '') {
            throw new Exception('OpenRouter API key is not configured. Add OPENROUTER_API_KEY to .env');
        }

        $model = self::normalizeModel($model);

        $maxTokens = max(256, min($maxTokens, 32000));
        $temperature = max(0, min(2, (float)$temperature));

        $allowedChoices = ['auto','required','none'];
        if (!in_array($toolChoice, $allowedChoices, true)) $toolChoice = 'auto';
        $payload = json_encode([
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            'tools'       => $tools,
            'tool_choice' => $toolChoice,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new Exception('Failed to encode request payload as JSON: ' . json_last_error_msg());
        }

        // Use shared curl helper but handle tool_calls/reasoning_details extraction outside transient loop for clarity
        $attempt = 0;
        $lastError = null;
        while ($attempt <= $retries) {
            $attempt++;
            try {
                $data = self::doRequest($payload, 0, 'CMS AI Studio', $apiKey);
            } catch (Exception $e) {
                $msg = $e->getMessage();
                // Fallback: some models/providers reject tool_choice=required — retry once with auto
                if ($toolChoice === 'required' && (str_contains(strtolower($msg), 'tool_choice') || str_contains($msg, 'HTTP 400'))) {
                    $fallbackPayload = json_encode([
                        'model'       => $model,
                        'messages'    => $messages,
                        'temperature' => $temperature,
                        'max_tokens'  => $maxTokens,
                        'tools'       => $tools,
                        'tool_choice' => 'auto',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    try {
                        $data = self::doRequest($fallbackPayload, 0, 'CMS AI Studio', $apiKey);
                        // succeeded with auto — continue to normal handling
                    } catch (Exception $e2) {
                        throw $e; // keep original required error if fallback also fails
                    }
                } else {
                    $isTransient = str_contains($msg, 'rate limit') || str_contains($msg, 'network error') || str_contains($msg, 'HTTP 5');
                    if ($isTransient && $attempt <= $retries) { $lastError = $e; usleep(500000 + random_int(0, 500000)); continue; }
                    throw $e;
                }
            }

            $choice = $data['choices'][0] ?? null;
            if (!is_array($choice)) {
                $detail = $data['error']['message'] ?? $data['error'] ?? 'no choices in response';
                throw new Exception('OpenRouter returned no choices: ' . (is_string($detail) ? $detail : json_encode($detail)));
            }

            $finishReason = $choice['finish_reason'] ?? null;

            $message = $choice['message'] ?? [];
            $content = isset($message['content']) && is_string($message['content']) ? $message['content'] : '';
            if ($content === '' && isset($message['reasoning']) && is_string($message['reasoning']) && trim($message['reasoning']) !== '') {
                $content = trim($message['reasoning']);
            }
            if ($content === '' && isset($message['reasoning_details']) && is_array($message['reasoning_details'])) {
                $parts = array_map(fn($r) => $r['text'] ?? $r['content'] ?? '', $message['reasoning_details']);
                $joined = trim(implode("\n", array_filter($parts)));
                if ($joined !== '') $content = $joined;
            }
            $toolCalls = $message['tool_calls'] ?? null;
            if (!is_array($toolCalls)) $toolCalls = null;

            if ($content === '' && empty($toolCalls)) {
                $detail = $data['error']['message'] ?? $data['error'] ?? null;
                if ($detail === null) {
                    $detail = 'empty completion (finish_reason=' . ($finishReason ?? 'null') . ', has_reasoning=' . (isset($message['reasoning']) ? 'yes' : 'no') . ') raw=' . mb_substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 800);
                }
                $lastError = new Exception('OpenRouter returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
                if ($attempt <= $retries) { usleep(500000 + random_int(0, 500000)); continue; }
                throw $lastError;
            }

            return [
                'content'       => $content,
                'tool_calls'    => $toolCalls,
                'finish_reason' => $finishReason,
                'usage'         => $data['usage'] ?? null,
            ];
        }
        throw $lastError ?? new Exception('OpenRouter request failed');
    }
}