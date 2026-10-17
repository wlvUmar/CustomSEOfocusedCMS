<?php
// path: ./models/ai/SkillLoader.php
// AGENTS.md + on-demand skills for AI Studio.

class SkillLoader {

    private const AGENTS_CANDIDATES = ['/AGENTS.md', '/.agents/AGENTS.md'];
    private const SKILLS_DIR = '/prompts/skills';
    private const MAX_CHARS = 4000;

    public static function agentsBlock(): string {
        foreach (self::AGENTS_CANDIDATES as $rel) {
            $path = BASE_PATH . $rel;
            $real = realpath($path);
            if ($real === false || !is_file($real)) continue;
            if (strpos($real, realpath(BASE_PATH)) !== 0) continue;
            if (@filesize($real) > 64 * 1024) continue;
            $text = @file_get_contents($real);
            if (!is_string($text) || trim($text) === '') continue;
            $text = str_replace(["\r\n", "\r"], "\n", trim($text));
            if (mb_strlen($text) > self::MAX_CHARS) $text = mb_substr($text, 0, self::MAX_CHARS) . "\n…[truncated]";
            return "\n\n═══ AGENTS.md (repo rules — follow unless user overrides) ═══\n" . $text;
        }
        return '';
    }

    public static function listSkills(): array {
        $dir = BASE_PATH . self::SKILLS_DIR;
        if (!is_dir($dir)) return [];
        $out = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            if (!preg_match('/^[a-z0-9_-]{1,40}$/', $name)) continue;
            $file = $dir . '/' . $name . '/SKILL.md';
            if (!is_file($file)) continue;
            $first = '';
            $fh = @fopen($file, 'r');
            if ($fh) {
                $head = @fread($fh, 600);
                @fclose($fh);
                if (is_string($head)) {
                    foreach (explode("\n", trim($head)) as $line) {
                        $line = trim(ltrim(trim($line), '# '));
                        if ($line !== '') { $first = mb_substr($line, 0, 120); break; }
                    }
                }
            }
            $out[] = ['name' => $name, 'description' => $first];
        }
        return $out;
    }

    public static function readSkill(string $name): string {
        if (!preg_match('/^[a-z0-9_-]{1,40}$/', $name)) throw new InvalidArgumentException('Unknown skill — call list_skills.');
        $file = BASE_PATH . self::SKILLS_DIR . '/' . $name . '/SKILL.md';
        $real = realpath($file);
        if ($real === false || !is_file($real)) throw new InvalidArgumentException('Unknown skill — call list_skills.');
        if (strpos($real, realpath(BASE_PATH . self::SKILLS_DIR)) !== 0) throw new InvalidArgumentException('Unknown skill.');
        if (@filesize($real) > 32 * 1024) throw new InvalidArgumentException('Skill file too large.');
        $text = @file_get_contents($real);
        if (!is_string($text) || trim($text) === '') throw new InvalidArgumentException('Skill is empty.');
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if (mb_strlen($text) > self::MAX_CHARS) $text = mb_substr($text, 0, self::MAX_CHARS) . "\n…[truncated]";
        return $text;
    }
}
