<?php
// path: ./controllers/admin/ComponentsController.php
// Admin UI for DB-canonical component library. Mirrors SEOController/PageAdmin patterns.

require_once BASE_PATH . '/models/Component.php';
require_once BASE_PATH . '/models/ComponentRevision.php';

class ComponentsController extends Controller {

    public function index() {
        $this->requireAuth();
        $m = new Component();
        $q = trim($_GET['q'] ?? '');
        $category = trim($_GET['category'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $rows = $m->getAll($category ?: null, $status ?: null, $q ?: null);
        // Group for stats
        $all = $m->getAll();
        $catCounts = [];
        foreach ($all as $r) { $catCounts[$r['category']] = ($catCounts[$r['category']] ?? 0) + 1; }
        $this->view('admin/component-lib/index', [
            'pageName' => 'component-lib/index',
            'rows' => $rows,
            'q' => $q,
            'category' => $category,
            'status' => $status,
            'catCounts' => $catCounts,
            'categories' => Component::categories(),
            'categoryLabels' => Component::categoryLabels(),
            'total' => count($all),
        ]);
    }

    public function edit(string $slug) {
        $this->requireAuth();
        $m = new Component();
        $row = $m->getBySlug($slug);
        if (!$row) {
            $_SESSION['error'] = 'Component not found: ' . $slug;
            $this->redirect('/admin/components');
            return;
        }
        $revModel = new ComponentRevision();
        $revisions = $revModel->getByComponentId((int)$row['id'], 20);
        // Design tokens for chip bar
        $tokens = $this->designTokens();
        $this->view('admin/component-lib/edit', [
            'pageName' => 'component-lib/edit',
            'row' => $row,
            'revisions' => $revisions,
            'categories' => Component::categories(),
            'categoryLabels' => Component::categoryLabels(),
            'tokens' => $tokens,
        ]);
    }

    public function save() {
        $this->requireAuth();
        $this->requireCsrf();
        $m = new Component();
        $id = (int)($_POST['id'] ?? 0);
        $slug = Component::sanitizeSlug((string)($_POST['slug'] ?? ''));
        $category = trim($_POST['category'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $htmlDemo = isset($_POST['html_demo']) ? (string)$_POST['html_demo'] : null;
        $cssBody = (string)($_POST['css_body'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $sort = (int)($_POST['sort'] ?? 0);
        $variantsRaw = trim($_POST['variants'] ?? '');
        $variants = null;
        if ($variantsRaw !== '') {
            $decoded = json_decode($variantsRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['error'] = 'Variants is not valid JSON: ' . json_last_error_msg();
                $this->redirect('/admin/components/edit/' . urlencode($slug ?: (string)$id));
                return;
            }
            $variants = $decoded;
        }

        // Server-side CSS sanitize + cap (mirrors SiteTools preview guard)
        $cssBody = Component::sanitizeCss($cssBody);
        if (mb_strlen($cssBody) > 200 * 1024) {
            $_SESSION['error'] = 'CSS too large (max 200KB)';
            $this->redirect('/admin/components/edit/' . urlencode($slug));
            return;
        }

        try {
            if ($id) {
                $existing = $m->getById($id);
                if (!$existing) throw new InvalidArgumentException('Component not found');
                // Allow slug change but validate uniqueness
                $m->update($id, [
                    'slug' => $slug,
                    'category' => $category,
                    'title' => $title ?: $slug,
                    'description' => $description ?: null,
                    'html_demo' => $htmlDemo,
                    'css_body' => $cssBody,
                    'variants' => $variants,
                    'status' => $status,
                    'sort' => $sort,
                ]);
                // Regenerate files
                try {
                    $stats = Component::rebuildCss();
                    $_SESSION['success'] = 'Saved ' . $slug . ' — rebuilt ' . $stats['bytes_after'] . ' bytes (' . $stats['rows'] . ' blocks)' . ($stats['changed'] ? '' : ' (no CSS change)');
                } catch (Throwable $e) {
                    $_SESSION['warning'] = 'Saved to DB but rebuild failed: ' . $e->getMessage() . ' — run Rebuild manually.';
                }
                $this->redirect('/admin/components/edit/' . urlencode($slug));
            } else {
                $id = $m->create([
                    'slug' => $slug,
                    'category' => $category,
                    'title' => $title ?: $slug,
                    'description' => $description ?: null,
                    'html_demo' => $htmlDemo,
                    'css_body' => $cssBody,
                    'variants' => $variants,
                    'status' => $status,
                    'sort' => $sort,
                ]);
                try { Component::rebuildCss(); } catch (Throwable $e) {}
                $_SESSION['success'] = 'Created ' . $slug;
                $this->redirect('/admin/components/edit/' . urlencode($slug));
            }
        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect($id ? '/admin/components/edit/' . urlencode($slug) : '/admin/components');
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Save failed: ' . $e->getMessage();
            $this->redirect('/admin/components');
        }
    }

    public function revision() {
        $this->requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { $this->json(['success'=>false,'message'=>'Missing id'], 400); return; }
        $revModel = new ComponentRevision();
        $rev = $revModel->getById($id);
        if (!$rev) { $this->json(['success'=>false,'message'=>'Revision not found'], 404); return; }
        $snap = json_decode((string)($rev['snapshot'] ?? ''), true);
        if (!is_array($snap)) { $this->json(['success'=>false,'message'=>'Snapshot corrupt'], 500); return; }
        $this->json(['success'=>true,'css_body'=>(string)($snap['css_body'] ?? ''),'html_demo'=>(string)($snap['html_demo'] ?? ''),'slug'=>(string)($snap['slug'] ?? '')]);
    }

    public function restore() {
        $this->requireAuth();
        $this->requireCsrf();
        $id = (int)($_POST['revision_id'] ?? 0);
        if (!$id) { $_SESSION['error'] = 'Missing revision id'; $this->redirect('/admin/components'); return; }
        try {
            $revModel = new ComponentRevision();
            $fresh = $revModel->restore($id);
            try { Component::rebuildCss(); } catch (Throwable $e) {}
            $_SESSION['success'] = 'Restored revision ' . $id . ' (' . ($fresh['slug'] ?? '') . ')';
            $this->redirect('/admin/components/edit/' . urlencode($fresh['slug'] ?? ''));
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Restore failed: ' . $e->getMessage();
            $this->redirect('/admin/components');
        }
    }

    /** JSON preview: POST { slug, css_body, html_demo, mods[], bg } → { html, chars, auto } */
    public function preview() {
        $this->requireAuth();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            $this->json(['success'=>false,'message'=>'CSRF failed'], 403);
            return;
        }
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) $data = $_POST;
        $cssBody = Component::sanitizeCss((string)($data['css_body'] ?? ''));
        $rawHtmlDemo = (string)($data['html_demo'] ?? '');
        $slug = Component::sanitizeSlug((string)($data['slug'] ?? ''));
        if (mb_strlen($cssBody) > 200 * 1024) {
            $this->json(['success'=>false,'message'=>'CSS too large (max 200KB)'], 400); return;
        }
        if (mb_strlen($rawHtmlDemo) > 200 * 1024) {
            $this->json(['success'=>false,'message'=>'html_demo too large (max 200KB)'], 400); return;
        }
        $mods = [];
        if (!empty($data['mods']) && is_array($data['mods'])) {
            foreach (array_slice($data['mods'], 0, 8) as $mod) {
                $mod = strtolower(trim((string)$mod));
                if (preg_match('/^[a-z0-9\-]+$/', $mod)) $mods[] = $mod;
            }
            $mods = array_values(array_unique($mods));
        }
        $bg = (($data['bg'] ?? 'light') === 'dark') ? 'dark' : 'light';
        $isAuto = false;
        $check = trim($rawHtmlDemo);
        if (preg_match('/^\s*<div class="c-section">\s*<\/div>\s*$/s', $check)) $check = '';
        if ($check === '') {
            $rowCat = 'utilities';
            $genCss = $cssBody;
            if ($slug !== '') {
                try {
                    $m = new Component();
                    $row = $m->getBySlug($slug);
                    if ($row) {
                        $rowCat = $row['category'] ?? $rowCat;
                        if (trim($genCss) === '') $genCss = (string)($row['css_body'] ?? '');
                    }
                } catch (Throwable $e) {}
            }
            $htmlDemo = $this->autoDemoHtml($slug ?: 'c-demo', $genCss, $rowCat, $mods);
            $isAuto = true;
        } else {
            $htmlDemo = $this->sanitizeHtmlFragment($rawHtmlDemo);
            $htmlDemo = $this->applyModsToHtml($htmlDemo, $slug, $mods);
        }
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $liveCss = $cssBody !== '' ? '<style id="preview-override">' . $cssBody . '</style>' : '';
        $demo = $htmlDemo;
        $bodyBg = $bg === 'dark' ? '#0f1117' : 'var(--surface)';
        $doc = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<link rel="stylesheet" href="' . $baseUrl . '/css/pages.css">'
            . '<link rel="stylesheet" href="' . $baseUrl . '/css/components.min.css">'
            . '<style>html,body{background:' . $bodyBg . '}*{opacity:1!important;transform:none!important;transition:none!important;animation:none!important}</style>'
            . $liveCss . '</head><body style="background:' . $bodyBg . '"><div class="content-body" style="padding:16px">' . $demo . '</div></body></html>';
        $this->json(['success'=>true,'html'=>$doc,'chars'=>mb_strlen($doc),'slug'=>$slug,'auto'=>$isAuto,'demo'=>$htmlDemo]);
    }

    private function applyModsToHtml(string $html, string $slug, array $mods): string {
        if (!$mods || $slug === '') return $html;
        $extra = '';
        foreach ($mods as $mod) $extra .= ' ' . $slug . '--' . $mod;
        $q = preg_quote($slug, '/');
        return (string)preg_replace('/class="' . $q . '(?=["\s])/', 'class="' . $slug . $extra, $html, 1);
    }

    private function autoDemoHtml(string $slug, string $cssBody, string $category, array $mods = []): string {
        $slug = Component::sanitizeSlug($slug) ?: 'c-demo';
        if ($slug === 'c-shared') {
            return '<div class="c-section"><p class="c-kicker">Preview — c-shared</p><h2 class="c-title">Shared helpers</h2><p class="c-lead" style="color:var(--muted)">Base section + kicker + title + lead. Uses tokens from pages.css. Edit HTML demo to customize.</p><p style="display:flex;gap:8px;flex-wrap:wrap"><a class="c-btn" href="#">Primary</a> <a class="c-btn c-btn--ghost" href="#">Ghost</a></p><hr class="c-divider" /><p class="c-muted" style="font-size:13px">Tokens: var(--teal), var(--orange), var(--ink), var(--muted), var(--surface), var(--border)</p></div>';
        }
        $parts = [];
        if ($cssBody !== '' && $slug !== '') {
            $q = preg_quote($slug, '/');
            if (preg_match_all('/\.' . $q . '__([a-z0-9\-]+)/', $cssBody, $m)) {
                foreach ($m[1] as $raw) {
                    $base = strtolower(explode('--', $raw)[0]);
                    $base = trim($base, '-');
                    if ($base === '' || in_array($base, $parts, true)) continue;
                    $parts[] = $base;
                }
            }
        }
        $detected = [];
        if ($cssBody !== '' && preg_match_all('/\.' . preg_quote($slug, '/') . '--([a-z0-9\-]+)/', $cssBody, $mm)) {
            $detected = array_values(array_unique(array_map('strtolower', $mm[1])));
        }
        $active = $mods ?: array_slice($detected, 0, 1);
        $rootClass = $slug;
        foreach ($active as $am) $rootClass .= ' ' . $slug . '--' . $am;
        if ($parts) {
            $inner = '';
            foreach ($parts as $part) {
                $inner .= $this->autoPartMarkup($slug, $part);
            }
            return '<div class="' . htmlspecialchars($rootClass) . '">' . $inner . '</div>';
        }
        return $this->autoCategoryFallback($slug, $category, $rootClass);
    }

    private function autoPartMarkup(string $slug, string $part): string {
        $cls = htmlspecialchars($slug . '__' . $part);
        $p = strtolower($part);
        if (strpos($p, 'kicker') !== false || strpos($p, 'eyebrow') !== false || $p === 'label') {
            return '<p class="' . $cls . '">Eyebrow — preview</p>';
        }
        if (strpos($p, 'title') !== false || strpos($p, 'heading') !== false || strpos($p, 'headline') !== false || $p === 'name') {
            return '<h2 class="' . $cls . '">Sample ' . htmlspecialchars(str_replace('-', ' ', $part)) . '</h2>';
        }
        if (strpos($p, 'subtitle') !== false || $p === 'lead') {
            return '<p class="' . $cls . '" style="color:var(--muted)">Lead text — lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>';
        }
        if (strpos($p, 'text') !== false || strpos($p, 'desc') !== false || strpos($p, 'copy') !== false || strpos($p, 'body') !== false || $p === 'content' || $p === 'excerpt') {
            return '<p class="' . $cls . '">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero.</p>';
        }
        if (strpos($p, 'actions') !== false || strpos($p, 'cta') !== false || $p === 'btn' || $p === 'button' || $p === 'links' || $p === 'footer') {
            return '<div class="' . $cls . '" style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0"><a class="c-btn" href="#">Primary action</a> <a class="c-btn c-btn--ghost" href="#">Secondary</a></div>';
        }
        if (strpos($p, 'media') !== false || strpos($p, 'image') !== false || strpos($p, 'thumb') !== false || strpos($p, 'figure') !== false || strpos($p, 'visual') !== false || $p === 'img' || $p === 'cover' || $p === 'avatar') {
            return '<div class="' . $cls . '" style="background:#e5e7eb;border:1px dashed #cbd5e1;border-radius:12px;height:180px;display:grid;place-items:center;color:#94a3b8;font-size:13px">320 × 180 — .' . $cls . '</div>';
        }
        if (strpos($p, 'list') !== false || $p === 'grid' || $p === 'row' || $p === 'items' || $p === 'cards' || $p === 'cols') {
            return '<div class="' . $cls . '" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px"><div class="c-card" style="padding:12px;border:1px solid var(--border);border-radius:10px">Card one — sample</div><div class="c-card" style="padding:12px;border:1px solid var(--border);border-radius:10px">Card two — sample</div><div class="c-card" style="padding:12px;border:1px solid var(--border);border-radius:10px">Card three — sample</div></div>';
        }
        if (strpos($p, 'price') !== false || $p === 'plan' || $p === 'tier' || $p === 'amount') {
            return '<div class="' . $cls . '"><strong style="font-size:22px">$49</strong> <span style="color:var(--muted)">/mo</span></div>';
        }
        if (strpos($p, 'stat') !== false || strpos($p, 'metric') !== false || $p === 'number' || $p === 'value' || $p === 'kpi') {
            return '<div class="' . $cls . '" style="display:flex;gap:12px"><div><b style="display:block;font-size:20px">1.2k+</b><span style="color:var(--muted);font-size:13px">Metric</span></div><div><b style="display:block;font-size:20px">98%</b><span style="color:var(--muted);font-size:13px">Rate</span></div></div>';
        }
        if (strpos($p, 'icon') !== false || $p === 'badge' || $p === 'pill') {
            return '<span class="' . $cls . '" style="display:inline-grid;place-items:center;width:40px;height:40px;border-radius:999px;background:var(--teal);color:#fff">◆</span>';
        }
        if (strpos($p, 'quote') !== false || $p === 'blockquote') {
            return '<blockquote class="' . $cls . '" style="border-left:3px solid var(--teal);padding-left:12px;color:var(--ink-soft)">“Sample quote — lorem ipsum dolor sit amet.”</blockquote>';
        }
        return '<div class="' . $cls . '" style="padding:10px;border:1px dashed #e5e7eb;border-radius:8px;color:var(--muted);font-size:13px">.' . $cls . ' — sample content</div>';
    }

    private function autoCategoryFallback(string $slug, string $category, string $rootClass): string {
        $rc = htmlspecialchars($rootClass);
        switch ($category) {
            case 'heroes':
                return '<div class="' . $rc . '"><p class="' . htmlspecialchars($slug) . '__kicker' . '" style="color:var(--teal);font-weight:700;letter-spacing:.08em;font-size:12px">HERO PREVIEW</p><h2 class="' . htmlspecialchars($slug) . '__title' . '" style="font-size:clamp(22px,3vw,32px);font-weight:800">Hero title — auto demo</h2><p class="' . htmlspecialchars($slug) . '__text' . '" style="color:var(--muted)">Auto-generated from CSS selectors. Add html_demo to customize. Lorem ipsum dolor sit amet.</p><div class="' . htmlspecialchars($slug) . '__actions' . '" style="display:flex;gap:8px;margin-top:10px"><a class="c-btn" href="#">Get started</a> <a class="c-btn c-btn--ghost" href="#">Learn more</a></div></div>';
            case 'stats':
                return '<div class="' . $rc . '" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px"><div style="text-align:center;padding:12px;border:1px solid var(--border);border-radius:10px"><b style="display:block;font-size:20px">1.2k+</b><span style="color:var(--muted);font-size:13px">Users</span></div><div style="text-align:center;padding:12px;border:1px solid var(--border);border-radius:10px"><b style="display:block;font-size:20px">98%</b><span style="color:var(--muted);font-size:13px">Uptime</span></div><div style="text-align:center;padding:12px;border:1px solid var(--border);border-radius:10px"><b style="display:block;font-size:20px">24/7</b><span style="color:var(--muted);font-size:13px">Support</span></div></div>';
            case 'cta':
                return '<div class="' . $rc . '" style="text-align:center;padding:20px;background:var(--surface-2);border:1px solid var(--border);border-radius:14px"><h3 style="font-weight:800">Call to action — auto demo</h3><p style="color:var(--muted)">Lorem ipsum dolor sit amet.</p><p><a class="c-btn" href="#">Take action</a></p></div>';
            case 'pricing':
                return '<div class="' . $rc . '" style="max-width:360px;margin:0 auto;padding:18px;border:1px solid var(--border);border-radius:14px"><h3 style="font-weight:800">Pro Plan</h3><p><strong style="font-size:24px">$49</strong> <span style="color:var(--muted)">/mo</span></p><ul style="color:var(--muted);font-size:14px"><li>Feature one</li><li>Feature two</li><li>Feature three</li></ul><p><a class="c-btn" href="#">Choose plan</a></p></div>';
            case 'cards':
                return '<div class="' . $rc . '" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px"><div style="padding:14px;border:1px solid var(--border);border-radius:12px"><strong>Card one</strong><p style="color:var(--muted);font-size:13px">Sample card body text.</p></div><div style="padding:14px;border:1px solid var(--border);border-radius:12px"><strong>Card two</strong><p style="color:var(--muted);font-size:13px">Sample card body text.</p></div></div>';
            default:
                return '<div class="' . $rc . '"><h3 style="font-weight:700">Auto demo — ' . htmlspecialchars($slug) . '</h3><p style="color:var(--muted)">No BEM children detected in CSS. Showing generic filler inside <code>.' . $rc . '</code>. Edit HTML demo to customize.</p><p><a class="c-btn" href="#">Action</a></p></div>';
        }
    }

    public function rebuild() {
        $this->requireAuth();
        $this->requireCsrf();
        try {
            $stats = Component::rebuildCss();
            $_SESSION['success'] = 'Rebuilt components.css — ' . $stats['rows'] . ' blocks, ' . $stats['bytes_after'] . ' bytes (was ' . $stats['bytes_before'] . ')' . ($stats['changed'] ? '' : ' — no change');
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Rebuild failed: ' . $e->getMessage();
        }
        $this->redirect('/admin/components');
    }

    private function sanitizeHtmlFragment(string $html): string {
        if (trim($html) === '') return '';
        $html = (string)preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = (string)preg_replace('/\bon\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = (string)preg_replace('/\b(href|src|action|formaction|xlink:href|srcdoc)\s*=\s*["\']\s*(javascript|data\s*:\s*text\/html|vbscript):[^"\']*["\']/i', '', $html);
        $html = (string)preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
        $html = (string)preg_replace('/<(object|embed|link|meta|base)\b[^>]*>/i', '', $html);
        return $html;
    }

    private function designTokens(): array {
        $path = BASE_PATH . '/public/css/pages.css';
        if (!is_file($path)) return [];
        $css = (string)@file_get_contents($path, false, null, 0, 200 * 1024);
        if ($css === '') return [];
        $tokens = [];
        if (preg_match('/:root\s*\{([^}]*)\}/s', $css, $m)) {
            if (preg_match_all('/(--[\w-]+)\s*:\s*([^;}]+)/', $m[1], $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $p) $tokens[trim($p[1])] = trim($p[2]);
            }
        }
        return $tokens;
    }
}
