<?php
// path: ./models/ai/AiToolRegistry.php
// Aggregates every domain's tool classes into one flat schema for the model
// and one dispatch point for the loop. Adding a new admin domain = adding a
// new *Tools.php class and listing it in TOOL_CLASSES — the loop itself
// never changes.

require_once BASE_PATH . '/models/ai/tools/PageTools.php';
require_once BASE_PATH . '/models/ai/tools/RotationTools.php';
require_once BASE_PATH . '/models/ai/tools/AnalyticsTools.php';
require_once BASE_PATH . '/models/ai/tools/AnalyticsQueryTools.php';
require_once BASE_PATH . '/models/ai/tools/SiteTools.php';
require_once BASE_PATH . '/models/ai/tools/FaqTools.php';
require_once BASE_PATH . '/models/ai/tools/GscTools.php';
require_once BASE_PATH . '/models/ai/tools/MemoryTools.php';

class AiToolRegistry {

    /** Tool classes aggregated into the model-facing schema. */
    private const TOOL_CLASSES = [
        PageTools::class,
        RotationTools::class,
        AnalyticsTools::class,
        AnalyticsQueryTools::class,
        SiteTools::class,
        FaqTools::class,
        GscTools::class,
        MemoryTools::class,
    ];

    /**
     * Tools that change or destroy content wholesale. When the loop hits one
     * without a matching approval hash, it halts and asks the user instead of
     * executing. The approval hash is deterministic (name + args), so the
     * user's "approve" survives the next model turn.
     */
    private const GUARDED_TOOLS = [
        'set_field'             => 'Replaces the entire existing field. This is the tool you should reserve for genuinely new content or full-page rewrites.',
        'delete_faq'            => 'Permanently deletes an FAQ from the site.',
        'set_rotation'          => 'Pins a manual rotation variant as the live content for a page.',
        'restore_page_revision' => 'Restores a page to a previous snapshot — current state is saved as a new revision first, so this is undoable, but still guarded.',
    ];

    /** Tools that are read-only and safe in PLAN mode. Everything else requires BUILD mode. */
    private const PLAN_ALLOWLIST = [
        // PageTools read + revisions read + section reads (granular, untruncated)
        'list_pages', 'get_page', 'search_content', 'list_sections', 'get_section', 'get_content_chunk', 'list_page_revisions', 'get_page_revision',
        // Rotation read
        'list_rotations', 'get_rotation',
        // Analytics read
        'get_top_pages', 'get_page_stats', 'get_underperforming_pages', 'get_crawl_frequency', 'get_internal_links', 'get_rotation_effectiveness',
        'run_analytics_query', 'query_builder',
        // Site read + preview (preview is non-persistent)
        'get_global_settings', 'get_template_variables', 'get_design_tokens', 'render_preview', 'render_full_page',
        // Faq read
        'list_faqs', 'get_faq',
        // GSC read
        'get_gsc_overview', 'get_page_gsc', 'get_gsc_queries', 'get_gsc_pages', 'search_gsc_queries', 'query_gsc',
        // Memory + debug read-only
        'list_context', 'get_context', 'get_tool_logs',
    ];

    public static function definitions(): array {
        $defs = [];
        foreach (self::TOOL_CLASSES as $class) {
            $defs = array_merge($defs, $class::definitions());
        }
        return $defs;
    }

    public static function definitionsForMode(string $mode): array {
        $mode = $mode === 'build' ? 'build' : 'plan';
        if ($mode === 'build') return self::definitions();
        return array_values(array_filter(self::definitions(), fn($d) => in_array($d['function']['name'] ?? '', self::PLAN_ALLOWLIST, true)));
    }

    public static function isPlanAllowed(string $name): bool {
        return in_array($name, self::PLAN_ALLOWLIST, true);
    }

    public static function guardedTools(): array {
        return self::GUARDED_TOOLS;
    }

