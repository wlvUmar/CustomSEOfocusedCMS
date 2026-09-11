<?php
// path: ./models/Opencode.php
// Provider: OpenCode Zen + Go (https://opencode.ai/zen, https://opencode.ai/zen/go)
// Replaces OpenRouter for AI Studio + page ai-edit/ai-chat.
// Supports model prefixes `opencode/` (Zen) and `opencode-go/` (Go).

class Opencode {
    private const API_ENDPOINT_ZEN = 'https://opencode.ai/zen/v1/chat/completions';
    private const MODELS_ENDPOINT_ZEN = 'https://opencode.ai/zen/v1/models';
    private const API_ENDPOINT_GO = 'https://opencode.ai/zen/go/v1/chat/completions';
    private const MODELS_ENDPOINT_GO = 'https://opencode.ai/zen/go/v1/models';

    // Back-compat: default endpoint is Zen
    private const API_ENDPOINT = self::API_ENDPOINT_ZEN;
    private const MODELS_ENDPOINT = self::MODELS_ENDPOINT_ZEN;

    public const MODELS_ZEN = [
        'opencode/muse-spark-1.2'              => 'Zen · Muse Spark 1.2 (default)',
        'opencode/muse-spark-1.3'              => 'Zen · Muse Spark 1.3',
        'opencode/gpt-5.6-luna'                => 'Zen · GPT-5.6 Luna (cheap)',
        'opencode/claude-haiku-4-5'            => 'Zen · Claude Haiku 4.5',
        'opencode/claude-sonnet-4-5'           => 'Zen · Claude Sonnet 4.5',
        'opencode/gemini-3-flash'              => 'Zen · Gemini 3 Flash',
        'opencode/deepseek-v4-flash'           => 'Zen · DeepSeek V4 Flash',
        'opencode/kimi-k2.6'                   => 'Zen · Kimi K2.6',
        'opencode/qwen3.6-plus'                => 'Zen · Qwen 3.6 Plus',
        'opencode/glm-5.3-flash'               => 'Zen · GLM 5.3 Flash',
        'opencode/big-pickle'                  => 'Zen · Big Pickle (free)',
        'opencode/muse-spark-1.3-contributor-free' => 'Zen · Muse Spark 1.3 Free',
    ];

    public const MODELS_GO = [
        'opencode-go/grok-4.6'                 => 'Go · Grok 4.6',
        'opencode-go/gpt-5.6-luna'             => 'Go · GPT-5.6 Luna',
        'opencode-go/glm-5.3-flash'            => 'Go · GLM-5.3 Flash',
        'opencode-go/kimi-k2.6'                => 'Go · Kimi K2.6',
        'opencode-go/kimi-k3'                  => 'Go · Kimi K3',
        'opencode-go/kimi-k2.7-code'           => 'Go · Kimi K2.7 Code',
        'opencode-go/deepseek-v4-flash'        => 'Go · DeepSeek V4 Flash',
        'opencode-go/deepseek-v4-pro'          => 'Go · DeepSeek V4 Pro',
        'opencode-go/qwen3.6-plus'             => 'Go · Qwen 3.6 Plus',
        'opencode-go/qwen3.8-flash'            => 'Go · Qwen 3.8 Flash',
        'opencode-go/minimax-m2.7'             => 'Go · MiniMax M2.7',
        'opencode-go/muse-spark-1.2-contributor' => 'Go · Muse Spark 1.2',
        'opencode-go/longcat-2.0'              => 'Go · LongCat-2.0',
        'opencode-go/hy3'                      => 'Go · Hy3',
    ];

