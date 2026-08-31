<?php
// path: ./models/Page.php
 
class Page {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getBySlug($slug, bool $includeUnpublished = false) {
        $sql = "SELECT * FROM pages WHERE slug = ?";
        $params = [$slug];
        if (!$includeUnpublished) $sql .= " AND is_published = 1";
        return $this->db->fetchOne($sql, $params);
    }

    public function getAll($includeUnpublished = false, ?int $limit = null) {
        $sql = "SELECT * FROM pages";
        if (!$includeUnpublished) {
            $sql .= " WHERE is_published = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        if ($limit !== null) {
            $limit = max(1, min(500, $limit));
            $sql .= " LIMIT {$limit}";
        }
        return $this->db->fetchAll($sql);
    }

    public function getById($id) {
        $sql = "SELECT * FROM pages WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function assertSlugUnique(string $slug, ?int $excludeId = null): void {
        $slug = trim($slug);
        if ($slug === '') throw new InvalidArgumentException('Slug cannot be empty');
        // Reserved slugs
        if (in_array($slug, ['home','main','admin','api','articles'], true)) {
            throw new InvalidArgumentException('Slug "' . $slug . '" is reserved');
        }
        $row = $this->db->fetchOne("SELECT id FROM pages WHERE slug = ? LIMIT 1", [$slug]);
        if ($row && (int)$row['id'] !== (int)($excludeId ?? -1)) {
            throw new InvalidArgumentException('Slug "' . $slug . '" already exists — choose a unique slug');
        }
    }

     public function create($data) {
        $sql = "INSERT INTO pages (
                    slug, title_ru, title_uz, content_ru, content_uz, custom_css,
                    meta_title_ru, meta_title_uz, meta_keywords_ru, meta_keywords_uz, 
                    meta_description_ru, meta_description_uz, 
                    og_title_ru, og_title_uz, og_description_ru, og_description_uz, og_image,
                    canonical_url, jsonld_ru, jsonld_uz, 
                    is_published, enable_rotation, rotation_mode, selected_rotation_id, sort_order, parent_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if (empty($data['slug'])) throw new InvalidArgumentException('Slug is required');
        $this->assertSlugUnique((string)$data['slug']);
        // Normalize parent_id: empty string → NULL
        if (array_key_exists('parent_id', $data) && ($data['parent_id'] === '' || $data['parent_id'] === 0 || $data['parent_id'] === '0')) {
            $data['parent_id'] = null;
        }

        // Respect explicit rotation_mode if provided (fix project-05 #3: creation ignored manual)
        if (array_key_exists('rotation_mode', $data) && in_array($data['rotation_mode'], ['auto','manual','disabled'], true)) {
            $rotationMode = $data['rotation_mode'];
        } elseif (array_key_exists('enable_rotation', $data)) {
            $rotationMode = !empty($data['enable_rotation']) ? 'auto' : 'disabled';
        } else {
            $rotationMode = $data['rotation_mode'] ?? 'auto';
        }
        
        $this->db->query($sql, [
            $data['slug'],
            $data['title_ru'],
            $data['title_uz'],
            $data['content_ru'],
            $data['content_uz'],
            $data['custom_css'] ?? null,
            $data['meta_title_ru'] ?? null,
            $data['meta_title_uz'] ?? null,
            $data['meta_keywords_ru'] ?? null,
            $data['meta_keywords_uz'] ?? null,
            $data['meta_description_ru'] ?? null,
            $data['meta_description_uz'] ?? null,
            $data['og_title_ru'] ?? null,
            $data['og_title_uz'] ?? null,
            $data['og_description_ru'] ?? null,
            $data['og_description_uz'] ?? null,
            $data['og_image'] ?? null,
            $data['canonical_url'] ?? null,
            $data['jsonld_ru'] ?? null,
            $data['jsonld_uz'] ?? null,
            $data['is_published'] ?? 1,
            $data['enable_rotation'] ?? 0,
            $rotationMode,
            $data['selected_rotation_id'] ?? null,
            $data['sort_order'] ?? 0,
            $data['parent_id'] ?? null
        ]);
        
        $id = $this->db->lastInsertId();
        
        if (!empty($data['parent_id'])) {
            $this->updateDepth($id);
        }
        
        return $id;
    }

