<?php
// path: ./core/helpers.php

require_once __DIR__ . '/Url.php';
require_once __DIR__ . '/../models/analytics/RotationContent.php';
require_once __DIR__ . '/../models/analytics/MediaPlaceholderStore.php';
require_once __DIR__ . '/../services/TrackingService.php';
require_once __DIR__ . '/../services/ImageVariants.php';
require_once __DIR__ . '/../services/MediaRenderer.php';
require_once __DIR__ . '/../services/SeoEnhancer.php';

function e($string) {
    $string = (string)($string ?? '');

    $decoded = html_entity_decode($string, ENT_QUOTES, 'UTF-8');

    $current_sub = ini_get('mbstring.substitute_character');
    ini_set('mbstring.substitute_character', 'none');
    
    $result = mb_convert_encoding($decoded, 'UTF-8', 'UTF-8');
    $result = str_replace("\xEF\xBF\xBD", '', $result); 
    
    ini_set('mbstring.substitute_character', $current_sub); 
    $result = ($result === '' && $string !== '') ? $string : $result;
    // Escape for HTML output — previously this helper stripped invalid bytes but did not escape.
    return htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

function logTrackingAudit(string $event, array $context = []): void {
    // Disabled via TRACKING_AUDIT_ENABLED (config/init.php); flip it to true
    // to re-enable audit logging. Kept intact for later debugging.
    if (!defined('TRACKING_AUDIT_ENABLED') || !TRACKING_AUDIT_ENABLED) {
        return;
    }

    $ts = date('Y-m-d H:i:s');
    $ip = getClientIp() ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '-';
    $referer = $_SERVER['HTTP_REFERER'] ?? '-';
    $cookie = $_SERVER['HTTP_COOKIE'] ?? '-';
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '-';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '-';
    $secFetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '-';
    $secFetchMode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '-';
    $secFetchDest = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '-';
    $cfIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '-';
    $xForwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '-';

    $line = json_encode([
        'ts' => $ts,
        'event' => $event,
        'ip' => $ip,
        'cf_ip' => $cfIp,
        'x_forwarded' => $xForwarded,
        'method' => $method,
        'uri' => $uri,
        'ua' => $ua,
        'referer' => $referer,
        'cookie' => $cookie,
        'accept_lang' => $acceptLang,
        'accept' => $accept,
        'sec_fetch_site' => $secFetchSite,
        'sec_fetch_mode' => $secFetchMode,
        'sec_fetch_dest' => $secFetchDest,
        'context' => $context,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    error_log($line . "\n", 3, TRACKING_AUDIT_LOG);
}

// ─────────────────────────────────────────────────────────────
// NOTE: Schema migrations are handled by migrate.php CLI.
// Do NOT add ALTER TABLE / DDL in this file — see migrations/
// directory instead.
// ─────────────────────────────────────────────────────────────

/**
 * Resolve the absolute canonical site base URL used for SEO/meta and JSON-LD.
 *
 * Priority:
 *  1) BASE_URL env/constant (should be absolute in production, e.g. https://kuplyu-tashkent.uz)
 *  2) Current request host/proto as a fallback
 */
function siteBaseUrl() {
    return Url::siteBaseUrl();
}

function siteUrl($path = '') {
    return Url::siteUrl($path);
}

/**
 * Convert a possibly-relative URL into an absolute URL on the canonical site host.
 * Also rewrites absolute localhost/127.0.0.1 URLs to the canonical host.
 */
function absoluteUrl($url, $baseUrl = null) {
    return Url::absoluteUrl($url, $baseUrl);
}

function canonicalUrlForPage($slug, $lang) {
    return Url::canonicalUrlForPage($slug, $lang);
}

function canonicalUrlForArticle($id, $lang) {
    return Url::canonicalUrlForArticle($id, $lang);
}


function isBot() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Empty User-Agent is almost certainly a bot or script
    if ($userAgent === '' || trim($userAgent) === '') {
        return true;
    }

    $userAgentLower = strtolower($userAgent);

    $bots = [
        // Search engines
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
        'slurp',
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'yandeximages',
        'yandexmetrika',
        'sogou',
        'exabot',
        'facebot',
        'ia_archiver',
        'alexa',
        'msnbot',
        'teoma',
        'seekbot',
        // Social media / messenger preview bots
        'facebookexternalhit',
        'whatsapp',
        'skypeuripreview',
        'viber',
        'pinterest',
        'discord',
        'slack',
        'telegram',
        // AI / LLM scrapers
        'claude',
        'anthropic-ai',
        'cohere-ai',
        'perplexitybot',
        'diffbot',
        'gptbot',
        'chatgpt-user',
        'bytespider',
        'omili',
        // SEO crawler tools
        'semrush',
        'majestic',
        'ahrefsbot',
        'dotbot',
        'blexbot',
        'seznambot',
        'serpstatbot',
        'seokicks',
        'trendkite-akashic',
        // Monitoring & uptime tools
        'pingdom',
        'statuscake',
        'newrelic',
        'uptimerobot',
        'site24x7',
        'freshping',
        'uptime',
        'datadogagent',
        // Dev tools & HTTP libraries
        'postman',
        'insomnia',
        'okhttp',
        'axios',
        'guzzle',
        'aiohttp',
        'httpx',
        'requests',
        'urllib',
        'httplib',
        'dart',
        'node-fetch',
        'php-curl-class',
        // Browser automation
        'headless',
        'phantomjs',
        'selenium',
        'puppeteer',
        'playwright',
        'cypress',
        'htmlunit',
        // Cloud / infrastructure scanners
        'netcraft',
        'censys',
        'shodan',
        'zoomeye',
        'fofa',
        'onyphe',
        'binaryedge',
        'threatscan',
        // Performance & validation tools
        'pagespeed',
        'lighthouse',
        'webpagetest',
        'google-web-light',
        'validator',
        'w3c_unicorn',
        'nu-html-checker',
        // Common bot-like platforms
        'amazonbot',
        'applebot',
        'apple-pubsub',
        'petalbot',
        'iaskspider',
        'jobboerse',
        'proximic',
        'blekkobot',
        'grapeshot',
        'netseer',
        'linkfluence',
        'iframely',
        'paperlibot',
        'nuzzel',
        'flipboard',
        'screaming frog',
        'deepcrawl',
        'sitebulb',
        'netsystemsresearch',
        'zoominfo',
        'discoveryengine',
        'ccbot',
        'evc-batch',
        'tweetedtimes',
        'tinEye',
        // Crawler / spider patterns
        'spider',
        'crawler',
        'scraper',
        'archive',
        'bot',
        // Common CLI / scripting tools
        'curl',
        'wget',
        'python',
        'go-http-client',
        'java/',
        'httpclient',
        'httpie',
        'scrapy',
        'masscan',
        'zgrab',
        'nmap',
        'libwww',
        'perl',
        'ruby',
        'lua',
        'lynx',
    ];

    foreach ($bots as $bot) {
        if (strpos($userAgentLower, $bot) !== false) {
            logDebug("[IS_BOT] Bot detected! Type: $bot, UA: $userAgent");
            return true;
        }
    }

    return false;
}

function getReferrerSource(): string {
    return TrackingService::getReferrerSource();
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
            if ($value === null || is_array($value)) {
                return '';
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
/**
 * Extract <style> blocks from HTML, sanitize CSS, return [cleanHtml, css].
 * Allows per-page overrides: authors can put <style> in content_ru/uz or
 * in custom_css column. The CSS is moved to <head> so it can target
 * header/footer even though content_ lives inside .content-body.
 */
function sanitizeCssBlock(string $css): string {
    if ($css === '') return '';
    // Strip dangerous vectors
    // Block @import to external origins — allow none for per-page (prevents SSRF)
    $css = preg_replace('/@import\s+[^;]+;?/i', '', $css);
    // Remove dangerous functions / protocols
    if (preg_match('/expression\s*\(|javascript\s*:|vbscript\s*:|-moz-binding|behaviour\s*:|behavior\s*:/i', $css)) {
        $css = preg_replace('/expression\s*\([^)]*\)/i', '', $css);
        $css = preg_replace('/javascript\s*:/i', '', $css);
        $css = preg_replace('/vbscript\s*:/i', '', $css);
        $css = preg_replace('/-moz-binding[^;}]*/i', '', $css);
    }
    // Neutralize data:text/html vector but keep data:image
    $css = preg_replace('/url\s*\(\s*["\']?\s*data\s*:\s*text\/html[^)]*\)/i', 'url("")', $css);
    // Block url(javascript:
    $css = preg_replace('/url\s*\(\s*["\']?\s*javascript:[^)]*\)/i', 'url("")', $css);
    return trim($css);
}

function extractAndSanitizePageStyles(string $html): array {
    if ($html === '' || stripos($html, '<style') === false) {
        return [$html, ''];
    }
    $cssParts = [];
    // Extract all <style> blocks (including with attributes)
    $html = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function($m) use (&$cssParts) {
        $block = sanitizeCssBlock((string)($m[1] ?? ''));
        if ($block !== '') $cssParts[] = $block;
        return '';
    }, $html);
    $merged = implode("\n\n", array_filter($cssParts));
    return [$html, $merged];
}

function getPageSlugClass(?string $slug): string {
    return Url::getPageSlugClass($slug);
}

function sanitizeFrontendHtml(string $html): string {
    if ($html === '') return '';
    // Layer 1: regex strip of dangerous protocols/tags before DOM parse (fast path)
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
    $html = preg_replace('/<object\b[^>]*>.*?<\/object>/is', '', $html);
    $html = preg_replace('/<embed\b[^>]*>/i', '', $html);
    // Layer 2: DOM purify — remove on* attrs, javascript:/data: hrefs, style expression
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);
    $onAttrs = $xpath->query('//@*[starts-with(name(),"on")]');
    if ($onAttrs !== false) foreach ($onAttrs as $attr) {
        if ($attr instanceof DOMAttr && $attr->ownerElement) $attr->ownerElement->removeAttribute($attr->nodeName);
    }
    $hrefNodes = $xpath->query('//*[@href or @src or @action or @xlink:href]');
    if ($hrefNodes !== false) foreach ($hrefNodes as $el) {
        if (!($el instanceof DOMElement)) continue;
        foreach (['href','src','action','xlink:href'] as $a) {
            if (!$el->hasAttribute($a)) continue;
            $v = trim($el->getAttribute($a));
            if (preg_match('/^\s*(javascript|data:text\/html|vbscript):/i', $v)) {
                $el->removeAttribute($a);
            }
        }
        if ($el->hasAttribute('style')) {
            $s = $el->getAttribute('style');
            if (preg_match('/expression|@import|javascript:/i', $s)) $el->removeAttribute('style');
        }
    }
    foreach (['script','iframe','object','embed','link','meta','base'] as $tag) {
        foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $n) $n->parentNode->removeChild($n);
    }
    $inner = '';
    $wrapper = $doc->getElementsByTagName('div')->item(0);
    if ($wrapper) foreach ($wrapper->childNodes as $c) $inner .= $doc->saveHTML($c);
    else $inner = $doc->saveHTML();
    return preg_replace('/^<\?xml[^>]*>\s*/', '', $inner) ?? $inner;
}

function renderPageMedia(array $items, string $section, string $lang): string {
    return MediaRenderer::renderPageMedia($items, $section, $lang);
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
    $nextElementSibling = function ($node) {
        if (!$node) return null;
        $next = $node->nextSibling;
        while ($next && $next->nodeType !== XML_ELEMENT_NODE) {
            $next = $next->nextSibling;
        }
        return $next;
    };
    $insertAfter = function ($target, $node): bool {
        if (!$target || !$node || !$target->parentNode) return false;
        if ($target->nextSibling) {
            $target->parentNode->insertBefore($node, $target->nextSibling);
        } else {
            $target->parentNode->appendChild($node);
        }
        return true;
    };
    $insertIntoSectionStart = function ($section, $node) use ($xpath): bool {
        if (!$section || !$node || $section->nodeType !== XML_ELEMENT_NODE) return false;
        $h2 = $xpath->query('.//h2', $section)->item(0);
        if ($h2) {
            $section->insertBefore($node, $h2->nextSibling);
        } else {
            $section->insertBefore($node, $section->firstChild);
        }
        return true;
    };

    $infoGrid = $xpath->query("//*[$infoGridClass]")->item(0);
    $processStep = $xpath->query("//*[$processStepClass]")->item(0);
    $infoGridAnchor = $resolveAnchor($infoGrid);
    $processAnchor = $resolveAnchor($processStep);

    if ($hasBanner && $infoGridAnchor) {
        $target = $infoGridAnchor;
        $node = htmlFragmentToNode($doc, renderPageMedia($mediaBySection['banner'], 'banner', $lang));
        if ($node && $insertAfter($target, $node)) {
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
                $insertIntoSectionStart($prev, $node);
                $placedContent = true;
            } elseif ($processSection->parentNode) {
                $processSection->parentNode->insertBefore($node, $processSection);
                $placedContent = true;
            }
        }
    }
    if ($hasContent && !$placedContent && $infoGridAnchor) {
        $node = htmlFragmentToNode($doc, renderPageMedia($mediaBySection['content'], 'content', $lang));
        if ($node) {
            $nextSection = $nextElementSibling($infoGridAnchor);
            if ($nextSection) {
                $insertIntoSectionStart($nextSection, $node);
                $placedContent = true;
            } elseif ($insertAfter($infoGridAnchor, $node)) {
                $placedContent = true;
            }
        }
    }

    if ($hasGallery && $processAnchor) {
        $target = $processAnchor;
        $node = htmlFragmentToNode($doc, renderPageMedia($mediaBySection['gallery'], 'gallery', $lang));
        if ($node && $insertAfter($target, $node)) {
            $placedGallery = true;
        }
    }
    if ($hasGallery && !$placedGallery && $infoGridAnchor) {
        $node = htmlFragmentToNode($doc, renderPageMedia($mediaBySection['gallery'], 'gallery', $lang));
        if ($node) {
            $nextSection = $nextElementSibling($infoGridAnchor);
            $target = $nextSection ?: $infoGridAnchor;
            if ($insertAfter($target, $node)) {
                $placedGallery = true;
            }
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
    return SeoEnhancer::replacePlaceholders($text, $page, $seo);
}

function shouldSkipTracking(): bool
{
    return TrackingService::shouldSkipTracking();
}

function normalizeTrackingLanguage($lang): string
{
    return TrackingService::normalizeTrackingLanguage($lang);
}

function isValidAnalyticsSlug($slug): bool
{
    return TrackingService::isValidAnalyticsSlug($slug);
}

function isValidAnalyticsInternalLinkSlug($slug): bool
{
    return TrackingService::isValidAnalyticsInternalLinkSlug($slug);
}

function trackingRateLimit(string $action, int $maxAttempts, int $windowSeconds): bool
{
    return TrackingService::trackingRateLimit($action, $maxAttempts, $windowSeconds);
}

function getOrCreateTrackingVisitorId(): string
{
    return TrackingService::getOrCreateTrackingVisitorId();
}

function setTrackingCookie(string $name, string $value, int $ttlSeconds): void
{
    TrackingService::setTrackingCookie($name, $value, $ttlSeconds);
}

function trackSiteVisit($language) {
    TrackingService::trackSiteVisit($language);
}

function shouldCountClickThisSession(string $slug, string $language, int $cooldownSeconds = 5): bool
{
    return TrackingService::shouldCountClickThisSession($slug, $language, $cooldownSeconds);
}

function getClientIp(): ?string
{
    return TrackingService::getClientIp();
}

function bumpMonthlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $isNewDay, $utmSource = ''): void
{
    TrackingService::bumpMonthlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $isNewDay, $utmSource);
}

function bumpHourlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource = ''): void
{
    TrackingService::bumpHourlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource);
}

function trackVisit($slug, $language) {
    TrackingService::trackVisit($slug, $language);
}

function trackBotVisit($slug, $language) {
    TrackingService::trackBotVisit($slug, $language);
}

function trackClick($slug, $language, $utmSource = '') {
    TrackingService::trackClick($slug, $language, $utmSource);
}

function trackPhoneCall($slug, $language, $utmSource = '') {
    TrackingService::trackPhoneCall($slug, $language, $utmSource);
}



function trackInternalLink($fromSlug, $toSlug, $language) {
    TrackingService::trackInternalLink($fromSlug, $toSlug, $language);
}

function bumpInternalLinkMonthlySummary($fromSlug, $toSlug, $language, $deltaClicks, $isNewDay): void
{
    TrackingService::bumpInternalLinkMonthlySummary($fromSlug, $toSlug, $language, $deltaClicks, $isNewDay);
}

/**
 * Update monthly summary for internal link tracking
 * @deprecated Legacy recompute retained for admin repair scripts; prefer Analytics::bumpInternalLinkMonthlySummary.
 */
function updateInternalLinkMonthlySummary($fromSlug, $toSlug, $language) {
    TrackingService::updateInternalLinkMonthlySummary($fromSlug, $toSlug, $language);
}

