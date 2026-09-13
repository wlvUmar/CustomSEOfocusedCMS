<?php
// path: ./models/Component.php
// DB-canonical component library. One row per /* === C-NAME === */ block (per-block, 95 rows).
// Public rendering stays on static files; this model is the editor's source of truth.

class Component {
    private $db;

    private const ALLOWED_CATEGORIES = [
        'shared', 'heroes', 'stats', 'features', 'process',
        'cards', 'cta', 'media', 'content', 'pricing', 'utilities'
    ];

    private const CATEGORY_LABELS = [
        'shared'    => 'Shared helpers',
        'heroes'    => 'Heroes',
        'stats'     => 'Stats',
        'features'  => 'Features',
        'process'   => 'Process / Timeline',
        'cards'     => 'Cards / Testimonials',
        'cta'       => 'CTAs',
        'media'     => 'Media',
        'content'   => 'Content blocks',
        'pricing'   => 'Pricing / Comparison',
        'utilities' => 'Utilities / Shapes',
    ];

    // Map section header numbers → category key
    private const SECTION_MAP = [
        1  => 'heroes',
        2  => 'stats',
        3  => 'features',
        4  => 'process',
        5  => 'cards',
        6  => 'cta',
        7  => 'media',
        8  => 'content',
        9  => 'pricing',
        10 => 'utilities',
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public static function categories(): array {
        return self::ALLOWED_CATEGORIES;
    }

    public static function categoryLabels(): array {
        return self::CATEGORY_LABELS;
    }

    public static function sectionMap(): array {
        return self::SECTION_MAP;
    }

    public function getAll(?string $category = null, ?string $status = null, ?string $search = null): array {
        $sql = "SELECT * FROM ai_components WHERE 1=1";
        $params = [];
        if ($category !== null && $category !== '' && in_array($category, self::ALLOWED_CATEGORIES, true)) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        if ($status !== null && $status !== '' && in_array($status, ['active','deprecated'], true)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $sql .= " AND (slug LIKE ? OR title LIKE ? OR description LIKE ?)";
            $params[] = $s; $params[] = $s; $params[] = $s;
        }
        $sql .= " ORDER BY sort ASC, slug ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function allByCategory(): array {
        $rows = $this->getAll();
        $out = [];
        foreach (self::ALLOWED_CATEGORIES as $cat) $out[$cat] = [];
        foreach ($rows as $r) {
            $cat = $r['category'] ?? 'utilities';
            if (!isset($out[$cat])) $out[$cat] = [];
            $out[$cat][] = $r;
        }
        return $out;
    }

    public function getBySlug(string $slug): ?array {
        $row = $this->db->fetchOne("SELECT * FROM ai_components WHERE slug = ? LIMIT 1", [strtolower(trim($slug))]);
        return $row ?: null;
    }

    public function getById(int $id): ?array {
        $row = $this->db->fetchOne("SELECT * FROM ai_components WHERE id = ? LIMIT 1", [$id]);
        return $row ?: null;
    }

    public function countAll(): int {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM ai_components");
        return (int)($row['c'] ?? 0);
    }

    public static function sanitizeSlug(string $slug): string {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/\-+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug !== '' && strpos($slug, 'c-') !== 0) $slug = 'c-' . $slug;
        return $slug;
    }

    public static function sanitizeCss(string $css): string {
        if (function_exists('sanitizeCssBlock')) return sanitizeCssBlock($css);
        return trim($css);
    }

    private function validate(array $data, ?int $excludeId = null): void {
        $slug = strtolower(trim($data['slug'] ?? ''));
        if ($slug === '') throw new InvalidArgumentException('Slug is required');
        if (!preg_match('/^c-[a-z0-9\-]+$/', $slug)) throw new InvalidArgumentException('Slug must match c-[a-z0-9-]+');
        $cat = $data['category'] ?? '';
        if (!in_array($cat, self::ALLOWED_CATEGORIES, true)) throw new InvalidArgumentException('Invalid category');
        $css = trim($data['css_body'] ?? '');
        if ($css === '') throw new InvalidArgumentException('CSS body is required');
        if (strlen($css) > 200 * 1024) throw new InvalidArgumentException('CSS body too large (max 200KB)');
        if (isset($data['status']) && !in_array($data['status'], ['active','deprecated'], true)) {
            throw new InvalidArgumentException('Invalid status');
        }
        // unique slug (excluding current row on update)
        $sql = "SELECT id FROM ai_components WHERE slug = ? LIMIT 1";
        $row = $this->db->fetchOne($sql, [$slug]);
        if ($row && (int)$row['id'] !== (int)($excludeId ?? -1)) {
            throw new InvalidArgumentException('Slug "' . $slug . '" already exists');
        }
    }

