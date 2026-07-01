<?php
// path: ./core/helpers.php

function e($string) {
    $string = (string)($string ?? '');

    $decoded = html_entity_decode($string, ENT_QUOTES, 'UTF-8');

    $current_sub = ini_get('mbstring.substitute_character');
    ini_set('mbstring.substitute_character', 'none');
    
    $result = mb_convert_encoding($decoded, 'UTF-8', 'UTF-8');
    $result = str_replace("\xEF\xBF\xBD", '', $result); 
    
    ini_set('mbstring.substitute_character', $current_sub); 

    return ($result === '' && $string !== '') ? $string : $result;
}

function getCurrentLanguage() {
    return $_SESSION['language'] ?? DEFAULT_LANGUAGE;
}

function setLanguage($lang) {
    if (in_array($lang, SUPPORTED_LANGUAGES)){
        $_SESSION['language'] = $lang;
    }
}

function logDebug($message) {
    if (!IS_PRODUCTION) {
        error_log($message);
    }
}

function logInfo($message) {
    if (!IS_PRODUCTION) {
        error_log($message);
    }
}

// Ensure database schema includes utm_source columns (one-time setup)
function ensureUtmSourceSchema() {
    static $schemaChecked = false;
    if ($schemaChecked) return;
    $schemaChecked = true;

    try {
        $db = Database::getInstance();
        
        // Add utm_source column to analytics table if it doesn't exist
        $db->query("ALTER TABLE analytics ADD COLUMN utm_source VARCHAR(255) NULL AFTER phone_calls");
    } catch (Exception $e) {
        // Column likely already exists, silently ignore
    }

    try {
        $db = Database::getInstance();
        
        // Add utm_source column to analytics_hourly table if it doesn't exist
        $db->query("ALTER TABLE analytics_hourly ADD COLUMN utm_source VARCHAR(255) NULL AFTER phone_calls");
    } catch (Exception $e) {
        // Column likely already exists, silently ignore
    }

    try {
        $db = Database::getInstance();
        
        // Add utm_source column to analytics_monthly table if it doesn't exist
        $db->query("ALTER TABLE analytics_monthly ADD COLUMN utm_source VARCHAR(255) NULL AFTER total_phone_calls");
    } catch (Exception $e) {
        // Column likely already exists, silently ignore
    }
}

// Call once on app initialization
ensureUtmSourceSchema();

/**
 * Resolve the absolute canonical site base URL used for SEO/meta and JSON-LD.
 *
 * Priority:
 *  1) BASE_URL env/constant (should be absolute in production, e.g. https://kuplyu-tashkent.uz)
 *  2) Current request host/proto as a fallback
 */
function siteBaseUrl() {
    $baseUrl = defined('BASE_URL') ? (string)BASE_URL : '';
    $baseUrl = trim($baseUrl);

    // If already absolute, use it as-is.
    if ($baseUrl !== '' && strpos($baseUrl, '://') !== false) {
        return rtrim($baseUrl, '/');
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $host = trim((string)$host);

    if ($host === '') {
        // CLI context: return relative BASE_URL (path) if provided, otherwise empty.
        return rtrim($baseUrl, '/');
    }

    // If BASE_URL is a path prefix (e.g. /myapp), append it to the host.
    if ($baseUrl !== '' && strpos($baseUrl, '/') === 0) {
        return $protocol . '://' . rtrim($host, '/') . rtrim($baseUrl, '/');
    }

    return $protocol . '://' . rtrim($host, '/');
}

function siteUrl($path = '') {
    $base = siteBaseUrl();
    $path = (string)($path ?? '');
    if ($path === '' || $path === '/') return $base . '/';
    return $base . '/' . ltrim($path, '/');
}

function metaPixelId(): string {
    return defined('META_PIXEL_ID') ? trim((string) META_PIXEL_ID) : '';
}

function renderMetaPixelHead(): string {
    $pixelId = metaPixelId();
    if ($pixelId === '') {
        return '';
    }

    return <<<HTML
    <!-- Meta Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '$pixelId');
    fbq('track', 'PageView');
    </script>
    <!-- End Meta Pixel Code -->
HTML;
}

function renderMetaPixelNoscript(): string {
    $pixelId = metaPixelId();
    if ($pixelId === '') {
        return '';
    }

    $pixelId = e($pixelId);
    return <<<HTML
    <!-- Meta Pixel Code (noscript) -->
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id={$pixelId}&ev=PageView&noscript=1"
    /></noscript>
    <!-- End Meta Pixel Code -->
HTML;
}

/**
 * Convert a possibly-relative URL into an absolute URL on the canonical site host.
 * Also rewrites absolute localhost/127.0.0.1 URLs to the canonical host.
 */
function absoluteUrl($url, $baseUrl = null) {
    $url = trim((string)($url ?? ''));
    if ($url === '') return '';

    $baseUrl = $baseUrl ? rtrim((string)$baseUrl, '/') : siteBaseUrl();

    // Protocol-relative URL.
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }

    $parsed = @parse_url($url);
    if (is_array($parsed) && !empty($parsed['scheme'])) {
        $host = strtolower((string)($parsed['host'] ?? ''));
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $path = $parsed['path'] ?? '';
            $query = isset($parsed['query']) ? ('?' . $parsed['query']) : '';
            $fragment = isset($parsed['fragment']) ? ('#' . $parsed['fragment']) : '';
            return $baseUrl . ($path ?: '') . $query . $fragment;
        }
        return $url;
    }

    if (strpos($url, '/') === 0) {
        return $baseUrl . $url;
    }

    return $baseUrl . '/' . $url;
}

function canonicalUrlForPage($slug, $lang) {
    $slug = (string)($slug ?? '');
    $lang = (string)($lang ?? DEFAULT_LANGUAGE);

    $isHome = in_array($slug, ['home', 'main', ''], true);
    if ($isHome) {
        return $lang !== DEFAULT_LANGUAGE ? siteUrl($lang) : siteUrl('/');
    }

    return $lang !== DEFAULT_LANGUAGE ? siteUrl($slug . '/' . $lang) : siteUrl($slug);
}

function canonicalUrlForArticle($id, $lang) {
    $id = (string)$id;
    $lang = (string)($lang ?? DEFAULT_LANGUAGE);

    return $lang !== DEFAULT_LANGUAGE ? siteUrl('articles/' . $id . '/' . $lang) : siteUrl('articles/' . $id);
}


function isBot() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $bots = [
        'googlebot',
        'google-inspectiontool',
        'google-structured-data-testing-tool',
        'google-pagerenderer',
        'google page renderer',
        'googleother',
        'google-extended',
        'adsbot-google',
        'mediapartners-google',
        'apis-google',
        'bingbot',
        'slurp',           // Yahoo
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'sogou',
        'exabot',
        'facebot',
        'ia_archiver',
        'alexa',
        'msnbot',
        'teoma',
        'seekbot',
        'spider',
        'crawler',
        'bot',
        'archive',
        'scraper'
    ];
    
    $userAgentLower = strtolower($userAgent);
    
    foreach ($bots as $bot) {
        if (strpos($userAgentLower, $bot) !== false) {
            logDebug("[IS_BOT] Bot detected! Type: $bot, UA: $userAgent");
            return true;
        }
    }
    
    return false;
}

