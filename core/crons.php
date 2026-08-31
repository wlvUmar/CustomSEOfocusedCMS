<?php
// Resolve .env robustly for both web and cron contexts (remaining-bugs #9)
$basePath = defined('BASE_PATH') ? BASE_PATH : (getenv('BASE_PATH') ?: __DIR__ . '/..');
$envFile = $basePath . '/.env';
if (!is_file($envFile)) {
    $alt = '/home/kuplyuta/.env';
    if (is_file($alt)) $envFile = $alt;
}
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
            $value = $m[2];
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function checkAndCacheBotHealth(): void
{
    $cacheFile = __DIR__ . '/cache/bot_health_cache.json';
    $callbackUrl = getenv('BOT_CALLBACK_URL') ?: '';
    $healthy = false;

    if ($callbackUrl !== '') {
        $healthUrl = rtrim($callbackUrl, '/') . '/health';
        $ch = curl_init($healthUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 3,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno === 0 && $httpCode === 200 && $body !== false) {
            $decoded = json_decode($body, true);
            $healthy = is_array($decoded) && ($decoded['status'] ?? null) === 'ok';
        }
    }

    file_put_contents($cacheFile, json_encode([
        'healthy'    => $healthy,
        'checked_at' => time(),
    ]));
}

checkAndCacheBotHealth();
