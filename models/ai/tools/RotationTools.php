<?php
// path: ./models/ai/tools/RotationTools.php
// Tools for the content rotation system (content_rotations + manual selection).

require_once BASE_PATH . '/models/ContentRotation.php';

class RotationTools {

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_rotations',
                    'description' => 'List the content-rotation variants for a page (id, active month, RU title, active flag).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                        ],
                        'required' => ['page_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_rotation',
                    'description' => 'Fetch one rotation variant by id (titles, content, meta). Long fields are truncated.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'rotation_id' => ['type' => 'integer', 'description' => 'Numeric rotation id.'],
                        ],
                        'required' => ['rotation_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'set_rotation',
                    'description' => 'Pin a rotation variant as the page\'s manually selected rotation (page must have rotation enabled). Use 0 to clear the manual selection and return to auto.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'rotation_id' => ['type' => 'integer', 'description' => 'Rotation id to pin, or 0 to clear.'],
                        ],
                        'required' => ['page_id', 'rotation_id'],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'list_rotations':
                return self::listRotations($args);
            case 'get_rotation':
                return self::getRotation($args);
            case 'set_rotation':
                return self::setRotation($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function listRotations(array $args): array {
        $pageId = (int)($args['page_id'] ?? 0);
        if ($pageId <= 0) throw new InvalidArgumentException('page_id is required');
        $model = new ContentRotation();
        $rows = $model->getByPageId($pageId);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int)$r['id'],
                'active_month' => (int)$r['active_month'],
                'title_ru' => $r['title_ru'] ?? '',
                'title_uz' => $r['title_uz'] ?? '',
                'is_active' => (int)$r['is_active'],
            ];
        }
        return ['page_id' => $pageId, 'rotations' => $out, 'count' => count($out)];
    }

    private static function getRotation(array $args): array {
        $id = (int)($args['rotation_id'] ?? 0);
        if ($id <= 0) throw new InvalidArgumentException('rotation_id is required');
        $model = new ContentRotation();
        $row = $model->getById($id);
        if (!$row) throw new InvalidArgumentException('Rotation not found');
        $row = PageTools::clipRow($row, [
            'content_ru' => 12000, 'content_uz' => 12000,
            'meta_description_ru' => 2000, 'meta_description_uz' => 2000,
            'jsonld_ru' => 4000, 'jsonld_uz' => 4000,
        ]);
        $keep = ['id', 'page_id', 'active_month', 'is_active', 'title_ru', 'title_uz', 'content_ru', 'content_uz',
                 'meta_title_ru', 'meta_title_uz', 'meta_description_ru', 'meta_description_uz', 'updated_at'];
        $result = [];
        foreach ($keep as $k) {
            if (array_key_exists($k, $row)) $result[$k] = $row[$k];
        }
        return $result;
    }

    private static function setRotation(array $args): array {
        $pageId = (int)($args['page_id'] ?? 0);
        $rotationId = (int)($args['rotation_id'] ?? 0);
        if ($pageId <= 0) throw new InvalidArgumentException('page_id is required');

        $model = new ContentRotation();
        if ($rotationId === 0) {
            $model->clearManualRotation($pageId);
            return ['ok' => true, 'page_id' => $pageId, 'selected_rotation_id' => null, 'note' => 'Manual selection cleared — page returns to auto rotation.'];
        }
        if (!$model->setManualRotation($pageId, $rotationId)) {
            throw new InvalidArgumentException('Rotation not found or does not belong to this page.');
        }
        return ['ok' => true, 'page_id' => $pageId, 'selected_rotation_id' => $rotationId, 'note' => 'Manual rotation pinned.'];
    }
}
