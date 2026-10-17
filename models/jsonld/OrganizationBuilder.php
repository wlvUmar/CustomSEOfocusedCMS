<?php
// path: ./models/jsonld/OrganizationBuilder.php
// Single owner of Organization / LocalBusiness nodes.

require_once BASE_PATH . '/models/JsonLdBase.php';

class OrganizationBuilder {

    public static function buildContactPoint($seoSettings) {
        $contactPoint = array(
            '@type' => 'ContactPoint',
            'telephone' => $seoSettings['phone'],
            'contactType' => 'customer service',
            'availableLanguage' => array('ru', 'uz')
        );

        $areaServed = !empty($seoSettings['area_served']) ? $seoSettings['area_served'] : (isset($seoSettings['city']) ? $seoSettings['city'] : null);
        if (!empty($areaServed)) {
            $contactPoint['areaServed'] = array('@type' => 'City', 'name' => $areaServed);
        }

        if (!empty($seoSettings['opening_hours'])) {
            $hoursLines = array_values(array_filter(array_map('trim', explode("\n", $seoSettings['opening_hours']))));
            if (!empty($hoursLines)) {
                $contactPoint['hoursAvailable'] = $hoursLines;
            }
        }

        return $contactPoint;
    }

    private static function openingHoursLines($raw) {
        if (empty($raw)) return null;
        if (is_array($raw)) {
            $lines = array_values(array_filter(array_map('trim', $raw)));
            return !empty($lines) ? $lines : null;
        }
        $lines = array_values(array_filter(array_map('trim', explode("\n", (string)$raw))));
        return !empty($lines) ? $lines : null;
    }

    private static function areaServedNode($seoSettings) {
        $name = !empty($seoSettings['area_served']) ? $seoSettings['area_served'] : (isset($seoSettings['city']) ? $seoSettings['city'] : 'Tashkent');
        return array('@type' => 'City', 'name' => $name ? $name : 'Tashkent');
    }

    private static function attachCommon($schema, $seoSettings, $lang, $baseUrl) {
        if (!empty($seoSettings['opening_hours'])) {
            $hoursLines = self::openingHoursLines($seoSettings['opening_hours']);
            if ($hoursLines) $schema['openingHours'] = $hoursLines;
        }
        $schema['areaServed'] = self::areaServedNode($seoSettings);
        if (!empty($seoSettings['phone'])) {
            $schema['contactPoint'] = self::buildContactPoint($seoSettings);
        }
        $schema['brand'] = array('@type' => 'Brand', 'name' => $schema['name']);
        return $schema;
    }