function getCurrentMonthContent($pageId, $lang) {
    return (new RotationContent())->getCurrentMonthContent($pageId, $lang);
}

function generateFAQSchema($faqs, $lang, $pageUrl = '') {
    return SeoEnhancer::generateFAQSchema($faqs, $lang, $pageUrl);
}

/**
 * Enhance content for SEO: fix images alt text, ensure proper heading hierarchy
 */
function enhanceContentSEO($content, $pageTitle = '', $applianceName = '') {
    return SeoEnhancer::enhanceContentSEO($content, $pageTitle, $applianceName);
}

/**
 * Process media placeholders in content
 * Supports: {{media:123}}, {{media:123:center:500}}, {{media-section:gallery}}
 */
function processMediaPlaceholders($content, $pageId) {
    return SeoEnhancer::processMediaPlaceholders($content, $pageId);
}

/**
 * Render slot-based media: {{media.slot_key}}
 * Invisible when empty (caller returns ''), single -> <img>, multiple -> c-gallery-grid
 * Side-scroll: use slot suffix _{scroll,carousel,slider,hscroll} e.g. {{media.photos_scroll}}
 *             1 placeholder = attach N images → renders as horizontal snap carousel
 */
function renderMediaSlot($mediaItems, $lang, $slot = '') {
    return MediaRenderer::renderMediaSlot($mediaItems, $lang, $slot);
}

