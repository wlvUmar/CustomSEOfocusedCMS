<?php
// path: ./models/llm/BaseLlmClient.php
// Shared base for Opencode (OpenCode Go) + OpenRouter chat clients.
// Subclasses keep provider specifics: MODELS/pricing, isAllowedModel,
// normalizeModel, getApiKey, endpoints/headers/payload builders.
// Shared here: settings-backed key lookup, timeouts, model-list cache,
// single POST helper (_request), payload encode, clamp helpers,
// OpenAI-style message extraction, fallback row builder.

abstract class BaseLlmClient {
    protected const REQUEST_TIMEOUT = 180;
    protected const CONNECT_TIMEOUT = 15;
    protected const FETCH_TIMEOUT = 8;
    protected const FETCH_CONNECT_TIMEOUT = 4;
    protected const MODELS_TTL = 600;
    protected const MAX_TOKENS_CAP = 32000;
    protected const MAX_TOKENS_FLOOR = 256;

    abstract public static function isAllowedModel(string $model): bool;
    abstract public static function normalizeModel(string $model): string;
    abstract public static function getApiKey(): string;

    public static function userAgent(): string {
        return 'CustomSEOFocusedCMS-AIStudio/1.0 (+https://kuplyu-tashkent.uz)';
    }

    protected static function trimKey($v): string {
        return trim((string)$v, " \t\n\r\0\x0B\"'");
    }

    protected static function constOrEnv(string $name): string {
        if (defined($name)) {
            $v = self::trimKey(constant($name));
            if ($v !== '') return $v;
        }
        return self::trimKey(getenv($name) ?: '');
    }

    protected static function clampTokens(int $v): int {
        return max(self::MAX_TOKENS_FLOOR, min($v, self::MAX_TOKENS_CAP));
    }

    protected static function clampTemp(float $v): float {
        return max(0, min(2, $v));
    }

    protected static function checkToolChoice(string $c): string {
        return in_array($c, ['auto', 'required', 'none'], true) ? $c : 'auto';
    }

