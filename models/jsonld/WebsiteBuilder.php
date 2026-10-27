<?php
// path: ./models/jsonld/WebsiteBuilder.php
// Single owner of WebSite nodes.

require_once BASE_PATH . '/models/JsonLdBase.php';

class WebsiteBuilder {

    /** Bare domain (no www) — used as alternateName so the brand name and the domain are tied together for site-name signals. */
    public static function siteHost($baseUrl) {
        $host = parse_url((string)$baseUrl, PHP_URL_HOST);
        return $host ? preg_replace('/^www\./i', '', $host) : '';
    }

    public static function build($seoSettings, $lang, $baseUrl) {
        $host = self::siteHost($baseUrl);

        if (!empty($seoSettings['website_schema'])) {
            $siteSchema = json_decode($seoSettings['website_schema'], true);
            if (is_array($siteSchema)) {
                $siteSchema = JsonLdBase::sanitizeUrlValues($siteSchema, $baseUrl);
                $siteSchema['@id'] = $baseUrl . '#website';
                $siteSchema['url'] = $baseUrl;
                $siteSchema['publisher'] = array('@id' => $baseUrl . '#organization');
                if (empty($siteSchema['alternateName']) && $host !== '') {
                    $siteSchema['alternateName'] = $host;
                }
                unset($siteSchema['@context']);
                return $siteSchema;
            }
        }

        $schema = array(
            '@type' => 'WebSite',
            '@id' => $baseUrl . '#website',
            'url' => $baseUrl,
            'name' => isset($seoSettings["site_name_$lang"]) ? $seoSettings["site_name_$lang"] : '',
            'inLanguage' => JsonLdBase::inLanguage($lang),
            'publisher' => array('@id' => $baseUrl . '#organization')
        );
        if ($host !== '') {
            $schema['alternateName'] = $host;
        }
        return $schema;
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
