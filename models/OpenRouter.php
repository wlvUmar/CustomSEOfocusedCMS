<?php
// path: ./models/OpenRouter.php

require_once BASE_PATH . '/models/BaseLlmClient.php';

class OpenRouter extends BaseLlmClient {
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
        return parent::constOrEnv('OPENROUTER_API_KEY');
    }

    protected static function providerName(): string { return 'OpenRouter'; }
    protected static function chatEndpoint(): string { return self::API_ENDPOINT; }
    protected static function modelsEndpoint(): string { return 'https://openrouter.ai/api/v1/models'; }
    protected static function modelsCacheFile(): string { return BASE_PATH . '/storage/openrouter_models.json'; }
    protected static function modelPricing(): array { return self::MODEL_PRICING; }
    protected static function modelContextMap(): array {
        return [
            'deepseek/deepseek-chat' => 131072, 'openrouter/free' => 131072,
            'openai/gpt-oss-120b:free' => 131072, 'openai/gpt-oss-20b:free' => 131072,
            'openai/gpt-4o-mini' => 128000, 'anthropic/claude-3.5-haiku' => 200000,
            'google/gemini-2.5-flash' => 1048576, 'deepseek/deepseek-r1' => 131072,
            'meta-llama/llama-3.3-70b-instruct' => 131072,
        ];
    }
    protected static function missingKeyMessage(): string {
        return 'OpenRouter API key is not configured. Add OPENROUTER_API_KEY to .env';
    }
    protected static function lengthSuffix(int $maxTokens): string {
        return "\n\n[Response truncated: hit {$maxTokens}-token output limit. Try a smaller field/section or raise max_tokens.]";
    }
    protected static function timeoutHintText(): string {
        return ' (model was slow/stream stalled — try DeepSeek Chat or GPT-4o-mini instead of R1/120B free)';
    }
}