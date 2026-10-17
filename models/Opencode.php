<?php
// path: ./models/Opencode.php
// Provider: OpenCode Go (https://opencode.ai/zen/go)
// Docs: https://opencode.ai/docs/go/ — per-model endpoints (responses / messages / chat)

require_once BASE_PATH . '/models/BaseLlmClient.php';
require_once BASE_PATH . '/models/OpenRouter.php';

class Opencode extends BaseLlmClient {
    private const CHAT_ENDPOINT = 'https://opencode.ai/zen/go/v1/chat/completions';
    private const RESPONSES_ENDPOINT = 'https://opencode.ai/zen/go/v1/responses';
    private const MESSAGES_ENDPOINT = 'https://opencode.ai/zen/go/v1/messages';
    private const MODELS_ENDPOINT = 'https://opencode.ai/zen/go/v1/models';

    public const DEFAULT_MODEL = 'opencode-go/muse-spark-1.2-contributor';

    public const MODELS = [
        'opencode-go/grok-4.6'                   => 'Go · Grok 4.6',
        'opencode-go/gpt-5.6-luna'               => 'Go · GPT-5.6 Luna',
        'opencode-go/glm-5.3-flash'              => 'Go · GLM-5.3 Flash',
        'opencode-go/glm-5.3'                    => 'Go · GLM-5.3',
        'opencode-go/glm-5.2'                    => 'Go · GLM-5.2',
        'opencode-go/glm-5.1'                    => 'Go · GLM-5.1',
        'opencode-go/kimi-k2.6'                  => 'Go · Kimi K2.6',
        'opencode-go/kimi-k3'                    => 'Go · Kimi K3',
        'opencode-go/kimi-k2.7-code'             => 'Go · Kimi K2.7 Code',
        'opencode-go/deepseek-v4-flash'          => 'Go · DeepSeek V4 Flash',
        'opencode-go/deepseek-v4.1-flash'        => 'Go · DeepSeek V4.1 Flash',
        'opencode-go/deepseek-v4-pro'            => 'Go · DeepSeek V4 Pro',
        'opencode-go/deepseek-v4-flash-vision-exp' => 'Go · DeepSeek V4 Flash Vision',
        'opencode-go/qwen3.6-plus'               => 'Go · Qwen 3.6 Plus',
        'opencode-go/qwen3.7-plus'               => 'Go · Qwen 3.7 Plus',
        'opencode-go/qwen3.7-max'                => 'Go · Qwen 3.7 Max',
        'opencode-go/qwen3.8-flash'              => 'Go · Qwen 3.8 Flash',
        'opencode-go/qwen3.8-max'                => 'Go · Qwen 3.8 Max',
        'opencode-go/minimax-m2.7'               => 'Go · MiniMax M2.7',
        'opencode-go/minimax-m3'                 => 'Go · MiniMax M3',
        'opencode-go/mimo-v2.5'                  => 'Go · MiMo V2.5',
        'opencode-go/mimo-v2.5-pro'              => 'Go · MiMo V2.5 Pro',
        'opencode-go/muse-spark-1.2-contributor' => 'Go · Muse Spark 1.2',
        'opencode-go/muse-spark-1.3-contributor' => 'Go · Muse Spark 1.3',
        'opencode-go/longcat-2.0'                => 'Go · LongCat-2.0',
        'opencode-go/hy3'                        => 'Go · Hy3',
        'opencode-go/hy4-preview'                => 'Go · Hy4 Preview',
    ];