    public const MODELS = [
        'opencode/muse-spark-1.2'              => 'Zen · Muse Spark 1.2 (default)',
        'opencode/muse-spark-1.3'              => 'Zen · Muse Spark 1.3',
        'opencode/gpt-5.6-luna'                => 'Zen · GPT-5.6 Luna (cheap)',
        'opencode/claude-haiku-4-5'            => 'Zen · Claude Haiku 4.5',
        'opencode/claude-sonnet-4-5'           => 'Zen · Claude Sonnet 4.5',
        'opencode/gemini-3-flash'              => 'Zen · Gemini 3 Flash',
        'opencode/deepseek-v4-flash'           => 'Zen · DeepSeek V4 Flash',
        'opencode/kimi-k2.6'                   => 'Zen · Kimi K2.6',
        'opencode/qwen3.6-plus'                => 'Zen · Qwen 3.6 Plus',
        'opencode/glm-5.3-flash'               => 'Zen · GLM 5.3 Flash',
        'opencode/big-pickle'                  => 'Zen · Big Pickle (free)',
        'opencode/muse-spark-1.3-contributor-free' => 'Zen · Muse Spark 1.3 Free',
        // Go curated appended
        'opencode-go/grok-4.6'                 => 'Go · Grok 4.6',
        'opencode-go/gpt-5.6-luna'             => 'Go · GPT-5.6 Luna',
        'opencode-go/glm-5.3-flash'            => 'Go · GLM-5.3 Flash',
        'opencode-go/kimi-k2.6'                => 'Go · Kimi K2.6',
        'opencode-go/kimi-k3'                  => 'Go · Kimi K3',
        'opencode-go/kimi-k2.7-code'           => 'Go · Kimi K2.7 Code',
        'opencode-go/deepseek-v4-flash'        => 'Go · DeepSeek V4 Flash',
        'opencode-go/deepseek-v4-pro'          => 'Go · DeepSeek V4 Pro',
        'opencode-go/qwen3.6-plus'             => 'Go · Qwen 3.6 Plus',
        'opencode-go/qwen3.8-flash'            => 'Go · Qwen 3.8 Flash',
        'opencode-go/minimax-m2.7'             => 'Go · MiniMax M2.7',
        'opencode-go/muse-spark-1.2-contributor' => 'Go · Muse Spark 1.2',
        'opencode-go/longcat-2.0'              => 'Go · LongCat-2.0',
        'opencode-go/hy3'                      => 'Go · Hy3',
    ];

    private const MODEL_PRICING = [
        // Zen pricing per-token (×1e6 = per 1M) — https://opencode.ai/docs/zen#pricing
        'opencode/muse-spark-1.2'              => ['prompt' => '0.00000125', 'completion' => '0.00000425'],
        'opencode/muse-spark-1.3'              => ['prompt' => '0.00000125', 'completion' => '0.00000425'],
        'opencode/gpt-5.6-luna'                => ['prompt' => '0.00000020', 'completion' => '0.00000120'],
        'opencode/claude-haiku-4-5'            => ['prompt' => '0.00000100', 'completion' => '0.00000500'],
        'opencode/claude-sonnet-4-5'           => ['prompt' => '0.00000300', 'completion' => '0.00001500'],
        'opencode/gemini-3-flash'              => ['prompt' => '0.00000050', 'completion' => '0.00000300'],
        'opencode/deepseek-v4-flash'           => ['prompt' => '0.00000014', 'completion' => '0.00000028'],
        'opencode/kimi-k2.6'                   => ['prompt' => '0.00000095', 'completion' => '0.00000400'],
        'opencode/qwen3.6-plus'                => ['prompt' => '0.00000050', 'completion' => '0.00000300'],
        'opencode/glm-5.3-flash'               => ['prompt' => '0.00000015', 'completion' => '0.00000050'],
        'opencode/big-pickle'                  => ['prompt' => '0', 'completion' => '0'],
        'opencode/muse-spark-1.3-contributor-free' => ['prompt' => '0', 'completion' => '0'],
        // Go pricing per-token (https://opencode.ai/docs/go#usage-limits) — same numeric as Zen where overlapping
        'opencode-go/grok-4.6'                 => ['prompt' => '0.00000200', 'completion' => '0.00000600'],
        'opencode-go/gpt-5.6-luna'             => ['prompt' => '0.00000020', 'completion' => '0.00000120'],
        'opencode-go/glm-5.3-flash'            => ['prompt' => '0.00000015', 'completion' => '0.00000050'],
        'opencode-go/kimi-k2.6'                => ['prompt' => '0.00000095', 'completion' => '0.00000400'],
        'opencode-go/kimi-k3'                  => ['prompt' => '0.00000300', 'completion' => '0.00001500'],
        'opencode-go/kimi-k2.7-code'           => ['prompt' => '0.00000095', 'completion' => '0.00000400'],
        'opencode-go/deepseek-v4-flash'        => ['prompt' => '0.00000030', 'completion' => '0.00000120'],
        'opencode-go/deepseek-v4-pro'          => ['prompt' => '0.00000132', 'completion' => '0.00000396'],
        'opencode-go/qwen3.6-plus'             => ['prompt' => '0.00000050', 'completion' => '0.00000300'],
        'opencode-go/qwen3.8-flash'            => ['prompt' => '0.00000015', 'completion' => '0.00000047'],
        'opencode-go/minimax-m2.7'             => ['prompt' => '0.00000030', 'completion' => '0.00000120'],
        'opencode-go/muse-spark-1.2-contributor' => ['prompt' => '0.00000010', 'completion' => '0.00000020'],
        'opencode-go/longcat-2.0'              => ['prompt' => '0.00000030', 'completion' => '0.00000120'],
        'opencode-go/hy3'                      => ['prompt' => '0.00000014', 'completion' => '0.00000058'],
    ];