function showError(int $code = 500) {
    global $router;

    if (isset($router) && method_exists($router, 'error')) {
        $router->error($code);
    } else {
        http_response_code($code);
        echo "Error $code";
        exit;
    }
}
/**
 * Enhanced template engine with Jinja-like syntax
 * Supports: {{variable}}, {{object.property}}, {{array.0}}, loops, conditionals
 */
function renderTemplate($text, $data = []) {
    if (empty($text)) return $text;
    
    // Process loops: {% for item in items %}...{% endfor %}
    $text = preg_replace_callback(
        '/\{%\s*for\s+(\w+)\s+in\s+([\w\.]+)\s*%\}(.*?)\{%\s*endfor\s*%\}/s',
        function($matches) use ($data) {
            $itemName = $matches[1];
            $arrayPath = $matches[2];
            $template = $matches[3];
            
            $items = getNestedValue($data, $arrayPath);
            if (!is_array($items)) return '';
            
            $output = '';
            foreach ($items as $index => $item) {
                $loopData = array_merge($data, [
                    $itemName => $item,
                    'loop' => ['index' => $index, 'first' => $index === 0, 'last' => $index === count($items) - 1]
                ]);
                $output .= renderTemplate($template, $loopData);
            }
            return $output;
        },
        $text
    );
    
    $text = preg_replace_callback(
        '/\{%\s*if\s+([\w\.]+)\s*%\}(.*?)(?:\{%\s*else\s*%\}(.*?))?\{%\s*endif\s*%\}/s',
        function($matches) use ($data) {
            $condition = $matches[1];
            $ifContent = $matches[2];
            $elseContent = $matches[3] ?? '';
            
            $value = getNestedValue($data, $condition);
            return $value ? renderTemplate($ifContent, $data) : renderTemplate($elseContent, $data);
        },
        $text
    );
    
    // Process variables: {{variable}} or {{object.property}}
    $text = preg_replace_callback(
        '/\{\{\s*([\w\.]+)\s*\}\}/',
        function($matches) use ($data) {
            $key = $matches[1];
            $value = getNestedValue($data, $key);
            if ($value === null) {
                return '';
            }
            if (is_array($value)) {
                return htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
            }
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        },
        $text
    );
    
    return $text;
}

/**
 * Builds the HTML fragment for a set of media rows in a given section.
 * Returns '' if $items is empty.
 */
function renderPageMedia(array $items, string $section, string $lang): string {
    if (empty($items)) return '';

    $baseUrl = siteBaseUrl();

    if ($section === 'banner') {
        $m = $items[0];
        $src = absoluteUrl('/uploads/' . $m['filename'], $baseUrl);
        $alt = e($m['alt_text_' . $lang] ?? '');
        return '<div class="auto-banner-section"><img src="' . $src . '" alt="' . $alt . '" class="img-full" loading="lazy"></div>';
    }

    if ($section === 'gallery') {
        $label = $lang === 'ru' ? 'Галерея' : 'Galereya';
        $html = '<div class="auto-gallery-section"><div class="section-label">' . $label . '</div><div class="media-gallery">';
        foreach ($items as $m) {
            $src = absoluteUrl('/uploads/' . $m['filename'], $baseUrl);
            $alt = e($m['alt_text_' . $lang] ?? '');
            $cap = e($m['caption_' . $lang] ?? '');
            $html .= '<figure class="gallery-item"><img src="' . $src . '" alt="' . $alt . '" loading="lazy">'
                   . ($cap ? '<figcaption>' . $cap . '</figcaption>' : '') . '</figure>';
        }
        return $html . '</div></div>';
    }

    if ($section === 'content') {
        $html = '<div class="auto-content-media">';
        foreach ($items as $m) {
            $src = absoluteUrl('/uploads/' . $m['filename'], $baseUrl);
            $alt = e($m['alt_text_' . $lang] ?? '');
            $cap = e($m['caption_' . $lang] ?? '');
            $alignment = !empty($m['alignment']) ? $m['alignment'] : 'center';
            $alignClass = 'img-' . $alignment;
            $style = !empty($m['width']) ? ' style="max-width:' . (int)$m['width'] . 'px"' : '';
            $html .= '<figure><img src="' . $src . '" alt="' . $alt . '" class="' . $alignClass . '"' . $style . ' loading="lazy">'
                   . ($cap ? '<figcaption>' . $cap . '</figcaption>' : '') . '</figure><div class="clear"></div>';
        }
        return $html . '</div>';
    }

    return '';
}

/**
 * Converts an HTML fragment into a node imported into the target document.
 */
