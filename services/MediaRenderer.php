<?php
// path: ./services/MediaRenderer.php
// Media HTML rendering extracted from core/helpers.php (god-module split).
// Owns HTML for media sections. No DB here - callers pass rows in.

class MediaRenderer {
    /**
     * Builds the HTML fragment for a set of media rows in a given section.
     * Returns '' if $items is empty.
     */
    public static function renderPageMedia(array $items, string $section, string $lang): string {
        if (empty($items)) return '';

        $baseUrl = siteBaseUrl();

        // Helper: emit width/height to avoid CLS (project-09 #4). Uses media width/height if present.
        $dims = function($m): string {
            $w = isset($m['width']) ? (int)$m['width'] : 0;
            $h = isset($m['height']) ? (int)$m['height'] : 0;
            // Common uploads are ~800x600 banner, gallery thumbs; only emit if both known
            if ($w > 0 && $h > 0) return ' width="' . $w . '" height="' . $h . '"';
            if ($w > 0) return ' width="' . $w . '" height="auto"';
            return '';
        };

        if ($section === 'banner') {
            $m = $items[0];
            $src = absoluteUrl('/uploads/' . $m['filename'], $baseUrl);
            $alt = e($m['alt_text_' . $lang] ?? '');
            return '<div class="auto-banner-section"><img src="' . $src . '" alt="' . $alt . '" class="img-full"' . $dims($m) . ' loading="lazy" decoding="async"></div>';
        }

        if ($section === 'gallery') {
            $label = $lang === 'ru' ? 'Галерея' : 'Galereya';
            $html = '<div class="auto-gallery-section"><div class="section-label">' . $label . '</div><div class="media-gallery">';
            foreach ($items as $m) {
                $src = absoluteUrl('/uploads/' . $m['filename'], $baseUrl);
                $alt = e($m['alt_text_' . $lang] ?? '');
                $cap = e($m['caption_' . $lang] ?? '');
                $html .= '<figure class="gallery-item"><img src="' . $src . '" alt="' . $alt . '"' . $dims($m) . ' loading="lazy" decoding="async">'
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
                $html .= '<figure><img src="' . $src . '" alt="' . $alt . '" class="' . $alignClass . '"' . $style . $dims($m) . ' loading="lazy" decoding="async">'
                       . ($cap ? '<figcaption>' . $cap . '</figcaption>' : '') . '</figure><div class="clear"></div>';
            }
            return $html . '</div>';
        }

        return '';
    }

