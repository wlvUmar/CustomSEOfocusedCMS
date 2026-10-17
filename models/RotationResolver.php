<?php
// path: ./models/RotationResolver.php

class RotationResolver {
    private $rotationModel;
    private $analyticsModel;

    public function __construct(ContentRotation $rotationModel, Analytics $analyticsModel) {
        $this->rotationModel = $rotationModel;
        $this->analyticsModel = $analyticsModel;
    }

    public function resolve(array $page, string $slug, string $lang): array {
        $rotationContent = null;
        if (($page['rotation_mode'] ?? null) === Page::ROTATION_MANUAL && !empty($page['selected_rotation_id'])) {
            $rotationContent = $this->rotationModel->getRotationById($page['selected_rotation_id']);
        } elseif (($page['rotation_mode'] ?? null) === Page::ROTATION_AUTO) {
            $rotationContent = $this->rotationModel->getCurrentMonth($page['id']);
        }

        if (!$rotationContent) {
            return ['page' => $page, 'used' => false, 'month' => null, 'rotation' => null];
        }

        $page = $this->merge($page, $rotationContent, $lang);

        if (!shouldSkipTracking() && !isBot()) {
            $this->analyticsModel->trackRotationShown($slug, $rotationContent['active_month'], $lang);
        }

        return [
            'page' => $page,
            'used' => true,
            'month' => $rotationContent['active_month'],
            'rotation' => $rotationContent
        ];
    }

    private function merge(array $page, array $rotationContent, string $lang): array {
        if (!empty($rotationContent["title_$lang"])) {
            $page["title_$lang"] = $rotationContent["title_$lang"];
        }

        $page["content_$lang"] = $rotationContent["content_$lang"];

        $seoFields = [
            'meta_title', 'meta_description', 'meta_keywords',
            'og_title', 'og_description', 'og_image',
            'jsonld'
        ];

        foreach ($seoFields as $field) {
            if ($field === 'og_image') {
                if (!empty($rotationContent[$field])) {
                    $page[$field] = $rotationContent[$field];
                }
            } else {
                $fieldWithLang = "{$field}_{$lang}";
                if (!empty($rotationContent[$fieldWithLang])) {
                    $page[$fieldWithLang] = $rotationContent[$fieldWithLang];
                }
            }
        }

        return $page;
    }
}
