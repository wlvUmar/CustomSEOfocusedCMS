<?php
// path: ./services/SeoEnhancer.php
// SEO content helpers extracted from core/helpers.php (god-module split).
// Pure string/DOM logic only - no DB access here. Media rows come from
// MediaPlaceholderStore (model owns SQL), rendering via MediaRenderer.

class SeoEnhancer {
    /**
     * Legacy function - kept for backwards compatibility
     */
    public static function replacePlaceholders($text, $page, $seo) {
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

    public static function generateFAQSchema($faqs, $lang, $pageUrl = '') {
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
    public static function enhanceContentSEO($content, $pageTitle = '', $applianceName = '') {
        if (empty($content)) return $content;

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        // Load HTML with UTF-8 encoding (PHP 8.2+ compatible)
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Enhance image alt text + CLS: add width/height where missing
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
            // Add decoding async if missing
            if (!$img->hasAttribute('decoding')) $img->setAttribute('decoding', 'async');
            // CLS: inject width/height from file if absent
            if (!$img->hasAttribute('width') || !$img->hasAttribute('height')) {
                $src = $img->getAttribute('src') ?? '';
                $dims = getPublicImageDimensions($src);
                if ($dims) {
                    if (!$img->hasAttribute('width')) $img->setAttribute('width', (string)$dims['width']);
                    if (!$img->hasAttribute('height')) $img->setAttribute('height', (string)$dims['height']);
                }
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
    public static function processMediaPlaceholders($content, $pageId) {
        if (empty($content)) return $content;

        $store = new MediaPlaceholderStore();
        $lang = getCurrentLanguage();

        // Process single media placeholders: {{media:123}} or {{media:123:center:500}}
        $content = preg_replace_callback(
            '/\{\{media:(\d+)(?::(\w+))?(?::(\d+))?\}\}/',
            function($matches) use ($store, $pageId, $lang) {
                $mediaId = $matches[1];
                $alignment = $matches[2] ?? 'center';
                $width = $matches[3] ?? null;

                // Get media details
                $media = $store->fetchSingle($pageId, $mediaId);

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
            function($matches) use ($store, $pageId, $lang) {
                $section = $matches[1];

                // Get all media for this section
                $mediaItems = $store->fetchSection($pageId, $section);

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

        // An EMPTY slot that sits alone in a <p>/<div> (the editor wraps it like that) would leave an empty element
        // behind (extra margin, a blank gap). Drop the wrapper too; filled slots are left for the replacement below.
        $content = preg_replace_callback(
            '/<(p|div)(?:\s[^>]*)?>(?:\s|&nbsp;)*\{\{media\.([a-zA-Z0-9_-]+)\}\}(?:\s|&nbsp;)*<\/\1>/i',
            function($matches) use ($store, $pageId) {
                $slot = $matches[2];
                if ($slot === 'hero' || $slot === 'banner' || empty($store->fetchSection($pageId, $slot))) return '';
                return $matches[0];
            },
            $content
        );

        // Process slot placeholders: {{media.slot_key}} — flexible in-content visuals
        // Uses page_media.section as slot_key. Reserved hero/banner stay untouched (no placeholder needed).
        // Empty slot -> '' (zero DOM, invisible, no layout shift). 1 image -> single <img>, N images -> c-gallery-grid.
        $content = preg_replace_callback(
            '/\{\{media\.([a-zA-Z0-9_-]+)\}\}/',
            function($matches) use ($store, $pageId, $lang) {
                $slot = $matches[1];
                // hero/banner are managed via {{media-section:hero}} / auto hero bg — keep them out of this namespace
                if ($slot === 'hero' || $slot === 'banner') return '';

                $items = $store->fetchSection($pageId, $slot);
                if (empty($items)) return '';
                return renderMediaSlot($items, $lang, $slot);
            },
            $content
        );

        // Safety net: never show a raw media token to visitors (spaces inside the braces, a non-numeric id,
        // a typo…): anything that still looks like {{media…}} after the passes above is removed.
        $content = preg_replace('/\{\{\s*media(?:[.:\-][^{}]*)?\s*\}\}/i', '', $content);

        return $content;
    }
}
