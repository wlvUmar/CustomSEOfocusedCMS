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
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $json . $timestamp, $secret);
        error_log('[TelegramNotifier] Sending callback to ' . $endpoint . ' for request_id=' . ($payload['request_id'] ?? 'unknown'));

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Callback-Timestamp: ' . $timestamp,
            'X-Callback-Signature: ' . $signature,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log('[TelegramNotifier] curl error: ' . $err . ' url=' . $endpoint);
            return false;
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[TelegramNotifier] callback failed http=' . $httpCode . ' body=' . substr((string)$resp, 0, 500));
            return false;
        }
        error_log('[TelegramNotifier] callback sent http=' . $httpCode);
        return $httpCode >= 200 && $httpCode < 300;
    }
}
