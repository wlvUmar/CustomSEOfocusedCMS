<?php
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function getCurrentLanguage() {
    return $_SESSION['language'] ?? DEFAULT_LANGUAGE;
}

function setLanguage($lang) {
    if (in_array($lang, SUPPORTED_LANGUAGES)) {
        $_SESSION['language'] = $lang;
    }
}

function replacePlaceholders($text, $page, $seo) {
    $lang = getCurrentLanguage();
    
    $replacements = [
        '{{page.title}}' => $page["title_$lang"] ?? '',
        '{{global.phone}}' => $seo['phone'] ?? '',
        '{{global.email}}' => $seo['email'] ?? '',
        '{{global.address}}' => $seo["address_$lang"] ?? '',
        '{{global.working_hours}}' => $seo["working_hours_$lang"] ?? '',
        '{{global.site_name}}' => $seo["site_name_$lang"] ?? '',
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $text);
}

function trackVisit($slug, $language) {
    try {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        
        $sql = "INSERT INTO analytics (page_slug, language, visits, date) 
                VALUES (?, ?, 1, ?) 
                ON DUPLICATE KEY UPDATE visits = visits + 1";
        
        $db->query($sql, [$slug, $language, $date]);
    } catch (Exception $e) {
        // Silent fail
    }
}