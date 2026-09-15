<?php
// path: ./models/ai/PromptLoader.php
// Loads model prompts from prompts/*.md (fail loudly when missing).

class PromptLoader {

    private const FILES = [
        'ai-studio-plan' => 'prompts/ai-studio-plan.md',
        'ai-studio-build' => 'prompts/ai-studio-build.md',
        'page-editor-generate' => 'prompts/page-editor-generate.md',
        'page-editor-chat' => 'prompts/page-editor-chat.md',
        'summarizer-system' => 'prompts/summarizer-system.md',
        'summarizer-user' => 'prompts/summarizer-user.md',
    ];

    private static array $cache = [];

    public static function load(string $name): string {
        if (isset(self::$cache[$name])) return self::$cache[$name];
        if (!isset(self::FILES[$name])) throw new RuntimeException("Unknown prompt '{$name}'");
        $path = BASE_PATH . '/' . self::FILES[$name];
        $real = realpath($path);
        if ($real === false || strpos($real, realpath(BASE_PATH)) !== 0 || !is_file($real)) {
            throw new RuntimeException("Prompt file missing: " . self::FILES[$name]);
        }
        $text = file_get_contents($real);
        if ($text === false) throw new RuntimeException("Prompt file unreadable: " . self::FILES[$name]);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $text = trim($text);
        self::$cache[$name] = $text;
        return $text;
    }

    public static function render(string $name, array $vars): string {
        $text = self::load($name);
        $text = preg_replace_callback(
            '/\[\[#if (\w+)\]\](.*?)\[\[\/if\]\]/s',
            function ($m) use ($vars) {
                return !empty($vars[$m[1]]) ? $m[2] : '';
            },
            $text
        );
        foreach ($vars as $k => $v) {
            if (!is_string($v) && !is_numeric($v)) continue;
            $text = str_replace('[[' . $k . ']]', (string)$v, $text);
        }
        if (preg_match('/\[\[.+?\]\]/', $text, $m)) {
            throw new RuntimeException("Prompt '{$name}' has unreplaced placeholder {$m[0]}");
        }
        return $text;
    }

    public static function fileMeta(string $name): array {
        if (!isset(self::FILES[$name])) return [];
        $path = BASE_PATH . '/' . self::FILES[$name];
        return ['file' => self::FILES[$name], 'mtime' => is_file($path) ? filemtime($path) : null];
    }
}
