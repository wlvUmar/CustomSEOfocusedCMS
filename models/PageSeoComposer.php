<?php
// path: ./models/PageSeoComposer.php

class PageSeoComposer {
    private $pageModel;
    private $contactOverrideModel;

    public function __construct(Page $pageModel, PageContactOverride $contactOverrideModel) {
        $this->pageModel = $pageModel;
        $this->contactOverrideModel = $contactOverrideModel;
    }

    public function compose(array $page, string $slug, string $lang, array $seoSettings, array $faqs, array $queryParams = []): array {
        $contactUi = $this->buildContactUi($page['id'], $lang, $seoSettings, $queryParams);

        $heroImageSchemaJson = $this->generateHeroImageSchema($page['id'], $lang);
        $heroImageSchemaArray = !empty($heroImageSchemaJson) ? json_decode($heroImageSchemaJson, true) : null;
        $primaryImageId = $heroImageSchemaArray ? ($heroImageSchemaArray['@id'] ?? null) : null;

        if ($heroImageSchemaArray && empty($page['og_image'])) {
            $page['og_image'] = $heroImageSchemaArray['url'] ?? $heroImageSchemaArray['contentUrl'] ?? '';
        }

        $pageForSchema = $page;
        $pageForSchema["title_$lang"] = replacePlaceholders($page["title_$lang"], $page, $seoSettings);
        $pageForSchema["meta_description_$lang"] = replacePlaceholders($page["meta_description_$lang"] ?? '', $page, $seoSettings);

        require_once BASE_PATH . '/models/GlobalJsonLdGenerator.php';
        $baseUrl = GlobalJsonLdGenerator::getBaseUrl();
        $orgSchema = GlobalJsonLdGenerator::generateOrganizationSchema($seoSettings, $lang, $baseUrl, $page['id']);
        $webSiteSchema = GlobalJsonLdGenerator::generateWebSiteSchema($seoSettings, $lang, $baseUrl);

        $faqId = null;
        $faqSchemaArray = null;
        if (!empty($faqs)) {
            require_once BASE_PATH . '/models/JsonLdGenerator.php';
            $pageUrl = canonicalUrlForPage($page['slug'], $lang);
            // FIX: generateFAQSchema is a helper function, not a class method
            $faqSchemaJson = generateFAQSchema($faqs, $lang, $pageUrl);
            $faqSchemaArray = json_decode($faqSchemaJson, true);
            if ($faqSchemaArray) {
                unset($faqSchemaArray['@context']);
                $faqId = $faqSchemaArray['@id'] ?? ($pageUrl . '#faq');
                $faqSchemaArray['@id'] = $faqId;
            }
        }

        $webPageSchema = GlobalJsonLdGenerator::generateWebPageSchema(
            $pageForSchema,
            $lang,
            $baseUrl,
            $primaryImageId,
            $faqId
        );
        $pageCanonicalUrl = $webPageSchema['url'];

        $breadcrumbs = $this->getBreadcrumbData($page, $lang, $baseUrl, $seoSettings);
        $breadcrumbSchema = null;
        if (!empty($breadcrumbs)) {
            $items = [];
            foreach ($breadcrumbs as $index => $item) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url']
                ];
            }
            $breadcrumbSchema = [
                '@type' => 'BreadcrumbList',
                '@id' => $pageCanonicalUrl . '#breadcrumb',
                'itemListElement' => $items
            ];