/**
 * Render hero section (single large banner)
 */
function renderHeroSection($mediaItems, $lang) {
    return MediaRenderer::renderHeroSection($mediaItems, $lang);
}

function getResponsiveImageWidths() {
    return ImageVariants::getResponsiveImageWidths();
}

function getUploadsDir() {
    return ImageVariants::getUploadsDir();
}

function getImageDimensions($filename) {
    return ImageVariants::getImageDimensions($filename);
}

function getPublicImageDimensions($url) {
    return ImageVariants::getPublicImageDimensions($url);
}

function buildResponsiveImageSources($filename, $widths) {
    return ImageVariants::buildResponsiveImageSources($filename, $widths);
}

function findExistingDerivedSources($filename, $widths) {
    return ImageVariants::findExistingDerivedSources($filename, $widths);
}

function ensureDerivedImage($filename, $targetWidth, $format) {
    return ImageVariants::ensureDerivedImage($filename, $targetWidth, $format);
}

function generateResponsiveImageVariants($filename, $widths) {
    ImageVariants::generateResponsiveImageVariants($filename, $widths);
}

function regenerateImageVariants($widths = null) {
    return ImageVariants::regenerateImageVariants($widths);
}

/**
 * Render gallery section (grid of images)
 */
function renderGallerySection($mediaItems, $lang) {
    return MediaRenderer::renderGallerySection($mediaItems, $lang);
}

