<?php
// path: ./models/ai/tools/LinkWidgetTools.php
// AI Studio tools for internal link widgets (page_link_widgets).
// Reads are PLAN-safe; writes require BUILD. link_auto_connect is guarded (approval).

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/LinkWidget.php';
require_once BASE_PATH . '/models/InternalLinkRepository.php';
require_once BASE_PATH . '/models/InternalLinkScorer.php';

class LinkWidgetTools {

    public static function definitions(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'link_list',
                    'description' => 'List internal link-widget connections for one page (outgoing + incoming) with widget on/off flag. PLAN-safe read. Use slug (preferred) or page_id.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'link_add',
                    'description' => 'Connect ONE internal link: source page -> target page. BUILD-only. Enables widget on source. For 2+ links use link_auto_connect once — never chain link_add call-by-call (each call costs a full turn).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Source page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Source page slug (alternative to page_id).'],
                            'target_page_id' => ['type' => 'integer', 'description' => 'Target page id.'],
                            'target_slug' => ['type' => 'string', 'description' => 'Target page slug (alternative to target_page_id).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'link_remove',
                    'description' => 'Disconnect one internal link: source page -> target page. BUILD-only. Removes the row; widget flag untouched.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Source page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Source page slug (alternative to page_id).'],
                            'target_page_id' => ['type' => 'integer', 'description' => 'Target page id.'],
                            'target_slug' => ['type' => 'string', 'description' => 'Target page slug (alternative to target_page_id).'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'link_toggle_widget',
                    'description' => 'Show/hide the link-widget carousel on a page (pages.show_link_widget). BUILD-only. Links stay stored when hidden.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Numeric page id.'],
                            'slug' => ['type' => 'string', 'description' => 'Page slug (alternative to page_id).'],
                            'show' => ['type' => 'boolean', 'description' => 'true = show widget, false = hide.'],
                        ],
                        'required' => ['show'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'link_auto_connect',
                    'description' => 'Bulk-link in ONE call (preferred over any link_add chain): auto-connect one page (scope=page) or all orphans (scope=orphans) to parent/children/siblings + keyword-similar pages, up to max_links. Guarded: requires approval. Always dry_run=true first, then one real run.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'page_id' => ['type' => 'integer', 'description' => 'Source page id (omit with scope=orphans for site-wide orphan pass).'],
                            'slug' => ['type' => 'string', 'description' => 'Source page slug (alternative to page_id).'],
                            'scope' => ['type' => 'string', 'enum' => ['page', 'orphans'], 'description' => 'page = one page (default), orphans = all orphan pages.'],
                            'max_links' => ['type' => 'integer', 'description' => 'Max outgoing links per page (default 5, max 20).'],
                            'dry_run' => ['type' => 'boolean', 'description' => 'true = preview only, no writes (default false).'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        switch ($name) {
            case 'link_list': return self::listLinks($args);
            case 'link_add': return self::add($args);
            case 'link_remove': return self::remove($args);
            case 'link_toggle_widget': return self::toggle($args);
            case 'link_auto_connect': return self::autoConnect($args);
        }
        throw new InvalidArgumentException("Unknown tool: {$name}");
    }

    private static function resolvePage(array $args, string $idKey = 'page_id', string $slugKey = 'slug'): array {
        $pageModel = new Page();
        if (!empty($args[$idKey])) {
            $page = $pageModel->getById((int)$args[$idKey]);
            if (!$page) throw new InvalidArgumentException("Page id {$args[$idKey]} not found");
            return $page;
        }
        if (!empty($args[$slugKey])) {
            $page = $pageModel->getBySlug(trim((string)$args[$slugKey]), true);
            if (!$page) throw new InvalidArgumentException("Page slug '{$args[$slugKey]}' not found");
            return $page;
        }
        throw new InvalidArgumentException("Provide $idKey or $slugKey");
    }

    private static function listLinks(array $args): array {
        $page = self::resolvePage($args);
        $widget = new LinkWidget();
        $repo = new InternalLinkRepository();
        return [
            'page_id' => (int)$page['id'],
            'slug' => $page['slug'],
            'show_link_widget' => (int)($page['show_link_widget'] ?? 0),
            'outgoing' => array_map(function ($r) {
                return ['id' => (int)$r['link_to_page_id'], 'slug' => $r['slug'] ?? '', 'title_ru' => $r['title_ru'] ?? '', 'title_uz' => $r['title_uz'] ?? ''];
            }, $widget->getLinksForPage($page['id'])),
            'incoming' => array_map(function ($r) {
                return ['id' => (int)$r['page_id'], 'slug' => $r['slug'] ?? '', 'title_ru' => $r['title_ru'] ?? ''];
            }, $repo->getIncomingLinks($page['id'])),
        ];
    }

    private static function add(array $args): array {
        $src = self::resolvePage($args, 'page_id', 'slug');
        $tgt = self::resolvePage($args, 'target_page_id', 'target_slug');
        if ((int)$src['id'] === (int)$tgt['id']) throw new InvalidArgumentException("Cannot link a page to itself");
        $widget = new LinkWidget();
        foreach ($widget->getLinksForPage($src['id']) as $r) {
            if ((int)($r['link_to_page_id'] ?? 0) === (int)$tgt['id']) {
                return ['ok' => true, 'note' => 'already linked', 'page_id' => (int)$src['id'], 'target_page_id' => (int)$tgt['id']];
            }
        }
        $widget->addLink($src['id'], $tgt['id']);
        if (empty($src['show_link_widget'])) $widget->toggleWidget($src['id'], true);
        return ['ok' => true, 'page_id' => (int)$src['id'], 'target_page_id' => (int)$tgt['id'], 'widget_enabled' => true];
    }

    private static function remove(array $args): array {
        $src = self::resolvePage($args, 'page_id', 'slug');
        $tgt = self::resolvePage($args, 'target_page_id', 'target_slug');
        $widget = new LinkWidget();
        $widget->removeLink($src['id'], $tgt['id']);
        return ['ok' => true, 'page_id' => (int)$src['id'], 'target_page_id' => (int)$tgt['id']];
    }

    private static function toggle(array $args): array {
        if (!array_key_exists('show', $args)) throw new InvalidArgumentException("show is required (true/false)");
        $page = self::resolvePage($args);
        $show = filter_var($args['show'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($show === null) throw new InvalidArgumentException("show must be true or false");
        (new LinkWidget())->toggleWidget($page['id'], $show);
        return ['ok' => true, 'page_id' => (int)$page['id'], 'show_link_widget' => $show ? 1 : 0];
    }

    private static function autoConnect(array $args): array {
        $scope = $args['scope'] ?? 'page';
        $maxLinks = max(1, min(20, (int)($args['max_links'] ?? 5)));
        $dryRun = filter_var($args['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pageModel = new Page();
        $widget = new LinkWidget();
        $repo = new InternalLinkRepository();

        if ($scope === 'orphans') {
            $stats = $repo->getNetworkStats();
            $all = $repo->getAllPagesWithLinkStats();
            $targets = array_values(array_filter($all, function ($p) {
                return ((int)($p['outgoing_links'] ?? 0) === 0 && (int)($p['incoming_links'] ?? 0) === 0);
            }));
        } else {
            $page = self::resolvePage($args);
            $targets = [['id' => $page['id'], 'slug' => $page['slug']]];
            $stats = null;
        }

        $planned = [];
        $allPages = $pageModel->getAll(false);
        foreach ($targets as $t) {
            $pid = (int)$t['id'];
            $src = $pageModel->getById($pid);
            if (!$src) continue;
            $existing = array_map(function ($r) { return (int)($r['link_to_page_id'] ?? 0); }, $widget->getLinksForPage($pid));
            $links = [];
            if (!empty($src['parent_id'])) $links[] = (int)$src['parent_id'];
            foreach ($pageModel->getChildren($pid, true) as $c) $links[] = (int)$c['id'];
            foreach (array_slice($pageModel->getSiblings($pid, true), 0, 3) as $s) $links[] = (int)$s['id'];
            $links = array_values(array_unique(array_filter($links, function ($id) use ($pid, $existing) {
                return $id !== $pid && !in_array($id, $existing, true);
            })));
            if (count($links) < $maxLinks) {
                $keywords = InternalLinkScorer::extractKeywords(($src['title_ru'] ?? '') . ' ' . ($src['content_ru'] ?? ''));
                if (!empty($keywords)) {
                    $exclude = array_merge($links, $existing, [$pid]);
                    $related = $repo->findRelatedByKeywords($pid, $keywords, $maxLinks - count($links), $exclude);
                    $links = array_merge($links, $related);
                }
            }
            if (empty($existing) && empty($links)) {
                $smart = InternalLinkScorer::findRelatedPagesSmart($src, $allPages, $maxLinks);
                $links = array_values(array_filter($smart, function ($id) use ($pid, $existing) {
                    return (int)$id !== $pid && !in_array((int)$id, $existing, true);
                }));
            }
            $links = array_values(array_slice(array_unique(array_map('intval', $links)), 0, $maxLinks));
            $planned[] = ['page_id' => $pid, 'slug' => $src['slug'] ?? '', 'links' => $links];
        }

        if ($dryRun) return ['dry_run' => true, 'max_links' => $maxLinks, 'planned' => $planned, 'network' => $stats];

        $created = 0;
        $enabled = 0;
        foreach ($planned as $p) {
            $added = 0;
            foreach ($p['links'] as $tid) {
                try {
                    $widget->addLink($p['page_id'], $tid);
                    $created++;
                    $added++;
                } catch (Throwable $e) {
                    continue;
                }
            }
            if ($added > 0) {
                $cur = $pageModel->getById($p['page_id']);
                if (empty($cur['show_link_widget'])) {
                    $widget->toggleWidget($p['page_id'], true);
                    $enabled++;
                }
            }
        }
        return ['ok' => true, 'created' => $created, 'widgets_enabled' => $enabled, 'pages' => count($planned)];
    }
}
