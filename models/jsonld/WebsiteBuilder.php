<?php
// path: ./models/jsonld/WebsiteBuilder.php
// Single owner of WebSite nodes.

require_once BASE_PATH . '/models/JsonLdBase.php';

class WebsiteBuilder {

    public static function build($seoSettings, $lang, $baseUrl) {
        if (!empty($seoSettings['website_schema'])) {
            $siteSchema = json_decode($seoSettings['website_schema'], true);
            if (is_array($siteSchema)) {
                $siteSchema = JsonLdBase::sanitizeUrlValues($siteSchema, $baseUrl);
                $siteSchema['@id'] = $baseUrl . '#website';
                $siteSchema['url'] = $baseUrl;
                $siteSchema['publisher'] = array('@id' => $baseUrl . '#organization');
                unset($siteSchema['@context']);
                return $siteSchema;
            }
        }

        return array(
            '@type' => 'WebSite',
            '@id' => $baseUrl . '#website',
            'url' => $baseUrl,
            'name' => isset($seoSettings["site_name_$lang"]) ? $seoSettings["site_name_$lang"] : '',
            'inLanguage' => JsonLdBase::inLanguage($lang),
            'publisher' => array('@id' => $baseUrl . '#organization')
        );
    }

    public static function buildFromData($data) {
        $schema = array(
            '@type' => 'WebSite',
            'name' => $data['name'],
            'url' => $data['url']
        );

        if (!empty($data['description'])) $schema['description'] = $data['description'];

        if (!empty($data['search_url'])) {
            $schema['potentialAction'] = array(
                '@type' => 'SearchAction',
                'target' => array('@type' => 'EntryPoint', 'urlTemplate' => $data['search_url'] . '?q={search_term_string}'),
                'query-input' => 'required name=search_term_string'
            );
        }

        return $schema;
    }
}