    private const MODEL_PRICING = [
        'opencode-go/grok-4.6'                   => ['prompt' => '0.00000200', 'completion' => '0.00000600'],
        'opencode-go/gpt-5.6-luna'               => ['prompt' => '0.00000020', 'completion' => '0.00000120'],
        'opencode-go/glm-5.3-flash'              => ['prompt' => '0.00000015', 'completion' => '0.00000050'],
        'opencode-go/glm-5.3'                    => ['prompt' => '0.00000140', 'completion' => '0.00000440'],
        'opencode-go/glm-5.2'                    => ['prompt' => '0.00000140', 'completion' => '0.00000440'],
        'opencode-go/glm-5.1'                    => ['prompt' => '0.00000140', 'completion' => '0.00000440'],
        'opencode-go/kimi-k2.6'                  => ['prompt' => '0.00000095', 'completion' => '0.00000400'],
        'opencode-go/kimi-k3'                    => ['prompt' => '0.00000300', 'completion' => '0.00001500'],
        'opencode-go/kimi-k2.7-code'             => ['prompt' => '0.00000095', 'completion' => '0.00000400'],
        'opencode-go/deepseek-v4-flash'          => ['prompt' => '0.00000030', 'completion' => '0.00000120'],
        'opencode-go/deepseek-v4.1-flash'        => ['prompt' => '0.00000030', 'completion' => '0.00000120'],
        'opencode-go/deepseek-v4-pro'            => ['prompt' => '0.00000132', 'completion' => '0.00000396'],
        'opencode-go/deepseek-v4-flash-vision-exp' => ['prompt' => '0.00000030', 'completion' => '0.00000120'],
        'opencode-go/qwen3.6-plus'               => ['prompt' => '0.00000050', 'completion' => '0.00000300'],
        'opencode-go/qwen3.7-plus'               => ['prompt' => '0.00000040', 'completion' => '0.00000160'],
        'opencode-go/qwen3.7-max'                => ['prompt' => '0.00000250', 'completion' => '0.00000750'],
        'opencode-go/qwen3.8-flash'              => ['prompt' => '0.00000015', 'completion' => '0.00000047'],
        'opencode-go/qwen3.8-max'                => ['prompt' => '0.00000200', 'completion' => '0.00000600'],
        'opencode-go/minimax-m2.7'               => ['prompt' => '0.00000030', 'completion' => '0.00000120'],
        'opencode-go/minimax-m3'                 => ['prompt' => '0.00000030', 'completion' => '0.00000120'],
        'opencode-go/mimo-v2.5'                  => ['prompt' => '0.00000014', 'completion' => '0.00000028'],
        'opencode-go/mimo-v2.5-pro'              => ['prompt' => '0.000000435', 'completion' => '0.00000087'],
        'opencode-go/muse-spark-1.2-contributor' => ['prompt' => '0.00000010', 'completion' => '0.00000020'],
        'opencode-go/muse-spark-1.3-contributor' => ['prompt' => '0.00000010', 'completion' => '0.00000020'],
        'opencode-go/longcat-2.0'                => ['prompt' => '0.00000030', 'completion' => '0.00000120'],
        'opencode-go/hy3'                        => ['prompt' => '0.00000014', 'completion' => '0.00000058'],
        'opencode-go/hy4-preview'                => ['prompt' => '0.00000083', 'completion' => '0.00000250'],
    ];

    public static function isAllowedModel(string $model): bool {
        if (isset(self::MODELS[$model])) return true;
        if (preg_match('#^opencode-go/[a-z0-9][a-z0-9/\-\._:]{2,79}$#i', $model)) return true;
        if (preg_match('/^[a-z0-9][a-z0-9\/\-\._:]{2,79}$/i', $model)) return true;
        return false;
    }

    public static function normalizeModel(string $model): string {
        $model = trim($model);
        $legacyMap = [
            'opencode/muse-spark-1.2'                => 'opencode-go/muse-spark-1.2-contributor',
            'opencode/muse-spark-1.3'                => 'opencode-go/muse-spark-1.3-contributor',
            'opencode/muse-spark-1.3-contributor-free' => 'opencode-go/muse-spark-1.3-contributor',
            'opencode/muse-spark-1.2-contributor-free' => 'opencode-go/muse-spark-1.2-contributor',
            'opencode/gpt-5.6-luna'                  => 'opencode-go/gpt-5.6-luna',
            'opencode/claude-haiku-4-5'              => 'opencode-go/grok-4.6',
            'opencode/claude-sonnet-4-5'             => 'opencode-go/grok-4.6',
            'opencode/gemini-3-flash'                => 'opencode-go/grok-4.6',
            'opencode/deepseek-v4-flash'             => 'opencode-go/deepseek-v4-flash',
            'opencode/kimi-k2.6'                     => 'opencode-go/kimi-k2.6',
            'opencode/qwen3.6-plus'                  => 'opencode-go/qwen3.6-plus',
            'opencode/glm-5.3-flash'                 => 'opencode-go/glm-5.3-flash',
            'opencode/big-pickle'                    => 'opencode-go/grok-4.6',
            'deepseek/deepseek-chat'                 => 'opencode-go/deepseek-v4-flash',
            'openrouter/free'                        => 'opencode-go/muse-spark-1.2-contributor',
            'openai/gpt-oss-120b:free'               => 'opencode-go/muse-spark-1.2-contributor',
            'openai/gpt-oss-20b:free'                => 'opencode-go/muse-spark-1.2-contributor',
            'openai/gpt-4o-mini'                     => 'opencode-go/gpt-5.6-luna',
            'anthropic/claude-3.5-haiku'             => 'opencode-go/grok-4.6',
            'google/gemini-2.5-flash'                => 'opencode-go/grok-4.6',
            'deepseek/deepseek-r1'                   => 'opencode-go/deepseek-v4-flash',
            'meta-llama/llama-3.3-70b-instruct'      => 'opencode-go/qwen3.6-plus',
        ];
        if (isset($legacyMap[$model])) return $legacyMap[$model];
        if (str_starts_with($model, 'opencode/') && !str_starts_with($model, 'opencode-go/')) {
            $goVersion = 'opencode-go/' . substr($model, strlen('opencode/'));
            if (isset(self::MODELS[$goVersion])) return $goVersion;
            return $goVersion;
        }
        if ($model === '' || !self::isAllowedModel($model)) return self::DEFAULT_MODEL;
        if (!str_starts_with($model, 'opencode-go/') && !str_contains($model, '/')) {
            return 'opencode-go/' . $model;
        }
        if (!str_starts_with($model, 'opencode-go/') && str_contains($model, '/')) {
            return 'opencode-go/' . ltrim($model, '/');
        }
        return $model;
    }

