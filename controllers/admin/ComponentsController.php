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

    /** JSON preview: POST { slug, css_body, html_demo } → { html, chars } */
    public function preview() {
        $this->requireAuth();
        // CSRF via header for fetch JSON
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            $this->json(['success'=>false,'message'=>'CSRF failed'], 403);
            return;
        }
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) $data = $_POST;
        $cssBody = Component::sanitizeCss((string)($data['css_body'] ?? ''));
        $htmlDemo = (string)($data['html_demo'] ?? '');
        $slug = Component::sanitizeSlug((string)($data['slug'] ?? ''));
        if (mb_strlen($cssBody) > 200 * 1024) {
            $this->json(['success'=>false,'message'=>'CSS too large (max 200KB)'], 400); return;
        }
        if (mb_strlen($htmlDemo) > 200 * 1024) {
            $this->json(['success'=>false,'message'=>'html_demo too large (max 200KB)'], 400); return;
        }
        // Sanitize html_demo like SiteTools::sanitizeForPreview (light)
        $htmlDemo = $this->sanitizeHtmlFragment($htmlDemo);
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        // Build iframe doc: pages.css + components (live DB preview) + unsaved css_body override
        $liveCss = $cssBody !== '' ? '<style id="preview-override">' . $cssBody . '</style>' : '';
        $demo = $htmlDemo !== '' ? $htmlDemo : '<div class="' . htmlspecialchars($slug) . '"><p style="color:var(--muted)">No demo HTML — add html_demo to preview.</p></div>';
        $doc = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<link rel="stylesheet" href="' . $baseUrl . '/css/pages.css">'
            . '<link rel="stylesheet" href="' . $baseUrl . '/css/components.min.css">'
            . '<style>html,body{background:var(--surface)}*{opacity:1!important;transform:none!important;transition:none!important;animation:none!important}</style>'
            . $liveCss . '</head><body><div class="content-body" style="padding:16px">' . $demo . '</div></body></html>';
        $this->json(['success'=>true,'html'=>$doc,'chars'=>mb_strlen($doc),'slug'=>$slug]);
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