    public static function build($seoSettings, $lang, $baseUrl, $pageImage = null) {
        if (!empty($seoSettings['organization_schema'])) {
            $orgSchema = json_decode($seoSettings['organization_schema'], true);
            if (is_array($orgSchema)) {
                $orgSchema = JsonLdBase::sanitizeUrlValues($orgSchema, $baseUrl);
                $orgSchema['description'] = JsonLdBase::cleanText(JsonLdBase::unifiedOrganizationDescriptionRu());
                $orgSchema['@id'] = $baseUrl . '#organization';
                $orgSchema['url'] = $baseUrl;

                if (!empty($orgSchema['logo'])) {
                    if (is_string($orgSchema['logo'])) {
                        $orgSchema['logo'] = array('@type' => 'ImageObject', 'url' => absoluteUrl($orgSchema['logo'], $baseUrl));
                    } elseif (is_array($orgSchema['logo']) && !empty($orgSchema['logo']['url'])) {
                        $orgSchema['logo']['url'] = absoluteUrl($orgSchema['logo']['url'], $baseUrl);
                    }
                    if (is_array($orgSchema['logo'])) {
                        $logoDims = JsonLdBase::imageDims(isset($orgSchema['logo']['url']) ? $orgSchema['logo']['url'] : '');
                        if ($logoDims) {
                            $orgSchema['logo']['width'] = $logoDims['width'];
                            $orgSchema['logo']['height'] = $logoDims['height'];
                        }
                    }
                }
                if (!empty($seoSettings['opening_hours'])) {
                    $hoursLines = self::openingHoursLines($seoSettings['opening_hours']);
                    if ($hoursLines) $orgSchema['openingHours'] = $hoursLines;
                }
                $orgSchema['areaServed'] = self::areaServedNode($seoSettings);
                if (!empty($seoSettings['phone'])) {
                    $orgSchema['contactPoint'] = self::buildContactPoint($seoSettings);
                }
                $orgSchema['brand'] = array(
                    '@type' => 'Brand',
                    'name' => isset($orgSchema['name']) ? $orgSchema['name'] : (isset($seoSettings["org_name_$lang"]) ? $seoSettings["org_name_$lang"] : (isset($seoSettings["site_name_$lang"]) ? $seoSettings["site_name_$lang"] : ''))
                );
                if (!empty($pageImage)) {
                    $orgSchema['image'] = $pageImage;
                }
                unset($orgSchema['@context']);
                return $orgSchema;
            }
        }

        $schema = array(
            '@type' => 'LocalBusiness',
            '@id' => $baseUrl . '#organization',
            'name' => isset($seoSettings["org_name_$lang"]) ? $seoSettings["org_name_$lang"] : (isset($seoSettings["site_name_$lang"]) ? $seoSettings["site_name_$lang"] : 'Site Name'),
            'url' => $baseUrl,
            'logo' => array(
                '@type' => 'ImageObject',
                'url' => absoluteUrl(isset($seoSettings['org_logo']) ? $seoSettings['org_logo'] : ($baseUrl . '/css/logo.png'), $baseUrl)
            )
        );

        $schema['description'] = JsonLdBase::cleanText(JsonLdBase::unifiedOrganizationDescriptionRu());

        if (!empty($seoSettings['phone'])) {
            $schema['telephone'] = $seoSettings['phone'];
        }

        if (!empty($seoSettings["address_$lang"])) {
            $schema['address'] = array(
                '@type' => 'PostalAddress',
                'streetAddress' => $seoSettings["address_$lang"],
                'addressCountry' => isset($seoSettings['country']) ? $seoSettings['country'] : 'UZ'
            );
            if (!empty($seoSettings['city'])) {
                $schema['address']['addressLocality'] = $seoSettings['city'];
            }
        }

        $sameAs = array();
        foreach (array('social_facebook', 'social_instagram', 'social_twitter', 'social_youtube') as $social) {
            if (!empty($seoSettings[$social])) $sameAs[] = $seoSettings[$social];
        }
        if (!empty($seoSettings['org_sameas_extra'])) {
            $extras = array_map('trim', explode(',', $seoSettings['org_sameas_extra']));
            $sameAs = array_merge($sameAs, $extras);
        }
        if (!empty($sameAs)) $schema['sameAs'] = $sameAs;

        $schema = self::attachCommon($schema, $seoSettings, $lang, $baseUrl);

        $logoDims = JsonLdBase::imageDims(isset($schema['logo']['url']) ? $schema['logo']['url'] : '');
        if ($logoDims) {
            $schema['logo']['width'] = $logoDims['width'];
            $schema['logo']['height'] = $logoDims['height'];
        }

        if (!empty($pageImage)) {
            $schema['image'] = $pageImage;
        }

        return $schema;
    }

    public static function buildFromData($data) {
        $schema = array(
            '@type' => isset($data['type']) ? $data['type'] : 'LocalBusiness',
            'name' => $data['name'],
            'url' => $data['url'],
        );

        if (!empty($data['id'])) $schema['@id'] = $data['id'];

        if (!empty($data['logo'])) {
            $logoData = array('@type' => 'ImageObject', 'url' => $data['logo']);
            if (!empty($data['logo_width'])) $logoData['width'] = $data['logo_width'];
            if (!empty($data['logo_height'])) $logoData['height'] = $data['logo_height'];
            $schema['logo'] = $logoData;
        }

        if (!empty($data['image'])) $schema['image'] = $data['image'];

        if (!empty($data['description'])) {
            $description = JsonLdBase::cleanDescription($data['description'], 300);
            if (!empty(trim($description))) $schema['description'] = $description;
        }

        if (!empty($data['telephone'])) $schema['telephone'] = $data['telephone'];
        if (!empty($data['email'])) $schema['email'] = $data['email'];

        if (!empty($data['address'])) {
            $schema['address'] = array(
                '@type' => 'PostalAddress',
                'streetAddress' => $data['address'],
                'addressLocality' => isset($data['city']) ? $data['city'] : '',
                'addressRegion' => isset($data['region']) ? $data['region'] : '',
                'postalCode' => isset($data['postal']) ? $data['postal'] : '',
                'addressCountry' => isset($data['country']) ? $data['country'] : 'UZ'
            );
        }

        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            $schema['geo'] = array(
                '@type' => 'GeoCoordinates',
                'latitude' => (float)$data['latitude'],
                'longitude' => (float)$data['longitude']
            );
        }

        if (!empty($data['opening_hours'])) {
            $schema['openingHours'] = is_array($data['opening_hours']) ? $data['opening_hours'] : array($data['opening_hours']);
        }

        if (!empty($data['price_range'])) $schema['priceRange'] = $data['price_range'];
        if (!empty($data['social_media'])) $schema['sameAs'] = array_values(array_filter($data['social_media']));

        return $schema;
    }
}
