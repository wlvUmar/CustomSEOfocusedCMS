<?php
// path: ./models/jsonld/ImageBuilder.php
// Single owner of ImageObject nodes.

require_once BASE_PATH . '/models/JsonLdBase.php';

class ImageBuilder {

    public static function build($data) {
        $schema = array(
            '@type' => 'ImageObject',
            'url' => $data['url'],
            'contentUrl' => isset($data['contentUrl']) ? $data['contentUrl'] : $data['url']
        );

        if (!empty($data['id'])) $schema['@id'] = $data['id'];
        if (!empty($data['caption'])) $schema['caption'] = $data['caption'];
        if (!empty($data['width'])) $schema['width'] = $data['width'];
        if (!empty($data['height'])) $schema['height'] = $data['height'];
        if (!empty($data['description'])) $schema['description'] = $data['description'];
        if (!empty($data['name'])) $schema['name'] = $data['name'];

        return $schema;
    }

    public static function buildPrimary($imageUrl, $nodeId, $caption = '', $description = '') {
        $schema = array(
            '@type' => 'ImageObject',
            '@id' => $nodeId,
            'url' => $imageUrl,
            'contentUrl' => $imageUrl
        );
        if ($caption !== '') $schema['caption'] = $caption;
        if ($description !== '') $schema['description'] = $description;
        $dims = JsonLdBase::imageDims($imageUrl);
        if ($dims) {
            $schema['width'] = $dims['width'];
            $schema['height'] = $dims['height'];
        }
        return $schema;
    }

    public static function buildFirstPageImage($pageId, $baseUrl) {
        if (empty($pageId)) return null;
        require_once BASE_PATH . '/models/PageMedia.php';
        $pageMediaModel = new PageMedia();
        $mediaItems = $pageMediaModel->getPageMedia($pageId);
        if (empty($mediaItems)) return null;
        $firstMedia = reset($mediaItems);
        if (empty($firstMedia['filename'])) return null;

        $imageUrl = absoluteUrl('/uploads/' . $firstMedia['filename'], $baseUrl);
        $imageObject = array('@type' => 'ImageObject', 'url' => $imageUrl, 'contentUrl' => $imageUrl);
        $dims = JsonLdBase::imageDims($imageUrl);
        if ($dims) {
            $imageObject['width'] = $dims['width'];
            $imageObject['height'] = $dims['height'];
        }
        $altText = isset($firstMedia['alt_text_ru']) ? $firstMedia['alt_text_ru'] : (isset($firstMedia['alt_text_uz']) ? $firstMedia['alt_text_uz'] : '');
        if (!empty($altText)) $imageObject['description'] = $altText;
        return $imageObject;
    }

    public static function buildMediaList($pageId, $lang, $baseUrl) {
        require_once BASE_PATH . '/models/PageMedia.php';
        $pageMediaModel = new PageMedia();
        $mediaItems = $pageMediaModel->getPageMedia($pageId);

        $imageSchemas = array();
        $baseIndex = 0;
        foreach ($mediaItems as $media) {
            if (empty($media['filename'])) continue;
            $imageUrl = absoluteUrl('/uploads/' . $media['filename'], $baseUrl);
            $caption = isset($media["caption_$lang"]) ? $media["caption_$lang"] : '';
            $altText = isset($media["alt_text_$lang"]) ? $media["alt_text_$lang"] : '';

            $schema = array(
                '@type' => 'ImageObject',
                '@id' => $baseUrl . '/uploads/' . $media['filename'] . '#image-' . ($baseIndex + 1),
                'url' => $imageUrl,
                'contentUrl' => $imageUrl
            );
            if (!empty($caption)) $schema['caption'] = $caption;
            if (!empty($altText)) {
                $schema['description'] = $altText;
            } elseif (!empty($caption)) {
                $schema['description'] = $caption;
            }
            $dims = JsonLdBase::imageDims($imageUrl);
            if ($dims) {
                $schema['width'] = $dims['width'];
                $schema['height'] = $dims['height'];
            }
            $imageSchemas[] = $schema;
            $baseIndex++;
        }
        return $imageSchemas;
    }

    public static function buildArticlePrimary($article, $lang, $articleUrl, $baseUrl, $seo) {
        if (!empty($article['image'])) {
            $imageUrl = absoluteUrl('/uploads/' . $article['image'], $baseUrl);
        } else {
            $imageUrl = absoluteUrl(isset($seo['org_logo']) ? $seo['org_logo'] : '/css/logo.png', $baseUrl);
        }
        $caption = isset($article["title_$lang"]) ? $article["title_$lang"] : '';
        $description = isset($article["meta_description_$lang"]) ? $article["meta_description_$lang"] : (isset($article["excerpt_$lang"]) ? $article["excerpt_$lang"] : '');
        return self::buildPrimary($imageUrl, $articleUrl . '#primaryimage', $caption, $description);
    }
}
