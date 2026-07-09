<?php
$envFile = '/home/kuplyuta/.env'; // adjust to actual .env location
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        putenv(trim($key) . '=' . trim($value));
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
