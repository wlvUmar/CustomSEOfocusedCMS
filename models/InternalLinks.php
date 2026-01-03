<?php
// path: models/InternalLinks.php
// INSTRUCTION: Replace the entire InternalLinks.php file

class InternalLinks {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * SIMPLIFIED: Generate link suggestions based on simple keyword matching
     */
    public function generateSuggestions() {
        $pages = $this->db->fetchAll("SELECT * FROM pages WHERE is_published = 1");
        $suggestions = [];

        foreach ($pages as $fromPage) {
            foreach ($pages as $toPage) {
                // Don't link to self
                if ($fromPage['id'] === $toPage['id']) continue;

                // Simple relevance score
                $score = 0;
                
                // Check if target page title appears in source content
                $fromContentRu = mb_strtolower(strip_tags($fromPage['content_ru']));
                $fromContentUz = mb_strtolower(strip_tags($fromPage['content_uz']));
                $toTitleRu = mb_strtolower($toPage['title_ru']);
                $toTitleUz = mb_strtolower($toPage['title_uz']);
                
                // Title mention = high relevance
                if (mb_strpos($fromContentRu, $toTitleRu) !== false) $score += 10;
                if (mb_strpos($fromContentUz, $toTitleUz) !== false) $score += 10;
                
                // Keyword overlap
                if (!empty($toPage['meta_keywords_ru'])) {
                    $keywords = explode(',', mb_strtolower($toPage['meta_keywords_ru']));
                    foreach ($keywords as $kw) {
                        $kw = trim($kw);
                        if ($kw && mb_strpos($fromContentRu, $kw) !== false) {
                            $score += 2;
                        }
                    }
                }
                
                if (!empty($toPage['meta_keywords_uz'])) {
                    $keywords = explode(',', mb_strtolower($toPage['meta_keywords_uz']));
                    foreach ($keywords as $kw) {
                        $kw = trim($kw);
                        if ($kw && mb_strpos($fromContentUz, $kw) !== false) {
                            $score += 2;
                        }
                    }
                }
                
                // Only suggest if score > 0
                if ($score > 0) {
                    $suggestions[] = [
                        'from_page_id' => $fromPage['id'],
                        'from_slug' => $fromPage['slug'],
                        'from_title' => $fromPage['title_ru'],
                        'to_page_id' => $toPage['id'],
                        'to_slug' => $toPage['slug'],
                        'to_title' => $toPage['title_ru'],
                        'anchor_text_ru' => $toPage['title_ru'],
                        'anchor_text_uz' => $toPage['title_uz'],
                        'relevance_score' => $score
                    ];
                }
            }
        }

        // Sort by relevance
        usort($suggestions, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        return $suggestions;
    }

    /**
     * Auto-insert links into page content (SIMPLIFIED)
     */
    public function autoInsertLinks($pageId, $maxLinks = 3, $language = 'ru') {
        $page = $this->db->fetchOne("SELECT * FROM pages WHERE id = ?", [$pageId]);
        if (!$page) return 0;

        // Get suggestions for this page
        $allSuggestions = $this->generateSuggestions();
        $suggestions = array_filter($allSuggestions, function($s) use ($pageId) {
            return $s['from_page_id'] == $pageId;
        });
        
        // Take top N
        $suggestions = array_slice($suggestions, 0, $maxLinks);
        
        $content = $page["content_$language"];
        $insertedCount = 0;

        foreach ($suggestions as $suggestion) {
            $anchorText = $suggestion["anchor_text_$language"];
            $toSlug = $suggestion['to_slug'];
            
            // Build link HTML
            $linkHtml = '<a href="' . BASE_URL . '/' . htmlspecialchars($toSlug) . '">' . 
                        htmlspecialchars($anchorText) . '</a>';

            // Find first occurrence (case-insensitive, whole words only)
            $pattern = '/\b' . preg_quote($anchorText, '/') . '\b/ui';
            
            // Only replace if not already a link
            if (!preg_match('/<a[^>]*>' . preg_quote($anchorText, '/') . '<\/a>/ui', $content)) {
                $content = preg_replace($pattern, $linkHtml, $content, 1, $count);
                
                if ($count > 0) {
                    $insertedCount++;
                }
            }
        }

        // Update page content
        if ($insertedCount > 0) {
            $this->db->query(
                "UPDATE pages SET content_$language = ? WHERE id = ?",
                [$content, $pageId]
            );
        }

        return $insertedCount;
    }

    /**
     * Get existing internal links in content
     */
    public function getExistingLinks($pageId, $language = 'ru') {
        $page = $this->db->fetchOne("SELECT * FROM pages WHERE id = ?", [$pageId]);
        if (!$page) return [];

        $content = $page["content_$language"];
        $links = [];

        // Extract all internal links
        preg_match_all('/<a\s+href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/i', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $href = $match[1];
            $text = strip_tags($match[2]);
            
            // Check if it's an internal link
            if (strpos($href, BASE_URL) === 0 || (strpos($href, '/') === 0 && strpos($href, '//') !== 0)) {
                $links[] = [
                    'href' => $href,
                    'anchor_text' => $text,
                    'full_html' => $match[0]
                ];
            }
        }

        return $links;
    }

    /**
     * Remove all internal links from content
     */
    public function removeAllLinks($pageId, $language = 'ru') {
        $page = $this->db->fetchOne("SELECT * FROM pages WHERE id = ?", [$pageId]);
        if (!$page) return false;

        $content = $page["content_$language"];
        
        // Remove internal links but keep anchor text
        $content = preg_replace_callback(
            '/<a\s+href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/i',
            function($matches) {
                $href = $matches[1];
                $text = $matches[2];
                
                // Only remove internal links
                if (strpos($href, BASE_URL) === 0 || (strpos($href, '/') === 0 && strpos($href, '//') !== 0)) {
                    return $text;
                }
                
                return $matches[0]; // Keep external links
            },
            $content
        );

        $this->db->query(
            "UPDATE pages SET content_$language = ? WHERE id = ?",
            [$content, $pageId]
        );

        return true;
    }

    /**
     * Get link statistics for a page
     */
    public function getLinkStats($pageId) {
        $page = $this->db->fetchOne("SELECT * FROM pages WHERE id = ?", [$pageId]);
        if (!$page) return null;

        $linksRu = $this->getExistingLinks($pageId, 'ru');
        $linksUz = $this->getExistingLinks($pageId, 'uz');

        return [
            'total_links_ru' => count($linksRu),
            'total_links_uz' => count($linksUz),
            'links_ru' => $linksRu,
            'links_uz' => $linksUz
        ];
    }
    // Add to InternalLinks.php

    /**
     * BETA FEATURE: Check for broken internal links
     */
    public function checkLinkHealth() {
        $pages = $this->db->fetchAll("SELECT id, slug, content_ru, content_uz FROM pages WHERE is_published = 1");
        $brokenLinks = [];
        $validSlugs = array_column($pages, 'slug');
        
        foreach ($pages as $page) {
            // Check both languages
            foreach (['ru', 'uz'] as $lang) {
                $content = $page["content_$lang"];
                
                // Find all internal links
                preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $content, $matches, PREG_SET_ORDER);
                
                foreach ($matches as $match) {
                    $url = $match[1];
                    $linkText = strip_tags($match[2]);
                    
                    // Check if internal link
                    if (strpos($url, BASE_URL) === 0 || (strpos($url, '/') === 0 && strpos($url, '//') !== 0)) {
                        // Extract slug from URL
                        $path = parse_url($url, PHP_URL_PATH);
                        $slug = trim($path, '/');
                        $slug = preg_replace('/\/(ru|uz)$/', '', $slug); // Remove language suffix
                        
                        // Check if slug exists
                        if (!empty($slug) && !in_array($slug, $validSlugs)) {
                            $brokenLinks[] = [
                                'page_id' => $page['id'],
                                'page_slug' => $page['slug'],
                                'broken_url' => $url,
                                'link_text' => $linkText,
                                'language' => $lang,
                                'severity' => 'high' // Could add logic to determine severity
                            ];
                        }
                    }
                }
            }
        }
        
        return $brokenLinks;
    }