    protected static function encodePayload(array $arr, string $provider): string {
        $payload = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) throw new Exception('Failed to encode request payload as JSON: ' . json_last_error_msg());
        return $payload;
    }

    protected static function readModelCache(string $cacheFile): ?array {
        if (!is_file($cacheFile) || (time() - filemtime($cacheFile) >= self::MODELS_TTL)) return null;
        $fh = @fopen($cacheFile, 'r');
        if (!$fh) return null;
        @flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);
        $cached = json_decode((string)$raw, true);
        if (is_array($cached) && isset($cached['data']) && is_array($cached['data'])) return $cached['data'];
        if (is_array($cached) && isset($cached[0]['id'])) return $cached;
        return null;
    }

    protected static function writeModelCache(string $cacheFile, array $data): void {
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
    }

    protected static function curlGet(string $url, array $headers): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => self::FETCH_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::FETCH_CONNECT_TIMEOUT,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$resp, $code];
    }

    protected static function authErrorMessage(string $provider, string $response, string $apiKey, string $model): string {
        return $provider . ' API key is invalid or unauthorized';
    }

    protected static function serverErrorSuffix(string $body): string {
        return '';
    }

    protected static function httpErrorHint(int $code, string $detail, string $endpoint): string {
        return '';
    }

    protected static function _request(string $endpoint, string $payload, array $headers, int $retries, string $provider, string $apiKey = '', string $model = '', int $lowSpeedLimit = 40, int $lowSpeedTime = 25, string $timeoutHint = ''): array {
        $attempt = 0;
        $lastError = null;
        while ($attempt <= $retries) {
            $attempt++;
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => self::REQUEST_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_LOW_SPEED_LIMIT => $lowSpeedLimit,
                CURLOPT_LOW_SPEED_TIME  => $lowSpeedTime,
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
                $lastError = new Exception($provider . ' network error: ' . $error . ($isTimeout ? $timeoutHint : ''));
                $transient = true;
            } elseif ($httpCode === 401) {
                throw new Exception(static::authErrorMessage($provider, (string)$response, $apiKey, $model));
            } elseif ($httpCode === 429) {
                $lastError = new Exception($provider . ' rate limit exceeded. Please try again in a moment');
                $transient = true;
            } elseif ($httpCode >= 500) {
                $body = mb_substr((string)$response, 0, 800);
                $lastError = new Exception($provider . ' API error (HTTP ' . $httpCode . '): ' . $body . static::serverErrorSuffix($body));
                $transient = true;
            } elseif ($httpCode < 200 || $httpCode >= 300) {
                $detail = mb_substr((string)$response, 0, 800);
                throw new Exception($provider . ' API error (HTTP ' . $httpCode . '): ' . $detail . static::httpErrorHint($httpCode, $detail, $endpoint));
            }
            if ($transient) {
                if ($attempt <= $retries) { usleep(800000 + random_int(0, 700000)); continue; }
                throw $lastError;
            }
            $data = json_decode($response, true);
            if (!is_array($data)) throw new Exception($provider . ' returned an invalid response');
            return $data;
        }
        throw $lastError ?? new Exception($provider . ' request failed');
    }

    protected static function messageContent(array $message): string {
        $content = isset($message['content']) && is_string($message['content']) ? $message['content'] : '';
        if ($content === '' && isset($message['reasoning']) && is_string($message['reasoning']) && trim($message['reasoning']) !== '') $content = trim($message['reasoning']);
        if ($content === '' && isset($message['reasoning_details']) && is_array($message['reasoning_details'])) {
            $parts = array_map(fn($r) => $r['text'] ?? $r['content'] ?? '', $message['reasoning_details']);
            $joined = trim(implode("\n", array_filter($parts)));
            if ($joined !== '') $content = $joined;
        }
        return $content;
    }

    protected static function messageToolCalls(array $message): ?array {
        $toolCalls = $message['tool_calls'] ?? null;
        return is_array($toolCalls) ? $toolCalls : null;
    }

    protected static function fallbackRows(array $models, array $pricing, array $ctx): array {
        $fallback = [];
        foreach ($models as $id => $label) {
            $row = ['id' => $id, 'name' => $label];
            if (isset($pricing[$id])) $row['pricing'] = $pricing[$id];
            if (isset($ctx[$id])) $row['context_length'] = $ctx[$id];
            $fallback[] = $row;
        }
        return $fallback;
    }

    // ------------------------------------------------------------------
    // Shared OpenAI-compatible flow. Subclasses keep only provider
    // differences via the hooks below (endpoints, headers, payloads).
    // ------------------------------------------------------------------

    protected static function providerName(): string { return 'LLM'; }
    protected static function chatEndpoint(): string { return ''; }
    protected static function modelsEndpoint(): string { return ''; }
    protected static function modelsCacheFile(): string { return ''; }
    protected static function modelPricing(): array { return []; }
    protected static function modelContextMap(): array { return []; }
    protected static function missingKeyMessage(): string { return 'API key is not configured.'; }
    protected static function lengthSuffix(int $maxTokens): string {
        return "\n\n[Response truncated: hit {$maxTokens}-token output limit.]";
    }
    protected static function timeoutHintText(): string { return ''; }
    protected static function lowSpeedLimit(): int { return 40; }
    protected static function lowSpeedTime(): int { return 25; }

    protected static function modelsAuthHeaders(string $apiKey): array {
        return $apiKey !== '' ? ['Authorization: Bearer ' . $apiKey] : [];
    }

    protected static function extractModelList(array $data): ?array {
        $list = $data['data'] ?? null;
        return (is_array($list) && $list) ? $list : null;
    }

    protected static function buildRequestHeaders(string $apiKey, string $xTitle): array {
        return [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: ' . (defined('BASE_URL') ? BASE_URL : ''),
            'X-Title: ' . $xTitle,
        ];
    }

    protected static function buildChatPayload(string $model, array $messages, array $tools, string $toolChoice, float $temperature, int $maxTokens): array {
        $payload = ['model' => $model, 'messages' => $messages, 'temperature' => $temperature, 'max_tokens' => $maxTokens];
        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = $toolChoice;
        }
        return $payload;
    }

    protected static function buildToolsPayload(string $model, array $messages, array $tools, string $toolChoice, float $temperature, int $maxTokens): array {
        return [
            'model' => $model, 'messages' => $messages, 'temperature' => $temperature,
            'max_tokens' => $maxTokens, 'tools' => $tools, 'tool_choice' => $toolChoice,
        ];
    }

    protected static function doRequest(string $payload, int $retries, string $xTitle, string $apiKey, string $model = ''): array {
        return static::_request(
            static::chatEndpoint(), $payload, static::buildRequestHeaders($apiKey, $xTitle),
            $retries, static::providerName(), $apiKey, $model,
            static::lowSpeedLimit(), static::lowSpeedTime(), static::timeoutHintText()
        );
    }

    protected static function isTransientError(string $msg): bool {
        return str_contains($msg, 'rate limit') || str_contains($msg, 'network error') || str_contains($msg, 'HTTP 5');
    }

    protected static function backoff(int $attempt): void {
        usleep(500000 + random_int(0, 500000));
    }

    protected static function normalizeOpenAiChoice(array $data): array {
        $choice = $data['choices'][0] ?? null;
        if (is_array($choice)) {
            $msg = $choice['message'] ?? [];
            if (!is_array($msg)) $msg = [];
            return [static::messageContent($msg), static::messageToolCalls($msg), $choice['finish_reason'] ?? null, $data['usage'] ?? null];
        }
        return ['', null, null, $data['usage'] ?? null];
    }

    /** Fetch live model list (cached 10min). Falls back to MODELS const. */
    public static function fetchModels(): array {
        $cacheFile = static::modelsCacheFile();
        $cached = static::readModelCache($cacheFile);
        if (is_array($cached)) return $cached;
        $apiKey = static::getApiKey();
        [$resp, $code] = static::curlGet(static::modelsEndpoint(), static::modelsAuthHeaders($apiKey));
        if ($resp !== false && $code >= 200 && $code < 300) {
            $data = json_decode((string)$resp, true);
            $list = is_array($data) ? static::extractModelList($data) : null;
            if (is_array($list) && $list) {
                static::writeModelCache($cacheFile, ['data' => $list]);
                return $list;
            }
        }
        return static::fallbackRows(static::MODELS, static::modelPricing(), static::modelContextMap());
    }

    public static function chat(
        array $messages,
        string $model,
        float $temperature = 0.7,
        int $maxTokens = 4096,
        int $retries = 2
    ): string {
        $model = static::normalizeModel($model);
        $apiKey = static::getApiKey();
        if ($apiKey === '') throw new Exception(static::missingKeyMessage());
        $maxTokens = static::clampTokens($maxTokens);
        $temperature = static::clampTemp($temperature);
        $payload = static::encodePayload(
            static::buildChatPayload($model, $messages, [], 'auto', $temperature, $maxTokens),
            static::providerName()
        );
        $data = static::doRequest($payload, $retries, 'CMS Page Editor', $apiKey, $model);
        [$content, , $finishReason, ] = static::normalizeOpenAiChoice($data);
        if (!is_string($content) || trim($content) === '') {
            $detail = $data['error']['message'] ?? $data['error'] ?? 'empty completion (finish_reason=' . ($finishReason ?? 'null') . ')';
            throw new Exception(static::providerName() . ' returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
        }
        if ($finishReason === 'length') {
            $content = trim((string)$content) . static::lengthSuffix($maxTokens);
            return $content;
        }
        return trim($content);
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
        $model = static::normalizeModel($model);
        $apiKey = static::getApiKey();
        if ($apiKey === '') throw new Exception(static::missingKeyMessage());
        $maxTokens = static::clampTokens($maxTokens);
        $temperature = static::clampTemp($temperature);
        $toolChoice = static::checkToolChoice($toolChoice);
        $payload = static::encodePayload(
            static::buildToolsPayload($model, $messages, $tools, $toolChoice, $temperature, $maxTokens),
            static::providerName()
        );
        $attempt = 0;
        $lastError = null;
        while ($attempt <= $retries) {
            $attempt++;
            try {
                $data = static::doRequest($payload, 0, 'CMS AI Studio', $apiKey, $model);
            } catch (Exception $e) {
                $msg = $e->getMessage();
                if ($toolChoice === 'required' && (str_contains(strtolower($msg), 'tool_choice') || str_contains($msg, 'HTTP 400'))) {
                    $fallbackPayload = static::encodePayload(
                        static::buildToolsPayload($model, $messages, $tools, 'auto', $temperature, $maxTokens),
                        static::providerName()
                    );
                    try {
                        $data = static::doRequest($fallbackPayload, 0, 'CMS AI Studio', $apiKey, $model);
                    } catch (Exception $e2) {
                        throw $e;
                    }
                } else {
                    if (static::isTransientError($msg) && $attempt <= $retries) { $lastError = $e; static::backoff($attempt); continue; }
                    throw $e;
                }
            }
            $choice = $data['choices'][0] ?? null;
            if (!is_array($choice)) {
                $detail = $data['error']['message'] ?? $data['error'] ?? 'no choices in response';
                throw new Exception(static::providerName() . ' returned no choices: ' . (is_string($detail) ? $detail : json_encode($detail)));
            }
            [$content, $toolCalls, $finishReason, $usage] = static::normalizeOpenAiChoice($data);
            if ($content === '' && empty($toolCalls)) {
                $message = $choice['message'] ?? [];
                $detail = $data['error']['message'] ?? $data['error'] ?? null;
                if ($detail === null) {
                    $detail = 'empty completion (finish_reason=' . ($finishReason ?? 'null') . ', has_reasoning=' . (isset($message['reasoning']) ? 'yes' : 'no') . ') raw=' . mb_substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 800);
                }
                $lastError = new Exception(static::providerName() . ' returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
                if ($attempt <= $retries) { static::backoff($attempt); continue; }
                throw $lastError;
            }
            return ['content' => $content, 'tool_calls' => $toolCalls, 'finish_reason' => $finishReason, 'usage' => $usage];
        }
        throw $lastError ?? new Exception(static::providerName() . ' request failed');
    }
}
