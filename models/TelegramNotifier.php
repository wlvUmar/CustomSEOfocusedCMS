<?php
class TelegramNotifier {
    // Sends a review-result payload to the bot callback server.
    public function sendCallback(array $payload): bool {
        $callbackUrl = getenv('BOT_CALLBACK_URL') ?: '';
        $secret = getenv('BOT_CALLBACK_SECRET') ?: '';
        if (!$callbackUrl || !$secret) {
            error_log('[TelegramNotifier] Missing BOT_CALLBACK_URL or BOT_CALLBACK_SECRET');
            return false;
        }
        $endpoint = rtrim($callbackUrl, '/') . '/webhook/review-result';
        $json = json_encode($payload);
        if ($json === false) {
            error_log('[TelegramNotifier] Failed to encode callback payload');
            return false;
        }

        $maxAttempts = 4;
        $backoffMs   = [0, 500, 1500, 3000]; // delay before each attempt

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($backoffMs[$attempt - 1] > 0) {
                usleep($backoffMs[$attempt - 1] * 1000);
            }

            // Regenerate timestamp+signature every attempt — reusing an old
            // timestamp on a retry can land outside the 60s window on the
            // Python side and get rejected as "expired" even though it's a
            // legitimate delivery.
            $timestamp = (string) time();
            $signature = hash_hmac('sha256', $json . $timestamp, $secret);

            error_log('[TelegramNotifier] Attempt ' . $attempt . '/' . $maxAttempts
                . ' sending callback to ' . $endpoint
                . ' for request_id=' . ($payload['request_id'] ?? 'unknown'));

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Callback-Timestamp: ' . $timestamp,
                'X-Callback-Signature: ' . $signature,
            ]);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                error_log('[TelegramNotifier] attempt ' . $attempt . ' curl error: ' . $err . ' url=' . $endpoint);
                continue; // network-level failure -> retry
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                error_log('[TelegramNotifier] callback sent http=' . $httpCode . ' attempt=' . $attempt);
                return true;
            }

            error_log('[TelegramNotifier] attempt ' . $attempt . ' failed http=' . $httpCode
                . ' body=' . substr((string)$resp, 0, 500));

            // Don't retry on 4xx (bad payload/auth) — retrying won't fix it.
            if ($httpCode >= 400 && $httpCode < 500) {
                return false;
            }
            // 5xx or connection failure with a response object -> retry
        }

        error_log('[TelegramNotifier] all ' . $maxAttempts . ' attempts failed, giving up. request_id=' . ($payload['request_id'] ?? 'unknown'));
        return false;
    }
}