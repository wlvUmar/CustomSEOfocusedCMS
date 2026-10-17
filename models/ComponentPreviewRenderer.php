<?php
// path: ./models/ComponentPreviewRenderer.php
// Presentational preview builder for the DB-canonical component library.
// Extracted from controllers/admin/ComponentsController.php::preview per docs/hygiene-audit-report.md P1.
// Owns mod allowlist, BEM auto-detect, category fallback, HTML fragment sanitize, debug overlay.
// Controller keeps auth + CSRF + size-cap validation only and delegates here.

require_once BASE_PATH . '/models/Component.php';

class ComponentPreviewRenderer {

    /** Build preview payload: render($data, $cssBody, $rawHtmlDemo) → { success, html, chars, slug, auto, demo } */
    public function preview(array $data, string $cssBody, string $rawHtmlDemo): array {
        $slug = Component::sanitizeSlug((string)($data['slug'] ?? ''));
        $mods = [];
        if (!empty($data['mods']) && is_array($data['mods'])) {
            foreach (array_slice($data['mods'], 0, 8) as $mod) {
                $mod = strtolower(trim((string)$mod));
                if (preg_match('/^[a-z0-9\-]+$/', $mod)) $mods[] = $mod;
            }
            $mods = array_values(array_unique($mods));
        }
        $bg = (($data['bg'] ?? 'light') === 'dark') ? 'dark' : 'light';
        $debug = !empty($data['debug']);
        $debugHead = '';
        if ($debug && $slug !== '' && $slug !== 'c-demo') {
            $slugJson = json_encode($slug, JSON_HEX_TAG | JSON_HEX_AMP);
            $debugHead = <<<HTML
<script>window.__pgDebugSlug={$slugJson};(function(){function px(v){var n=parseFloat(v);return isNaN(n)?0:n}function draw(){var old=document.querySelector('.pg-dbg-overlay');if(old)old.remove();var slug=window.__pgDebugSlug;if(!slug)return;var roots=[];Array.prototype.forEach.call(document.querySelectorAll('body *'),function(el){if(el.classList&&el.classList.contains(slug)&&roots.indexOf(el)<0)roots.push(el)});if(!roots.length)return;var ov=document.createElement('div');ov.className='pg-dbg-overlay';ov.setAttribute('style','position:fixed;inset:0;z-index:99999;pointer-events:none;');roots.forEach(function(el){var r=el.getBoundingClientRect();var cs=window.getComputedStyle(el);var pt=px(cs.paddingTop),pr=px(cs.paddingRight),pb=px(cs.paddingBottom),pl=px(cs.paddingLeft);var bt=px(cs.borderTopWidth),br=px(cs.borderRightWidth),bb=px(cs.borderBottomWidth),bl=px(cs.borderLeftWidth);function box(x,y,w,h,st){if(w<=0||h<=0)return;var d=document.createElement('div');d.setAttribute('style','position:absolute;left:'+x+'px;top:'+y+'px;width:'+w+'px;height:'+h+'px;box-sizing:border-box;'+st);ov.appendChild(d)}box(r.left,r.top,r.width,r.height,'border:2px solid #0ea5e9;');var pad='background:rgba(249,115,22,.30);';box(r.left+bl,r.top+bt,r.width-bl-br,pt,pad);box(r.left+bl,r.top+r.height-bb-pb,r.width-bl-br,pb,pad);box(r.left+bl,r.top+bt+pt,pl,r.height-bt-bb-pt-pb,pad);box(r.left+r.width-br-pr,r.top+bt+pt,pr,r.height-bt-bb-pt-pb,pad);box(r.left+bl+pl,r.top+bt+pt,r.width-bl-br-pl-pr,r.height-bt-bb-pt-pb,'border:1px dashed #6366f1;');var label=document.createElement('div');label.setAttribute('style','position:absolute;left:'+Math.max(2,r.left)+'px;top:'+Math.max(2,r.top-26)+'px;background:#0f172a;color:#fff;font:11px/1.4 monospace;padding:2px 8px;border-radius:6px;white-space:nowrap;');label.textContent='.'+slug+'  '+Math.round(r.width)+'x'+Math.round(r.height)+'  pad '+pt+' '+pr+' '+pb+' '+pl;ov.appendChild(label)});document.documentElement.appendChild(ov)}var t=null;function sched(){clearTimeout(t);t=setTimeout(draw,60)}window.addEventListener('resize',sched);window.addEventListener('scroll',sched,true);window.addEventListener('load',sched);setTimeout(draw,50);setTimeout(draw,800);})();</script>
HTML;
        }
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
            . $liveCss . $debugHead . '</head><body style="background:' . $bodyBg . '"><div class="content-body" style="padding:16px">' . $demo . '</div></body></html>';
        return ['success'=>true,'html'=>$doc,'chars'=>mb_strlen($doc),'slug'=>$slug,'auto'=>$isAuto,'demo'=>$htmlDemo];
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

    private function sanitizeHtmlFragment(string $html): string {
        if (trim($html) === '') return '';
        $html = (string)preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = (string)preg_replace('/\bon\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = (string)preg_replace('/\b(href|src|action|formaction|xlink:href|srcdoc)\s*=\s*["\']\s*(javascript|data\s*:\s*text\/html|vbscript):[^"\']*["\']/i', '', $html);
        $html = (string)preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
        $html = (string)preg_replace('/<(object|embed|link|meta|base)\b[^>]*>/i', '', $html);
        return $html;
    }
}