    public static function isAllowedModel(string $model): bool {
        if (isset(self::MODELS[$model])) return true;
        if (preg_match('#^opencode(-go)?/[a-z0-9][a-z0-9/\\-\\._:]{2,79}$#i', $model)) return true;
        if (preg_match('/^[a-z0-9][a-z0-9\\/\\-\\._:]{2,79}$/i', $model)) return true;
        return false;
    }

    public static function normalizeModel(string $model): string {
        $model = trim($model);
        if ($model === '' || !self::isAllowedModel($model)) return 'opencode/muse-spark-1.2';
        $legacyMap = [
            'deepseek/deepseek-chat' => 'opencode/deepseek-v4-flash',
            'openrouter/free' => 'opencode/big-pickle',
            'openai/gpt-oss-120b:free' => 'opencode/big-pickle',
            'openai/gpt-oss-20b:free' => 'opencode/big-pickle',
            'openai/gpt-4o-mini' => 'opencode/gpt-5.6-luna',
            'anthropic/claude-3.5-haiku' => 'opencode/claude-haiku-4-5',
            'google/gemini-2.5-flash' => 'opencode/gemini-3-flash',
            'deepseek/deepseek-r1' => 'opencode/deepseek-v4-flash',
            'meta-llama/llama-3.3-70b-instruct' => 'opencode/qwen3.6-plus',
        ];
        if (isset($legacyMap[$model])) return $legacyMap[$model];
        return $model;
    }

    public static function isGoModel(string $model): bool {
        return str_starts_with($model, 'opencode-go/');
    }

    public static function getApiKey(): string {
        return self::getApiKeyForModel('');
    }

    public static function getApiKeyForModel(string $model): string {
        $isGo = self::isGoModel($model);
        if ($isGo) {
            if (defined('OPENCODE_GO_API_KEY') && OPENCODE_GO_API_KEY !== '') return (string)OPENCODE_GO_API_KEY;
            $k = getenv('OPENCODE_GO_API_KEY') ?: '';
            if ($k !== '') return $k;
        }
        if (defined('OPENCODE_API_KEY') && OPENCODE_API_KEY !== '') return (string)OPENCODE_API_KEY;
        $k = getenv('OPENCODE_API_KEY') ?: getenv('OPENCODE_ZEN_API_KEY') ?: '';
        if ($k !== '') return $k;
        if (defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '') return (string)OPENROUTER_API_KEY;
        $k2 = getenv('OPENROUTER_API_KEY') ?: '';
        if ($k2 !== '') return $k2;
        // fallback: read auth.json (local dev)
        $candidates = [
            (getenv('HOME') ?: ($_SERVER['HOME'] ?? '')) . '/.local/share/opencode/auth.json',
            (getenv('USERPROFILE') ?: '') . '/.local/share/opencode/auth.json',
        ];
        foreach ($candidates as $authFile) {
            if ($authFile && is_file($authFile)) {
                $raw = @file_get_contents($authFile);
                $j = json_decode((string)$raw, true);
                if ($isGo && isset($j['opencode-go']['key']) && is_string($j['opencode-go']['key']) && $j['opencode-go']['key'] !== '') return $j['opencode-go']['key'];
                if (!$isGo && isset($j['opencode']['key']) && is_string($j['opencode']['key']) && $j['opencode']['key'] !== '') return $j['opencode']['key'];
                // allow cross if requested provider missing
                if (isset($j['opencode']['key']) && $j['opencode']['key'] !== '') return $j['opencode']['key'];
                if (isset($j['opencode-go']['key']) && $j['opencode-go']['key'] !== '') return $j['opencode-go']['key'];
            }
        }
        return '';
    }

