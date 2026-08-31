<?php
// path: ./models/ai/tools/PageSectionsHelper.php
// Extracted from PageTools God object (02-architecture #3) — section parsing + style helpers.
// PageTools remains facade for BC; new code should call this helper directly.

class PageSectionsHelper {

    public static function splitIntoSections(string $html): array {
        // Mirrors PageTools::splitIntoSections original to keep behavior identical
        $parts = preg_split('/(<!--.*?-->)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) return [['name' => 'Section', 'text' => $html]];
        $sections = [];
        $currentName = 'Top of document';
        $buffer = '';
        foreach ($parts as $part) {
            if (preg_match('/^<!--\s*(.*?)\s*-->$/s', $part, $m)) {
                if (trim($buffer) !== '') $sections[] = ['name' => $currentName, 'text' => $buffer];
                $currentName = trim($m[1]) !== '' ? trim($m[1]) : $currentName;
                $buffer = $part . "\n";
            } else {
                $buffer .= $part;
            }
        }
        if (trim($buffer) !== '') $sections[] = ['name' => $currentName, 'text' => $buffer];
        return $sections;
    }

    public static function rebuildContentFromSections(array $sections): string {
        $out = '';
        foreach ($sections as $i => $s) {
            if ($i > 0) $out .= "\n\n";
            $out .= rtrim($s['text']);
        }
        return $out;
    }

    public static function findSectionIndex(array $sections, string $ref): ?int {
        if (ctype_digit($ref)) {
            $i = (int)$ref;
            return isset($sections[$i]) ? $i : null;
        }
        foreach ($sections as $i => $s) {
            if (mb_strtolower($s['name']) === mb_strtolower($ref)) return $i;
        }
        foreach ($sections as $i => $s) {
            $genId = $i . ':' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($s['name']));
            if ($genId === $ref) return $i;
        }
        return null;
    }

    public static function mergeStyleIntoTag(string $tagHtml, string $styleDecl): string {
        $styleDecl = trim(trim($styleDecl), ';');
        if ($styleDecl === '') return $tagHtml;
        if (!str_ends_with($styleDecl, ';')) $styleDecl .= ';';
        // 03-code-bugs #10: avoid double-escape accumulating &quot; literals — decode then single-encode
        // Added single-quoted and word unquoted style handling (06-09) + word-boundary safe regex
        $decode = fn(string $s): string => html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/\sstyle\s*=\s*"([^"]*)"/i', $tagHtml, $m)) {
            $existing = $decode(rtrim(trim($m[1]), ';'));
            $merged = $existing !== '' ? $existing . '; ' . $styleDecl : $styleDecl;
            $mergedAttr = htmlspecialchars($merged, ENT_COMPAT, 'UTF-8');
            return preg_replace('/\sstyle\s*=\s*"[^"]*"/i', ' style="' . $mergedAttr . '"', $tagHtml, 1);
        }
        if (preg_match('/\sstyle\s*=\s*\'([^\']*)\'/i', $tagHtml, $m)) {
            $existing = $decode(rtrim(trim($m[1]), ';'));
            $merged = $existing !== '' ? $existing . '; ' . $styleDecl : $styleDecl;
            $mergedAttr = htmlspecialchars($merged, ENT_COMPAT, 'UTF-8');
            return preg_replace("/\sstyle\s*=\s*'[^']*'/i", " style=\"" . $mergedAttr . "\"", $tagHtml, 1);
        }
        // Unquoted style=foo (rare but regex previously failed)
        if (preg_match('/\sstyle\s*=\s*([^\s"\'>]+)/i', $tagHtml, $m)) {
            $existing = $decode(rtrim(trim($m[1]), ';'));
            $merged = $existing !== '' ? $existing . '; ' . $styleDecl : $styleDecl;
            $mergedAttr = htmlspecialchars($merged, ENT_COMPAT, 'UTF-8');
            return preg_replace('/\sstyle\s*=\s*[^\s"\'>]+/i', ' style="' . $mergedAttr . '"', $tagHtml, 1);
        }
        $attr = htmlspecialchars($styleDecl, ENT_COMPAT, 'UTF-8');
        // Handle self-closing /> as well
        if (preg_match('/\/\s*>$/', $tagHtml)) {
            return preg_replace('/\/\s*>$/', ' style="' . $attr . '" />', $tagHtml, 1);
        }
        return preg_replace('/\s*>$/', ' style="' . $attr . '">', $tagHtml, 1) ?? (rtrim($tagHtml, '>') . ' style="' . $attr . '">');
    }

    public const DESIGN_TOKENS = ['--teal','--teal-dark','--orange','--green','--ink','--ink-soft','--muted','--surface','--surface-2','--border','--max-w','--px','--section-gap','--ease','--dur','--teal-light','--orange-light','--green-dark','--orange-dark','--tg','--success'];

    public static function expandStyleTokens(string $style): string {
        // 06-09: block arbitrary CSS vectors while allowing allowlisted tokens + safe values
        $blocked = ['javascript:', 'vbscript:', 'data:text/html', 'expression(', '@import', 'behavior', 'url(javascript', '-moz-binding'];
        $lower = strtolower($style);
        foreach ($blocked as $b) {
            if (str_contains($lower, $b)) throw new InvalidArgumentException('Blocked CSS vector "' . $b . '" — use design tokens var(--teal etc.)');
        }
        // Allow only safe characters in style string (prevent injection of </style> etc.)
        if (preg_match('/[<>]/', $style)) throw new InvalidArgumentException('Style must not contain < or >');
        $shorthandMap = ['bg'=>'background','text'=>'color','border'=>'border-color'];
        $allowed = self::DESIGN_TOKENS;
        $parts = array_filter(array_map('trim', explode(';', $style)), fn($v) => $v !== '');
        $out = [];
        foreach ($parts as $part) {
            if (strpos($part, ':') === false) { $out[] = $part; continue; }
            [$prop, $val] = array_map('trim', explode(':', $part, 2));
            // prop allowlist — basic CSS props only
            if (!preg_match('/^[a-z-]+$/i', $prop)) throw new InvalidArgumentException('Invalid CSS property "' . $prop . '"');
            if (isset($shorthandMap[strtolower($prop)])) $prop = $shorthandMap[strtolower($prop)];
            if (preg_match_all('/var\(\s*(--[\w-]+)\s*\)/i', $val, $vm)) {
                foreach ($vm[1] as $tok) if (!in_array($tok, $allowed, true)) throw new InvalidArgumentException('Unknown design token "' . $tok . '" — allowed: ' . implode(', ', $allowed));
                $out[] = $prop . ':' . $val; continue;
            }
            // Block url() with non-http values if suspicious
            if (preg_match('/url\s*\(/i', $val) && !preg_match('/url\s*\(\s*["\']?\s*(https?:|\/|data:image\/)/i', $val) && stripos($val, 'var(') === false) {
                throw new InvalidArgumentException('Blocked url() value — only https://, /, data:image/ or var() allowed');
            }
            $varCandidate = '--' . ltrim($val, '-');
            if (in_array($varCandidate, $allowed, true) && !str_contains($val, ' ') && !str_contains($val, '#') && !str_contains($val, '(')) {
                $out[] = $prop . ':var(' . $varCandidate . ')'; continue;
            }
            $out[] = $prop . ':' . $val;
        }
        return implode('; ', $out) . ';';
    }
}
