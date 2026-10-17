<?php
// path: ./models/jsonld/FaqBuilder.php
// Single owner of FAQPage nodes.

require_once BASE_PATH . '/models/JsonLdBase.php';

class FaqBuilder {

    public static function build($faqs, $lang, $pageUrl = '') {
        if (empty($faqs)) return null;
        $faqItems = array();
        foreach ($faqs as $faq) {
            $faqItems[] = array(
                '@type' => 'Question',
                'name' => isset($faq["question_$lang"]) ? $faq["question_$lang"] : '',
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => isset($faq["answer_$lang"]) ? $faq["answer_$lang"] : ''
                )
            );
        }
        $schema = array('@type' => 'FAQPage');
        if (!empty($pageUrl)) {
            $schema['@id'] = $pageUrl . '#faq';
        }
        $schema['inLanguage'] = JsonLdBase::inLanguage($lang);
        $schema['mainEntity'] = $faqItems;
        return $schema;
    }

    public static function withContext($faqs, $lang, $pageUrl = '') {
        $schema = self::build($faqs, $lang, $pageUrl);
        if ($schema === null) return null;
        return array_merge(array('@context' => 'https://schema.org'), $schema);
    }
}
