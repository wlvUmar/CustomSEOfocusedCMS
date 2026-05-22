<?php

class TelegramNotifier {
    // Sends a review-result payload to the bot callback server.
    public function sendCallback(array $payload): bool {
        $callbackUrl = getenv('BOT_CALLBACK_URL') ?: '';
        $secret = getenv('BOT_CALLBACK_SECRET') ?: '';
        if (!$callbackUrl || !$secret) return false;

        $endpoint = rtrim($callbackUrl, '/') . '/webhook/review-result';
        $json = json_encode($payload);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $json . $timestamp, $secret);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Callback-Timestamp: ' . $timestamp,
            'X-Callback-Signature: ' . $signature,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log('[TelegramNotifier] curl error: ' . $err);
            return false;
        }
        return $httpCode >= 200 && $httpCode < 300;
    }
}
