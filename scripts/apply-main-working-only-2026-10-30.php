<?php
// scripts/apply-main-working-only-2026-10-30.php
// Working-only cleanup for main page (id=1): remove contradictions, no new "we don't buy broken" copy.
// RUN ON SERVER (PHP + DB available): php scripts/apply-main-working-only-2026-10-30.php [--apply]
// Default is --dry-run (prints diff, writes nothing). With --apply it writes via Page/FAQ models
// (each write snapshotted to page_revisions / audit_log by the models).
//
// Policy: working-only (remove contradictions, do not add explicit "we don't buy broken" copy).
// RU<->UZ parity on every write. No inline style="". Preserve {{global.*}}/{{media.*}}/{{page.*}}.
// FAQ answers are rendered escaped (views/partials/faq-accordion.php: e()+nl2br) — plain text only, no <a>.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/FAQ.php';
require_once BASE_PATH . '/models/ai/tools/PageSectionsHelper.php';

$apply = in_array('--apply', $argv ?? [], true);
$log = function ($m) { echo $m . PHP_EOL; };

// ---------------------------------------------------------------- resolve page
$db = Database::getInstance();
$page = (new Page())->getById(1);
if (!$page) $page = (new Page())->getBySlug('main', true);
if (!$page) $page = (new Page())->getBySlug('home', true);
if (!$page) { fwrite(STDERR, "FATAL: main page (id=1 / slug main) not found\n"); exit(1); }
$pageId = (int)$page['id'];
$slug = $page['slug'];
$log("page: id={$pageId} slug={$slug} theme=" . ($page['theme'] ?? '?'));

// Existing category slugs — in-body links only to pages that exist (no broken links).
$candidateSlugs = ['xolodilnik','stiralka','morozilnik','televizor','konditsioner','mebel','gas-plita'];
$existing = [];
foreach ($candidateSlugs as $s) {
    $row = $db->fetchOne("SELECT slug FROM pages WHERE slug = ? LIMIT 1", [$s]);
    if ($row) $existing[$s] = true;
}
$log('existing category slugs: ' . implode(',', array_keys($existing)));

// link helper: RU -> /slug, UZ -> /slug/uz (site convention)
$linkRu = function ($s, $text) use ($existing) {
    if (empty($existing[$s])) return $text;
    return '<a href="/' . $s . '">' . $text . '</a>';
};
$linkUz = function ($s, $text) use ($existing) {
    if (empty($existing[$s])) return $text;
    return '<a href="/' . $s . '/uz">' . $text . '</a>';
};

// ---------------------------------------------------------------- drafts (RU)
$topRu =
'<div class="content-section">' . "\n" .
'<h2>Что мы покупаем</h2>' . "\n" .
'<div class="info-grid">' . "\n" .
'<div class="info-card"><h3>Крупная бытовая техника</h3><p>Скупка холодильников, стиральных машин, морозильников в рабочем состоянии.</p><p>' .
    $linkRu('xolodilnik','Скупка холодильников') . ' · ' . $linkRu('stiralka','Скупка стиральных машин') . ' · ' . $linkRu('morozilnik','Скупка морозильников') . '</p></div>' . "\n" .
'<div class="info-card"><h3>Телевизоры и мониторы</h3><p>Покупаем Smart TV, QLED и современные модели в рабочем состоянии.</p><p>' .
    $linkRu('televizor','Скупка телевизоров') . '</p></div>' . "\n" .
'<div class="info-card"><h3>Климатическая техника</h3><p>Кондиционеры и сплит-системы — Samsung, LG, Artel, Gree, Midea. Только исправная техника с проверкой на месте.</p><p>' .
    $linkRu('konditsioner','Скупка кондиционеров') . '</p></div>' . "\n" .
'<div class="info-card"><h3>Мебель и плиты</h3><p>Диваны, кровати, шкафы и газовые плиты в рабочем состоянии.</p><p>' .
    $linkRu('mebel','Скупка мебели') . ' · ' . $linkRu('gas-plita','Скупка газовых плит') . '</p></div>' . "\n" .
