<?php

class BotNonceStore {
    private $dir;

    public function __construct($dir = null) {
        $this->dir = $dir ? $dir : BASE_PATH . '/storage/bot_nonces';
    }

    public function isNonceUsed($nonceKey) {
        if (!preg_match('/^[a-f0-9]{64}$/i', (string)$nonceKey)) {
            return false;
        }
        $file = $this->dir . '/' . $nonceKey . '.json';
        if (!is_file($file)) {
            return false;
        }
        $data = @json_decode(@file_get_contents($file), true);
        if (!$data || !isset($data['expires'])) {
            return false;
        }
        if (time() > (int)$data['expires']) {
            @unlink($file);
            return false;
        }
        return true;
    }

    public function markNonce($nonceKey) {
        if (!preg_match('/^[a-f0-9]{64}$/i', (string)$nonceKey)) {
            return;
        }
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0750, true);
        }
        $file = $this->dir . '/' . $nonceKey . '.json';
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $payload = json_encode(array('expires' => time() + 120));
        $fh = @fopen($tmp, 'wb');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                fwrite($fh, $payload);
                fflush($fh);
                flock($fh, LOCK_UN);
            }
            fclose($fh);
            @rename($tmp, $file);
        } else {
            @file_put_contents($file, $payload, LOCK_EX);
        }
    }

    public function maybePrune() {
        if (mt_rand(1, 100) !== 1) {
            return;
        }
        $this->pruneExpired();
    }

    public function pruneExpired() {
        foreach (@glob($this->dir . '/*.json') ?: array() as $f) {
            $d = @json_decode(@file_get_contents($f), true);
            if ($d && isset($d['expires']) && time() > (int)$d['expires']) {
                @unlink($f);
            }
        }
    }
}
