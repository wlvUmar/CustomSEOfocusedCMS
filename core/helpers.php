<?php
// path: ./core/helpers.php

function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function getCurrentLanguage() {
    return $_SESSION['language'] ?? DEFAULT_LANGUAGE;
}

function setLanguage($lang) {
    if (in_array($lang, SUPPORTED_LANGUAGES)) {
        $_SESSION['language'] = $lang;
    }
}

/**
 * Check if the current request is from a search engine bot/crawler
 */
function isBot() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Common bot identifiers
    $bots = [
        'googlebot',
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
            return true;
        }
    }
    
    return false;
}

function showError(int $code = 500) {
    // Check if $router exists in this scope
    global $router;

    if (isset($router) && method_exists($router, 'error')) {
        $router->error($code);
    } else {
        // fallback if router is unavailable
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
    
    // Process conditionals: {% if condition %}...{% endif %}
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

function trackVisit($slug, $language) {
    // Skip tracking bot visits in regular analytics
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
        
        // Update monthly summary
        updateMonthlySummary($slug, $language);
    } catch (Exception $e) {
        error_log("Analytics error: " . $e->getMessage());
    }
}

/**
 * Track bot/crawler visits separately for crawl analysis
 */
function trackBotVisit($slug, $language) {
    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Determine bot type
        $botType = 'unknown';
        $userAgentLower = strtolower($userAgent);
        
        if (strpos($userAgentLower, 'googlebot') !== false) $botType = 'googlebot';
        elseif (strpos($userAgentLower, 'bingbot') !== false) $botType = 'bingbot';
        elseif (strpos($userAgentLower, 'yandexbot') !== false) $botType = 'yandexbot';
        elseif (strpos($userAgentLower, 'baiduspider') !== false) $botType = 'baiduspider';
        elseif (strpos($userAgentLower, 'slurp') !== false) $botType = 'yahoo';
        elseif (strpos($userAgentLower, 'duckduckbot') !== false) $botType = 'duckduckgo';
        else $botType = 'other';
        
        $sql = "INSERT INTO analytics_bot_visits 
                (page_slug, language, bot_type, user_agent, visit_date, visits) 
                VALUES (?, ?, ?, ?, ?, 1) 
                ON DUPLICATE KEY UPDATE visits = visits + 1, last_visit = NOW()";
        
        $db->query($sql, [$slug, $language, $botType, substr($userAgent, 0, 255), $date]);
    } catch (Exception $e) {
        error_log("Bot tracking error: " . $e->getMessage());
    }
}

function trackClick($slug, $language) {
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

/**
 * Track internal link navigation
 * Records when users click from one page to another
 */
function trackInternalLink($fromSlug, $toSlug, $language) {
    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        
        $sql = "INSERT INTO analytics_internal_links (from_slug, to_slug, language, clicks, date) 
                VALUES (?, ?, ?, 1, ?) 
                ON DUPLICATE KEY UPDATE clicks = clicks + 1";
        
        $db->query($sql, [$fromSlug, $toSlug, $language, $date]);
        
        // Update monthly summary
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
        
        $sql = "INSERT INTO analytics_monthly (page_slug, language, year, month, total_visits, total_clicks, unique_days)
                SELECT page_slug, language, YEAR(date), MONTH(date), 
                       SUM(visits), SUM(clicks), COUNT(DISTINCT date)
                FROM analytics
                WHERE page_slug = ? AND language = ? AND YEAR(date) = ? AND MONTH(date) = ?
                GROUP BY page_slug, language, YEAR(date), MONTH(date)
                ON DUPLICATE KEY UPDATE 
                    total_visits = VALUES(total_visits),
                    total_clicks = VALUES(total_clicks),
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