/**
 * Render banner section
 */
function renderBannerSection($mediaItems, $lang) {
    return MediaRenderer::renderBannerSection($mediaItems, $lang);
}

/**
 * Render content section (inline media)
 */
function renderContentSection($mediaItems, $lang) {
    return MediaRenderer::renderContentSection($mediaItems, $lang);
}

function getBotServiceStatus(): array
{
    $cacheFile = __DIR__ . '/cache/bot_health_cache.json';

    if (!is_file($cacheFile)) {
        return ['healthy' => false, 'checked_at' => 0, 'from_cache' => false];
    }

    $cached = json_decode(file_get_contents($cacheFile), true);
    if (!is_array($cached)) {
        return ['healthy' => false, 'checked_at' => 0, 'from_cache' => false];
    }

    return [
        'healthy'    => (bool) $cached['healthy'],
        'checked_at' => (int) $cached['checked_at'],
        'from_cache' => true,
    ];
}


/**
 * Periodic cleanup of old dedup table rows.
 * Called probabilistically (~1% of trackVisit calls).
 * Prevents unbounded growth of analytics_dedup_* tables.
 */
function cleanupDedupTables(): void
{
    TrackingService::cleanupDedupTables();
}

function getGaClientId(): ?string
{
    return TrackingService::getGaClientId();
}