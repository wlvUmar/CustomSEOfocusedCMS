<?php
// FIXED: models/InternalLinks.php
// Issues: 
// 1. Link insertion corrupting HTML structure
// 2. Not checking if text is already inside tags
// 3. Simple regex causing malformed HTML

class InternalLinks {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Generate link suggestions based on keyword matching
     */
    public function generateSuggestions() {
        $pages = $this->db->fetchAll("SELECT * FROM pages WHERE is_published = 1");
        $suggestions = [];

        foreach ($pages as $fromPage) {
            foreach ($pages as $toPage) {
                if ($fromPage['id'] === $toPage['id']) continue;

                $score = 0;
                
                $fromContentRu = mb_strtolower(strip_tags($fromPage['content_ru']));
                $fromContentUz = mb_strtolower(strip_tags($fromPage['content_uz']));
                $toTitleRu = mb_strtolower($toPage['title_ru']);
                $toTitleUz = mb_strtolower($toPage['title_uz']);
                
                if (mb_strpos($fromContentRu, $toTitleRu) !== false) $score += 10;
                if (mb_strpos($fromContentUz, $toTitleUz) !== false) $score += 10;
                
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

        usort($suggestions, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        return $suggestions;
    }

    /**
     * FIXED: Safe HTML-aware link insertion
     * Prevents corrupting HTML structure
     */
    public function autoInsertLinks($pageId, $maxLinks = 3, $language = 'ru') {
        $page = $this->db->fetchOne("SELECT * FROM pages WHERE id = ?", [$pageId]);
        if (!$page) return 0;

        $allSuggestions = $this->generateSuggestions();
        $suggestions = array_filter($allSuggestions, function($s) use ($pageId) {
            return $s['from_page_id'] == $pageId;
        });
        
        $suggestions = array_slice($suggestions, 0, $maxLinks);
        
        $content = $page["content_$language"];
        $insertedCount = 0;

        foreach ($suggestions as $suggestion) {
            $anchorText = $suggestion["anchor_text_$language"];
            $toSlug = $suggestion['to_slug'];
            
            // Build link HTML
            $linkHtml = '<a href="' . BASE_URL . '/' . htmlspecialchars($toSlug) . '">' . 
                        htmlspecialchars($anchorText) . '</a>';

            // FIX: Use DOMDocument for safe HTML manipulation
            try {
                $dom = new DOMDocument('1.0', 'UTF-8');
                // Suppress warnings for malformed HTML
                libxml_use_internal_errors(true);
                
                // Load HTML with proper encoding
                $dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                
                $xpath = new DOMXPath($dom);
                
                // Find text nodes that contain the anchor text (not already in links)
                $textNodes = $xpath->query('//text()[not(ancestor::a)]');
                
                $replaced = false;
                foreach ($textNodes as $textNode) {
                    $text = $textNode->nodeValue;
                    
                    // Case-insensitive search for anchor text
                    $pos = mb_stripos($text, $anchorText);
                    
                    if ($pos !== false && !$replaced) {
                        // Split the text node
                        $before = mb_substr($text, 0, $pos);
                        $match = mb_substr($text, $pos, mb_strlen($anchorText));
                        $after = mb_substr($text, $pos + mb_strlen($anchorText));
                        
                        // Create new link element
                        $link = $dom->createElement('a');
                        $link->setAttribute('href', BASE_URL . '/' . htmlspecialchars($toSlug));
                        $link->nodeValue = $match;
                        
                        // Create document fragment
                        $fragment = $dom->createDocumentFragment();
                        if ($before) {
                            $fragment->appendXML(htmlspecialchars($before, ENT_NOQUOTES, 'UTF-8'));
                        }
                        $fragment->appendChild($link);
                        if ($after) {
                            $fragment->appendXML(htmlspecialchars($after, ENT_NOQUOTES, 'UTF-8'));
                        }
                        
                        // Replace the text node
                        $textNode->parentNode->replaceChild($fragment, $textNode);
                        
                        $replaced = true;
                        $insertedCount++;
                        break;
                    }
                }
                
                if ($replaced) {
                    // Save back the HTML
                    $content = $dom->saveHTML();
                    
                    // Remove the XML declaration if present
                    $content = preg_replace('/^<\?xml[^>]+>\s*/', '', $content);
                }
                
            } catch (Exception $e) {
                // Fallback to simple replacement if DOM fails
                $pattern = '/\b' . preg_quote($anchorText, '/') . '\b/ui';
                
                if (!preg_match('/<a[^>]*>' . preg_quote($anchorText, '/') . '<\/a>/ui', $content)) {
                    $content = preg_replace($pattern, $linkHtml, $content, 1, $count);
                    
                    if ($count > 0) {
                        $insertedCount++;
                    }
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

        preg_match_all('/<a\s+href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/i', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $href = $match[1];
            $text = strip_tags($match[2]);
            
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
        
        $content = preg_replace_callback(
            '/<a\s+href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/i',
            function($matches) {
                $href = $matches[1];
                $text = $matches[2];
                
                if (strpos($href, BASE_URL) === 0 || (strpos($href, '/') === 0 && strpos($href, '//') !== 0)) {
                    return $text;
                }
                
                return $matches[0];
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

    /**
     * Check for broken internal links
     */
    public function checkLinkHealth() {
        $pages = $this->db->fetchAll("SELECT id, slug, content_ru, content_uz FROM pages WHERE is_published = 1");
        $brokenLinks = [];
        $validSlugs = array_column($pages, 'slug');
        
        foreach ($pages as $page) {
            foreach (['ru', 'uz'] as $lang) {
                $content = $page["content_$lang"];
                
                preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $content, $matches, PREG_SET_ORDER);
                
                foreach ($matches as $match) {
                    $url = $match[1];
                    $linkText = strip_tags($match[2]);
                    
                    if (strpos($url, BASE_URL) === 0 || (strpos($url, '/') === 0 && strpos($url, '//') !== 0)) {
                        $path = parse_url($url, PHP_URL_PATH);
                        $slug = trim($path, '/');
                        $slug = preg_replace('/\/(ru|uz)$/', '', $slug);
                        
                        if (!empty($slug) && !in_array($slug, $validSlugs)) {
                            $brokenLinks[] = [
                                'page_id' => $page['id'],
                                'page_slug' => $page['slug'],
                                'broken_url' => $url,
                                'link_text' => $linkText,
                                'language' => $lang,
                                'severity' => 'high'
                            ];
                        }
                    }
                }
            }
        }
        
        return $brokenLinks;
    }

    /**
     * Auto-fix broken links by removing them
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
     * Get link health summary
     */
    public function getLinkHealthSummary() {
        $brokenLinks = $this->checkLinkHealth();
        
        $summary = [
            'total_broken' => count($brokenLinks),
            'pages_affected' => count(array_unique(array_column($brokenLinks, 'page_id'))),
            'by_severity' => [
                'high' => count(array_filter($brokenLinks, fn($l) => $l['severity'] === 'high')),
                'medium' => count(array_filter($brokenLinks, fn($l) => ($l['severity'] ?? '') === 'medium')),
                'low' => count(array_filter($brokenLinks, fn($l) => ($l['severity'] ?? '') === 'low'))
            ]
        ];
        
        return $summary;
    }
}