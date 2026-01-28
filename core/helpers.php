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

    $clientIp = getClientIp();
    $skipIps = [
        '213.230.80.213',
    ];

    if ($clientIp && in_array($clientIp, $skipIps, true)) {
        return true;
    }

    return false;
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


function trackVisit($slug, $language) {
    if (shouldSkipTracking()) return;

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    if (!empty($_GET['debug_ua']) && $_GET['debug_ua'] === '1') {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        error_log("[DEBUG_UA] method=$method uri=$uri slug=$slug lang=$language ua=$userAgent");
    }

    if (isBot()) {
        trackBotVisit($slug, $language);
        return;
    }

    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');

        $sql = "INSERT INTO analytics (page_slug, language, visits, clicks, date) 
                VALUES (?, ?, 1, 0, ?) 
                ON DUPLICATE KEY UPDATE visits = visits + 1";

        $db->query($sql, [$slug, $language, $date]);
        updateMonthlySummary($slug, $language);
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

function trackClick($slug, $language) {
    if (shouldSkipTracking()) return;

    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');

        $sql = "UPDATE analytics SET clicks = clicks + 1 
                WHERE page_slug = ? AND language = ? AND date = ?";

        $db->query($sql, [$slug, $language, $date]);
        updateMonthlySummary($slug, $language);
    } catch (Exception $e) {
        error_log("Click tracking error: " . $e->getMessage());
    }
}

function trackPhoneCall($slug, $language) {
    if (shouldSkipTracking()) return;

    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');

        // Note: Phone calls are counted as BOTH a click and a phone_call
        $sql = "INSERT INTO analytics (page_slug, language, visits, clicks, phone_calls, date) 
                VALUES (?, ?, 0, 1, 1, ?) 
                ON DUPLICATE KEY UPDATE clicks = clicks + 1, phone_calls = phone_calls + 1";

        $db->query($sql, [$slug, $language, $date]);
        updateMonthlySummary($slug, $language);
    } catch (Exception $e) {
        error_log("Phone call tracking error: " . $e->getMessage());
    }
}



function trackInternalLink($fromSlug, $toSlug, $language) {
    if (shouldSkipTracking()) return;

    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        
        $sql = "INSERT INTO analytics_internal_links (from_slug, to_slug, language, clicks, date) 
                VALUES (?, ?, ?, 1, ?) 
                ON DUPLICATE KEY UPDATE clicks = clicks + 1";
        
        $db->query($sql, [$fromSlug, $toSlug, $language, $date]);
        
        updateInternalLinkMonthlySummary($fromSlug, $toSlug, $language);
    } catch (Exception $e) {
        error_log("Internal link tracking error: " . $e->getMessage());
    }
}

function updateMonthlySummary($slug, $language) {
    try {
        $db = Database::getInstance();
        $year = date('Y');
        $month = date('n');
        
        $sql = "INSERT INTO analytics_monthly (page_slug, language, year, month, total_visits, total_clicks, total_phone_calls, unique_days)
                SELECT page_slug, language, YEAR(date), MONTH(date), 
                       SUM(visits), SUM(clicks), SUM(phone_calls), COUNT(DISTINCT date)
                FROM analytics
                WHERE page_slug = ? AND language = ? AND YEAR(date) = ? AND MONTH(date) = ?
                GROUP BY page_slug, language, YEAR(date), MONTH(date)
                ON DUPLICATE KEY UPDATE 
                    total_visits = VALUES(total_visits),
                    total_clicks = VALUES(total_clicks),
                    total_phone_calls = VALUES(total_phone_calls),
                    unique_days = VALUES(unique_days)";
        
        $db->query($sql, [$slug, $language, $year, $month]);
    } catch (Exception $e) {
        error_log("Monthly summary error: " . $e->getMessage());
    }
}

/**
 * Update monthly summary for internal link tracking
 */
function updateInternalLinkMonthlySummary($fromSlug, $toSlug, $language) {
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
    
    $html = '<div class="hero-media">';
    $html .= '<img src="/uploads/' . htmlspecialchars($media['filename']) . '" ';
    $html .= 'alt="' . htmlspecialchars($alt) . '" ';
    $html .= 'class="hero-image" loading="eager">';
    
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