function htmlFragmentToNode(DOMDocument $targetDoc, string $htmlFragment): ?DOMNode {
    if ($htmlFragment === '') return null;

    $frag = new DOMDocument();
    libxml_use_internal_errors(true);
    $frag->loadHTML('<?xml encoding="UTF-8">' . $htmlFragment, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    return $frag->documentElement ? $targetDoc->importNode($frag->documentElement, true) : null;
}

/**
 * Parses the page HTML and inserts media at structural landmarks.
 */
function injectMediaByStructure(string $html, array $mediaBySection, string $lang): string {
    $hasBanner = !empty($mediaBySection['banner']);
    $hasContent = !empty($mediaBySection['content']);
    $hasGallery = !empty($mediaBySection['gallery']);
    $placedBanner = false;
    $placedContent = false;
    $placedGallery = false;

    if (!$hasBanner && !$hasContent && !$hasGallery) {
        return $html;
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    $hasClass = function ($class) {
        return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
    };

    $infoGridClass = $hasClass('info-grid');
    $processStepClass = $hasClass('process-step');
    $nodeHasClass = function ($node, string $class): bool {
        if (!$node || $node->nodeType !== XML_ELEMENT_NODE) return false;
        $classes = ' ' . trim((string)$node->getAttribute('class')) . ' ';
        return strpos($classes, ' ' . $class . ' ') !== false;
    };
    $resolveAnchor = function ($node) use ($nodeHasClass) {
        if (!$node || $node->nodeType !== XML_ELEMENT_NODE) {
            return null;
        }

        $current = $node;
        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            $tag = strtolower((string)$current->nodeName);
            if ($nodeHasClass($current, 'content-section') || $tag === 'section') {
                return $current;
            }

            $parent = $current->parentNode;
            if (!$parent || $parent->nodeType !== XML_ELEMENT_NODE) {
                break;
            }
            $current = $parent;
        }

        return $node;
    };

    $infoGrid = $xpath->query("//*[$infoGridClass]")->item(0);
    $processStep = $xpath->query("//*[$processStepClass]")->item(0);
    $infoGridAnchor = $resolveAnchor($infoGrid);
    $processAnchor = $resolveAnchor($processStep);

    if ($hasBanner && $infoGridAnchor) {
        $target = $infoGridAnchor;
        $node = htmlFragmentToNode($doc, renderPageMedia($mediaBySection['banner'], 'banner', $lang));
        if ($node && $target->parentNode) {
            if ($target->nextSibling) {
                $target->parentNode->insertBefore($node, $target->nextSibling);
            } else {
                $target->parentNode->appendChild($node);
            }
            $placedBanner = true;
        }
    }

    if ($hasContent && $processAnchor) {
        $processSection = $processAnchor;
        $prev = $processSection->previousSibling;
        while ($prev && $prev->nodeType !== XML_ELEMENT_NODE) {
            $prev = $prev->previousSibling;
        }

        $node = htmlFragmentToNode($doc, renderPageMedia($mediaBySection['content'], 'content', $lang));
        if ($node) {
            if ($prev) {
                $h2 = $xpath->query('.//h2', $prev)->item(0);
                $prev->insertBefore($node, $h2 ? $h2->nextSibling : $prev->firstChild);
                $placedContent = true;
            } elseif ($processSection->parentNode) {
                $processSection->parentNode->insertBefore($node, $processSection);
                $placedContent = true;
            }
        }
    }

    if ($hasGallery && $processAnchor) {
        $target = $processAnchor;
        $node = htmlFragmentToNode($doc, renderPageMedia($mediaBySection['gallery'], 'gallery', $lang));
        if ($node && $target->parentNode) {
            if ($target->nextSibling) {
                $target->parentNode->insertBefore($node, $target->nextSibling);
            } else {
                $target->parentNode->appendChild($node);
            }
            $placedGallery = true;
        }
    }

    $out = $doc->saveHTML();
    $out = preg_replace('/^<\?xml[^>]*>\s*/', '', $out);

    if ($hasBanner && !$placedBanner) {
        $out .= renderPageMedia($mediaBySection['banner'], 'banner', $lang);
    }
    if ($hasContent && !$placedContent) {
        $out .= renderPageMedia($mediaBySection['content'], 'content', $lang);
    }
    if ($hasGallery && !$placedGallery) {
        $out .= renderPageMedia($mediaBySection['gallery'], 'gallery', $lang);
    }

    return $out;
}

/**
 * Get nested value from array using dot notation
 */
function getNestedValue($array, $key, $default = null) {
    if (strpos($key, '.') === false) {
        return $array[$key] ?? $default;
    }
    
    $keys = explode('.', $key);
    $value = $array;
    
    foreach ($keys as $k) {
        if (is_array($value) && isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    
    return $value;
}

/**
 * Legacy function - kept for backwards compatibility
 */
function replacePlaceholders($text, $page, $seo) {
    $lang = getCurrentLanguage();
    
    // Build comprehensive data array
    $data = [
        'page' => [
            'title' => $page["title_$lang"] ?? '',
            'slug' => $page['slug'] ?? '',
            'content' => $page["content_$lang"] ?? '',
        ],
        'global' => [
            'phone' => $seo['phone'] ?? '',
            'email' => $seo['email'] ?? '',
            'address' => $seo["address_$lang"] ?? '',
            'working_hours' => $seo["working_hours_$lang"] ?? '',
            'site_name' => $seo["site_name_$lang"] ?? '',
        ],
        'seo' => $seo,
        'lang' => $lang,
        'date' => [
            'year' => date('Y'),
            'month' => date('n'),
            'day' => date('j'),
        ]
    ];
    
    return renderTemplate($text, $data);
}

function shouldSkipTracking(): bool
{
    if (!empty($_COOKIE['no_track']) && $_COOKIE['no_track'] === '1') {
        return true;
    }

    // Skip tracking for logged-in admins browsing the front-end
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
        return true;
    }

    $clientIp = getClientIp();
    $skipIps = [
        '144.124.192.237',
    ];

    if ($clientIp && in_array($clientIp, $skipIps, true)) {
        return true;
    }

    return false;
}

function normalizeTrackingLanguage($lang): string
{
    $lang = strtolower(trim((string)($lang ?? '')));
    if (!in_array($lang, SUPPORTED_LANGUAGES, true)) {
        return DEFAULT_LANGUAGE;
    }
    return $lang;
}

function isValidAnalyticsSlug($slug): bool
{
    $slug = (string)($slug ?? '');
    if ($slug === '' || strlen($slug) > 120) return false;

    // Accept page slugs like "televizor" and synthetic slugs like "article-123".
    return (bool)preg_match('/^[a-z0-9][a-z0-9\-_]*$/', $slug);
}

function isValidAnalyticsInternalLinkSlug($slug): bool
{
    // Slightly looser: allow article-* too (for future use) but still ASCII-safe.
    return isValidAnalyticsSlug($slug);
}

function trackingRateLimit(string $action, int $maxAttempts, int $windowSeconds): bool
{
    // Lightweight in-session limiter for public analytics endpoints.
    // Avoid using the login-oriented RateLimiter defaults (too strict for real users).
    if (session_status() !== PHP_SESSION_ACTIVE) return true;

    $maxAttempts = max(1, (int)$maxAttempts);
    $windowSeconds = max(1, (int)$windowSeconds);

    $key = 'trk_' . preg_replace('/[^a-z0-9_\-]/i', '_', $action);
    $now = time();

    $state = $_SESSION[$key] ?? ['count' => 0, 'ts' => $now];
    if (!is_array($state) || !isset($state['count'], $state['ts'])) {
        $state = ['count' => 0, 'ts' => $now];
    }

    if (($now - (int)$state['ts']) > $windowSeconds) {
        $state = ['count' => 0, 'ts' => $now];
    }

    $state['count'] = (int)$state['count'] + 1;
    $_SESSION[$key] = $state;

    return $state['count'] <= $maxAttempts;
}

function getTrackingCookieValue(string $name): array
{
    $raw = $_COOKIE[$name] ?? '';
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function setTrackingCookieValue(string $name, array $value, int $ttlSeconds = 2592000): void
{
    $expires = time() + max(60, $ttlSeconds);
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $options = [
        'expires' => $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    setcookie($name, $encoded, $options);
    $_COOKIE[$name] = $encoded;
}

function getOrCreateTrackingVisitorId(): string
{
    $cookieName = 'trk_visitor_id';
    $visitorId = trim((string)($_COOKIE[$cookieName] ?? ''));
    if ($visitorId !== '') {
        return $visitorId;
    }

    $visitorId = bin2hex(random_bytes(16));
    $options = [
        'expires' => time() + (365 * 24 * 60 * 60),
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie($cookieName, $visitorId, $options);
    $_COOKIE[$cookieName] = $visitorId;

    return $visitorId;
}

function shouldCountSiteVisit(string $language): bool
{
    $visitorId = getOrCreateTrackingVisitorId();
    $cookieName = 'trk_site_visit';
    $state = getTrackingCookieValue($cookieName);

    if (!isset($state['visitor_id'], $state['counted']) || $state['visitor_id'] !== $visitorId) {
        $state = ['visitor_id' => $visitorId, 'counted' => false];
    }

    if (!empty($state['counted'])) {
        return false;
    }

    $state['counted'] = true;
    setTrackingCookieValue($cookieName, $state, 365 * 24 * 60 * 60);

    return true;
}

function shouldCountVisitThisBrowser(string $slug, string $language): bool
{
    $slug = (string)$slug;
    $language = normalizeTrackingLanguage($language);
    $visitorId = getOrCreateTrackingVisitorId();
    $cookieName = 'trk_visit_pages';

    $state = getTrackingCookieValue($cookieName);
    if (!isset($state['visitor_id'], $state['visited']) || !is_array($state['visited']) || $state['visitor_id'] !== $visitorId) {
        $state = ['visitor_id' => $visitorId, 'visited' => []];
    }

    $visitKey = $slug . '|' . $language;
    if (!empty($state['visited'][$visitKey])) {
        return false;
    }

    $state['visited'][$visitKey] = time();
    if (count($state['visited']) > 100) {
        $state['visited'] = array_slice($state['visited'], -100, null, true);
    }
    setTrackingCookieValue($cookieName, $state, 365 * 24 * 60 * 60);

    return true;
}

function trackSiteVisit($language) {
    if (shouldSkipTracking()) return;
    if (isBot()) return;

    $language = normalizeTrackingLanguage($language);
    if (!shouldCountSiteVisit($language)) {
        return;
    }

    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        $hour = (int)date('G');

        $sql = "INSERT INTO analytics_hourly (page_slug, language, date, hour, visits, clicks, phone_calls)
                VALUES ('__site__', ?, ?, ?, 1, 0, 0)
                ON DUPLICATE KEY UPDATE visits = visits + 1";

        $db->query($sql, [$language, $date, $hour]);
    } catch (Exception $e) {
        error_log("Site visit tracking error: " . $e->getMessage());
    }
}

function shouldCountClickThisSession(string $slug, string $language, int $cooldownSeconds = 5): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) return true;

    $slug = (string)$slug;
    $language = normalizeTrackingLanguage($language);

    $key = 'trk_clicks';
    $state = $_SESSION[$key] ?? [];
    if (!is_array($state)) {
        $state = [];
    }

    $clickKey = $slug . '|' . $language;
    $now = time();

    if (isset($state[$clickKey])) {
        $lastClickTime = $state[$clickKey];
        if (($now - $lastClickTime) < $cooldownSeconds) {
            return false;
        }
    }

    $state[$clickKey] = $now;
    $_SESSION[$key] = $state;

    return true;
}

function shouldCountPhoneCallThisBrowser(string $slug, string $language): bool
{
    $slug = (string)$slug;
    $language = normalizeTrackingLanguage($language);
    $visitorId = getOrCreateTrackingVisitorId();
    $cookieName = 'trk_phone_calls';

    $state = getTrackingCookieValue($cookieName);
    if (!isset($state['visitor_id'], $state['visited']) || !is_array($state['visited']) || $state['visitor_id'] !== $visitorId) {
        $state = ['visitor_id' => $visitorId, 'visited' => []];
    }

    $visitKey = $slug . '|' . $language;
    if (!empty($state['visited'][$visitKey])) {
        return false;
    }

    $state['visited'][$visitKey] = time();
    if (count($state['visited']) > 100) {
        $state['visited'] = array_slice($state['visited'], -100, null, true);
    }
    setTrackingCookieValue($cookieName, $state, 365 * 24 * 60 * 60);

    return true;
}

function getClientIp(): ?string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
}

function bumpMonthlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $isNewDay, $utmSource = ''): void
{
    try {
        $db = Database::getInstance();
        $year = (int)date('Y');
        $month = (int)date('n');

        $deltaVisits = (int)$deltaVisits;
        $deltaClicks = (int)$deltaClicks;
        $deltaPhoneCalls = (int)$deltaPhoneCalls;
        $deltaDays = $isNewDay ? 1 : 0;
        $utmSource = trim((string)$utmSource) ?: null;

        $sql = "INSERT INTO analytics_monthly
                    (page_slug, language, year, month, total_visits, total_clicks, total_phone_calls, utm_source, unique_days)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_visits = total_visits + VALUES(total_visits),
                    total_clicks = total_clicks + VALUES(total_clicks),
                    total_phone_calls = total_phone_calls + VALUES(total_phone_calls),
                    unique_days = unique_days + VALUES(unique_days)";

        $db->query($sql, [$slug, $language, $year, $month, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource, $deltaDays]);
    } catch (Exception $e) {
        error_log("Monthly summary error: " . $e->getMessage());
    }
}

function bumpHourlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource = ''): void
{
    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        $hour = (int)date('G');

        $deltaVisits = (int)$deltaVisits;
        $deltaClicks = (int)$deltaClicks;
        $deltaPhoneCalls = (int)$deltaPhoneCalls;
        $utmSource = trim((string)$utmSource) ?: null;

        $sql = "INSERT INTO analytics_hourly
                    (page_slug, language, date, hour, visits, clicks, phone_calls, utm_source)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    visits = visits + VALUES(visits),
                    clicks = clicks + VALUES(clicks),
                    phone_calls = phone_calls + VALUES(phone_calls)";

        $db->query($sql, [$slug, $language, $date, $hour, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource]);
    } catch (Exception $e) {
        error_log("Hourly summary error: " . $e->getMessage());
    }
}

function trackVisit($slug, $language) {
    if (shouldSkipTracking()) return;

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    if (!IS_PRODUCTION && !empty($_GET['debug_ua']) && $_GET['debug_ua'] === '1') {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        error_log("[DEBUG_UA] method=$method uri=$uri slug=$slug lang=$language ua=$userAgent");
    }

    $language = normalizeTrackingLanguage($language);
    if (!isValidAnalyticsSlug($slug)) return;

    if (isBot()) {
        trackBotVisit($slug, $language);
        return;
    }

    if (!shouldCountVisitThisBrowser($slug, $language)) {
        return;
    }

    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        $utmSource = trim((string)($_GET['utm_source'] ?? ''));

        $sql = "INSERT INTO analytics (page_slug, language, visits, clicks, utm_source, date) 
                VALUES (?, ?, 1, 0, ?, ?) 
                ON DUPLICATE KEY UPDATE visits = visits + 1";

        $stmt = $db->query($sql, [$slug, $language, $utmSource ?: NULL, $date]);
        $isNewDay = ($stmt && $stmt->rowCount() === 1);
        bumpMonthlySummary($slug, $language, 1, 0, 0, $isNewDay, $utmSource);
        bumpHourlySummary($slug, $language, 1, 0, 0, $utmSource);
    } catch (Exception $e) {
        error_log("Analytics error: " . $e->getMessage());
    }
}