    public static function isGoModel(string $model): bool {
        return str_starts_with($model, 'opencode-go/');
    }

    public static function getApiKey(): string {
        return self::getApiKeyForModel(self::DEFAULT_MODEL);
    }

    public static function getApiKeyForModel(string $model): string {
        $k = parent::constOrEnv('OPENCODE_GO_API_KEY');
        if ($k !== '') return $k;
        $k2 = parent::constOrEnv('OPENCODE_API_KEY');
        if ($k2 !== '') return $k2;
        $candidates = [
            (getenv('HOME') ?: ($_SERVER['HOME'] ?? '')) . '/.local/share/opencode/auth.json',
            (getenv('USERPROFILE') ?: '') . '/.local/share/opencode/auth.json',
        ];
        foreach ($candidates as $authFile) {
            if ($authFile && is_file($authFile)) {
                $raw = @file_get_contents($authFile);
                $j = json_decode((string)$raw, true);
                if (isset($j['opencode-go']['key']) && is_string($j['opencode-go']['key']) && $j['opencode-go']['key'] !== '') return $j['opencode-go']['key'];
                if (isset($j['opencode']['key']) && is_string($j['opencode']['key']) && $j['opencode']['key'] !== '') return $j['opencode']['key'];
            }
        }
        return '';
    }

    public static function getFormatForModel(string $model): string {
        $bare = preg_replace('#^opencode-go/#', '', $model);
        $responses = ['grok-4.6','gpt-5.6-luna','muse-spark-1.2-contributor','muse-spark-1.3-contributor','muse-spark-1.2','muse-spark-1.3'];
        $anthropic = ['minimax-m2.7','minimax-m3','minimax-m2.5','qwen3.6-plus','qwen3.7-plus','qwen3.7-max','qwen3.8-flash','qwen3.8-max','qwen3.5-plus','qwen3.6-plus'];
        if (in_array($bare, $responses, true)) return 'openai';
        if (in_array($bare, $anthropic, true)) return 'anthropic';
        return 'oa-compat';
    }

    public static function getEndpointForModel(string $model): string {
        $fmt = self::getFormatForModel($model);
        if ($fmt === 'openai') return self::RESPONSES_ENDPOINT;
        if ($fmt === 'anthropic') return self::MESSAGES_ENDPOINT;
        return self::CHAT_ENDPOINT;
    }

    protected static function providerName(): string { return 'OpenCode'; }
    protected static function modelsCacheFile(): string { return BASE_PATH . '/storage/opencode_models.json'; }
    protected static function modelsEndpoint(): string { return self::MODELS_ENDPOINT; }
    protected static function modelPricing(): array { return self::MODEL_PRICING; }
    protected static function modelContextMap(): array {
        return [
            'opencode-go/grok-4.6' => 200000, 'opencode-go/gpt-5.6-luna' => 272000,
            'opencode-go/glm-5.3-flash' => 131072, 'opencode-go/glm-5.3' => 131072,
            'opencode-go/glm-5.2' => 131072, 'opencode-go/glm-5.1' => 131072,
            'opencode-go/kimi-k2.6' => 131072, 'opencode-go/kimi-k3' => 131072, 'opencode-go/kimi-k2.7-code' => 131072,
            'opencode-go/deepseek-v4-flash' => 131072, 'opencode-go/deepseek-v4.1-flash' => 131072,
            'opencode-go/deepseek-v4-pro' => 131072, 'opencode-go/deepseek-v4-flash-vision-exp' => 131072,
            'opencode-go/qwen3.6-plus' => 131072, 'opencode-go/qwen3.7-plus' => 131072, 'opencode-go/qwen3.7-max' => 200000,
            'opencode-go/qwen3.8-flash' => 131072, 'opencode-go/qwen3.8-max' => 200000,
            'opencode-go/minimax-m2.7' => 131072, 'opencode-go/minimax-m3' => 131072,
            'opencode-go/mimo-v2.5' => 131072, 'opencode-go/mimo-v2.5-pro' => 131072,
            'opencode-go/muse-spark-1.2-contributor' => 131072, 'opencode-go/muse-spark-1.3-contributor' => 131072,
            'opencode-go/longcat-2.0' => 131072, 'opencode-go/hy3' => 131072, 'opencode-go/hy4-preview' => 131072,
        ];
    }
    protected static function modelsAuthHeaders(string $apiKey): array {
        $headers = [];
        if ($apiKey !== '') $headers[] = 'Authorization: Bearer ' . $apiKey;
        $headers[] = 'User-Agent: ' . parent::userAgent();
        return $headers;
    }
    protected static function extractModelList(array $data): ?array {
        $list = $data['data'] ?? (is_array($data) && isset($data[0]['id']) ? $data : null);
        if (!is_array($list) || !$list) return null;
        $merged = [];
        $seen = [];
        foreach ($list as $row) {
            if (!isset($row['id'])) continue;
            $id = (string)$row['id'];
            if ($id !== '' && strpos($id, '/') === false) {
                $id = 'opencode-go/' . $id;
                $row['id'] = $id;
            } elseif ($id !== '' && !str_starts_with($id, 'opencode-go/')) {
                $row['id'] = 'opencode-go/' . ltrim($id, '/');
            }
            if (isset($seen[$row['id']])) continue;
            $seen[$row['id']] = true;
            $merged[] = $row;
        }
        return $merged ?: null;
    }
    protected static function timeoutHintText(): string {
        return ' (model was slow/stream stalled — try ' . self::DEFAULT_MODEL . ' or gpt-5.6-luna)';
    }

