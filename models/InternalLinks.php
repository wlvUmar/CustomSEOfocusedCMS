<?php
// NEW FILE: models/InternalLinks.php

class InternalLinks {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Analyze all pages and suggest internal links
     */
    public function generateSuggestions() {
        $pages = $this->db->fetchAll("SELECT * FROM pages WHERE is_published = 1");
        $suggestions = [];

        foreach ($pages as $fromPage) {
            foreach ($pages as $toPage) {
                // Don't link to self
                if ($fromPage['id'] === $toPage['id']) continue;

                // Check if toPage keywords/title appear in fromPage content
                $score = $this->calculateRelevanceScore($fromPage, $toPage);
                
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
                        'relevance_score' => $score,
                        'suggested_position' => 'auto'
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
     * Calculate how relevant a link would be
     */
    private function calculateRelevanceScore($fromPage, $toPage) {
        $score = 0;
        
        // Check title mentions
        $toTitleRu = mb_strtolower($toPage['title_ru']);
        $toTitleUz = mb_strtolower($toPage['title_uz']);
        $fromContentRu = mb_strtolower(strip_tags($fromPage['content_ru']));
        $fromContentUz = mb_strtolower(strip_tags($fromPage['content_uz']));
        
        if (mb_strpos($fromContentRu, $toTitleRu) !== false) $score += 10;
        if (mb_strpos($fromContentUz, $toTitleUz) !== false) $score += 10;
        
        // Keyword overlap
        $toKeywordsRu = explode(',', mb_strtolower($toPage['meta_keywords_ru'] ?? ''));
        $toKeywordsUz = explode(',', mb_strtolower($toPage['meta_keywords_uz'] ?? ''));
        
        foreach ($toKeywordsRu as $keyword) {
            $keyword = trim($keyword);
            if ($keyword && mb_strpos($fromContentRu, $keyword) !== false) {
                $score += 2;
            }
        }
        
        foreach ($toKeywordsUz as $keyword) {
            $keyword = trim($keyword);
            if ($keyword && mb_strpos($fromContentUz, $keyword) !== false) {
                $score += 2;
            }
        }
        
        // Boost for related slugs
        if (strpos($fromPage['slug'], $toPage['slug']) !== false || 
            strpos($toPage['slug'], $fromPage['slug']) !== false) {
            $score += 5;
        }
        
        return $score;
    }

    /**
     * Auto-insert links into page content
     */
    public function autoInsertLinks($pageId, $maxLinks = 3, $language = 'ru') {
        $page = $this->db->fetchOne("SELECT * FROM pages WHERE id = ?", [$pageId]);
        if (!$page) return false;

        $suggestions = array_filter($this->generateSuggestions(), function($s) use ($pageId) {
            return $s['from_page_id'] == $pageId;
        });

        // Take top N suggestions
        $suggestions = array_slice($suggestions, 0, $maxLinks);
        
        $content = $page["content_$language"];
        $insertedCount = 0;

        foreach ($suggestions as $suggestion) {
            $anchorText = $suggestion["anchor_text_$language"];
            $toSlug = $suggestion['to_slug'];
            $linkHtml = '<a href="' . BASE_URL . '/' . htmlspecialchars($toSlug) . '">' . 
                        htmlspecialchars($anchorText) . '</a>';

            // Find and replace first occurrence of anchor text (case-insensitive)
            $pattern = '/\b' . preg_quote($anchorText, '/') . '\b/ui';
            $replaced = preg_replace($pattern, $linkHtml, $content, 1, $count);
            
            if ($count > 0) {
                $content = $replaced;
                $insertedCount++;
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
            if (strpos($href, BASE_URL) === 0 || strpos($href, '/') === 0) {
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
                    return $text; // Return just the text
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
}