    /**
     * Deterministic id for one tool call. The client echoes it back in the
     * `approved` list when the user confirms, and the same args produce the
     * same id on the retry turn.
     */
    public static function callId(string $name, array $args): string {
        $sorted = self::sortKeysRecursive($args);
        return sha1($name . ':' . json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function sortKeysRecursive(array $arr): array {
        ksort($arr);
        foreach ($arr as $k => $v) {
            if (is_array($v)) $arr[$k] = self::sortKeysRecursive($v);
        }
        return $arr;
    }

    /**
     * Execute one tool call.
     *
     * @param string $name     Tool name
     * @param array  $args     Tool arguments
     * @param array  $approved List of approved call ids (from the client)
     * @return array One of:
     *   ['type'=>'result',   'name'=>.., 'call_id'=>.., 'result'=>..]
     *   ['type'=>'approval', 'name'=>.., 'call_id'=>.., 'plan'=>.., 'args'=>.., 'reason'=>..]
     *   ['type'=>'error',    'name'=>.., 'call_id'=>.., 'message'=>..]
     */
    public static function execute(string $name, array $args, array $approved = [], string $mode = 'build'): array {
        $callId = self::callId($name, $args);
        // PLAN mode enforcement: block any write tool server-side even if model hallucinates it.
        $mode = $mode === 'build' ? 'build' : 'plan';
        if ($mode === 'plan' && !self::isPlanAllowed($name)) {
            return [
                'type' => 'error',
                'name' => $name,
                'call_id' => $callId,
                'message' => "Blocked in PLAN mode — switch to BUILD mode to run '{$name}'. PLAN mode is read-only (pages, FAQs, rotations, analytics, GSC, preview).",
            ];
        }

        // BUILD mode is auto-execute — approvals disabled per owner request.
        // Guarded logic is retained only for PLAN mode (which already blocks writes) or if you re-enable it later.
        $isGuarded = false;
        $guardReason = '';
        if ($mode !== 'build') {
            $isGuarded = isset(self::GUARDED_TOOLS[$name]);
            $guardReason = self::GUARDED_TOOLS[$name] ?? '';
            // str_replace_field / patch_section are only guarded when they would replace with a large payload.
            if (!$isGuarded && in_array($name, ['str_replace_field','patch_section'], true) && isset($args['replace']) && mb_strlen((string)$args['replace']) > 800) {
                $isGuarded = true;
                $guardReason = 'Large ' . $name . ' (>800 chars) is considered destructive — requires approval.';
            }
            if (!$isGuarded && $name === 'update_section' && isset($args['html']) && mb_strlen((string)$args['html']) > 800) {
                $isGuarded = true;
                $guardReason = 'Large update_section (>800 chars) is considered destructive — requires approval.';
            }
        }
        if ($isGuarded && !in_array($callId, $approved, true)) {
            $planArgs = [];
            foreach ($args as $k => $v) {
                $planArgs[$k] = is_scalar($v) ? $v : json_encode($v);
            }
            $plan = $name . '(' . implode(', ', array_map(
                fn($k, $v) => $k . '=' . (mb_strlen((string)$v) > 60 ? mb_substr((string)$v, 0, 60) . '…' : $v),
                array_keys($planArgs),
                $planArgs
            )) . ')';
            return [
                'type' => 'approval',
                'name' => $name,
                'call_id' => $callId,
                'plan' => $plan,
                'args' => $args,
                'reason' => $guardReason,
            ];
        }

        try {
            $result = null;
            $dispatched = false;
            foreach (self::TOOL_CLASSES as $class) {
                $names = array_map(fn($d) => $d['function']['name'] ?? '', $class::definitions());
                if (in_array($name, $names, true)) {
                    $result = $class::handle($name, $args);
                    $dispatched = true;
                    break;
                }
            }
            if (!$dispatched) {
                return ['type' => 'error', 'name' => $name, 'call_id' => $callId, 'message' => "Unknown tool: {$name}"];
            }
            return ['type' => 'result', 'name' => $name, 'call_id' => $callId, 'result' => $result];
        } catch (\Throwable $e) {
            return ['type' => 'error', 'name' => $name, 'call_id' => $callId, 'message' => $e->getMessage()];
        }
    }
}
