<?php
// path: ./models/JsonLdBase.php
// Shared helpers for all JSON-LD generators. Pure array/string logic only.

class JsonLdBase {

    public static function getBaseUrl() {
        return siteBaseUrl();
    }

    public static function inLanguage($lang) {
        return $lang === 'ru' ? 'ru-RU' : 'uz-UZ';
    }

    public static function unifiedOrganizationDescriptionRu() {
        return 'Сервис скупки бытовой техники в Ташкенте: быстро оцениваем, честно предлагаем цену и выкупаем технику в удобное время. Принимаем разные категории техники, работаем аккуратно и прозрачно, отвечаем на вопросы и помогаем с вывозом при необходимости.';
    }

    public static function cleanText($text) {
        $text = (string)($text !== null ? $text : '');
        $text = str_replace(array("\r\n", "\r", "\n"), ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', trim($text));

        $parts = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || count($parts) < 2) return $text;

        $out = array();
        $seen = array();
        foreach ($parts as $p) {
            $key = function_exists('mb_strtolower') ? mb_strtolower(trim($p)) : strtolower(trim($p));
            if ($key === '') continue;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = trim($p);
        }

        return trim(implode(' ', $out));
    }

    public static function cleanDescription($text, $maxLen = null) {
        $text = self::cleanText($text);
        if ($text === '') return $text;
        $text = preg_replace('/\\+?\\d[\\d\\s\\-\\(\\)]{7,}\\d/u', '', $text);
        $text = preg_replace('/(?:звоните|позвоните|call us?|телефон)[\\s:!\\.—\\-]*(?:\\s|$)/ui', '', $text);
        $text = preg_replace('/\\s{2,}/', ' ', trim($text));
        if ($maxLen !== null && function_exists('mb_strlen') && mb_strlen($text) > $maxLen) {
            $text = trim(function_exists('mb_substr') ? mb_substr($text, 0, $maxLen - 3) : substr($text, 0, $maxLen - 3)) . '...';
        }
        return $text;
    }

    public static function sanitizeUrlValues($value, $baseUrl) {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::sanitizeUrlValues($v, $baseUrl);
            }
            return $value;
        }

        if (!is_string($value)) return $value;

        if (strpos($value, '/') === 0) {
            return absoluteUrl($value, $baseUrl);
        }

        $parsed = @parse_url($value);
        if (!is_array($parsed) || empty($parsed['scheme'])) return $value;

        $host = strtolower((string)(isset($parsed['host']) ? $parsed['host'] : ''));
        if (!in_array($host, array('localhost', '127.0.0.1', '::1'), true)) return $value;

        $path = isset($parsed['path']) ? $parsed['path'] : '';
        $query = isset($parsed['query']) ? ('?' . $parsed['query']) : '';
        $fragment = isset($parsed['fragment']) ? ('#' . $parsed['fragment']) : '';
        return rtrim($baseUrl, '/') . ($path ? $path : '') . $query . $fragment;
    }

    public static function toIso8601($mysqlTimestamp) {
        try {
            $dt = new DateTime((string)$mysqlTimestamp, new DateTimeZone(date_default_timezone_get() ? date_default_timezone_get() : 'UTC'));
            return $dt->format('c');
        } catch (Exception $e) {
            return null;
        }
    }

    public static function imageDims($url) {
        return getPublicImageDimensions($url);
    }

    public static function encode($schema) {
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function encodeGraph($graph) {
        return self::encode(array('@context' => 'https://schema.org', '@graph' => $graph));
    }
}
