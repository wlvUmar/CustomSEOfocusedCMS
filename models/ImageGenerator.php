<?php
// path: ./models/ImageGenerator.php
// AI image generation through OpenRouter's Images API (POST /api/v1/images, key: OPENROUTER_API_KEY).
// One image per call; the admin (Media → Quick add) reviews the result before anything is saved.
// Guards: model + aspect whitelist, prompt length cap, daily cap (IMAGE_GEN_DAILY_LIMIT, default 40), and an
// image_generations log (migration 019) for the cap and a cost trail.
// Errors are RuntimeException with code = HTTP status (402 no credit, 429 limit, 502 generation failed …).

require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/models/OpenRouter.php';

class ImageGenerator {
    private const ENDPOINT = 'https://openrouter.ai/api/v1/images';
    public const MAX_PROMPT = 600;

    /** model id => label for the admin select (ids from OpenRouter's image-generation docs). */
    public const MODELS = [
        'google/gemini-2.5-flash-image' => 'Gemini 2.5 Flash Image (быстро, недорого)',
        'openai/gpt-image-1'            => 'GPT Image 1',
        'black-forest-labs/flux.2-pro'  => 'FLUX.2 Pro',
        'bytedance-seed/seedream-4.5'   => 'Seedream 4.5',
    ];
    public const ASPECTS = ['4:3', '1:1', '16:9', '3:4', '3:2'];

    public static function isConfigured(): bool {
        return OpenRouter::getApiKey() !== '';
    }

    public static function dailyLimit(): int {
        return max(1, min(500, (int)(getenv('IMAGE_GEN_DAILY_LIMIT') ?: 40)));
    }

    /** Successful generations today. Throws when the table is missing (migration 019 not applied). */
    public static function usedToday(): int {
        $row = Database::getInstance()->fetchOne("SELECT COUNT(*) AS c FROM image_generations WHERE created_at >= CURDATE() AND status = 'ok'");
        return (int)($row['c'] ?? 0);
    }

    /**
     * @return array{b64:string,media_type:string,cost:?float,model:string}
     * @throws InvalidArgumentException (param name) on bad input
     * @throws RuntimeException (code = HTTP status) on provider/limit errors
     */
    public static function generate(string $model, string $prompt, string $aspect, ?int $pageId): array {
        if (!isset(self::MODELS[$model])) throw new InvalidArgumentException('model');
        if (!in_array($aspect, self::ASPECTS, true)) throw new InvalidArgumentException('aspect_ratio');
        $prompt = trim($prompt);
        $len = mb_strlen($prompt);
        if ($len < 3 || $len > self::MAX_PROMPT) throw new InvalidArgumentException('prompt');

        $key = OpenRouter::getApiKey();
        if ($key === '') throw new RuntimeException('OPENROUTER_API_KEY is not configured in .env', 500);

        try {
            $used = self::usedToday();
        } catch (Throwable $e) {
            error_log('ImageGenerator: usage table unavailable: ' . $e->getMessage());
            throw new RuntimeException('Таблица image_generations не найдена — запустите migrate.php', 500);
        }
        if ($used >= self::dailyLimit()) {
            throw new RuntimeException('Дневной лимит генераций исчерпан (' . self::dailyLimit() . '). Поднять: IMAGE_GEN_DAILY_LIMIT в .env', 429);
        }

        $payload = json_encode(
            ['model' => $model, 'prompt' => $prompt, 'aspect_ratio' => $aspect, 'n' => 1],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 150,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '' || $resp === false) {
            self::log($pageId, $model, $prompt, null, 'fail');
            throw new RuntimeException('Нет ответа от OpenRouter: ' . $err, 502);
        }
        $data = json_decode((string)$resp, true);
        if ($code < 200 || $code >= 300) {
            self::log($pageId, $model, $prompt, null, 'fail');
            $msg = is_array($data) ? (string)($data['error']['message'] ?? '') : '';
            throw new RuntimeException($msg !== '' ? $msg : 'HTTP ' . $code, $code);
        }
        $item = is_array($data) ? ($data['data'][0] ?? null) : null;
        if (!is_array($item) || empty($item['b64_json'])) {
            self::log($pageId, $model, $prompt, null, 'fail');
            throw new RuntimeException('Модель не вернула изображение', 502);
        }
        $cost = isset($data['usage']['cost']) ? (float)$data['usage']['cost'] : null;
        self::log($pageId, $model, $prompt, $cost, 'ok');

        return [
            'b64' => (string)$item['b64_json'],
            'media_type' => (string)($item['media_type'] ?? 'image/png'),
            'cost' => $cost,
            'model' => $model,
        ];
    }

    private static function log(?int $pageId, string $model, string $prompt, ?float $cost, string $status): void {
        try {
            Database::getInstance()->query(
                "INSERT INTO image_generations (page_id, model, prompt, cost_usd, status) VALUES (?, ?, ?, ?, ?)",
                [$pageId, $model, mb_substr($prompt, 0, 700), $cost, $status]
            );
        } catch (Throwable $e) {
            error_log('ImageGenerator: log failed: ' . $e->getMessage());
        }
    }
}
