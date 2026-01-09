<?php
// path: ./models/Page.php

class Page {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getBySlug($slug) {
        $sql = "SELECT * FROM pages WHERE slug = ? AND is_published = 1";
        return $this->db->fetchOne($sql, [$slug]);
    }

    public function getAll($includeUnpublished = false) {
        $sql = "SELECT * FROM pages";
        if (!$includeUnpublished) {
            $sql .= " WHERE is_published = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        return $this->db->fetchAll($sql);
    }

    public function getById($id) {
        $sql = "SELECT * FROM pages WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function create($data) {
        $sql = "INSERT INTO pages (
                    slug, title_ru, title_uz, content_ru, content_uz, 
                    meta_title_ru, meta_title_uz, meta_keywords_ru, meta_keywords_uz, 
                    meta_description_ru, meta_description_uz, 
                    og_title_ru, og_title_uz, og_description_ru, og_description_uz, og_image,
                    canonical_url, jsonld_ru, jsonld_uz, 
                    is_published, enable_rotation, sort_order, parent_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $data['slug'],
            $data['title_ru'],
            $data['title_uz'],
            $data['content_ru'],
            $data['content_uz'],
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
        $oldParentId = $currentPage['parent_id'] ?? null;
        $newParentId = $data['parent_id'] ?? null;
        
        if ($newParentId && !$this->canBeParent($id, $newParentId)) {
            throw new Exception('Invalid parent: circular reference detected');
        }
        
        $sql = "UPDATE pages SET 
                    slug = ?, title_ru = ?, title_uz = ?, 
                    content_ru = ?, content_uz = ?, 
                    meta_title_ru = ?, meta_title_uz = ?, 
                    meta_keywords_ru = ?, meta_keywords_uz = ?, 
                    meta_description_ru = ?, meta_description_uz = ?, 
                    og_title_ru = ?, og_title_uz = ?, 
                    og_description_ru = ?, og_description_uz = ?, 
                    og_image = ?, canonical_url = ?,
                    jsonld_ru = ?, jsonld_uz = ?, 
                    is_published = ?, enable_rotation = ?, sort_order = ?,
                    parent_id = ?
                WHERE id = ?";
        
        $result = $this->db->query($sql, [
            $data['slug'],
            $data['title_ru'],
            $data['title_uz'],
            $data['content_ru'],
            $data['content_uz'],
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
            $data['sort_order'] ?? 0,
            $newParentId,
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
        $sql = "SELECT * FROM pages WHERE parent_id IS NULL";
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
    private function getChildrenRecursive($parentId, $publishedOnly = true) {
        $children = $this->getChildren($parentId, $publishedOnly);
        
        foreach ($children as &$child) {
            $child['children'] = $this->getChildrenRecursive($child['id'], $publishedOnly);
        }
        
        return $children;
    }

    /**
     * Get siblings of a page
     */
    public function getSiblings($id, $publishedOnly = true) {
        $page = $this->getById($id);
        if (!$page) return [];
        
        $sql = "SELECT * FROM pages WHERE parent_id ";
        $params = [];
        
        if ($page['parent_id']) {
            $sql .= "= ?";
            $params[] = $page['parent_id'];
        } else {
            $sql .= "IS NULL";
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
            $this->updateDepth($child['id']);
        }
    }
}
