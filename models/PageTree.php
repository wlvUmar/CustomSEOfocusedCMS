<?php
// models/PageTree.php
class PageTree {
    public static function sortPagesHierarchically($pages) {
        $grouped = [];
        foreach ($pages as $page) {
            $parentId = $page['parent_id'] ? $page['parent_id'] : 'root';
            $grouped[$parentId][] = $page;
        }

        $sorted = [];
        self::flattenTree($grouped, 'root', 0, $sorted);

        if (count($sorted) < count($pages)) {
            $sortedIds = array_flip(array_column($sorted, 'id'));
            foreach ($pages as $page) {
                if (!isset($sortedIds[$page['id']])) {
                    $page['level'] = 0;
                    $sorted[] = $page;
                }
            }
        }

        return $sorted;
    }

    private static function flattenTree(&$grouped, $parentId, $depth, &$result) {
        if (!isset($grouped[$parentId])) return;

        usort($grouped[$parentId], function($a, $b) {
            return strcmp($a['title_ru'], $b['title_ru']);
        });

        foreach ($grouped[$parentId] as $page) {
            $page['level'] = $depth;
            $result[] = $page;
            self::flattenTree($grouped, $page['id'], $depth + 1, $result);
        }
    }
}