function trackBotVisit($slug, $language) {
    logDebug("[TRACK_BOT_VISIT] Called for slug: $slug, language: $language");
    
    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $botType = 'unknown';
        $userAgentLower = strtolower($userAgent);
        
        if (strpos($userAgentLower, 'googlebot') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'google-inspectiontool') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'google-structured-data-testing-tool') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'google-pagerenderer') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'google page renderer') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'googleother') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'google-extended') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'adsbot-google') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'mediapartners-google') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'apis-google') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'bingbot') !== false) $botType = 'bingbot';
        elseif (strpos($userAgentLower, 'yandexbot') !== false) $botType = 'yandexbot';
        elseif (strpos($userAgentLower, 'baiduspider') !== false) $botType = 'baiduspider';
        elseif (strpos($userAgentLower, 'slurp') !== false) $botType = 'yahoo';
        elseif (strpos($userAgentLower, 'duckduckbot') !== false) $botType = 'duckduckgo';
        else {
            // Expanded regex-based detection for "Other" bots
            if (preg_match('/([a-zA-Z0-9_\-]+bot|spider|crawler|scraper)/i', $userAgent, $matches)) {
                $botType = strtolower($matches[1]);
            } elseif (preg_match('/(facebook|twitter|slack|discord|whatsapp|telegram|pinterest|linkedin)externalhit/i', $userAgent, $matches)) {
                $botType = strtolower($matches[1]);
            } else {
                $botType = 'other';
            }
        }
        
        logDebug("[TRACK_BOT_VISIT] Bot type: $botType, Date: $date");
        
        $sql = "INSERT INTO analytics_bot_visits 
                (page_slug, language, bot_type, user_agent, visit_date, visits) 
                VALUES (?, ?, ?, ?, ?, 1) 
                ON DUPLICATE KEY UPDATE visits = visits + 1, last_visit = NOW()";
        
        $db->query($sql, [$slug, $language, $botType, substr($userAgent, 0, 255), $date]);
        
        logDebug("[TRACK_BOT_VISIT] Successfully inserted/updated bot visit record");
    } catch (Exception $e) {
        error_log("[TRACK_BOT_VISIT] ERROR: " . $e->getMessage());
    }
}

function trackClick($slug, $language, $utmSource = '') {
    if (shouldSkipTracking()) return;
    if (isBot()) return;

    if (!shouldCountClickThisSession($slug, $language)) {
        return;
    }

    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        $language = normalizeTrackingLanguage($language);
        if (!isValidAnalyticsSlug($slug)) return;

        $utmSource = trim((string)$utmSource) ?: null;

        $sql = "INSERT INTO analytics (page_slug, language, visits, clicks, phone_calls, utm_source, date)
                VALUES (?, ?, 0, 1, 0, ?, ?)
                ON DUPLICATE KEY UPDATE clicks = clicks + 1";

        $stmt = $db->query($sql, [$slug, $language, $utmSource, $date]);
        $isNewDay = ($stmt && $stmt->rowCount() === 1);
        bumpMonthlySummary($slug, $language, 0, 1, 0, $isNewDay, $utmSource);
        bumpHourlySummary($slug, $language, 0, 1, 0, $utmSource);
    } catch (Exception $e) {
        error_log("Click tracking error: " . $e->getMessage());
    }
}

function trackPhoneCall($slug, $language, $utmSource = '') {
    if (shouldSkipTracking()) return;
    if (isBot()) return;

    if (!shouldCountPhoneCallThisBrowser($slug, $language)) {
        return;
    }

    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        $language = normalizeTrackingLanguage($language);
        if (!isValidAnalyticsSlug($slug)) return;

        $utmSource = trim((string)$utmSource) ?: null;

        $sql = "INSERT INTO analytics (page_slug, language, visits, clicks, phone_calls, utm_source, date) 
                VALUES (?, ?, 0, 0, 1, ?, ?) 
                ON DUPLICATE KEY UPDATE phone_calls = phone_calls + 1";

        $stmt = $db->query($sql, [$slug, $language, $utmSource, $date]);
        $isNewDay = ($stmt && $stmt->rowCount() === 1);
        bumpMonthlySummary($slug, $language, 0, 0, 1, $isNewDay, $utmSource);
        bumpHourlySummary($slug, $language, 0, 0, 1, $utmSource);
    } catch (Exception $e) {
        error_log("Phone call tracking error: " . $e->getMessage());
    }
}



function trackInternalLink($fromSlug, $toSlug, $language) {
    if (shouldSkipTracking()) return;
    if (isBot()) return;

    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        $language = normalizeTrackingLanguage($language);
        if (!isValidAnalyticsInternalLinkSlug($fromSlug)) return;
        if (!isValidAnalyticsInternalLinkSlug($toSlug)) return;
        
        $sql = "INSERT INTO analytics_internal_links (from_slug, to_slug, language, clicks, date) 
                VALUES (?, ?, ?, 1, ?) 
                ON DUPLICATE KEY UPDATE clicks = clicks + 1";
        
        $stmt = $db->query($sql, [$fromSlug, $toSlug, $language, $date]);
        $isNewDay = ($stmt && $stmt->rowCount() === 1);
        bumpInternalLinkMonthlySummary($fromSlug, $toSlug, $language, 1, $isNewDay);
    } catch (Exception $e) {
        error_log("Internal link tracking error: " . $e->getMessage());
    }
}

function bumpInternalLinkMonthlySummary($fromSlug, $toSlug, $language, $deltaClicks, $isNewDay): void
{
    try {
        $db = Database::getInstance();
        $year = (int)date('Y');
        $month = (int)date('n');
        $deltaClicks = (int)$deltaClicks;
        $deltaDays = $isNewDay ? 1 : 0;

        $sql = "INSERT INTO analytics_internal_links_monthly
                    (from_slug, to_slug, language, year, month, total_clicks, unique_days)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_clicks = total_clicks + VALUES(total_clicks),
                    unique_days = unique_days + VALUES(unique_days)";

        $db->query($sql, [$fromSlug, $toSlug, $language, $year, $month, $deltaClicks, $deltaDays]);
    } catch (Exception $e) {
        error_log("Internal link monthly summary error: " . $e->getMessage());
    }
}

/**
 * Update monthly summary for internal link tracking
 */