'</div>' . "\n" .
'<div class="c-notice">Ноутбуки, ПК и компьютерную технику не принимаем. Зато дорого покупаем холодильники, стиралки, ТВ, плиты, кондиционеры и мебель — звоните {{global.phone}} для быстрой оценки за 5 минут.</div>' . "\n" .
'</div>';

$topUz =
'<div class="content-section">' . "\n" .
'<h2>Biz nimalarni sotib olamiz</h2>' . "\n" .
'<div class="info-grid">' . "\n" .
'<div class="info-card"><h3>Yirik maishiy texnika</h3><p>Ish holatidagi muzlatgichlar, kir yuvish mashinalari va morozilniklar xaridi.</p><p>' .
    $linkUz('xolodilnik','Muzlatgichlar xaridi') . ' · ' . $linkUz('stiralka','Kir mashinalari xaridi') . ' · ' . $linkUz('morozilnik','Morozilniklar xaridi') . '</p></div>' . "\n" .
'<div class="info-card"><h3>Televizorlar</h3><p>Ish holatidagi Smart TV, QLED va zamonaviy modellarni sotib olamiz.</p><p>' .
    $linkUz('televizor','Televizorlar xaridi') . '</p></div>' . "\n" .
'<div class="info-card"><h3>Iqlim texnikasi</h3><p>Konditsionerlar va split-tizimlar — Samsung, LG, Artel, Gree, Midea. Faqat joyida tekshiruvdan o‘tgan soz texnika.</p><p>' .
    $linkUz('konditsioner','Konditsionerlar xaridi') . '</p></div>' . "\n" .
'<div class="info-card"><h3>Mebel va plitalar</h3><p>Ish holatidagi divanlar, krovatlar, shkaflar va gaz plitalari.</p><p>' .
    $linkUz('mebel','Mebel xaridi') . ' · ' . $linkUz('gas-plita','Gaz plitalari xaridi') . '</p></div>' . "\n" .
'</div>' . "\n" .
'<div class="c-notice">Noutbuklar, PK va kompyuter texnikasini qabul qilmaymiz. Muzlatgich, kir mashina, TV, plita, konditsioner va mebelni qimmatga olamiz — 5 daqiqada baholash uchun {{global.phone}} ga qo‘ng‘iroq qiling.</div>' . "\n" .
'</div>';

$brandsRu =
'<div class="content-section">' . "\n" .
'<h2>Скупаем технику любых брендов</h2>' . "\n" .
'<div class="c-flex brands-list"><span class="brands-chip">Samsung</span><span class="brands-chip">LG</span><span class="brands-chip">Sony</span><span class="brands-chip">Bosch</span><span class="brands-chip">Artel</span><span class="brands-chip">Philips</span><span class="brands-chip">Panasonic</span><span class="brands-chip">Xiaomi</span></div>' . "\n" .
'</div>';

$brandsUz =
'<div class="content-section">' . "\n" .
'<h2>Har qanday brend texnikasini sotib olamiz</h2>' . "\n" .
'<div class="c-flex brands-list"><span class="brands-chip">Samsung</span><span class="brands-chip">LG</span><span class="brands-chip">Sony</span><span class="brands-chip">Bosch</span><span class="brands-chip">Artel</span><span class="brands-chip">Philips</span><span class="brands-chip">Panasonic</span><span class="brands-chip">Xiaomi</span></div>' . "\n" .
'</div>';

$districtsRu =
'<div class="content-section">' . "\n" .
'<h2>Выезд по всему Ташкенту</h2>' . "\n" .
'<p>Регулярно забираем технику во всех районах: Чиланзар, Юнусабад, Мирзо-Улугбек, Сергели, Яккасарай, Мирабад, Шайхантахур, Учтепа, Яшнабад, Бектемир — и по области по договоренности. Выезд бесплатно при сделке.</p>' . "\n" .
'</div>';

