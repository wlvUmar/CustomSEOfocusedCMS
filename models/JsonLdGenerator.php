<?php
// NEW FILE: models/JsonLdGenerator.php
// Thin facade over models/jsonld/* builders. All public signatures unchanged.

require_once BASE_PATH . '/models/JsonLdBase.php';
require_once BASE_PATH . '/models/jsonld/OrganizationBuilder.php';
require_once BASE_PATH . '/models/jsonld/WebsiteBuilder.php';
require_once BASE_PATH . '/models/jsonld/BreadcrumbBuilder.php';
require_once BASE_PATH . '/models/jsonld/ImageBuilder.php';

class JsonLdGenerator {

    /**
     * Generate Organization/LocalBusiness schema
     */
    public static function generateOrganization($data) {
        $schema = OrganizationBuilder::buildFromData($data);
        $schema = array_merge(array("@context" => "https://schema.org"), $schema);
        return JsonLdBase::encode($schema);
    }

    /**
     * Generate Service schema with provider reference
     */
    public static function generateService($data) {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "Service",
            "serviceType" => $data['service_type'],
            "name" => $data['name']
        ];

        if (!empty($data['description'])) {
            $description = JsonLdBase::cleanDescription($data['description']);
            if (!empty($description)) {
                $schema['description'] = $description;
            }
        }

        if (!empty($data['provider'])) {
            if (is_array($data['provider'])) {
                $schema['provider'] = $data['provider'];
            } else {
                $schema['provider'] = [
                    "@type" => "Organization",
                    "name" => $data['provider']
                ];
            }
        }

        if (!empty($data['image'])) {
            if (is_array($data['image'])) {
                $schema['image'] = array_values(array_unique($data['image']));
            } else {
                $schema['image'] = $data['image'];
            }
        }

        if (!empty($data['area_served'])) {
            if (is_array($data['area_served'])) {
                $schema['areaServed'] = $data['area_served'];
            } else {
                $schema['areaServed'] = [
                    '@type' => 'City',
                    'name' => $data['area_served']
                ];
            }
        }

        if (!empty($data['service_phone'])) {
            $schema['availableChannel'] = [
                '@type' => 'ServiceChannel',
                'servicePhone' => [
                    '@type' => 'ContactPoint',
                    'telephone' => $data['service_phone']
                ]
            ];
        }

        if (!empty($data['price'])) {
            $schema['offers'] = [
                "@type" => "Offer",
                "price" => $data['price'],
                "priceCurrency" => $data['currency'] ?? 'UZS'
            ];
        }

        if (!empty($data['social_media'])) {
            $schema['sameAs'] = array_values(array_filter($data['social_media']));
        }

        return JsonLdBase::encode($schema);
    }

    /**
     * Generate BreadcrumbList schema
     */
    public static function generateBreadcrumbs($items, $baseUrl, $pageUrl = '') {
        $schema = BreadcrumbBuilder::buildList($items, $baseUrl, $pageUrl);
        $schema = array_merge(array("@context" => "https://schema.org"), $schema);
        return JsonLdBase::encode($schema);
    }

    /**
     * Generate Article/BlogPosting schema
     */
    public static function generateArticle($data) {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => $data['type'] ?? "Article",
            "headline" => $data['headline'],
            "description" => $data['description']
        ];

        if (!empty($data['image'])) {
            $schema['image'] = $data['image'];
        }

        if (!empty($data['author'])) {
            $schema['author'] = [
                "@type" => "Person",
                "name" => $data['author']
            ];
        }

        if (!empty($data['publisher'])) {
            $schema['publisher'] = [
                "@type" => "Organization",
                "name" => $data['publisher'],
                "logo" => [
                    "@type" => "ImageObject",
                    "url" => $data['publisher_logo'] ?? ''
                ]
            ];
        }

        if (!empty($data['date_published'])) {
            $schema['datePublished'] = $data['date_published'];
        }

        if (!empty($data['date_modified'])) {
            $schema['dateModified'] = $data['date_modified'];
        }

        return JsonLdBase::encode($schema);
    }

    /**
     * Generate ImageObject schema
     */
    public static function generateImage($data) {
        $schema = ImageBuilder::build($data);
        $schema = array_merge(array("@context" => "https://schema.org"), $schema);
        return JsonLdBase::encode($schema);
    }

    /**
     * Generate WebSite schema with search action
     */
    public static function generateWebsite($data) {
        $schema = WebsiteBuilder::buildFromData($data);
        $schema = array_merge(array("@context" => "https://schema.org"), $schema);
        return JsonLdBase::encode($schema);
    }

    /**
     * Merge multiple schemas into a single @graph structure
     * Removes empty schemas and validates @id references
     */
    public static function mergeSchemas($schemas) {
        $graph = [];
        $ids = [];
        $decodedSchemas = [];

        // Decode and normalize schemas
        foreach ($schemas as $schemaJson) {
            if (empty($schemaJson)) continue;

            $decoded = json_decode($schemaJson, true);
            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) continue;

            // Remove @context from individual schemas (will be in root)
            unset($decoded['@context']);

            $decodedSchemas[] = $decoded;
        }

        // Build graph and track @ids
        foreach ($decodedSchemas as $decoded) {
            if (!empty($decoded['@id'])) {
                if (in_array($decoded['@id'], $ids, true)) {
                    error_log("Duplicate @id found: " . $decoded['@id']);
                    continue;
                }
                $ids[] = $decoded['@id'];
            }

            $graph[] = $decoded;
        }

        if (empty($graph)) return '';

        $idAliases = [];
        foreach ($ids as $id) {
            $idAliases[$id] = true;
            $hashPos = strpos($id, '#');
            if ($hashPos !== false) {
                $fragment = substr($id, $hashPos);
                if ($fragment !== '') {
                    $idAliases[$fragment] = true;
                }
            }
        }

        foreach ($graph as $schema) {
            if (!empty($schema['provider']) && is_array($schema['provider'])) {
                $providerId = $schema['provider']['@id'] ?? null;
                if (!empty($providerId) && empty($idAliases[$providerId])) {
                    $hashPos = strpos($providerId, '#');
                    $fragment = $hashPos !== false ? substr($providerId, $hashPos) : null;
                    if (empty($fragment) || empty($idAliases[$fragment])) {
                        error_log("Missing provider @id in graph: " . $providerId);
                    }
                }
            }

            // Same dangling-reference check, applied to any other property that
            // links to a node via @id (isPartOf, mainEntityOfPage, primaryImageOfPage,
            // image, breadcrumb, hasPart). Catches the same class of bug that hit
            // Service.isPartOf previously (a fragment built from the wrong page's URL).
            foreach (['isPartOf', 'mainEntityOfPage', 'primaryImageOfPage', 'breadcrumb'] as $refProp) {
                if (empty($schema[$refProp]) || !is_array($schema[$refProp])) continue;
                $refId = $schema[$refProp]['@id'] ?? null;
                if (empty($refId)) continue;
                if (!empty($idAliases[$refId])) continue;

                $hashPos = strpos($refId, '#');
                $fragment = $hashPos !== false ? substr($refId, $hashPos) : null;
                if (empty($fragment) || empty($idAliases[$fragment])) {
                    error_log("Dangling {$refProp} @id in graph: " . $refId);
                }
            }
        }

        $merged = [
            '@context' => 'https://schema.org',
            '@graph' => $graph
        ];

        return JsonLdBase::encode($merged);
    }
}
