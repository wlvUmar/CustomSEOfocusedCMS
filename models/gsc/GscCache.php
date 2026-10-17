<?php
// path: ./models/gsc/GscCache.php
// GSC file cache: storage/gsc_cache/*.json, 1h TTL, 200-file GC.
// Loaded via models/GscClient.php (thin facade keeps caller API stable).

class GscCache {

    private const CACHE_TTL = 3600; // seconds

    public static function key(string $siteUrl, array $payload): string {
        return sha1($siteUrl . '|' . json_encode($payload));
    }

    public static function get(string $key): ?array {
        $dir = BASE_PATH . '/storage/gsc_cache';
        $file = $dir . '/' . $key . '.json';
        if (!is_file($file)) return null;
        $mtime = @filemtime($file);
        if ($mtime === false || (time() - $mtime) > self::CACHE_TTL) return null;
        $fh = @fopen($file, 'r');
        if (!$fh) return null;
        @flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    public static function set(string $key, array $data): void {
        $dir = BASE_PATH . '/storage/gsc_cache';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $file = $dir . '/' . $key . '.json';
        // Bounded cache: cap at 200 files, GC oldest if over
        $files = glob($dir . '/*.json') ?: [];
        if (count($files) > 200) {
            usort($files, fn($a,$b) => filemtime($a) <=> filemtime($b));
            foreach (array_slice($files, 0, count($files) - 180) as $old) {
                if (is_link($old)) continue; // skip symlink (TOCTOU)
                @unlink($old);
            }
        }
        $fh = @fopen($file, 'c');
        if ($fh) {
            @flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            @flock($fh, LOCK_UN);
            @fclose($fh);
        } else {
            @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }

    public static function clearCache(): void {
        $dir = BASE_PATH . '/storage/gsc_cache';
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            if (is_link($f)) continue;
            @unlink($f);
        }
    }
}