$districtsUz =
'<div class="content-section">' . "\n" .
'<h2>Butun Toshkent bo‘ylab chiqamiz</h2>' . "\n" .
'<p>Barcha tumanlardan muntazam olib ketamiz: Chilonzor, Yunusobod, Mirzo Ulug‘bek, Sergeli, Yakkasaroy, Mirobod, Shayxontohur, Uchtepa, Yashnobod, Bektemir — viloyatga kelishuv bo‘yicha. Bitimda chiqish bepul.</p>' . "\n" .
'</div>';

// FAQ answers — plain text (template escapes HTML), working-only, no new "broken" copy.
$faq51Ru = 'Мы покупаем холодильники, стиральные машины, морозильники, телевизоры, кондиционеры, газовые плиты и мебель — только в рабочем состоянии с проверкой на месте. Ноутбуки, ПК и компьютерную технику не принимаем. Оценку делаем за 5 минут, вывоз по Ташкенту бесплатно при сделке. Звоните, договоримся о времени выезда.';
$faq51Uz = 'Muzlatgichlar, kir yuvish mashinalari, morozilniklar, televizorlar, konditsionerlar, gaz plitalari va mebelni sotib olamiz — faqat ish holatida, joyida tekshiruv bilan. Noutbuk, PK va kompyuter texnikasini olmaymiz. Baholash 5 daqiqada, Toshkent bo‘ylab olib ketish bitimda bepul. Qo‘ng‘iroq qiling, chiqish vaqtini kelishamiz.';
$faq52Ru = 'Принимаем Samsung, LG, Sony, Bosch, Artel, Philips, Panasonic, Xiaomi: холодильники, стиральные машины, телевизоры, кондиционеры, газовые плиты. Главное условие — техника в рабочем состоянии, проверку делаем на месте за 5 минут. Выезд по Ташкенту бесплатно при сделке, оплату делаем сразу.';
$faq52Uz = 'Samsung, LG, Sony, Bosch, Artel, Philips, Panasonic, Xiaomi ni qabul qilamiz: muzlatgichlar, kir mashinalari, televizorlar, konditsionerlar, gaz plitalari. Asosiy shart — texnika ish holatida bo‘lishi, tekshiruvni joyida 5 daqiqada qilamiz. Toshkent bo‘ylab chiqish bitimda bepul, to‘lovni darhol qilamiz.';

