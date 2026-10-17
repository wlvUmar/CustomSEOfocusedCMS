<?php
// path: ./models/jsonld/BreadcrumbBuilder.php
// Single owner of BreadcrumbList nodes. RU/UZ labels keep parity.

require_once BASE_PATH . '/models/JsonLdBase.php';

class BreadcrumbBuilder {

    public static function buildList($items, $baseUrl, $pageUrl = '') {
        $listItems = array();
        foreach ($items as $index => $item) {
            $listItems[] = array(
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => rtrim($baseUrl, '/') . '/' . ltrim($item['url'], '/')
            );
        }

        $schema = array('@type' => 'BreadcrumbList', 'itemListElement' => $listItems);
        if (!empty($pageUrl)) $schema['@id'] = $pageUrl . '#breadcrumb';
        return $schema;
    }

    public static function buildPageTrail($breadcrumbs, $pageCanonicalUrl) {
        if (empty($breadcrumbs)) return null;
        $items = array();
        foreach ($breadcrumbs as $index => $item) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url']
            );
        }
        return array(
            '@type' => 'BreadcrumbList',
            '@id' => $pageCanonicalUrl . '#breadcrumb',
            'itemListElement' => $items
        );
    }

    public static function buildArticleTrail($title, $lang, $baseUrl, $articleUrl) {
        return array(
            '@type' => 'BreadcrumbList',
            '@id' => $articleUrl . '#breadcrumb',
            'itemListElement' => array(
                array('@type' => 'ListItem', 'position' => 1, 'name' => $lang === 'ru' ? 'Главная' : 'Bosh sahifa', 'item' => $baseUrl . '/'),
                array('@type' => 'ListItem', 'position' => 2, 'name' => $lang === 'ru' ? 'Статьи' : 'Maqolalar', 'item' => $baseUrl . '/articles'),
                array('@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $articleUrl)
            )
        );
    }
}