    public function update($id, $data) {
        $currentPage = $this->getById($id);
        if (!$currentPage) {
            throw new Exception('Page not found');
        }
        // Auto-backup: snapshot current row before any mutation so AI wipes are undoable.
        // Never let backup failure block the page save.
        try {
            if (!class_exists('PageRevision', false)) {
                require_once BASE_PATH . '/models/PageRevision.php';
            }
            if (class_exists('PageRevision', true)) {
                // Avoid spamming revisions when only depth was recalculated — depth
                // is a derived field, not user content. Still snapshot on real edits.
                $isDepthOnly = (count($data) === 1 && array_key_exists('depth', $data));
                if (!$isDepthOnly) {
                    PageRevision::createSnapshot((int)$id, $currentPage, array_keys($data));
                }
            }
        } catch (Throwable $e) {
            error_log('[Page] revision snapshot failed for page ' . $id . ': ' . $e->getMessage());
        }
        // Slug uniqueness on update (project-09 #3)
        if (array_key_exists('slug', $data) && $data['slug'] !== $currentPage['slug']) {
            $this->assertSlugUnique((string)$data['slug'], (int)$id);
        }
        // Normalize empty parent_id
        if (array_key_exists('parent_id', $data) && ($data['parent_id'] === '' || $data['parent_id'] === 0 || $data['parent_id'] === '0')) {
            $data['parent_id'] = null;
        }
        $oldParentId = $currentPage['parent_id'] ?? null;
        $newParentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $oldParentId;
        // Also normalize retrieved old value for comparison
        if ($oldParentId === '' || $oldParentId === 0 || $oldParentId === '0') $oldParentId = null;
        if ($newParentId === '' || $newParentId === 0 || $newParentId === '0') $newParentId = null;
        
        if ($newParentId && !$this->canBeParent($id, $newParentId)) {
            throw new Exception('Invalid parent: circular reference detected');
        }
        
        // Use explicit rotation_mode if provided, otherwise keep current
        $rotationMode = array_key_exists('rotation_mode', $data) ? $data['rotation_mode'] : ($currentPage['rotation_mode'] ?? 'auto');
        
        $sql = "UPDATE pages SET 
                    slug = ?, title_ru = ?, title_uz = ?, 
                    content_ru = ?, content_uz = ?, custom_css = ?,
                    meta_title_ru = ?, meta_title_uz = ?, 
                    meta_keywords_ru = ?, meta_keywords_uz = ?, 
                    meta_description_ru = ?, meta_description_uz = ?, 
                    og_title_ru = ?, og_title_uz = ?, 
                    og_description_ru = ?, og_description_uz = ?, 
                    og_image = ?, canonical_url = ?,
                    jsonld_ru = ?, jsonld_uz = ?, 
                    is_published = ?, enable_rotation = ?, rotation_mode = ?, selected_rotation_id = ?, sort_order = ?,
                    parent_id = ?, show_link_widget = ?, widget_title_ru = ?, widget_title_uz = ?
                WHERE id = ?";
        
        $result = $this->db->query($sql, [
            array_key_exists('slug', $data) ? $data['slug'] : $currentPage['slug'],
            array_key_exists('title_ru', $data) ? $data['title_ru'] : $currentPage['title_ru'],
            array_key_exists('title_uz', $data) ? $data['title_uz'] : $currentPage['title_uz'],
            array_key_exists('content_ru', $data) ? $data['content_ru'] : $currentPage['content_ru'],
            array_key_exists('content_uz', $data) ? $data['content_uz'] : $currentPage['content_uz'],
            array_key_exists('custom_css', $data) ? $data['custom_css'] : ($currentPage['custom_css'] ?? null),
            array_key_exists('meta_title_ru', $data) ? $data['meta_title_ru'] : $currentPage['meta_title_ru'],
            array_key_exists('meta_title_uz', $data) ? $data['meta_title_uz'] : $currentPage['meta_title_uz'],
            array_key_exists('meta_keywords_ru', $data) ? $data['meta_keywords_ru'] : $currentPage['meta_keywords_ru'],
            array_key_exists('meta_keywords_uz', $data) ? $data['meta_keywords_uz'] : $currentPage['meta_keywords_uz'],
            array_key_exists('meta_description_ru', $data) ? $data['meta_description_ru'] : $currentPage['meta_description_ru'],
            array_key_exists('meta_description_uz', $data) ? $data['meta_description_uz'] : $currentPage['meta_description_uz'],
            array_key_exists('og_title_ru', $data) ? $data['og_title_ru'] : $currentPage['og_title_ru'],
            array_key_exists('og_title_uz', $data) ? $data['og_title_uz'] : $currentPage['og_title_uz'],
            array_key_exists('og_description_ru', $data) ? $data['og_description_ru'] : $currentPage['og_description_ru'],
            array_key_exists('og_description_uz', $data) ? $data['og_description_uz'] : $currentPage['og_description_uz'],
            array_key_exists('og_image', $data) ? $data['og_image'] : $currentPage['og_image'],
            array_key_exists('canonical_url', $data) ? $data['canonical_url'] : $currentPage['canonical_url'],
            array_key_exists('jsonld_ru', $data) ? $data['jsonld_ru'] : $currentPage['jsonld_ru'],
            array_key_exists('jsonld_uz', $data) ? $data['jsonld_uz'] : $currentPage['jsonld_uz'],
            array_key_exists('is_published', $data) ? $data['is_published'] : $currentPage['is_published'],
            array_key_exists('enable_rotation', $data) ? $data['enable_rotation'] : ($currentPage['enable_rotation'] ?? 0),
            $rotationMode,
            array_key_exists('selected_rotation_id', $data) ? $data['selected_rotation_id'] : $currentPage['selected_rotation_id'],
            array_key_exists('sort_order', $data) ? $data['sort_order'] : $currentPage['sort_order'],
            $newParentId,
            array_key_exists('show_link_widget', $data) ? $data['show_link_widget'] : ($currentPage['show_link_widget'] ?? 0),
            array_key_exists('widget_title_ru', $data) ? $data['widget_title_ru'] : ($currentPage['widget_title_ru'] ?? null),
            array_key_exists('widget_title_uz', $data) ? $data['widget_title_uz'] : ($currentPage['widget_title_uz'] ?? null),
            $id
        ]);
        
        if ($oldParentId != $newParentId) {
            $this->updateDepth($id);
        }
        
        return $result;
    }