    public static function getEndpointForModel(string $model): string {
        return self::isGoModel($model) ? self::API_ENDPOINT_GO : self::API_ENDPOINT_ZEN;
    }

    /** Fetch live model list from Zen + Go (cached 10min). Falls back to MODELS const. */
    public static function fetchModels(): array {
        $cacheFile = BASE_PATH . '/storage/opencode_models.json';
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
        $merged = [];
        $seen = [];
        foreach ([self::MODELS_ENDPOINT_ZEN, self::MODELS_ENDPOINT_GO] as $idx => $endpoint) {
            $isGo = $endpoint === self::MODELS_ENDPOINT_GO;
            $fakeModel = $isGo ? 'opencode-go/kimi-k2.6' : 'opencode/muse-spark-1.2';
            $apiKey = self::getApiKeyForModel($fakeModel);
            $ch = curl_init($endpoint);
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
                $list = $data['data'] ?? (is_array($data) && isset($data[0]['id']) ? $data : null);
                if (is_array($list) && $list) {
                    foreach ($list as $row) {
                        if (!isset($row['id'])) continue;
                        // normalize bare ids like grok-4.6 → opencode(-go)/grok-4.6
                        $id = (string)$row['id'];
                        if ($id !== '' && strpos($id, '/') === false) {
                            $id = ($isGo ? 'opencode-go/' : 'opencode/') . $id;
                            $row['id'] = $id;
                        } elseif ($id !== '' && !str_starts_with($id, 'opencode')) {
                            // e.g. openai/gpt-5 style still allowed but prefix for grouping
                            // keep as-is; provider inferred by endpoint origin
                            if ($isGo && !str_starts_with($id, 'opencode-go/')) {
                                $row['id'] = 'opencode-go/' . ltrim($id, '/');
                            } elseif (!$isGo && !str_starts_with($id, 'opencode/')) {
                                $row['id'] = 'opencode/' . ltrim($id, '/');
                            }
                        }
                        if (isset($seen[$row['id']])) continue;
                        $seen[$row['id']] = true;
                        $merged[] = $row;
                    }
                }
            }
        }
        if ($merged) {
            @mkdir(dirname($cacheFile), 0750, true);
            $fh = @fopen($cacheFile, 'c');
            if ($fh) {
                @flock($fh, LOCK_EX);
                ftruncate($fh, 0);
                fwrite($fh, json_encode(['data' => $merged]));
                @flock($fh, LOCK_UN);
                @fclose($fh);
            } else {
                @file_put_contents($cacheFile, json_encode(['data' => $merged]), LOCK_EX);
            }
            return $merged;
        }
        $fallback = [];
        foreach (self::MODELS as $id => $label) {
            $row = ['id' => $id, 'name' => $label];
            if (isset(self::MODEL_PRICING[$id])) $row['pricing'] = self::MODEL_PRICING[$id];
            $ctxMap = [
                'opencode/muse-spark-1.2' => 131072, 'opencode/muse-spark-1.3' => 131072,
                'opencode/gpt-5.6-luna' => 272000, 'opencode/claude-haiku-4-5' => 200000,
                'opencode/claude-sonnet-4-5' => 200000, 'opencode/gemini-3-flash' => 1048576,
                'opencode/deepseek-v4-flash' => 131072, 'opencode/kimi-k2.6' => 131072,
                'opencode/qwen3.6-plus' => 131072, 'opencode/glm-5.3-flash' => 131072,
                'opencode/big-pickle' => 131072, 'opencode/muse-spark-1.3-contributor-free' => 131072,
                'opencode-go/grok-4.6' => 200000, 'opencode-go/gpt-5.6-luna' => 272000,
                'opencode-go/glm-5.3-flash' => 131072, 'opencode-go/kimi-k2.6' => 131072,
                'opencode-go/kimi-k3' => 131072, 'opencode-go/kimi-k2.7-code' => 131072,
                'opencode-go/deepseek-v4-flash' => 131072, 'opencode-go/deepseek-v4-pro' => 131072,
                'opencode-go/qwen3.6-plus' => 131072, 'opencode-go/qwen3.8-flash' => 131072,
                'opencode-go/minimax-m2.7' => 131072, 'opencode-go/muse-spark-1.2-contributor' => 131072,
                'opencode-go/longcat-2.0' => 131072, 'opencode-go/hy3' => 131072,
            ];
            if (isset($ctxMap[$id])) $row['context_length'] = $ctxMap[$id];
            $fallback[] = $row;
        }
        return $fallback;
    }

    public static function chat(
        array $messages,
        string $model,
        float $temperature = 0.7,
        int $maxTokens = 4096,
        int $retries = 2
    ): string {
        $model = self::normalizeModel($model);
        $apiKey = self::getApiKeyForModel($model);
        if ($apiKey === '') {
            $which = self::isGoModel($model) ? 'OPENCODE_GO_API_KEY' : 'OPENCODE_API_KEY';
            throw new Exception("OpenCode API key is not configured. Add {$which} to .env (https://opencode.ai/auth)");
        }
        $maxTokens = max(256, min($maxTokens, 32000));
        $temperature = max(0, min(2, (float)$temperature));
        $apiModel = preg_replace('#^opencode(-go)?/#', '', $model);
        $payload = json_encode([
            'model'       => $apiModel,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) throw new Exception('Failed to encode request payload as JSON: ' . json_last_error_msg());
        $data = self::doRequest($payload, $retries, 'CMS Page Editor', $apiKey, $model);
        $choice = $data['choices'][0] ?? null;
        $msg = $choice['message'] ?? [];
        $content = $msg['content'] ?? null;
        if ((!is_string($content) || trim($content) === '') && isset($msg['reasoning']) && is_string($msg['reasoning']) && trim($msg['reasoning']) !== '') $content = trim($msg['reasoning']);
        $finishReason = $choice['finish_reason'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            $detail = $data['error']['message'] ?? $data['error'] ?? 'empty completion (finish_reason=' . ($finishReason ?? 'null') . ')';
            throw new Exception('OpenCode returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
        }
        if ($finishReason === 'length') {
            $content = trim((string)$content) . "\n\n[Response truncated: hit {$maxTokens}-token output limit.]";
            return $content;
        }
        return trim($content);
    }

    private static function doRequest(string $payload, int $retries, string $xTitle, string $apiKey, string $model = ''): array {
        $endpoint = $model !== '' ? self::getEndpointForModel($model) : self::API_ENDPOINT;
        $attempt = 0;
        $lastError = null;
        while ($attempt <= $retries) {
            $attempt++;
            $ch = curl_init($endpoint);
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
                $isTimeout = $errno === 28 || stripos($error, 'timed out') !== false || stripos($error, 'timeout') !== false;
                $hint = $isTimeout ? ' (model was slow/stream stalled — try Muse Spark or GPT-5.6 Luna)' : '';
                $lastError = new Exception('OpenCode network error: ' . $error . $hint);
                $transient = true;
            } elseif ($httpCode === 401) {
                throw new Exception('OpenCode API key is invalid or unauthorized');
            } elseif ($httpCode === 429) {
                $lastError = new Exception('OpenCode rate limit exceeded. Please try again in a moment');
                $transient = true;
            } elseif ($httpCode >= 500) {
                $lastError = new Exception('OpenCode API error (HTTP ' . $httpCode . '): ' . mb_substr((string)$response, 0, 500));
                $transient = true;
            } elseif ($httpCode < 200 || $httpCode >= 300) {
                throw new Exception('OpenCode API error (HTTP ' . $httpCode . '): ' . mb_substr((string)$response, 0, 500));
            }
            if ($transient) {
                if ($attempt <= $retries) { usleep(800000 + random_int(0, 700000)); continue; }
                throw $lastError;
            }
            $data = json_decode($response, true);
            if (!is_array($data)) throw new Exception('OpenCode returned an invalid response');
            return $data;
        }
        throw $lastError ?? new Exception('OpenCode request failed');
    }

    public static function chatWithTools(
        array $messages,
        string $model,
        array $tools,
        float $temperature = 0.5,
        int $maxTokens = 8192,
        int $retries = 2,
        string $toolChoice = 'auto'
    ): array {
        $model = self::normalizeModel($model);
        $apiKey = self::getApiKeyForModel($model);
        if ($apiKey === '') {
            $which = self::isGoModel($model) ? 'OPENCODE_GO_API_KEY' : 'OPENCODE_API_KEY';
            throw new Exception("OpenCode API key is not configured. Add {$which} to .env (https://opencode.ai/auth)");
        }
        $maxTokens = max(256, min($maxTokens, 32000));
        $temperature = max(0, min(2, (float)$temperature));
        $allowedChoices = ['auto','required','none'];
        if (!in_array($toolChoice, $allowedChoices, true)) $toolChoice = 'auto';
        $apiModel = preg_replace('#^opencode(-go)?/#', '', $model);
        $payload = json_encode([
            'model'       => $apiModel,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            'tools'       => $tools,
            'tool_choice' => $toolChoice,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) throw new Exception('Failed to encode request payload as JSON: ' . json_last_error_msg());
        $attempt = 0;
        $lastError = null;
        while ($attempt <= $retries) {
            $attempt++;
            try {
                $data = self::doRequest($payload, 0, 'CMS AI Studio', $apiKey, $model);
            } catch (Exception $e) {
                $msg = $e->getMessage();
                if ($toolChoice === 'required' && (str_contains(strtolower($msg), 'tool_choice') || str_contains($msg, 'HTTP 400'))) {
                    $fallbackPayload = json_encode([
                        'model'       => $apiModel,
                        'messages'    => $messages,
                        'temperature' => $temperature,
                        'max_tokens'  => $maxTokens,
                        'tools'       => $tools,
                        'tool_choice' => 'auto',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    try {
                        $data = self::doRequest($fallbackPayload, 0, 'CMS AI Studio', $apiKey, $model);
                    } catch (Exception $e2) { throw $e; }
                } else {
                    $isTransient = str_contains($msg, 'rate limit') || str_contains($msg, 'network error') || str_contains($msg, 'HTTP 5');
                    if ($isTransient && $attempt <= $retries) { $lastError = $e; usleep(500000 + random_int(0, 500000)); continue; }
                    throw $e;
                }
            }
            $choice = $data['choices'][0] ?? null;
            if (!is_array($choice)) {
                $detail = $data['error']['message'] ?? $data['error'] ?? 'no choices in response';
                throw new Exception('OpenCode returned no choices: ' . (is_string($detail) ? $detail : json_encode($detail)));
            }
            $finishReason = $choice['finish_reason'] ?? null;
            $message = $choice['message'] ?? [];
            $content = isset($message['content']) && is_string($message['content']) ? $message['content'] : '';
            if ($content === '' && isset($message['reasoning']) && is_string($message['reasoning']) && trim($message['reasoning']) !== '') $content = trim($message['reasoning']);
            if ($content === '' && isset($message['reasoning_details']) && is_array($message['reasoning_details'])) {
                $parts = array_map(fn($r) => $r['text'] ?? $r['content'] ?? '', $message['reasoning_details']);
                $joined = trim(implode("\n", array_filter($parts)));
                if ($joined !== '') $content = $joined;
            }
            $toolCalls = $message['tool_calls'] ?? null;
            if (!is_array($toolCalls)) $toolCalls = null;
            if ($content === '' && empty($toolCalls)) {
                $detail = $data['error']['message'] ?? $data['error'] ?? null;
                if ($detail === null) $detail = 'empty completion (finish_reason=' . ($finishReason ?? 'null') . ') raw=' . mb_substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 800);
                $lastError = new Exception('OpenCode returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
                if ($attempt <= $retries) { usleep(500000 + random_int(0, 500000)); continue; }
                throw $lastError;
            }
            return ['content' => $content, 'tool_calls' => $toolCalls, 'finish_reason' => $finishReason, 'usage' => $data['usage'] ?? null];
        }
        throw $lastError ?? new Exception('OpenCode request failed');
    }
}