    /**
     * Render slot-based media: {{media.slot_key}}
     * Invisible when empty (caller returns ''), single -> <img>, multiple -> c-gallery-grid
     * Side-scroll: use slot suffix _{scroll,carousel,slider,hscroll} e.g. {{media.photos_scroll}}
     *             1 placeholder = attach N images → renders as horizontal snap carousel
     */
    public static function renderMediaSlot($mediaItems, $lang, $slot = '') {
        if (empty($mediaItems)) return '';
        $isScroll = (bool)preg_match('/_(scroll|carousel|slider|hscroll)$/i', (string)$slot);
        if (count($mediaItems) === 1) {
            $m = $mediaItems[0];
            $alt = $m["alt_text_$lang"] ?? $m['original_name'] ?? '';
            $caption = $m["caption_$lang"] ?? '';
            $alignment = $m['alignment'] ?? 'center';
            $cssClass = $m['css_class'] ?? '';
            $lazy = $m['lazy_load'] ?? 1;
            $filename = $m['filename'] ?? '';
            $dims = $filename ? getImageDimensions($filename) : null;
            $sources = $filename ? buildResponsiveImageSources($filename, getResponsiveImageWidths()) : null;
            $classes = ['page-media', 'align-' . $alignment, 'slot-media'];
            if ($cssClass) $classes[] = $cssClass;
            if ($isScroll) $classes[] = 'slot-media--scroll';
            $classAttr = implode(' ', $classes);
            $img = '<img src="/uploads/' . htmlspecialchars($filename) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($classAttr) . '"';
            if ($dims) $img .= ' width="' . (int)$dims['width'] . '" height="' . (int)$dims['height'] . '"';
            if ($sources && !empty($sources['fallback'])) $img .= ' srcset="' . htmlspecialchars($sources['fallback']) . '" sizes="(max-width: 768px) 100vw, 720px"';
            $img .= ($lazy ? ' loading="lazy"' : ' loading="eager"') . ' decoding="async">';
            if ($isScroll) {
                // Single item but scroll-intent: wrap in scroll track so adding 2nd image doesn't shift layout
                $imgWrapped = $img;
                if ($caption) $imgWrapped = '<figure class="media-figure slot-figure" style="flex:0 0 auto;min-width:min(320px,85vw);margin:0">' . $img . '<figcaption>' . htmlspecialchars($caption) . '</figcaption></figure>';
                return '<div class="slot-gallery slot-gallery--scroll" data-slot="' . htmlspecialchars($slot) . '">' . $imgWrapped . '</div>';
            }
            if ($caption) {
                return '<figure class="media-figure align-' . htmlspecialchars($alignment) . ' slot-figure">' . $img . '<figcaption>' . htmlspecialchars($caption) . '</figcaption></figure>';
            }
            return $img;
        }
        // Gallery: 2+ images
        if ($isScroll) {
            // Side-scrollable carousel: horizontal snap, not grid
            $html = '<div class="slot-gallery slot-gallery--scroll" data-slot="' . htmlspecialchars($slot) . '">';
            foreach ($mediaItems as $m) {
                $alt = $m["alt_text_$lang"] ?? $m['original_name'] ?? '';
                $caption = $m["caption_$lang"] ?? '';
                $filename = $m['filename'] ?? '';
                $dims = $filename ? getImageDimensions($filename) : null;
                $dimAttr = $dims ? ' width="' . (int)$dims['width'] . '" height="' . (int)$dims['height'] . '"' : '';
                $img = '<img src="/uploads/' . htmlspecialchars($filename) . '" alt="' . htmlspecialchars($alt) . '"' . $dimAttr . ' loading="lazy" decoding="async">';
                if ($caption) $img = '<figure style="flex:0 0 auto;min-width:min(320px,85vw);margin:0;display:grid;gap:6px">' . $img . '<figcaption style="font-size:.85em;color:var(--muted);text-align:center">' . htmlspecialchars($caption) . '</figcaption></figure>';
                $html .= $img;
            }
            return $html . '</div>';
        }
        // Default grid
        $html = '<div class="c-gallery-grid slot-gallery">';
        foreach ($mediaItems as $m) {
            $alt = $m["alt_text_$lang"] ?? $m['original_name'] ?? '';
            $filename = $m['filename'] ?? '';
            $dims = $filename ? getImageDimensions($filename) : null;
            $dimAttr = $dims ? ' width="' . (int)$dims['width'] . '" height="' . (int)$dims['height'] . '"' : '';
            $html .= '<img src="/uploads/' . htmlspecialchars($filename) . '" alt="' . htmlspecialchars($alt) . '"' . $dimAttr . ' loading="lazy" decoding="async">';
        }
        return $html . '</div>';
    }

    /**
     * Render hero section (single large banner)
     */
    public static function renderHeroSection($mediaItems, $lang) {
        if (empty($mediaItems)) return '';

        $media = $mediaItems[0]; // Only use first image
        $alt = $media["alt_text_$lang"] ?? $media['original_name'];
        $filename = $media['filename'] ?? '';
        $imagePath = '/uploads/' . htmlspecialchars($filename);
        $imageSources = $filename ? buildResponsiveImageSources($filename, getResponsiveImageWidths()) : null;
        $imageDims = $filename ? getImageDimensions($filename) : null;

        $html = '<div class="hero-media">';
        if ($imageSources) {
            $html .= '<picture>';
            if (!empty($imageSources['webp'])) {
                // FIX: hero is full-bleed 100vw (public/css/pages.css:318 min-height clamp), not 1100px max-w
                // previous "1100px" forced desktop to fetch too-small, mobile to fetch 768w for 513px display (18.4 KiB waste)
                $html .= '<source type="image/webp" srcset="' . htmlspecialchars($imageSources['webp']) . '" sizes="100vw">';
            }
        }
        $html .= '<img src="' . $imagePath . '" ';
        $html .= 'alt="' . htmlspecialchars($alt) . '" ';
        if ($imageDims) {
            $html .= 'width="' . (int)$imageDims['width'] . '" height="' . (int)$imageDims['height'] . '" ';
        }
        $html .= 'class="hero-image" loading="eager" decoding="async" fetchpriority="high"';
        if ($imageSources) {
            $html .= ' srcset="' . htmlspecialchars($imageSources['fallback']) . '" sizes="100vw"';
        }
        $html .= '>';
        if ($imageSources) {
            $html .= '</picture>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render gallery section (grid of images)
     */
    public static function renderGallerySection($mediaItems, $lang) {
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
    public static function renderBannerSection($mediaItems, $lang) {
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
    public static function renderContentSection($mediaItems, $lang) {
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
}