    public function delete($id) {
        $sql = "DELETE FROM pages WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }

    /**
     * Get all media attached to this page
     */
    public function getMedia($id) {
        $sql = "SELECT pm.*, m.filename, m.original_name, m.file_size, m.mime_type
                FROM page_media pm
                JOIN media m ON pm.media_id = m.id
                WHERE pm.page_id = ?
                ORDER BY pm.section ASC, pm.position ASC, pm.id ASC";
        return $this->db->fetchAll($sql, [$id]);
    }

    /**
     * Get parent page
     */
    public function getParent($id) {
        $sql = "SELECT p.* FROM pages p
                INNER JOIN pages c ON p.id = c.parent_id
                WHERE c.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Get all children of a page
     */
    public function getChildren($parentId, $publishedOnly = true) {
        $sql = "SELECT * FROM pages WHERE parent_id = ?";
        if ($publishedOnly) {
            $sql .= " AND is_published = 1";
        }
        $sql .= " ORDER BY sort_order ASC, title_ru ASC";
        return $this->db->fetchAll($sql, [$parentId]);
    }

    /**
     * Get all root pages (no parent)
     */
    public function getRootPages($publishedOnly = true) {
        $sql = "SELECT * FROM pages WHERE (parent_id IS NULL OR parent_id = '' OR parent_id = 0)";
        if ($publishedOnly) {
            $sql .= " AND is_published = 1";
        }
        $sql .= " ORDER BY sort_order ASC, title_ru ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Get breadcrumb trail for a page
     * Returns array from root to current page
     */
    public function getBreadcrumbs($id) {
        $breadcrumbs = [];
        $currentPage = $this->getById($id);
        
        if (!$currentPage) {
            return $breadcrumbs;
        }
        
        $breadcrumbs[] = $currentPage;
        
        $parentId = $currentPage['parent_id'];
        $maxDepth = 10;
        $depth = 0;
        
        while ($parentId && $depth < $maxDepth) {
            $parent = $this->getById($parentId);
            if (!$parent) break;
            
            array_unshift($breadcrumbs, $parent);
            $parentId = $parent['parent_id'];
            $depth++;
        }
        
        return $breadcrumbs;
    }

    /**
     * Get full page hierarchy as nested array
     */
    public function getHierarchy($publishedOnly = true) {
        $rootPages = $this->getRootPages($publishedOnly);
        
        foreach ($rootPages as &$root) {
            $root['children'] = $this->getChildrenRecursive($root['id'], $publishedOnly);
        }
        
        return $rootPages;
    }

    /**
     * Recursively get children
     */
    private function getChildrenRecursive($parentId, $publishedOnly = true, int $depth = 0) {
        if ($depth > 10) return [];
        $children = $this->getChildren($parentId, $publishedOnly);
        
        foreach ($children as &$child) {
            $child['children'] = $this->getChildrenRecursive($child['id'], $publishedOnly, $depth + 1);
        }
        
        return $children;
    }

    /**
     * Get siblings of a page
     */
    public function getSiblings($id, $publishedOnly = true) {
        $page = $this->getById($id);
        if (!$page) return [];
        
        $sql = "SELECT * FROM pages WHERE ";
        $params = [];
        
        if (!empty($page['parent_id']) && $page['parent_id'] !== '' && $page['parent_id'] !== 0 && $page['parent_id'] !== '0') {
            $sql .= "parent_id = ?";
            $params[] = $page['parent_id'];
        } else {
            $sql .= "(parent_id IS NULL OR parent_id = '' OR parent_id = 0)";
        }
        
        $sql .= " AND id != ?";
        $params[] = $id;
        
        if ($publishedOnly) {
            $sql .= " AND is_published = 1";
        }
        
        $sql .= " ORDER BY sort_order ASC, title_ru ASC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Check if a page can be set as parent (prevent circular references)
     */
    public function canBeParent($pageId, $potentialParentId) {
        if ($pageId == $potentialParentId) {
            return false;
        }
        
        $descendants = $this->getDescendantIds($pageId);
        return !in_array($potentialParentId, $descendants);
    }

    /**
     * Get all descendant IDs (children, grandchildren, etc.)
     */
    public function getDescendantIds($parentId) {
        $descendants = [];
        $children = $this->getChildren($parentId, false);
        
        foreach ($children as $child) {
            $descendants[] = $child['id'];
            $descendants = array_merge($descendants, $this->getDescendantIds($child['id']));
        }
        
        return $descendants;
    }

    /**
     * Update page depth when parent changes
     */
    private function updateDepth($id) {
        $db = $this->db;
        $wasInTxn = $db->inTransaction();
        if (!$wasInTxn) $db->beginTransaction();
        try {
            $this->updateDepthInner($id, 0);
            if (!$wasInTxn) $db->commit();
        } catch (Throwable $e) {
            if (!$wasInTxn && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
    private function updateDepthInner($id, int $depthGuard) {
        if ($depthGuard > 20) return;
        $page = $this->getById($id);
        if (!$page) return;
        
        $depth = 0;
        $parentId = $page['parent_id'];
        $maxDepth = 10;
        
        while ($parentId && $depth < $maxDepth) {
            $parent = $this->getById($parentId);
            if (!$parent) break;
            
            $depth++;
            $parentId = $parent['parent_id'];
        }
        
        $sql = "UPDATE pages SET depth = ? WHERE id = ?";
        $this->db->query($sql, [$depth, $id]);
        
        $children = $this->getChildren($id, false);
        foreach ($children as $child) {
            $this->updateDepthInner($child['id'], $depthGuard + 1);
        }
    }
}
