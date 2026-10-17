<?php
// models/InternalLinkScorer.php
class InternalLinkScorer {
    public static function findRelatedPagesSmart($sourcePage, $allPages, $limit = 5) {
        $scores = [];
        $sourceTokens = self::extractTokens($sourcePage);

        foreach ($allPages as $targetPage) {
            if ($targetPage['id'] == $sourcePage['id']) continue;

            $targetTokens = self::extractTokens($targetPage);
            $score = self::calculateRelevanceScore($sourceTokens, $targetTokens);

            if ($score > 0) {
                $scores[$targetPage['id']] = $score;
            }
        }

        arsort($scores);

        return array_slice(array_keys($scores), 0, $limit);
    }

    private static function extractTokens($page) {
        $tokens = [];
        self::addTokens($tokens, $page['title_ru'], 3);

        if (!empty($page['meta_keywords_ru'])) {
            self::addTokens($tokens, $page['meta_keywords_ru'], 5);
        }

        $content = strip_tags($page['content_ru']);
        $content = substr($content, 0, 1000);
        self::addTokens($tokens, $content, 1);

        return $tokens;
    }

    private static function addTokens(&$tokens, $text, $weight) {
        if (empty($text)) return;

        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $words = preg_split('/\s+/', $text);

        $stopWords = ['the', 'and', 'for', 'that', 'this', 'with', 'from', 'but', 'not', 'are', 'was', 'were',
                      'ва', 'билан', 'учун', 'бу', 'шу', 'ҳам', 'деб', 'ки',
                      'и', 'в', 'во', 'не', 'что', 'он', 'на', 'я', 'с', 'со', 'как', 'а', 'то', 'все', 'она', 'так', 'его', 'но', 'да', 'ты', 'к', 'у', 'же', 'вы', 'за', 'бы', 'по', 'только', 'ее', 'мне', 'было', 'вот', 'от', 'меня', 'еще', 'нет', 'о', 'из', 'ему', 'теперь', 'когда', 'даже', 'ну', 'вдруг', 'ли', 'если', 'уже', 'или', 'ни', 'быть', 'был', 'него', 'до', 'вас', 'нибудь', 'опять', 'уж', 'вам', 'ведь', 'там', 'потом', 'себя', 'ничего', 'ей', 'может', 'они', 'тут', 'где', 'есть', 'надо', 'ней', 'для', 'мы', 'тебя', 'их', 'чем', 'была', 'сам', 'чтоб', 'без', 'будто', 'чего', 'раз', 'тоже', 'себе', 'под', 'будет', 'ж', 'тогда', 'кто', 'этот', 'того', 'потому', 'этого', 'какой', 'совсем', 'ним', 'здесь', 'этом', 'один', 'почти', 'мой', 'тем', 'чтобы', 'нее', 'сейчас', 'были', 'куда', 'зачем', 'всех', 'никогда', 'можно', 'при', 'наконец', 'два', 'об', 'другой', 'хоть', 'после', 'над', 'больше', 'тот', 'через', 'эти', 'нас', 'про', 'всего', 'них', 'какая', 'много', 'разве', 'три', 'эту', 'моя', 'впрочем', 'хорошо', 'свою', 'этой', 'перед', 'иногда', 'лучше', 'чуть', 'том', 'нельзя', 'такой', 'им', 'более', 'всегда', 'конечно', 'всю', 'между'];

        foreach ($words as $word) {
            if (mb_strlen($word) < 3) continue;
            if (in_array($word, $stopWords)) continue;

            if (!isset($tokens[$word])) {
                $tokens[$word] = 0;
            }
            $tokens[$word] += $weight;
        }
    }

    private static function calculateRelevanceScore($sourceTokens, $targetTokens) {
        $score = 0;
        foreach ($sourceTokens as $word => $weight) {
            if (isset($targetTokens[$word])) {
                $score += $weight * $targetTokens[$word];
            }
        }
        return $score;
    }

    public static function extractKeywords($text) {
        $text = strip_tags($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $words = preg_split('/\s+/', mb_strtolower($text));
        $words = array_filter($words, function($w) { return mb_strlen($w) > 4; });
        return array_slice(array_unique($words), 0, 10);
    }
}