function updateInternalLinkMonthlySummary($fromSlug, $toSlug, $language) {
    // Legacy recompute retained for admin repair scripts; prefer bumpInternalLinkMonthlySummary.
    try {
        $db = Database::getInstance();
        $year = date('Y');
        $month = date('n');

        $sql = "INSERT INTO analytics_internal_links_monthly 
                (from_slug, to_slug, language, year, month, total_clicks, unique_days)
                SELECT from_slug, to_slug, language, YEAR(date), MONTH(date), 
                       SUM(clicks), COUNT(DISTINCT date)
                FROM analytics_internal_links
                WHERE from_slug = ? AND to_slug = ? AND language = ? 
                  AND YEAR(date) = ? AND MONTH(date) = ?
                GROUP BY from_slug, to_slug, language, YEAR(date), MONTH(date)
                ON DUPLICATE KEY UPDATE 
                    total_clicks = VALUES(total_clicks),
                    unique_days = VALUES(unique_days)";

        $db->query($sql, [$fromSlug, $toSlug, $language, $year, $month]);
    } catch (Exception $e) {
        error_log("Internal link monthly summary error: " . $e->getMessage());
    }
}

function getCurrentMonthContent($pageId, $lang) {
    $db = Database::getInstance();
    $currentMonth = date('n');
    
    $sql = "SELECT content_ru, content_uz FROM content_rotations 
            WHERE page_id = ? AND active_month = ? AND is_active = 1 
            LIMIT 1";
    
    $rotation = $db->fetchOne($sql, [$pageId, $currentMonth]);
    
    return $rotation ? $rotation["content_$lang"] : null;
}

function generateFAQSchema($faqs, $lang, $pageUrl = '') {
    if (empty($faqs)) return '';
    
    $faqItems = [];
    foreach ($faqs as $faq) {
        $faqItems[] = [
            '@type' => 'Question',
            'name' => $faq["question_$lang"],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq["answer_$lang"]
            ]
        ];
    }
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faqItems
    ];
    
    if (!empty($pageUrl)) {
        $schema['@id'] = $pageUrl . '#faq';
    }
    
    return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/**
 * Enhance content for SEO: fix images alt text, ensure proper heading hierarchy
 */
function enhanceContentSEO($content, $pageTitle = '', $applianceName = '') {
    if (empty($content)) return $content;
    
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    
    // Load HTML with UTF-8 encoding (PHP 8.2+ compatible)
    $dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    // Enhance image alt text
    $images = $dom->getElementsByTagName('img');
    foreach ($images as $img) {
        $alt = $img->getAttribute('alt');
        if (empty($alt) && !empty($applianceName)) {
            $img->setAttribute('alt', ucfirst($applianceName) . ' - ' . $pageTitle);
        }
        
        // Add loading="lazy" for performance
        if (!$img->hasAttribute('loading')) {
            $img->setAttribute('loading', 'lazy');
        }
    }
    
    // Ensure heading hierarchy (no h1 in content as it's in template)
    $headings = $dom->getElementsByTagName('h1');
    foreach ($headings as $h1) {
        $h2 = $dom->createElement('h2');
        foreach ($h1->childNodes as $child) {
            $h2->appendChild($child->cloneNode(true));
        }
        foreach ($h1->attributes as $attr) {
            $h2->setAttribute($attr->nodeName, $attr->nodeValue);
        }
        $h1->parentNode->replaceChild($h2, $h1);
    }

    // Remove duplicate title when template already renders the page title
    $pageTitleRendered = !empty($GLOBALS['pageTitleRendered']);
    if ($pageTitleRendered && $pageTitle) {
        $normalize = function ($text) {
            $text = mb_strtolower(trim($text));
            return preg_replace('/\s+/', ' ', $text);
        };
        $normalizedTitle = $normalize($pageTitle);
        $h2s = $dom->getElementsByTagName('h2');
        foreach ($h2s as $h2) {
            $text = $normalize($h2->textContent ?? '');
            if ($text === $normalizedTitle) {
                $h2->parentNode->removeChild($h2);
                break;
            }
        }
    }
    
    // Enhance internal links with keyword-rich anchor text
    $links = $dom->getElementsByTagName('a');
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        $text = trim($link->textContent);
        
        // If link text is generic, enhance it
        if (in_array(strtolower($text), ['here', 'click', 'link', 'read more', 'узнать больше', 'здесь', 'подробнее'])) {
            // Try to extract context or use appliance name
            if (!empty($applianceName)) {
                $link->textContent = 'Продать ' . $applianceName;
            }
        }
    }
    
    $html = $dom->saveHTML();
    
    // Remove XML declaration added by DOMDocument
    $html = preg_replace('/^<!DOCTYPE.+?>/', '', $html);
    $html = str_replace(['<html>', '</html>', '<body>', '</body>'], '', $html);
    
    return trim($html);
}

/**
 * Process media placeholders in content
 * Supports: {{media:123}}, {{media:123:center:500}}, {{media-section:gallery}}
 */
function processMediaPlaceholders($content, $pageId) {
    if (empty($content)) return $content;
    
    $db = Database::getInstance();
    $lang = getCurrentLanguage();
    
    // Process single media placeholders: {{media:123}} or {{media:123:center:500}}
    $content = preg_replace_callback(
        '/\{\{media:(\d+)(?::(\w+))?(?::(\d+))?\}\}/',
        function($matches) use ($db, $pageId, $lang) {
            $mediaId = $matches[1];
            $alignment = $matches[2] ?? 'center';
            $width = $matches[3] ?? null;
            
            // Get media details
            $sql = "SELECT m.*, pm.alt_text_ru, pm.alt_text_uz, pm.caption_ru, pm.caption_uz, 
                           pm.width, pm.alignment, pm.css_class, pm.lazy_load
                    FROM media m
                    LEFT JOIN page_media pm ON m.id = pm.media_id AND pm.page_id = ?
                    WHERE m.id = ?
                    LIMIT 1";
            
            $media = $db->fetchOne($sql, [$pageId, $mediaId]);
            
            if (!$media) return '';
            
            // Use page_media settings or defaults
            $alt = $media["alt_text_$lang"] ?? $media['original_name'];
            $caption = $media["caption_$lang"] ?? '';
            $displayWidth = $width ?? $media['width'] ?? null;
            $displayAlign = $media['alignment'] ?? $alignment;
            $cssClass = $media['css_class'] ?? '';
            $lazyLoad = $media['lazy_load'] ?? 1;
            
            // Build image tag
            $imgTag = '<img src="/uploads/' . htmlspecialchars($media['filename']) . '" ';
            $imgTag .= 'alt="' . htmlspecialchars($alt) . '" ';
            
            if ($displayWidth) {
                $imgTag .= 'width="' . $displayWidth . '" ';
            }
            
            if ($lazyLoad) {
                $imgTag .= 'loading="lazy" ';
            }
            
            $classes = ['page-media', 'align-' . $displayAlign];
            if ($cssClass) $classes[] = $cssClass;
            $imgTag .= 'class="' . implode(' ', $classes) . '">';
            
            // Wrap with figure if caption exists
            if ($caption) {
                return '<figure class="media-figure align-' . $displayAlign . '">' . 
                       $imgTag . 
                       '<figcaption>' . htmlspecialchars($caption) . '</figcaption>' .
                       '</figure>';
            }
            
            return $imgTag;
        },
        $content
    );
    
    // Process section placeholders: {{media-section:gallery}}
    $content = preg_replace_callback(
        '/\{\{media-section:(\w+)\}\}/',
        function($matches) use ($db, $pageId, $lang) {
            $section = $matches[1];
            
            // Get all media for this section
            $sql = "SELECT m.*, pm.alt_text_ru, pm.alt_text_uz, pm.caption_ru, pm.caption_uz,
                           pm.width, pm.alignment, pm.css_class, pm.lazy_load, pm.position
                    FROM page_media pm
                    JOIN media m ON pm.media_id = m.id
                    WHERE pm.page_id = ? AND pm.section = ?
                    ORDER BY pm.position ASC, pm.id ASC";
            
            $mediaItems = $db->fetchAll($sql, [$pageId, $section]);
            
            if (empty($mediaItems)) return '';
            
            // Render based on section type
            switch ($section) {
                case 'hero':
                    return renderHeroSection($mediaItems, $lang);
                case 'gallery':
                    return renderGallerySection($mediaItems, $lang);
                case 'banner':
                    return renderBannerSection($mediaItems, $lang);
                default:
                    return renderContentSection($mediaItems, $lang);
            }
        },
        $content
    );
    
    return $content;
}

