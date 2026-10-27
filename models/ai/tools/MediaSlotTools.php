<?php
// path: ./models/ai/tools/MediaSlotTools.php
// READ-ONLY: image slots ({{media.slot_key}}) in page bodies and whether photos are attached yet.
// The AI can PLACE slots with the normal edit tools (insert_section / update_section / patch_section / str_replace_field);
// it cannot attach images — the owner does that in the page editor ("Media slots on this page") or Media → Quick add.
//
// How slots work on this site (also returned by the tool so the model never has to guess):
//   • {{media.slot_key}} in content_ru / content_uz; slot_key = [a-zA-Z0-9_-]+, the images attached to that key are
//     shared by RU and UZ (alt text/captions are per language) → put the SAME slot in both languages.
//   • empty slot → renders nothing (no gap); 1 image → <img>; 2+ → gallery; key ending _scroll/_carousel/_slider/_hscroll →
//     horizontal carousel. "hero" and "banner" are reserved (they have their own uploader, never use them as slots).

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/PageMedia.php';

class MediaSlotTools {
    private const RESERVED = ['hero', 'banner'];

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_media_slots',
                    'description' => 'Image slots ({{media.slot_key}}) found in page bodies and how many photos are attached to each (0 = still empty, waiting for the owner). '
                        . 'With slug: that page. Without slug: pages that have empty slots or slots present in only one language (up to 30 pages). Read-only — you place slots with the edit tools, you cannot attach images. '
                        . 'RULES for placing slots: (1) lowercase snake_case, descriptive and unique per page, e.g. card_fridges, photos_scroll (suffix _scroll = carousel); never hero/banner. '
                        . '(2) Put the SAME {{media.key}} in BOTH content_ru and content_uz — the photos are shared, only alt text differs. '
                        . '(3) Put each placeholder alone on its own line inside its own <p> or <div> where an image would help (after the intro, inside a feature/steps section, or one gallery near the end); 1-3 slots per page, not more. '
                        . '(4) The text must read fine with NO image (empty slots render nothing). (5) Never remove or rename an existing slot unless asked: images are attached by key.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'slug' => ['type' => 'string', 'description' => 'Page slug. Omit to scan all pages for empty / one-language slots.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        if ($name === 'list_media_slots') return self::listSlots($args);
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    /** @return string[] slot keys in an HTML body, in order of first appearance */
    private static function slotsIn(string $html): array {
        if ($html === '' || !preg_match_all('/\{\{media\.([a-zA-Z0-9_-]+)\}\}/', $html, $m)) return [];
        return array_values(array_diff(array_unique($m[1]), self::RESERVED));
    }

    /** @return array<string,int> slot key => number of attached images */
    private static function attachedCounts(int $pageId): array {
        $counts = [];
        foreach ((new PageMedia())->getPageMedia($pageId) as $row) {
            $sec = (string)($row['section'] ?? '');
            if ($sec === '' || in_array($sec, self::RESERVED, true)) continue;
            $counts[$sec] = ($counts[$sec] ?? 0) + 1;
        }
        return $counts;
    }

    private static function describe(array $page): array {
        $ru = self::slotsIn((string)($page['content_ru'] ?? ''));
        $uz = self::slotsIn((string)($page['content_uz'] ?? ''));
        $attached = self::attachedCounts((int)$page['id']);
        $slots = [];
        foreach (array_values(array_unique(array_merge($ru, $uz))) as $key) {
            $inRu = in_array($key, $ru, true);
            $inUz = in_array($key, $uz, true);
            $slots[] = [
                'slot' => $key,
                'placeholder' => '{{media.' . $key . '}}',
                'in_ru' => $inRu,
                'in_uz' => $inUz,
                'attached_images' => $attached[$key] ?? 0,
                'status' => ($attached[$key] ?? 0) > 0 ? 'filled' : 'empty',
                'problem' => $inRu && $inUz ? null : ($inRu ? 'missing in content_uz' : 'missing in content_ru'),
            ];
        }
        // images attached to a key that no longer appears in any body (orphaned: not shown on the site)
        $orphans = array_values(array_diff(array_keys($attached), $ru, $uz));
        return [
            'slug' => $page['slug'],
            'page_id' => (int)$page['id'],
            'slots' => $slots,
            'attached_but_not_in_content' => $orphans,
        ];
    }

    private static function listSlots(array $args): array {
        $pageModel = new Page();
        $slug = trim((string)($args['slug'] ?? ''));
        if ($slug !== '') {
            $page = $pageModel->getBySlug($slug, true);
            if (!$page) throw new InvalidArgumentException("Page not found: {$slug}");
            return self::describe($page);
        }

        $out = [];
        $emptyTotal = 0;
        foreach ($pageModel->getAll(true) as $page) {
            $d = self::describe($page);
            $needs = array_filter($d['slots'], static fn($s) => $s['status'] === 'empty' || $s['problem'] !== null);
            if (!$needs && !$d['attached_but_not_in_content']) continue;
            $emptyTotal += count(array_filter($d['slots'], static fn($s) => $s['status'] === 'empty'));
            $out[] = $d;
            if (count($out) >= 30) break;
        }
        return [
            'pages_needing_attention' => count($out),
            'empty_slots_total' => $emptyTotal,
            'pages' => $out,
            'note' => 'Empty slots render nothing on the site until photos are attached (page editor → "Media slots on this page", or Media → Quick add).',
        ];
    }
}