    private static function getFallbackKey(string $model, string $primaryKey): string {
        $candidates = [];
        $candidates[] = parent::constOrEnv('OPENCODE_GO_API_KEY');
        $candidates[] = parent::constOrEnv('OPENCODE_API_KEY');
        $candidates[] = parent::constOrEnv('OPENROUTER_API_KEY');
        foreach ([(getenv('HOME') ?: ($_SERVER['HOME'] ?? '')) . '/.local/share/opencode/auth.json', (getenv('USERPROFILE') ?: '') . '/.local/share/opencode/auth.json'] as $authFile) {
            if ($authFile && is_file($authFile)) {
                $raw = @file_get_contents($authFile);
                $j = json_decode((string)$raw, true);
                if (is_array($j)) {
                    if (isset($j['opencode-go']['key'])) $candidates[] = parent::trimKey($j['opencode-go']['key']);
                    if (isset($j['opencode']['key'])) $candidates[] = parent::trimKey($j['opencode']['key']);
                }
            }
        }
        foreach ($candidates as $k) {
            if ($k !== '' && $k !== $primaryKey) return $k;
        }
        return '';
    }

    private static function buildHeaders(string $apiKey, string $format, string $sessionId = '', string $xTitle = ''): array {
        $headers = ['Content-Type: application/json; charset=utf-8', 'User-Agent: ' . parent::userAgent()];
        if ($sessionId !== '') $headers[] = 'x-opencode-session: ' . $sessionId;
        if ($xTitle !== '') $headers[] = 'X-Title: ' . $xTitle;
        if (defined('BASE_URL') && BASE_URL !== '') $headers[] = 'HTTP-Referer: ' . BASE_URL;
        if ($format === 'anthropic') {
            $headers[] = 'x-api-key: ' . $apiKey;
            $headers[] = 'anthropic-version: 2023-06-01';
        } else {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        return $headers;
    }

    private static function buildPayloadResponses(string $apiModel, array $messages, array $tools, string $toolChoice, float $temperature, int $maxTokens): array {
        $input = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? '';
            if ($role === 'system') {
                $c = (string)($m['content'] ?? '');
                if ($c !== '') $input[] = ['role' => 'system', 'content' => $c];
            } elseif ($role === 'user') {
                $c = $m['content'];
                if (is_string($c)) {
                    $input[] = ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $c]]];
                } elseif (is_array($c)) {
                    $parts = [];
                    foreach ($c as $p) {
                        if (isset($p['type']) && $p['type'] === 'text' && isset($p['text'])) $parts[] = ['type' => 'input_text', 'text' => $p['text']];
                        elseif (isset($p['type']) && $p['type'] === 'image_url') $parts[] = ['type' => 'input_image', 'image_url' => $p['image_url']];
                    }
                    if ($parts) $input[] = ['role' => 'user', 'content' => $parts];
                }
            } elseif ($role === 'assistant') {
                $c = (string)($m['content'] ?? '');
                if ($c !== '') $input[] = ['role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $c]]];
                if (!empty($m['tool_calls']) && is_array($m['tool_calls'])) {
                    foreach ($m['tool_calls'] as $tc) {
                        $name = $tc['function']['name'] ?? '';
                        $args = $tc['function']['arguments'] ?? '{}';
                        $id = $tc['id'] ?? ('call_' . substr(sha1($name . $args), 0, 12));
                        $input[] = ['type' => 'function_call', 'call_id' => $id, 'name' => $name, 'arguments' => is_string($args) ? $args : json_encode($args)];
                    }
                }
            } elseif ($role === 'tool') {
                $content = (string)($m['content'] ?? '');
                $callId = (string)($m['tool_call_id'] ?? '');
                $input[] = ['type' => 'function_call_output', 'call_id' => $callId, 'output' => $content];
            }
        }
        $respTools = null;
        if (!empty($tools)) {
            $respTools = array_map(function($t) {
                if (($t['type'] ?? '') === 'function' && isset($t['function'])) {
                    return ['type' => 'function', 'name' => $t['function']['name'] ?? '', 'description' => $t['function']['description'] ?? '', 'parameters' => $t['function']['parameters'] ?? ['type'=>'object','properties'=>[]]];
                }
                return $t;
            }, $tools);
        }
        $payload = ['model' => $apiModel, 'input' => $input, 'temperature' => $temperature, 'max_output_tokens' => $maxTokens];
        if ($respTools !== null) {
            $payload['tools'] = $respTools;
            if ($toolChoice === 'required') $payload['tool_choice'] = 'required';
            elseif ($toolChoice === 'none') $payload['tool_choice'] = 'none';
            else $payload['tool_choice'] = 'auto';
        }
        return $payload;
    }

    private static function buildPayloadAnthropic(string $apiModel, array $messages, array $tools, string $toolChoice, float $temperature, int $maxTokens): array {
        $system = [];
        $anthMsgs = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? '';
            if ($role === 'system') {
                $c = trim((string)($m['content'] ?? ''));
                if ($c !== '') $system[] = ['type' => 'text', 'text' => $c, 'cache_control' => ['type' => 'ephemeral']];
            } elseif ($role === 'user') {
                $c = $m['content'];
                if (is_string($c)) {
                    $anthMsgs[] = ['role' => 'user', 'content' => [['type' => 'text', 'text' => $c, 'cache_control' => ['type' => 'ephemeral']]]];
                } elseif (is_array($c)) {
                    $parts = [];
                    foreach ($c as $p) {
                        if (($p['type'] ?? '') === 'text' && isset($p['text'])) $parts[] = ['type' => 'text', 'text' => $p['text'], 'cache_control' => ['type' => 'ephemeral']];
                        if (($p['type'] ?? '') === 'image_url') $parts[] = ['type' => 'image', 'source' => ['type'=>'url','url'=> $p['image_url']['url'] ?? ''], 'cache_control' => ['type'=>'ephemeral']];
                    }
                    if ($parts) $anthMsgs[] = ['role' => 'user', 'content' => $parts];
                }
            } elseif ($role === 'assistant') {
                $content = [];
                $c = (string)($m['content'] ?? '');
                if ($c !== '') $content[] = ['type' => 'text', 'text' => $c, 'cache_control' => ['type'=>'ephemeral']];
                if (!empty($m['tool_calls']) && is_array($m['tool_calls'])) {
                    foreach ($m['tool_calls'] as $tc) {
                        $name = $tc['function']['name'] ?? '';
                        $args = $tc['function']['arguments'] ?? '{}';
                        $id = $tc['id'] ?? ('toolu_' . substr(sha1($name.$args),0,12));
                        $decoded = json_decode((string)$args, true);
                        if (!is_array($decoded)) $decoded = ['_raw' => $args];
                        $content[] = ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $decoded, 'cache_control'=>['type'=>'ephemeral']];
                    }
                }
                if ($content) $anthMsgs[] = ['role' => 'assistant', 'content' => $content];
            } elseif ($role === 'tool') {
                $content = (string)($m['content'] ?? '');
                $callId = (string)($m['tool_call_id'] ?? '');
                $anthMsgs[] = ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => $callId, 'content' => $content, 'cache_control'=>['type'=>'ephemeral']]]];
            }
        }
        $anthTools = null;
        if (!empty($tools)) {
            $anthTools = array_map(function($t) {
                $fn = $t['function'] ?? $t;
                return ['name' => $fn['name'] ?? '', 'description' => $fn['description'] ?? '', 'input_schema' => $fn['parameters'] ?? ['type'=>'object','properties'=>[]], 'cache_control'=>['type'=>'ephemeral']];
            }, $tools);
        }
        $payload = ['model' => $apiModel, 'max_tokens' => $maxTokens, 'temperature' => $temperature, 'messages' => $anthMsgs];
        if ($system) $payload['system'] = $system;
        if ($anthTools !== null) {
            $payload['tools'] = $anthTools;
            if ($toolChoice === 'required') $payload['tool_choice'] = ['type' => 'any'];
            elseif ($toolChoice === 'none') $payload['tool_choice'] = ['type' => 'auto'];
            else $payload['tool_choice'] = ['type' => 'auto'];
        }
        return $payload;
    }

    private static function normalizeResponse(array $data, string $format): array {
        if ($format === 'openai') {
            $r = $data['response'] ?? $data;
            if (isset($r['output']) && is_array($r['output'])) {
                $textParts = [];
                $toolCalls = [];
                foreach ($r['output'] as $o) {
                    if (($o['type'] ?? '') === 'message' && isset($o['content']) && is_array($o['content'])) {
                        foreach ($o['content'] as $c) {
                            if (($c['type'] ?? '') === 'output_text' && isset($c['text'])) $textParts[] = $c['text'];
                        }
                    }
                    if (($o['type'] ?? '') === 'function_call') {
                        $name = $o['name'] ?? '';
                        $args = $o['arguments'] ?? '{}';
                        $id = $o['call_id'] ?? $o['id'] ?? ('call_' . substr(sha1($name.$args),0,12));
                        $toolCalls[] = ['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => is_string($args) ? $args : json_encode($args)]];
                    }
                }
                $content = implode('', $textParts);
                $finish = $r['stop_reason'] ?? $data['stop_reason'] ?? null;
                $finishMap = ['stop'=>'stop','tool_call'=>'tool_calls','tool_calls'=>'tool_calls','max_output_tokens'=>'length','length'=>'length'];
                $finishReason = $finishMap[$finish] ?? $finish;
                $usage = $r['usage'] ?? $data['usage'] ?? null;
                $normUsage = null;
                if (is_array($usage)) {
                    $pt = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0;
                    $ct = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0;
                    $normUsage = ['prompt_tokens'=>$pt,'completion_tokens'=>$ct,'total_tokens'=>($pt+$ct),'cost'=> $data['cost'] ?? null];
                } else {
                    $normUsage = $data['usage'] ?? null;
                }
                return ['content'=>$content,'tool_calls'=>$toolCalls ?: null,'finish_reason'=>$finishReason,'usage'=>$normUsage,'raw'=>$data];
            }
        } elseif ($format === 'anthropic') {
            if (isset($data['type']) && $data['type'] === 'message' && isset($data['content']) && is_array($data['content'])) {
                $textParts = [];
                $toolCalls = [];
                foreach ($data['content'] as $b) {
                    if (($b['type'] ?? '') === 'text' && isset($b['text'])) $textParts[] = $b['text'];
                    if (($b['type'] ?? '') === 'tool_use') {
                        $name = $b['name'] ?? '';
                        $id = $b['id'] ?? ('toolu_' . substr(sha1($name.json_encode($b['input'] ?? [])),0,12));
                        $inp = $b['input'] ?? [];
                        $args = is_string($inp) ? $inp : json_encode($inp);
                        $toolCalls[] = ['id'=>$id,'type'=>'function','function'=>['name'=>$name,'arguments'=>$args]];
                    }
                }
                $content = implode('', $textParts);
                $stop = $data['stop_reason'] ?? null;
                $finishMap = ['end_turn'=>'stop','tool_use'=>'tool_calls','max_tokens'=>'length'];
                $finishReason = $finishMap[$stop] ?? $stop;
                $u = $data['usage'] ?? null;
                $normUsage = null;
                if (is_array($u)) {
                    $pt = $u['input_tokens'] ?? 0;
                    $ct = $u['output_tokens'] ?? 0;
                    $normUsage = ['prompt_tokens'=>$pt,'completion_tokens'=>$ct,'total_tokens'=>($pt+$ct),'cost'=> $data['cost'] ?? null];
                }
                return ['content'=>$content,'tool_calls'=>$toolCalls ?: null,'finish_reason'=>$finishReason,'usage'=>$normUsage,'raw'=>$data];
            }
        }
        [$oaContent, $oaToolCalls, $oaFinish, $oaUsage] = parent::normalizeOpenAiChoice($data);
        return ['content'=>$oaContent,'tool_calls'=>$oaToolCalls,'finish_reason'=>$oaFinish,'usage'=>$oaUsage,'raw'=>$data];
    }

    public static function chat(
        array $messages,
        string $model,
        float $temperature = 0.7,
        int $maxTokens = 4096,
        int $retries = 2,
        string $sessionId = ''
    ): string {
        $model = self::normalizeModel($model);
        $apiKey = self::getApiKeyForModel($model);
        if ($apiKey === '') {
            throw new Exception("OpenCode Go API key is not configured. Add OPENCODE_GO_API_KEY to .env (https://opencode.ai/zen/go)");
        }
        $maxTokens = parent::clampTokens($maxTokens);
        $temperature = parent::clampTemp((float)$temperature);
        $apiModel = preg_replace('#^opencode-go/#', '', $model);
        $format = self::getFormatForModel($model);
        $endpoint = self::getEndpointForModel($model);
        if ($format === 'openai') $payloadArr = self::buildPayloadResponses($apiModel, $messages, [], 'auto', $temperature, $maxTokens);
        elseif ($format === 'anthropic') $payloadArr = self::buildPayloadAnthropic($apiModel, $messages, [], 'auto', $temperature, $maxTokens);
        else $payloadArr = static::buildChatPayload($apiModel, $messages, [], 'auto', $temperature, $maxTokens);
        $payload = parent::encodePayload($payloadArr, 'OpenCode');
        $headers = self::buildHeaders($apiKey, $format, $sessionId, 'CMS Page Editor');
        $attempt = 0;
        $lastError = null;
        $triedFallback = false;
        while ($attempt <= $retries) {
            $attempt++;
            try {
                $data = self::goRequest($payload, $endpoint, $headers, 0, $apiKey, $model);
                $norm = self::normalizeResponse($data, $format);
                $content = $norm['content'] ?? '';
                $finishReason = $norm['finish_reason'] ?? null;
                if (!is_string($content) || trim($content) === '') {
                    $detail = $data['error']['message'] ?? $data['error'] ?? 'empty completion (finish_reason=' . ($finishReason ?? 'null') . ')';
                    throw new Exception('OpenCode returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
                }
                if ($finishReason === 'length') $content = trim((string)$content) . "\n\n[Response truncated: hit {$maxTokens}-token output limit.]";
                return trim($content);
            } catch (Exception $e) {
                $msg = $e->getMessage();
                $is500 = str_contains($msg, 'HTTP 500') || str_contains($msg, 'Internal server error');
                $isAuth = str_contains($msg, 'invalid or unauthorized');
                if ($is500 && $attempt <= $retries) {
                    $lastError = $e;
                    usleep(1000000 * $attempt + random_int(0, 500000));
                    continue;
                }
                if ($is500 && OpenRouter::getApiKey() !== '') {
                    try {
                        return OpenRouter::chat($messages, 'deepseek/deepseek-chat', $temperature, $maxTokens, 1);
                    } catch (Exception $e2) {
                        throw new Exception($msg . ' (OpenRouter fallback also failed: ' . $e2->getMessage() . ')');
                    }
                }
                if ($is500) throw new Exception($msg . ' — Opencode service down (500). Try again in 30s or switch model to ' . self::DEFAULT_MODEL . '.');
                if ($isAuth && !$triedFallback) {
                    $fallback = self::getFallbackKey($model, $apiKey);
                    if ($fallback !== '' && $fallback !== $apiKey) {
                        $triedFallback = true;
                        $headers = self::buildHeaders($fallback, $format, $sessionId, 'CMS Page Editor');
                        try {
                            $data = self::goRequest($payload, $endpoint, $headers, 0, $fallback, $model);
                            $norm = self::normalizeResponse($data, $format);
                            $content = $norm['content'] ?? '';
                            if (trim($content) !== '') return trim($content);
                        } catch (Exception $e2) { throw $e; }
                    }
                    throw $e;
                }
                throw $e;
            }
        }
        throw $lastError ?? new Exception('OpenCode request failed');
    }

    protected static function authErrorMessage(string $provider, string $response, string $apiKey, string $model): string {
        $body = mb_substr($response, 0, 800);
        $masked = $apiKey !== '' ? substr($apiKey, 0, 8) . '…' . substr($apiKey, -4) . ' (' . strlen($apiKey) . ' chars)' : 'empty';
        $hint = '';
        if (str_contains(strtolower($body), 'expired') || str_contains(strtolower($body), 'revoked')) $hint = ' — key appears expired/revoked';
        elseif (str_contains(strtolower($body), 'invalid')) $hint = ' — key rejected as invalid';
        return 'OpenCode Go API key is invalid or unauthorized (model: ' . ($model ?: 'n/a') . ', key: ' . $masked . ')' . $hint . '. Body: ' . $body . '. Fix: check OPENCODE_GO_API_KEY in .env (https://opencode.ai/zen/go), ensure no quotes/spaces, then delete storage/opencode_models.json and retry.';
    }

    protected static function serverErrorSuffix(string $body): string {
        if (str_contains(strtolower($body), 'internal server error')) return ' — Opencode service temporarily unavailable.';
        return '';
    }

    protected static function httpErrorHint(int $code, string $detail, string $endpoint): string {
        if ($code === 400 && str_contains(strtolower($detail), 'tool_choice')) return ' (tool_choice format mismatch for endpoint ' . $endpoint . ')';
        return '';
    }

    private static function goRequest(string $payload, string $endpoint, array $headers, int $retries, string $apiKey, string $model = ''): array {
        return parent::_request($endpoint, $payload, $headers, $retries, 'OpenCode', $apiKey, $model, 10, 60, ' (model was slow/stream stalled — try ' . self::DEFAULT_MODEL . ' or gpt-5.6-luna)');
    }

    public static function chatWithTools(
        array $messages,
        string $model,
        array $tools,
        float $temperature = 0.5,
        int $maxTokens = 8192,
        int $retries = 2,
        string $toolChoice = 'auto',
        string $sessionId = ''
    ): array {
        $model = self::normalizeModel($model);
        $apiKey = self::getApiKeyForModel($model);
        if ($apiKey === '') {
            throw new Exception("OpenCode Go API key is not configured. Add OPENCODE_GO_API_KEY to .env (https://opencode.ai/zen/go)");
        }
        $maxTokens = parent::clampTokens($maxTokens);
        $temperature = parent::clampTemp((float)$temperature);
        $toolChoice = parent::checkToolChoice($toolChoice);
        $apiModel = preg_replace('#^opencode-go/#', '', $model);
        $format = self::getFormatForModel($model);
        $endpoint = self::getEndpointForModel($model);
        if ($format === 'openai') $payloadArr = self::buildPayloadResponses($apiModel, $messages, $tools, $toolChoice, $temperature, $maxTokens);
        elseif ($format === 'anthropic') $payloadArr = self::buildPayloadAnthropic($apiModel, $messages, $tools, $toolChoice, $temperature, $maxTokens);
        else $payloadArr = static::buildChatPayload($apiModel, $messages, $tools, $toolChoice, $temperature, $maxTokens);
        $payload = parent::encodePayload($payloadArr, 'OpenCode');
        $headers = self::buildHeaders($apiKey, $format, $sessionId, 'CMS AI Studio');
        $attempt = 0;
        $lastError = null;
        $triedFallback = false;
        while ($attempt <= $retries) {
            $attempt++;
            try {
                $data = self::goRequest($payload, $endpoint, $headers, 1, $apiKey, $model);
            } catch (Exception $e) {
                $msg = $e->getMessage();
                $is500 = str_contains($msg, 'HTTP 500') || str_contains($msg, 'Internal server error');
                $isAuth = str_contains($msg, 'invalid or unauthorized');
                $isToolChoice400 = str_contains(strtolower($msg), 'tool_choice') && str_contains($msg, 'HTTP 400');
                if ($is500) {
                    $hasOpenRouter = OpenRouter::getApiKey() !== '';
                    if ($hasOpenRouter) {
                        try {
                            $fallbackModel = 'deepseek/deepseek-chat';
                            error_log('Opencode 500 fallback to OpenRouter ' . $fallbackModel . ' for ' . $model . ' endpoint ' . $endpoint);
                            return OpenRouter::chatWithTools($messages, $fallbackModel, $tools, $temperature, $maxTokens, 1, $toolChoice);
                        } catch (Exception $e2) {
                            throw new Exception($msg . ' (OpenRouter fallback also failed: ' . $e2->getMessage() . '). Try again in 30s or switch model to ' . self::DEFAULT_MODEL);
                        }
                    }
                    if ($attempt <= $retries) { $lastError = $e; usleep(800000 + random_int(0,500000)); continue; }
                    $suffix = $hasOpenRouter ? '' : ' (OpenRouter fallback not configured — set OPENROUTER_API_KEY to enable)';
                    throw new Exception($msg . ' — Opencode service temporarily unavailable. Will retry with backoff; if persists, wait 30s or switch model to ' . self::DEFAULT_MODEL . $suffix . '. See https://opencode.ai/docs/go');
                } elseif (!$triedFallback && $isAuth) {
                    $fallback = self::getFallbackKey($model, $apiKey);
                    if ($fallback !== '' && $fallback !== $apiKey) {
                        $triedFallback = true;
                        $headers = self::buildHeaders($fallback, $format, $sessionId, 'CMS AI Studio');
                        try {
                            $data = self::goRequest($payload, $endpoint, $headers, 1, $fallback, $model);
                            $apiKey = $fallback;
                        } catch (Exception $e2) {
                            throw $e;
                        }
                    } else {
                        throw $e;
                    }
                } elseif ($isToolChoice400) {
                    $fallbackChoice = 'auto';
                    if ($format === 'openai') $payloadArr2 = self::buildPayloadResponses($apiModel, $messages, $tools, $fallbackChoice, $temperature, $maxTokens);
                    elseif ($format === 'anthropic') $payloadArr2 = self::buildPayloadAnthropic($apiModel, $messages, $tools, $fallbackChoice, $temperature, $maxTokens);
                    else $payloadArr2 = static::buildChatPayload($apiModel, $messages, $tools, $fallbackChoice, $temperature, $maxTokens);
                    $fallbackPayload = parent::encodePayload($payloadArr2, 'OpenCode');
                    try {
                        $data = self::goRequest($fallbackPayload, $endpoint, $headers, 1, $apiKey, $model);
                    } catch (Exception $e2) { throw $e; }
                } else {
                    $lower = strtolower($msg);
                    $isTransient = str_contains($lower, 'rate limit') || str_contains($lower, '429') || str_contains($lower, 'network error') || str_contains($lower, 'timed out') || str_contains($lower, 'timeout') || str_contains($msg, 'HTTP 5');
                    if ($isTransient && $attempt <= $retries) { $lastError = $e; usleep(500000 + random_int(0, 500000)); continue; }
                    throw $e;
                }
            }
            $norm = self::normalizeResponse($data, $format);
            $content = $norm['content'] ?? '';
            $toolCalls = $norm['tool_calls'] ?? null;
            $finishReason = $norm['finish_reason'] ?? null;
            $usage = $norm['usage'] ?? ($data['usage'] ?? null);
            if ($content === '' && empty($toolCalls)) {
                $detail = $data['error']['message'] ?? $data['error'] ?? null;
                if ($detail === null) $detail = 'empty completion (finish_reason=' . ($finishReason ?? 'null') . ') raw=' . mb_substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 800);
                $lastError = new Exception('OpenCode returned no content: ' . (is_string($detail) ? $detail : json_encode($detail)));
                if ($attempt <= $retries) { usleep(500000 + random_int(0, 500000)); continue; }
                throw $lastError;
            }
            if ($finishReason === 'length' && $content !== '') {
                $content = trim($content) . "\n\n[Response truncated: hit {$maxTokens}-token output limit.]";
            }
            return ['content' => $content, 'tool_calls' => $toolCalls, 'finish_reason' => $finishReason, 'usage' => $usage];
        }
        throw $lastError ?? new Exception('OpenCode request failed');
    }
}