/**
 * Render hero section (single large banner)
 */
function renderHeroSection($mediaItems, $lang) {
    if (empty($mediaItems)) return '';
    
    $media = $mediaItems[0]; // Only use first image
    $alt = $media["alt_text_$lang"] ?? $media['original_name'];
    $caption = $media["caption_$lang"] ?? '';
    $heroTitle = $GLOBALS['currentPageTitle'] ?? '';
    $filename = $media['filename'] ?? '';
    $imagePath = '/uploads/' . htmlspecialchars($filename);
    $imageSources = $filename ? buildResponsiveImageSources($filename, getResponsiveImageWidths()) : null;
    $imageDims = $filename ? getImageDimensions($filename) : null;
    
    $html = '<div class="hero-media">';
    if ($imageSources) {
        $html .= '<picture>';
        if (!empty($imageSources['webp'])) {
            $html .= '<source type="image/webp" srcset="' . htmlspecialchars($imageSources['webp']) . '" sizes="(max-width: 900px) 100vw, 1100px">';
        }
    }
    $html .= '<img src="' . $imagePath . '" ';
    $html .= 'alt="' . htmlspecialchars($alt) . '" ';
    if ($imageDims) {
        $html .= 'width="' . (int)$imageDims['width'] . '" height="' . (int)$imageDims['height'] . '" ';
    }
    $html .= 'class="hero-image" loading="eager" decoding="async" fetchpriority="high"';
    if ($imageSources) {
        $html .= ' srcset="' . htmlspecialchars($imageSources['fallback']) . '" sizes="(max-width: 900px) 100vw, 1100px"';
    }
    $html .= '>';
    if ($imageSources) {
        $html .= '</picture>';
    }
    
    if ($heroTitle || $caption) {
        $html .= '<div class="hero-content">';
        if ($heroTitle) {
            $html .= '<h1 class="hero-title">' . htmlspecialchars($heroTitle) . '</h1>';
        }
        if ($caption) {
            $html .= '<div class="hero-caption">' . htmlspecialchars($caption) . '</div>';
        }
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

function getResponsiveImageWidths() {
    return [480, 768, 1024, 1366];
}

function getUploadsDir() {
    if (defined('UPLOAD_PATH') && UPLOAD_PATH) {
        return rtrim(UPLOAD_PATH, '/\\');
    }
    if (defined('PUBLIC_PATH') && PUBLIC_PATH) {
        return rtrim(PUBLIC_PATH, '/\\') . '/uploads';
    }
    return __DIR__ . '/../public/uploads';
}

function getImageDimensions($filename) {
    $path = getUploadsDir() . '/' . $filename;
    if (!file_exists($path)) return null;
    $info = @getimagesize($path);
    if (!$info) return null;
    return ['width' => $info[0], 'height' => $info[1]];
}

function getPublicImageDimensions($url) {
    $url = trim((string)($url ?? ''));
    if ($url === '') return null;

    $path = '';
    if (strpos($url, '/') === 0) {
        $path = $url;
    } else {
        $parsed = @parse_url($url);
        if (!is_array($parsed) || empty($parsed['path'])) return null;
        $base = siteBaseUrl();
        $baseHost = parse_url($base, PHP_URL_HOST);
        $urlHost = $parsed['host'] ?? '';
        if ($urlHost && $baseHost && strcasecmp($urlHost, $baseHost) !== 0) {
            return null;
        }
        $path = $parsed['path'];
    }

    if ($path === '' || $path[0] !== '/') return null;
    if (!defined('PUBLIC_PATH')) return null;

    $fullPath = rtrim(PUBLIC_PATH, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!file_exists($fullPath)) return null;
    $info = @getimagesize($fullPath);
    if (!$info) return null;
    return ['width' => $info[0], 'height' => $info[1]];
}

function buildResponsiveImageSources($filename, $widths) {
    $path = getUploadsDir() . '/' . $filename;
    if (!file_exists($path)) return null;
    $prebuilt = findExistingDerivedSources($filename, $widths);
    if ($prebuilt) {
        return $prebuilt;
    }
    if (!function_exists('getimagesize')) return null;
    $info = @getimagesize($path);
    if (!$info) return null;
    $originalWidth = (int)$info[0];
    $usableWidths = array_values(array_filter($widths, function($w) use ($originalWidth) {
        return $w > 0 && $w <= $originalWidth;
    }));
    if (empty($usableWidths)) return null;
    $fallbackSrcset = [];
    $webpSrcset = [];
    foreach ($usableWidths as $width) {
        $fallback = ensureDerivedImage($filename, $width, 'jpg');
        if ($fallback) {
            $fallbackSrcset[] = $fallback . ' ' . $width . 'w';
        }
        $webp = ensureDerivedImage($filename, $width, 'webp');
        if ($webp) {
            $webpSrcset[] = $webp . ' ' . $width . 'w';
        }
    }
    if (empty($fallbackSrcset) && empty($webpSrcset)) return null;
    return [
        'fallback' => implode(', ', $fallbackSrcset),
        'webp' => implode(', ', $webpSrcset)
    ];
}

function findExistingDerivedSources($filename, $widths) {
    $derivedDir = getUploadsDir() . '/derived';
    if (!is_dir($derivedDir)) return null;
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $fallbackSrcset = [];
    $webpSrcset = [];
    foreach ($widths as $width) {
        if ($width <= 0) continue;
        $fallbackPath = $derivedDir . '/' . $name . '_w' . $width . '.jpg';
        if (file_exists($fallbackPath)) {
            $fallbackSrcset[] = '/uploads/derived/' . $name . '_w' . $width . '.jpg ' . $width . 'w';
        }
        $webpPath = $derivedDir . '/' . $name . '_w' . $width . '.webp';
        if (file_exists($webpPath)) {
            $webpSrcset[] = '/uploads/derived/' . $name . '_w' . $width . '.webp ' . $width . 'w';
        }
    }
    if (empty($fallbackSrcset) && empty($webpSrcset)) {
        return null;
    }
    return [
        'fallback' => implode(', ', $fallbackSrcset),
        'webp' => implode(', ', $webpSrcset)
    ];
}

function ensureDerivedImage($filename, $targetWidth, $format) {
    $sourcePath = getUploadsDir() . '/' . $filename;
    if (!file_exists($sourcePath)) return null;
    if (!function_exists('getimagesize')) return null;
    if ($format === 'webp' && !function_exists('imagewebp')) return null;

    $info = @getimagesize($sourcePath);
    if (!$info) return null;
    $sourceWidth = (int)$info[0];
    $sourceHeight = (int)$info[1];
    if ($targetWidth <= 0 || $targetWidth >= $sourceWidth) return null;

    $derivedDir = getUploadsDir() . '/derived';
    if (!is_dir($derivedDir)) {
        @mkdir($derivedDir, 0755, true);
    }

    $name = pathinfo($filename, PATHINFO_FILENAME);
    $targetName = $name . '_w' . $targetWidth . '.' . $format;
    $targetPath = $derivedDir . '/' . $targetName;

    if (file_exists($targetPath) && filemtime($targetPath) >= filemtime($sourcePath)) {
        return '/uploads/derived/' . $targetName;
    }

    $type = $info[2];
    $sourceImage = null;
    if ($type === IMAGETYPE_JPEG) {
        $sourceImage = @imagecreatefromjpeg($sourcePath);
    } elseif ($type === IMAGETYPE_PNG) {
        $sourceImage = @imagecreatefrompng($sourcePath);
    } elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        $sourceImage = @imagecreatefromwebp($sourcePath);
    }
    if (!$sourceImage) return null;

    $targetHeight = (int)round($sourceHeight * ($targetWidth / $sourceWidth));
    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
    }

    imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

    $saved = false;
    if ($format === 'webp') {
        $saved = imagewebp($targetImage, $targetPath, 80);
    } else {
        $saved = imagejpeg($targetImage, $targetPath, 82);
    }

    imagedestroy($sourceImage);
    imagedestroy($targetImage);

    if (!$saved) return null;
    return '/uploads/derived/' . $targetName;
}