    public function create(array $data): int {
        $data['slug'] = strtolower(trim($data['slug'] ?? ''));
        $this->validate($data);
        $data['css_body'] = self::sanitizeCss((string)$data['css_body']);
        $tokens = $this->extractTokens($data['css_body']);
        $this->db->query(
            "INSERT INTO ai_components (slug, category, title, description, html_demo, css_body, variants, tokens_used, status, sort)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['slug'],
                $data['category'],
                trim($data['title'] ?? $data['slug']),
                $data['description'] ?? null,
                $data['html_demo'] ?? null,
                $data['css_body'],
                isset($data['variants']) ? json_encode($data['variants'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
                $tokens ? json_encode($tokens, JSON_UNESCAPED_UNICODE) : null,
                $data['status'] ?? 'active',
                (int)($data['sort'] ?? 0),
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void {
        $current = $this->getById($id);
        if (!$current) throw new InvalidArgumentException('Component not found: ' . $id);
        $merged = array_merge($current, $data);
        // Normalize slug if present
        if (array_key_exists('slug', $data)) $merged['slug'] = strtolower(trim((string)$data['slug']));
        $this->validate($merged, $id);
        if (array_key_exists('css_body', $data)) $merged['css_body'] = self::sanitizeCss((string)$data['css_body']);
        $tokens = $this->extractTokens($merged['css_body']);

        // Snapshot before mutation (like Page::update does for PageRevision)
        try {
            if (!class_exists('ComponentRevision', false)) require_once BASE_PATH . '/models/ComponentRevision.php';
            if (class_exists('ComponentRevision', true)) {
                ComponentRevision::createSnapshot($id, $current, array_keys($data));
            }
        } catch (Throwable $e) {
            error_log('[Component] revision snapshot failed for ' . $id . ': ' . $e->getMessage());
        }

        $variantsJson = null;
        if (array_key_exists('variants', $data) || array_key_exists('variants', $merged)) {
            $v = $data['variants'] ?? $merged['variants'] ?? null;
            if (is_string($v)) {
                $decoded = json_decode($v, true);
                $variantsJson = $decoded !== null ? json_encode($decoded, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : $v;
            } elseif (is_array($v)) {
                $variantsJson = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            } else {
                $variantsJson = $v;
            }
        } else {
            $variantsJson = $current['variants'] ?? null;
            if (is_array($variantsJson)) $variantsJson = json_encode($variantsJson, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }

        $this->db->query(
            "UPDATE ai_components SET slug=?, category=?, title=?, description=?, html_demo=?, css_body=?, variants=?, tokens_used=?, status=?, sort=? WHERE id=?",
            [
                $merged['slug'],
                $merged['category'],
                trim($merged['title'] ?? $merged['slug']),
                $merged['description'] ?? null,
                $merged['html_demo'] ?? null,
                $merged['css_body'],
                $variantsJson,
                $tokens ? json_encode($tokens, JSON_UNESCAPED_UNICODE) : null,
                $merged['status'] ?? 'active',
                (int)($merged['sort'] ?? 0),
                $id,
            ]
        );
    }

    public function delete(int $id): void {
        $this->db->query("DELETE FROM ai_components WHERE id=?", [$id]);
    }

    private function extractTokens(string $css): array {
        if (preg_match_all('/var\(\s*(--[\w\-]+)/', $css, $m)) {
            $t = array_values(array_unique($m[1]));
            sort($t);
            return $t;
        }
        return [];
    }

    /**
     * Regenerate public/css/components.css + components.min.css from DB.
     * Atomic write (tmp+rename), keeps .bak. Returns stats.
     */
    public static function rebuildCss(): array {
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT * FROM ai_components ORDER BY sort ASC, slug ASC");
        $cssPath = BASE_PATH . '/public/css/components.css';
        $minPath = BASE_PATH . '/public/css/components.min.css';

        // Build CSS: file header + category dividers + per-block markers. Shared is synthetic (c-shared).
        $header = self::fileHeader();
        $body = $header;
        $catHeaders = [
            'shared'    => 'Shared helpers',
            'heroes'    => '1 ── HERO variants',
            'stats'     => '2 ── STATS',
            'features'  => '3 ── FEATURES',
            'process'   => '4 ── PROCESS / TIMELINE',
            'cards'     => '5 ── CARDS / TESTIMONIALS / TEAM',
            'cta'       => '6 ── CTA / CALLOUT',
            'media'     => '7 ── MEDIA / GALLERY',
            'content'   => '8 ── CONTENT BLOCKS',
            'pricing'   => '9 ── PRICING / COMPARISON',
            'utilities' => '10 ── UTILITIES / SHAPES',
        ];
        $currentCat = null;
        foreach ($rows as $row) {
            $cat = $row['category'] ?? 'utilities';
            $slugUpper = strtoupper($row['slug'] ?? '');
            $cssBody = rtrim((string)($row['css_body'] ?? ''));
            if ($cssBody === '') continue;
            if ($cat !== $currentCat) {
                $title = $catHeaders[$cat] ?? strtoupper($cat);
                // Shared helpers has its own divider style (── Shared helpers ──)
                if ($cat === 'shared' && $slugUpper === 'C-SHARED') {
                    $body .= "\n/* ── Shared helpers ─────────────────────────────────────────────────────── */\n";
                } elseif ($cat !== 'shared') {
                    $body .= "\n/* " . $title . " ─────────────────────────────────────────────────────── */\n";
                }
                $currentCat = $cat;
            }
            if ($cat === 'shared' && $slugUpper === 'C-SHARED') {
                $body .= $cssBody . "\n";
            } else {
                $body .= "/* === " . $slugUpper . " === */\n" . $cssBody . "\n";
            }
        }
        // Trim trailing whitespace but ensure newline at EOF
        $body = rtrim($body) . "\n";

        // Backup existing
        $oldBytes = is_file($cssPath) ? @filesize($cssPath) : 0;
        $oldHash = is_file($cssPath) ? @sha1_file($cssPath) : '';
        if (is_file($cssPath)) {
            @copy($cssPath, $cssPath . '.bak');
        }
        // Atomic write
        $tmp = $cssPath . '.tmp.' . getmypid();
        $written = @file_put_contents($tmp, $body, LOCK_EX);
        if ($written === false) throw new RuntimeException('Failed to write components.css tmp file');
        if (!@rename($tmp, $cssPath)) {
            @unlink($tmp);
            throw new RuntimeException('Failed to rename components.css tmp file');
        }
        $newBytes = strlen($body);
        $newHash = sha1($body);

        // Minify
        $minResult = self::minify($cssPath, $minPath, $body);

        // Sync to PUBLIC_PATH if different from BASE_PATH/public (production has public_html)
        if (defined('PUBLIC_PATH')) {
            $pubCss = rtrim(PUBLIC_PATH, '/\\') . '/css/components.css';
            $pubMin = rtrim(PUBLIC_PATH, '/\\') . '/css/components.min.css';
            if ($pubCss !== $cssPath && $pubCss !== $minPath) {
                @copy($cssPath, $pubCss);
                @copy($minPath, $pubMin);
            }
        }

        return [
            'rows' => count($rows),
            'bytes_before' => (int)$oldBytes,
            'bytes_after' => $newBytes,
            'hash_before' => $oldHash,
            'hash_after' => $newHash,
            'changed' => $oldHash !== $newHash,
            'min' => $minResult,
        ];
    }

    private static function fileHeader(): string {
        // Try to preserve original header verbatim from existing file
        $path = BASE_PATH . '/public/css/components.css';
        if (is_file($path)) {
            $raw = @file_get_contents($path, false, null, 0, 8192);
            if ($raw !== false && $raw !== '') {
                // Capture from start through the ═══ closing line
                if (preg_match('/\A.*?═══════════════════════════════════════════════════════════════════════════ \*\/\s*\n/s', $raw, $m)) {
                    return $m[0];
                }
            }
        }
        return "/* ── components.css (DB-canonical, file generated) ─────────────────────\n"
            . "   Generated from ai_components. Do not edit by hand — use /admin/components.\n"
            . "   All classes prefixed .c- to avoid collisions. Uses :root tokens from pages.css\n"
            . "   (var(--teal), --orange, --ink, --muted, --surface, --border, --max-w, --px).\n"
            . "   Zero-cost if unused; per-page overrides win due to later <style id=\"page-custom-css\">.\n"
            . "   ──────────────────────────────────────────────────────────────────── */\n\n";
    }

    private static function minify(string $cssPath, string $minPath, string $cssBody): array {
        $oldMinBytes = is_file($minPath) ? @filesize($minPath) : 0;
        $usedCli = false;
        $min = '';
        $tmpIn = $cssPath . '.min.tmp.in.' . getmypid();
        $tmpOut = $minPath . '.tmp.' . getmypid();
        @file_put_contents($tmpIn, $cssBody);
        $cmd = 'npx --yes clean-css-cli --version 2>/dev/null';
        $hasCleancss = false;
        $out = @shell_exec($cmd);
        if ($out !== null && stripos($out, 'clean-css') !== false) $hasCleancss = true;
        if ($hasCleancss || true) {
            $cleancssCmd = 'npx cleancss -o ' . escapeshellarg($tmpOut) . ' ' . escapeshellarg($tmpIn) . ' 2>/dev/null';
            @shell_exec($cleancssCmd);
            if (is_file($tmpOut) && @filesize($tmpOut) > 0) {
                $min = (string)@file_get_contents($tmpOut);
                if ($min !== '' && @rename($tmpOut, $minPath)) { $usedCli = true; }
                else { @unlink($tmpOut); }
            }
        }
        @unlink($tmpIn);
        if (!$usedCli) {
            // PHP fallback: whitespace minify
            $min = preg_replace('/\/\*.*?\*\//s', '', $cssBody);
            $min = preg_replace('/\s+/', ' ', $min);
            $min = str_replace([' {', '{ ', ' }', '} ', ' ;', '; ', ' :', ': ', ' ,', ', '], ['{','{','}','}',';',';',':',':',',',','], $min);
            $min = trim($min);
            if (is_file($minPath)) @copy($minPath, $minPath . '.bak');
            $tmp = $minPath . '.tmp.' . getmypid();
            @file_put_contents($tmp, $min, LOCK_EX);
            @rename($tmp, $minPath);
        }
        $newMinBytes = is_file($minPath) ? @filesize($minPath) : strlen($min);
        return ['bytes_before' => (int)$oldMinBytes, 'bytes_after' => (int)$newMinBytes, 'used_cli' => $usedCli];
    }
}
