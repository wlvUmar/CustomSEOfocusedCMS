<?php
// path: ./models/Article.php

class Article {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all articles with optional filters
     */
    public function getAll($publishedOnly = true, $search = '', $category = '', $limit = null, $offset = 0) {
        $sql = "SELECT * FROM articles WHERE 1=1";
        $params = [];
        
        if ($publishedOnly) {
            $sql .= " AND is_published = 1";
        }
        
        if (!empty($search)) {
            $sql .= " AND (title_ru LIKE ? OR title_uz LIKE ? OR content_ru LIKE ? OR content_uz LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($category)) {
            $sql .= " AND (category_ru = ? OR category_uz = ?)";
            $params[] = $category;
            $params[] = $category;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get article by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM articles WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Get article by slug for public display
     */
    public function getBySlug($slug) {
        $sql = "SELECT * FROM articles WHERE slug = ? AND is_published = 1";
        return $this->db->fetchOne($sql, [$slug]);
    }

    /**
     * Create new article
     */
    public function create($data) {
        // Auto-generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title_ru'] ?? $data['title_uz'] ?? '');
        }
        
        // Auto-generate SEO fields for both languages
        $data = $this->autoGenerateSEO($data, 'ru');
        $data = $this->autoGenerateSEO($data, 'uz');
        
        $sql = "INSERT INTO articles (
            slug, title_ru, title_uz, content_ru, content_uz,
            excerpt_ru, excerpt_uz, category_ru, category_uz,
            author, image, is_published, related_page_id,
            meta_title_ru, meta_title_uz, meta_description_ru, meta_description_uz,
            meta_keywords_ru, meta_keywords_uz,
            og_title_ru, og_title_uz, og_description_ru, og_description_uz, og_image,
            jsonld_ru, jsonld_uz
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['slug'],
            $data['title_ru'] ?? '',
            $data['title_uz'] ?? '',
            $data['content_ru'] ?? '',
            $data['content_uz'] ?? '',
            $data['excerpt_ru'] ?? '',
            $data['excerpt_uz'] ?? '',
            $data['category_ru'] ?? '',
            $data['category_uz'] ?? '',
            $data['author'] ?? 'Admin',
            $data['image'] ?? null,
            $data['is_published'] ?? 0,
            $data['related_page_id'] ?? null,
            $data['meta_title_ru'] ?? '',
            $data['meta_title_uz'] ?? '',
            $data['meta_description_ru'] ?? '',
            $data['meta_description_uz'] ?? '',
            $data['meta_keywords_ru'] ?? '',
            $data['meta_keywords_uz'] ?? '',
            $data['og_title_ru'] ?? '',
            $data['og_title_uz'] ?? '',
            $data['og_description_ru'] ?? '',
            $data['og_description_uz'] ?? '',
            $data['og_image'] ?? null,
            $data['jsonld_ru'] ?? '',
            $data['jsonld_uz'] ?? ''
        ];
        
        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Update article
     */
    public function update($id, $data) {
        // Regenerate slug if title changed
        $existing = $this->getById($id);
        if (!empty($data['title_ru']) && $data['title_ru'] !== $existing['title_ru']) {
            if (empty($data['slug']) || $data['slug'] === $existing['slug']) {
                $data['slug'] = $this->generateSlug($data['title_ru']);
            }
        }
        
        // Auto-generate SEO fields for both languages
        $data = $this->autoGenerateSEO($data, 'ru');
        $data = $this->autoGenerateSEO($data, 'uz');
        
        $sql = "UPDATE articles SET
            slug = ?, title_ru = ?, title_uz = ?, content_ru = ?, content_uz = ?,
            excerpt_ru = ?, excerpt_uz = ?, category_ru = ?, category_uz = ?,
            author = ?, image = ?, is_published = ?, related_page_id = ?,
            meta_title_ru = ?, meta_title_uz = ?, meta_description_ru = ?, meta_description_uz = ?,
            meta_keywords_ru = ?, meta_keywords_uz = ?,
            og_title_ru = ?, og_title_uz = ?, og_description_ru = ?, og_description_uz = ?, og_image = ?,
            jsonld_ru = ?, jsonld_uz = ?
            WHERE id = ?";
        
        $params = [
            $data['slug'] ?? $existing['slug'],
            $data['title_ru'] ?? $existing['title_ru'],
            $data['title_uz'] ?? $existing['title_uz'],
            $data['content_ru'] ?? $existing['content_ru'],
            $data['content_uz'] ?? $existing['content_uz'],
            $data['excerpt_ru'] ?? $existing['excerpt_ru'],
            $data['excerpt_uz'] ?? $existing['excerpt_uz'],
            $data['category_ru'] ?? $existing['category_ru'],
            $data['category_uz'] ?? $existing['category_uz'],
            $data['author'] ?? $existing['author'],
            $data['image'] ?? $existing['image'],
            $data['is_published'] ?? $existing['is_published'],
            $data['related_page_id'] ?? ($existing['related_page_id'] ?? null),
            $data['meta_title_ru'] ?? $existing['meta_title_ru'],
            $data['meta_title_uz'] ?? $existing['meta_title_uz'],
            $data['meta_description_ru'] ?? $existing['meta_description_ru'],
            $data['meta_description_uz'] ?? $existing['meta_description_uz'],
            $data['meta_keywords_ru'] ?? $existing['meta_keywords_ru'],
            $data['meta_keywords_uz'] ?? $existing['meta_keywords_uz'],
            $data['og_title_ru'] ?? $existing['og_title_ru'],
            $data['og_title_uz'] ?? $existing['og_title_uz'],
            $data['og_description_ru'] ?? $existing['og_description_ru'],
            $data['og_description_uz'] ?? $existing['og_description_uz'],
            $data['og_image'] ?? $existing['og_image'],
            $data['jsonld_ru'] ?? $existing['jsonld_ru'],
            $data['jsonld_uz'] ?? $existing['jsonld_uz'],
            $id
        ];
        
        return $this->db->query($sql, $params);
    }

    /**
     * Delete article
     */
    public function delete($id) {
        $sql = "DELETE FROM articles WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }

    /**
     * Toggle publish status
     */
    public function togglePublish($id) {
        $sql = "UPDATE articles SET is_published = NOT is_published WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }

    /**
     * Generate URL-friendly slug from title
     */
    public function generateSlug($title) {
        // Transliterate Cyrillic to Latin
        $translitMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
            // Uzbek specific
            'ў' => 'o', 'қ' => 'q', 'ғ' => 'g', 'ҳ' => 'h',
            'Ў' => 'O', 'Қ' => 'Q', 'Ғ' => 'G', 'Ҳ' => 'H'
        ];
        
        $slug = strtr($title, $translitMap);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Check if slug already exists
     */
    private function slugExists($slug) {
        $sql = "SELECT COUNT(*) as count FROM articles WHERE slug = ?";
        $result = $this->db->fetchOne($sql, [$slug]);
        return $result['count'] > 0;
    }

    /**
     * Auto-generate SEO fields from content
     */
    public function autoGenerateSEO($data, $lang) {
        $titleKey = "title_$lang";
        $contentKey = "content_$lang";
        $excerptKey = "excerpt_$lang";
        
        // Auto-generate excerpt if not provided
        if (empty($data[$excerptKey]) && !empty($data[$contentKey])) {
            $data[$excerptKey] = $this->extractExcerpt($data[$contentKey], 160);
        }
        
        // Auto-generate meta_title if not provided
        if (empty($data["meta_title_$lang"]) && !empty($data[$titleKey])) {
            $data["meta_title_$lang"] = $data[$titleKey];
        }
        
        // Auto-generate meta_description if not provided
        if (empty($data["meta_description_$lang"])) {
            if (!empty($data[$excerptKey])) {
                $data["meta_description_$lang"] = $data[$excerptKey];
            } elseif (!empty($data[$contentKey])) {
                $data["meta_description_$lang"] = $this->extractExcerpt($data[$contentKey], 160);
            }
        }
        
        // Auto-generate OG tags if not provided
        if (empty($data["og_title_$lang"]) && !empty($data["meta_title_$lang"])) {
            $data["og_title_$lang"] = $data["meta_title_$lang"];
        }
        
        if (empty($data["og_description_$lang"]) && !empty($data["meta_description_$lang"])) {
            $data["og_description_$lang"] = $data["meta_description_$lang"];
        }
        
        return $data;
    }

    /**
     * Extract excerpt from content
     */
    public function extractExcerpt($content, $length = 160) {
        // Remove HTML tags
        $text = strip_tags($content);
        
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // Truncate to length, ensuring we end at a word boundary
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length);
            $lastSpace = mb_strrpos($text, ' ');
            if ($lastSpace !== false) {
                $text = mb_substr($text, 0, $lastSpace);
            }
            $text .= '...';
        }
        
        return $text;
    }

    /**
     * Get word count of content
     */
    public function getWordCount($content) {
        $text = strip_tags($content);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return count($words);
    }

    /**
     * Check if content is thin (less than 300 words)
     */
    public function isThinContent($content) {
        return $this->getWordCount($content) < 300;
    }

    /**
     * Suggest internal links based on content analysis
     * Returns array of suggested page slugs with relevance scores
     */
    public function suggestInternalLinks($content, $lang) {
        require_once BASE_PATH . '/models/Page.php';
        $pageModel = new Page();
        $pages = $pageModel->getAll(true);
        
        $suggestions = [];
        $contentLower = mb_strtolower(strip_tags($content));
        
        foreach ($pages as $page) {
            $pageTitle = mb_strtolower($page["title_$lang"] ?? '');
            $pageContent = mb_strtolower(strip_tags($page["content_$lang"] ?? ''));
            
            // Calculate relevance score
            $score = 0;
            
            // Check if page title appears in article content
            if (!empty($pageTitle) && mb_strpos($contentLower, $pageTitle) !== false) {
                $score += 10;
            }
            
            // Check for keyword overlap
            $contentWords = preg_split('/\s+/', $contentLower, -1, PREG_SPLIT_NO_EMPTY);
            $pageWords = preg_split('/\s+/', $pageTitle . ' ' . $pageContent, -1, PREG_SPLIT_NO_EMPTY);
            
            // Remove common words
            $stopWords = ['и', 'в', 'на', 'с', 'по', 'для', 'как', 'что', 'это', 'все', 'the', 'a', 'an', 'and', 'or', 'but'];
            $contentWords = array_diff($contentWords, $stopWords);
            $pageWords = array_diff($pageWords, $stopWords);
            
            $commonWords = array_intersect($contentWords, $pageWords);
            $score += count($commonWords);
            
            if ($score > 0) {
                $suggestions[] = [
                    'slug' => $page['slug'],
                    'title' => $page["title_$lang"],
                    'score' => $score
                ];
            }
        }
        
        // Sort by score descending
        usort($suggestions, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Return top 5 suggestions
        return array_slice($suggestions, 0, 5);
    }

    /**
     * Get all unique categories
     */
    public function getCategories($lang = 'ru') {
        $sql = "SELECT DISTINCT category_$lang as category FROM articles 
                WHERE category_$lang IS NOT NULL AND category_$lang != '' 
                ORDER BY category_$lang";
        $results = $this->db->fetchAll($sql);
        return array_column($results, 'category');
    }

    /**
     * Get related articles based on category
     */
    public function getRelatedArticles($articleId, $category, $lang = 'ru', $limit = 3) {
        $sql = "SELECT * FROM articles 
                WHERE id != ? AND category_$lang = ? AND is_published = 1 
                ORDER BY created_at DESC 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$articleId, $category, $limit]);
    }
}