    /**
     * BETA FEATURE: Auto-fix broken links by removing them
     */
    public function fixBrokenLinks($pageId, $language = 'ru') {
        $broken = array_filter($this->checkLinkHealth(), function($link) use ($pageId, $language) {
            return $link['page_id'] == $pageId && $link['language'] == $language;
        });
        
        if (empty($broken)) return 0;
        
        $page = $this->db->fetchOne("SELECT * FROM pages WHERE id = ?", [$pageId]);
        $content = $page["content_$language"];
        $fixedCount = 0;
        
        foreach ($broken as $link) {
            // Remove link but keep text
            $pattern = '/<a[^>]+href=["\']' . preg_quote($link['broken_url'], '/') . '["\'][^>]*>(.*?)<\/a>/i';
            $content = preg_replace($pattern, '$1', $content, -1, $count);
            $fixedCount += $count;
        }
        
        if ($fixedCount > 0) {
            $this->db->query("UPDATE pages SET content_$language = ? WHERE id = ?", [$content, $pageId]);
        }
        
        return $fixedCount;
    }

    /**
     * BETA FEATURE: Get link health summary
     */
    public function getLinkHealthSummary() {
        $brokenLinks = $this->checkLinkHealth();
        
        $summary = [
            'total_broken' => count($brokenLinks),
            'pages_affected' => count(array_unique(array_column($brokenLinks, 'page_id'))),
            'by_severity' => [
                'high' => count(array_filter($brokenLinks, fn($l) => $l['severity'] === 'high')),
                'medium' => count(array_filter($brokenLinks, fn($l) => $l['severity'] === 'medium')),
                'low' => count(array_filter($brokenLinks, fn($l) => $l['severity'] === 'low'))
            ]
        ];
        
        return $summary;
    }

}