function generateResponsiveImageVariants($filename, $widths) {
    if (empty($filename)) return;
    if (empty($widths)) {
        $widths = getResponsiveImageWidths();
    }
    foreach ($widths as $width) {
        ensureDerivedImage($filename, (int)$width, 'jpg');
        ensureDerivedImage($filename, (int)$width, 'webp');
    }
}

function regenerateImageVariants($widths = null) {
    if (empty($widths)) {
        $widths = getResponsiveImageWidths();
    }

    $uploadsDir = getUploadsDir();
    if (!is_dir($uploadsDir)) {
        return [
            'success' => false,
            'message' => 'Uploads directory not found.'
        ];
    }

    $canProcess = function_exists('getimagesize') &&
        (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng') || function_exists('imagecreatefromwebp'));
    if (!$canProcess) {
        return [
            'success' => false,
            'message' => 'Image processing not available (PHP GD missing).'
        ];
    }

    $processed = 0;
    $skipped = 0;
    $errors = 0;
    $webpEnabled = function_exists('imagewebp');

    @set_time_limit(0);

    $iterator = new DirectoryIterator($uploadsDir);
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDot()) continue;
        if ($fileInfo->isDir()) {
            if ($fileInfo->getFilename() === 'derived') continue;
            continue;
        }

        $filename = $fileInfo->getFilename();
        if ($filename === '.htaccess') {
            $skipped++;
            continue;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $skipped++;
            continue;
        }

        try {
            generateResponsiveImageVariants($filename, $widths);
            $processed++;
        } catch (Throwable $e) {
            $errors++;
            error_log('Variant generation failed for ' . $filename . ': ' . $e->getMessage());
        }
    }

    return [
        'success' => true,
        'processed' => $processed,
        'skipped' => $skipped,
        'errors' => $errors,
        'webp' => $webpEnabled
    ];
}

/**
 * Render gallery section (grid of images)
 */
function renderGallerySection($mediaItems, $lang) {
    if (empty($mediaItems)) return '';
    
    $html = '<div class="media-gallery">';
    
    foreach ($mediaItems as $media) {
        $alt = $media["alt_text_$lang"] ?? $media['original_name'];
        $caption = $media["caption_$lang"] ?? '';
        
        $html .= '<figure class="gallery-item">';
        $html .= '<img src="/uploads/' . htmlspecialchars($media['filename']) . '" ';
        $html .= 'alt="' . htmlspecialchars($alt) . '" loading="lazy">';
        
        if ($caption) {
            $html .= '<figcaption>' . htmlspecialchars($caption) . '</figcaption>';
        }
        
        $html .= '</figure>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Render banner section
 */
function renderBannerSection($mediaItems, $lang) {
    if (empty($mediaItems)) return '';
    
    $media = $mediaItems[0];
    $alt = $media["alt_text_$lang"] ?? $media['original_name'];
    
    $html = '<div class="media-banner">';
    $html .= '<img src="/uploads/' . htmlspecialchars($media['filename']) . '" ';
    $html .= 'alt="' . htmlspecialchars($alt) . '" class="banner-image" loading="lazy">';
    $html .= '</div>';
    
    return $html;
}

/**
 * Render content section (inline media)
 */
function renderContentSection($mediaItems, $lang) {
    if (empty($mediaItems)) return '';
    
    $html = '';
    foreach ($mediaItems as $media) {
        $alt = $media["alt_text_$lang"] ?? $media['original_name'];
        $caption = $media["caption_$lang"] ?? '';
        $alignment = $media['alignment'] ?? 'center';
        
        if ($caption) {
            $html .= '<figure class="media-figure align-' . $alignment . '">';
        }
        
        $html .= '<img src="/uploads/' . htmlspecialchars($media['filename']) . '" ';
        $html .= 'alt="' . htmlspecialchars($alt) . '" ';
        $html .= 'class="page-media align-' . $alignment . '" loading="lazy">';
        
        if ($caption) {
            $html .= '<figcaption>' . htmlspecialchars($caption) . '</figcaption>';
            $html .= '</figure>';
        }
    }
    
    return $html;
}