            $webPageSchema['breadcrumb'] = [
                '@id' => $pageCanonicalUrl . '#breadcrumb'
            ];
        }

        $graph = [$orgSchema, $webSiteSchema];

        $pageDepth = (int)($page['depth'] ?? 0);
        if (!in_array($slug, ['home', 'main']) && $pageDepth <= 1) {
            $parentService = null;
            if ($pageDepth === 1) {
                $parentPage = $this->pageModel->getParent($page['id']);
                if ($parentPage && !in_array($parentPage['slug'], ['home', 'main'], true)) {
                    $parentUrl = canonicalUrlForPage($parentPage['slug'], $lang);
                    $parentService = ['@id' => $parentUrl . '#service'];
                }
            }

            $serviceSchema = GlobalJsonLdGenerator::generateServiceSchema(
                $pageForSchema,
                $lang,
                $baseUrl,
                $seoSettings,
                $pageCanonicalUrl,
                $parentService
            );

            $webPageSchema['mainEntity'] = ['@id' => $serviceSchema['@id']];
            $graph[] = $serviceSchema;
        }

        $graph[] = $webPageSchema;

        if ($breadcrumbSchema) {
            $graph[] = $breadcrumbSchema;
        }

        if ($heroImageSchemaArray) {
            unset($heroImageSchemaArray['@context']);
            $graph[] = $heroImageSchemaArray;
        }

        if ($faqSchemaArray) {
            $graph[] = $faqSchemaArray;
        }

        $mediaImageSchemas = GlobalJsonLdGenerator::generateMediaImageSchemas($page['id'], $lang, $baseUrl);
        foreach ($mediaImageSchemas as $imageSchema) {
            $graph[] = $imageSchema;
        }

        $sitelinksSchemas = GlobalJsonLdGenerator::generateSitelinksSchema($page['id'], $lang, $baseUrl, $pageCanonicalUrl);
        if ($sitelinksSchemas) {
            foreach ($sitelinksSchemas as $sitelinkSchema) {
                $graph[] = $sitelinkSchema;
            }
        }

        $sitewideSchema = [
            '@context' => 'https://schema.org',
            '@graph' => $graph
        ];

        return [
            'page' => $page,
            'contactUi' => $contactUi,
            'sitewideSchema' => $sitewideSchema
        ];
    }

    public function buildContactUi($pageId, $lang, $seoSettings, array $queryParams = []) {
        $override = $this->findInheritedContactOverride($pageId);

        $defaultPhone = $seoSettings['phone'] ?? '';
        $defaultEmail = $seoSettings['email'] ?? '';
        $defaultTelegramUrl = 'https://t.me/azimjumayev';
        $defaultInstagramUrl = trim((string)($seoSettings['social_instagram'] ?? ''));

        $phone = $defaultPhone;
        $email = $defaultEmail;

        $paramFloatingType = null;
        $paramFloatingUrl = null;
        if (!empty($queryParams['instagram'])) {
            $paramFloatingType = 'instagram';
            $paramFloatingUrl = trim((string)$queryParams['instagram']);
        } elseif (!empty($queryParams['telegram'])) {
            $paramFloatingType = 'telegram';
            $paramFloatingUrl = trim((string)$queryParams['telegram']);
        }

        if ($override) {
            $overridePhone = trim((string)($override['phone'] ?? ''));
            $overrideEmail = trim((string)($override['email'] ?? ''));
            if ($overridePhone !== '') {
                $phone = $overridePhone;
            }
            if ($overrideEmail !== '') {
                $email = $overrideEmail;
            }
        }

        $floatingType = 'telegram';
        $floatingUrl = $defaultTelegramUrl;

        if ($override) {
            $candidateType = trim((string)($override['floating_cta_type'] ?? 'default'));
            $candidateUrl = trim((string)($override['floating_cta_url'] ?? ''));

            if ($candidateType === 'none') {
                $floatingType = 'none';
                $floatingUrl = '';
            } elseif ($candidateType === 'instagram') {
                $floatingType = 'instagram';
                $floatingUrl = $candidateUrl !== '' ? $candidateUrl : $defaultInstagramUrl;
            } elseif ($candidateType === 'custom') {
                $floatingType = 'custom';
                $floatingUrl = $candidateUrl;
            } elseif ($candidateType === 'telegram') {
                $floatingType = 'telegram';
                $floatingUrl = $candidateUrl !== '' ? $candidateUrl : $defaultTelegramUrl;
            }
        }

        if ($floatingType !== 'none' && $floatingUrl === '') {
            $floatingType = 'telegram';
            $floatingUrl = $defaultTelegramUrl;
        }

        if (!empty($paramFloatingType)) {
            if ($paramFloatingType === 'instagram') {
                $floatingType = 'instagram';
                $floatingUrl = $paramFloatingUrl !== '' ? $paramFloatingUrl : $defaultInstagramUrl;
            } elseif ($paramFloatingType === 'telegram') {
                $floatingType = 'telegram';
                $floatingUrl = $paramFloatingUrl !== '' ? $paramFloatingUrl : $defaultTelegramUrl;
            }
        }

        if (!empty($queryParams['phone'])) {
            $urlPhone = $this->sanitizePhone($queryParams['phone']);
            if ($urlPhone !== '') {
                $phone = $urlPhone;
            }
        }

        $defaultLabel = $lang === 'ru' ? 'Написать в Telegram' : 'Telegramda yozish';
        if ($floatingType === 'instagram') {
            $defaultLabel = $lang === 'ru' ? 'Написать в Instagram' : 'Instagramda yozish';
        } elseif ($floatingType === 'custom') {
            $defaultLabel = $lang === 'ru' ? 'Открыть ссылку' : 'Havolani ochish';
        } elseif ($floatingType === 'phone') {
            $defaultLabel = $lang === 'ru' ? 'Позвонить' : 'Qo\'ng\'iroq qilish';
        }

        $overrideLabel = '';
        if ($override) {
            $overrideLabel = trim((string)($override["floating_cta_label_$lang"] ?? ''));
        }

        $floatingClass = 'floating-telegram';
        if ($floatingType === 'instagram') {
            $floatingClass .= ' floating-telegram--instagram';
        } elseif ($floatingType === 'custom') {
            $floatingClass .= ' floating-telegram--custom';
        } elseif ($floatingType === 'phone') {
            $floatingClass = 'floating-call';
        }

        return [
            'phone' => $phone,
            'email' => $email,
            'floating_cta' => [
                'type' => $floatingType,
                'url' => $floatingUrl,
                'label' => $overrideLabel !== '' ? $overrideLabel : $defaultLabel,
                'class' => $floatingClass
            ]
        ];
    }

    private function findInheritedContactOverride($pageId) {
        $currentPageId = (int)$pageId;
        $visited = [];
        $maxDepth = 20;
        $depth = 0;

        while ($currentPageId > 0 && $depth < $maxDepth) {
            if (isset($visited[$currentPageId])) {
                break;
            }
            $visited[$currentPageId] = true;

            $override = $this->contactOverrideModel->getByPageId($currentPageId);
            if ($override) {
                return $override;
            }

            $page = $this->pageModel->getById($currentPageId);
            if (!$page || empty($page['parent_id'])) {
                break;
            }

            $currentPageId = (int)$page['parent_id'];
            $depth++;
        }

        return null;
    }

    private function sanitizePhone($phone) {
        $phone = (string)($phone ?? '');
        $phone = trim($phone);
        $phone = str_replace(' ', '', $phone);
        $phone = preg_replace('/[^\d+]/', '', $phone);

        if (!empty($phone) && strpos($phone, '+') === false) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    public function generateHeroImageSchema($pageId, $lang) {
        require_once BASE_PATH . '/models/PageMedia.php';
        $pageMediaModel = new PageMedia();
        $heroMedia = $pageMediaModel->getPageMedia($pageId, 'hero');

        if (empty($heroMedia)) {
            return '';
        }

        $page = $this->pageModel->getById($pageId);
        $baseUrl = siteBaseUrl();
        $canonicalUrl = canonicalUrlForPage($page['slug'] ?? '', $lang);

        $heroImage = $heroMedia[0];
        $heroImageUrl = absoluteUrl('/uploads/' . $heroImage['filename'], $baseUrl);

        $imageData = [
            'id' => $canonicalUrl . '#primaryimage',
            'url' => $heroImageUrl,
            'contentUrl' => $heroImageUrl,
            'name' => $heroImage["alt_text_$lang"] ?? $page["title_$lang"] ?? '',
            'caption' => $heroImage["caption_$lang"] ?? '',
            'description' => $heroImage["alt_text_$lang"] ?? $page["meta_description_$lang"] ?? ''
        ];

        if (!empty($heroImage['width'])) {
            $imageData['width'] = $heroImage['width'];
        }
        if (!empty($heroImage['height'])) {
            $imageData['height'] = $heroImage['height'];
        }
        if (empty($imageData['width']) || empty($imageData['height'])) {
            $dims = getPublicImageDimensions($heroImageUrl);
            if ($dims) {
                $imageData['width'] = $dims['width'];
                $imageData['height'] = $dims['height'];
            }
        }

        return JsonLdGenerator::generateImage($imageData);
    }

    public function getBreadcrumbData($page, $lang, $baseUrl, $seoSettings) {
        $breadcrumbs = [
            ['name' => $seoSettings["site_name_$lang"] ?? 'Home', 'url' => $baseUrl . '/']
        ];

        if (in_array($page['slug'], ['home', 'main'])) {
            return [];
        }

        $breadcrumbPages = $this->pageModel->getBreadcrumbs($page['id']);

        foreach ($breadcrumbPages as $bPage) {
            if (in_array($bPage['slug'], ['home', 'main'])) continue;

            $url = $baseUrl . '/' . $bPage['slug'];
            if ($lang !== DEFAULT_LANGUAGE) {
                $url .= '/' . $lang;
            }

            $rawName = replacePlaceholders($bPage["title_$lang"], $bPage, $seoSettings);
            $name = $this->cleanBreadcrumbLabel($rawName, $lang);

            $breadcrumbs[] = [
                'name' => $name,
                'url' => $url
            ];
        }

        return $breadcrumbs;
    }

    public function cleanBreadcrumbLabel($text, $lang) {
        $text = trim(strip_tags((string)($text ?? '')));
        $text = preg_replace('/\s{2,}/', ' ', $text);

        if ($lang === 'ru') {
            if (preg_match('/(продать|скупка|выкуп)\\s+([а-яёa-z0-9\\s\\-]{2,60})/ui', $text, $m)) {
                // keep the original verb: "Скупка телевизоров" is grammatical, "Продать телевизоров" is not
                $verb = mb_strtolower($m[1]);
                $appliance = trim($m[2]);
                $appliance = preg_replace('/\\s+(в\\s+ташкенте|в\\s+узбекистане|быстро|срочно|недорого|дорого).*$/ui', '', $appliance);
                $appliance = preg_split('/\\s*[\\|\\-—:]+\\s*/u', $appliance)[0] ?? $appliance;
                $appliance = trim($appliance);
                if ($appliance !== '') return mb_strtoupper(mb_substr($verb, 0, 1)) . mb_substr($verb, 1) . ' ' . $appliance;
            }
        }

        $parts = preg_split('/\\s*[\\|\\-—:]+\\s*/u', $text);
        if (is_array($parts) && !empty($parts[0])) {
            $text = trim($parts[0]);
        }

        if (mb_strlen($text) > 70) {
            $text = trim(mb_substr($text, 0, 67)) . '...';
        }

        return $text;
    }
}