// ---------------------------------------------------------------- helpers
function findSectionIndexByName($sections, $name) {
    foreach ($sections as $i => $s) {
        if (mb_strtolower(trim($s['name'])) === mb_strtolower(trim($name))) return $i;
    }
    return null;
}
function mediaKeys($html) {
    preg_match_all('/\{\{\s*media\.([a-zA-Z0-9_]+)\s*\}\}/', $html, $m);
    return array_values(array_unique($m[1] ?? []));
}
function stripInlineStyles($html) {
    // remove style="..." (both quote forms); collapse leftover double spaces before >
    $html = preg_replace('/\s+style\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace("/\s+style\s*=\s*'[^']*'/i", '', $html);
    return $html;
}

$model = new Page();
$faqModel = new FAQ();
$changes = 0;

foreach (['ru' => [$topRu, $brandsRu, $districtsRu], 'uz' => [$topUz, $brandsUz, $districtsUz]] as $lang => $drafts) {
    [$newTop, $newBrands, $newDistricts] = $drafts;
    $field = "content_{$lang}";
    $current = (string)($page[$field] ?? '');
    if ($current === '') { $log("[$lang] SKIP: {$field} empty"); continue; }
    $sections = PageSectionsHelper::splitIntoSections($current);
    $log("[$lang] sections: " . count($sections));
    foreach ($sections as $i => $s) {
        $log("[$lang]   #{$i} '{$s['name']}' chars=" . mb_strlen($s['text']) . ' hash=' . substr(md5($s['text']), 0, 8)
            . ' styles=' . preg_match_all('/\sstyle\s*=/i', $s['text'], $mm));
    }

    // 1) TOP: replace first section wholesale (audit: "Top of document", 2002 chars)
    $topIdx = findSectionIndexByName($sections, 'Top of document');
    if ($topIdx === null) $topIdx = 0;
    $oldTopName = $sections[$topIdx]['name'];
    $oldTopText = $sections[$topIdx]['text'];
    // preserve any {{media.*}} keys living in old top (re-append marker comment if missing)
    $oldMedia = mediaKeys($oldTopText);
    $guardTop = $newTop;
    foreach ($oldMedia as $k) {
        if (strpos($guardTop, 'media.' . $k) === false) {
            $guardTop .= "\n<!-- preserved media: {{media.{$k}}} -->";
            $log("[$lang]   note: old TOP had {{media.{$k}}}, preserved as comment");
        }
    }
    if (strpos($guardTop, '{{global.phone}}') === false) { $log("[$lang] FATAL: draft lost {{global.phone}}"); exit(1); }
    $sections[$topIdx]['text'] = "<!-- {$oldTopName} -->\n" . $guardTop;

    // 2) BRANDS: find section containing Dell/HP/Lenovo/brands-list, replace wholesale
    $brandIdx = null;
    foreach ($sections as $i => $s) {
        if (stripos($s['text'], 'brands-list') !== false || stripos($s['text'], 'Dell') !== false || stripos($s['text'], 'Lenovo') !== false || stripos($s['name'], 'Gallery') !== false || stripos($s['name'], 'purchased') !== false) { $brandIdx = $i; break; }
    }
    if ($brandIdx === null) {
        foreach ($sections as $i => $s) {
            if (stripos($s['text'], 'Samsung') !== false && stripos($s['text'], 'Apple') !== false) { $brandIdx = $i; break; }
        }
    }
    if ($brandIdx !== null && $brandIdx !== $topIdx) {
        $oldName = $sections[$brandIdx]['name'];
        $oldMediaB = mediaKeys($sections[$brandIdx]['text']);
        $guardBrands = $newBrands;
        foreach ($oldMediaB as $k) {
            if (strpos($guardBrands, 'media.' . $k) === false) {
                $guardBrands .= "\n<!-- preserved media: {{media.{$k}}} -->";
                $log("[$lang]   note: old BRANDS had {{media.{$k}}}, preserved as comment");
            }
        }
        $sections[$brandIdx]['text'] = "<!-- {$oldName} -->\n" . $guardBrands;
        $log("[$lang]   brands section -> #{$brandIdx} '{$oldName}'");
    } else {
        $log("[$lang]   WARN: brands section not found, skipped");
    }

    // 3) strip style="..." everywhere in content
    $styleCount = 0;
    foreach ($sections as $i => $s) {
        $clean = stripInlineStyles($s['text']);
        $styleCount += preg_match_all('/\sstyle\s*=/i', $s['text'], $mm);
        $sections[$i]['text'] = $clean;
    }
    $log("[$lang]   stripped {$styleCount} style= attributes");

    // 4) districts section: idempotent (skip if district heading already present)
    $joined = implode("\n", array_column($sections, 'text'));
    if (stripos($joined, 'Выезд по всему Ташкенту') !== false || stripos($joined, 'Butun Toshkent') !== false) {
        $log("[$lang]   districts section already present, skipped");
    } else {
        $sections[] = ['name' => ($lang === 'ru' ? 'Districts' : 'Tumanlar'), 'text' => "<!-- " . ($lang === 'ru' ? 'Districts' : 'Tumanlar') . " -->\n" . $newDistricts];
        $log("[$lang]   districts section appended");
    }

    $updated = PageSectionsHelper::rebuildContentFromSections($sections);
    // sanity: no inline styles, no computer-brand chips
    $bad = [];
    if (preg_match('/\sstyle\s*=/i', $updated)) $bad[] = 'style= remains';
    if (preg_match('/brands-chip[^<]*(Dell|HP|Lenovo)/i', $updated)) $bad[] = 'computer-brand chip remains';
    if (strpos($updated, 'Apple') !== false && preg_match('/brands-chip[^<]*Apple/i', $updated)) $bad[] = 'Apple chip remains';
    if (!empty($bad)) { $log("[$lang] FATAL: " . implode('; ', $bad)); exit(1); }

    if ($apply) {
        $model->update($pageId, [$field => $updated]);
        $fresh = $model->getById($pageId);
        $ok = (($fresh[$field] ?? '') === $updated);
        $log("[$lang]   WRITE " . ($ok ? 'verified' : 'MISMATCH') . ' chars ' . mb_strlen($current) . '->' . mb_strlen($updated));
        if ($ok) $changes++;
    } else {
        $log("[$lang]   DRY-RUN chars " . mb_strlen($current) . '->' . mb_strlen($updated) . ' md5 ' . substr(md5($updated), 0, 8));
    }
}

// ---------------------------------------------------------------- FAQs
$faqs = $db->fetchAll("SELECT * FROM faqs WHERE page_slug = ? ORDER BY sort_order ASC, id ASC", [$slug]);
$log("faqs for '{$slug}': " . count($faqs));
foreach ($faqs as $f) {
    $log("  id={$f['id']} active={$f['is_active']} sort={$f['sort_order']} q=" . mb_substr($f['question_ru'] ?? '', 0, 70));
}
$byId = [];
foreach ($faqs as $f) $byId[(int)$f['id']] = $f;

$doFaq = function ($id, $patch) use ($apply, $faqModel, &$changes, $log, $byId) {
    if (!isset($byId[$id])) { $log("  faq id={$id} NOT FOUND, skipped"); return; }
    $cur = $byId[$id];
    $data = [
        'page_slug' => $cur['page_slug'],
        'question_ru' => $patch['question_ru'] ?? $cur['question_ru'],
        'question_uz' => $patch['question_uz'] ?? $cur['question_uz'],
        'answer_ru' => $patch['answer_ru'] ?? $cur['answer_ru'],
        'answer_uz' => $patch['answer_uz'] ?? $cur['answer_uz'],
        'sort_order' => $patch['sort_order'] ?? $cur['sort_order'],
        'is_active' => $patch['is_active'] ?? $cur['is_active'],
    ];
    // UZ fallback guard mirrors FAQ::update, but we require real parity — abort on empty UZ
    if (trim($data['answer_uz']) === '' || trim($data['question_uz']) === '') { $log("  faq id={$id} FATAL: empty UZ"); exit(1); }
    if (!$apply) { $log("  faq id={$id} DRY-RUN active {$cur['is_active']}->" . $data['is_active']); return; }
    $faqModel->update($id, $data);
    $log("  faq id={$id} updated active=" . $data['is_active']);
    $changes++;
};

// id 48 (broken-technique contradiction) — deactivate, keep row (reversible, no 301)
if (isset($byId[48])) {
    $doFaq(48, ['is_active' => 0]);
} else {
    // fallback: find by question text
    foreach ($faqs as $f) {
        if (mb_stripos($f['question_ru'] ?? '', 'сломанную') !== false) {
            $doFaq((int)$f['id'], ['is_active' => 0]);
            break;
        }
    }
}
// id 51 — working categories only, plain text (template escapes HTML)
if (isset($byId[51])) {
    $doFaq(51, ['answer_ru' => $faq51Ru, 'answer_uz' => $faq51Uz]);
}
// id 52 — appliance brands only (no Dell/HP/Lenovo/Apple)
if (isset($byId[52])) {
    $doFaq(52, ['answer_ru' => $faq52Ru, 'answer_uz' => $faq52Uz]);
}

$log($apply ? "DONE: {$changes} writes applied" : 'DRY-RUN complete (no writes). Re-run with --apply to write.');
