<?php
// NEW FILE: models/JsonLdGenerator.php

class JsonLdGenerator {
    
    /**
     * Generate Organization/LocalBusiness schema
     */
    public static function generateOrganization($data) {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => $data['type'] ?? "LocalBusiness",
            "name" => $data['name'],
            "url" => $data['url'],
        ];
        
        // Optional @id to reference the organization from other schemas
        if (!empty($data['id'])) {
            $schema['@id'] = $data['id'];
        }
        
        if (!empty($data['logo'])) {
            $schema['logo'] = $data['logo'];
        }
        
        if (!empty($data['image'])) {
            $schema['image'] = $data['image'];
        }
        
        if (!empty($data['description'])) {
            $schema['description'] = $data['description'];
        }
        
        if (!empty($data['telephone'])) {
            $schema['telephone'] = $data['telephone'];
        }
        
        if (!empty($data['email'])) {
            $schema['email'] = $data['email'];
        }
        
        // Address
        if (!empty($data['address'])) {
            $schema['address'] = [
                "@type" => "PostalAddress",
                "streetAddress" => $data['address'],
                "addressLocality" => $data['city'] ?? '',
                "addressRegion" => $data['region'] ?? '',
                "postalCode" => $data['postal'] ?? '',
                "addressCountry" => $data['country'] ?? 'UZ'
            ];
        }
        
        // Geo coordinates
        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float)$data['latitude'],
                'longitude' => (float)$data['longitude']
            ];
        }
        
        // Opening hours
        if (!empty($data['opening_hours'])) {
            $schema['openingHours'] = is_array($data['opening_hours']) 
                ? $data['opening_hours'] 
                : [$data['opening_hours']];
        }
        
        // Price range
        if (!empty($data['price_range'])) {
            $schema['priceRange'] = $data['price_range'];
        }
        
        // Social media
        if (!empty($data['social_media'])) {
            $schema['sameAs'] = array_values(array_filter($data['social_media']));
        }
        
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    
    /**
     * Generate Service schema with provider reference
     */
    public static function generateService($data) {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "Service",
            "serviceType" => $data['service_type'],
            "name" => $data['name'],
            "description" => $data['description']
        ];
        
        // Provider - can be either a reference or a full object
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
        
        // Image for the service
        if (!empty($data['image'])) {
            $schema['image'] = $data['image'];
        }
        
        // Area served - prefer structured City object when a simple name is provided
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
        
        // Available channel (phone)
        if (!empty($data['service_phone'])) {
            $schema['availableChannel'] = [
                '@type' => 'ServiceChannel',
                'servicePhone' => [
                    '@type' => 'ContactPoint',
                    'telephone' => $data['service_phone']
                ]
            ];
        }
        
        // Price
        if (!empty($data['price'])) {
            $schema['offers'] = [
                "@type" => "Offer",
                "price" => $data['price'],
                "priceCurrency" => $data['currency'] ?? 'UZS'
            ];
        }
        
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    
    /**
     * Generate BreadcrumbList schema
     */
    public static function generateBreadcrumbs($items, $baseUrl, $pageUrl = '') {
        $listItems = [];
        
        foreach ($items as $index => $item) {
            $listItems[] = [
                "@type" => "ListItem",
                "position" => $index + 1,
                "name" => $item['name'],
                "item" => rtrim($baseUrl, '/') . '/' . ltrim($item['url'], '/')
            ];
        }
        
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "BreadcrumbList",
            "itemListElement" => $listItems
        ];
        
        if (!empty($pageUrl)) {
            $schema['@id'] = $pageUrl . '#breadcrumb';
        }
        
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
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
        
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    
    /**
     * Generate ImageObject schema
     */
    public static function generateImage($data) {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "ImageObject",
            "url" => $data['url'],
            "contentUrl" => $data['url']
        ];
        
        if (!empty($data['caption'])) {
            $schema['caption'] = $data['caption'];
        }
        
        if (!empty($data['width'])) {
            $schema['width'] = $data['width'];
        }
        
        if (!empty($data['height'])) {
            $schema['height'] = $data['height'];
        }
        
        if (!empty($data['description'])) {
            $schema['description'] = $data['description'];
        }
        
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    
    /**
     * Generate WebSite schema with search action
     */
    public static function generateWebsite($data) {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "WebSite",
            "name" => $data['name'],
            "url" => $data['url']
        ];
        
        if (!empty($data['description'])) {
            $schema['description'] = $data['description'];
        }
        
        // Add search action if search URL provided
        if (!empty($data['search_url'])) {
            $schema['potentialAction'] = [
                "@type" => "SearchAction",
                "target" => [
                    "@type" => "EntryPoint",
                    "urlTemplate" => $data['search_url'] . "?q={search_term_string}"
                ],
                "query-input" => "required name=search_term_string"
            ];
        }
        
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    
    /**
     * Merge multiple schemas into a single @graph structure
     * Removes empty schemas and validates @id references
     */
    public static function mergeSchemas($schemas) {
        $graph = [];
        $ids = [];
        
        // Collect all schemas and their @ids
        foreach ($schemas as $schemaJson) {
            if (empty($schemaJson)) continue;
            
            $decoded = json_decode($schemaJson, true);
            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) continue;
            
            // Remove @context from individual schemas (will be in root)
            unset($decoded['@context']);
            
            // Track @ids
            if (!empty($decoded['@id'])) {
                $ids[] = $decoded['@id'];
            }
            
            $graph[] = $decoded;
        }
        
        if (empty($graph)) return '';
        
        $merged = [
            '@context' => 'https://schema.org',
            '@graph' => $graph
        ];
        
        return json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}