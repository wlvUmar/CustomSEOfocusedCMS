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

class AiToolRegistry {

    /** Tool classes aggregated into the model-facing schema. */
    private const TOOL_CLASSES = [
        PageTools::class,
        RotationTools::class,
        AnalyticsTools::class,
        AnalyticsQueryTools::class,
        SiteTools::class,
        FaqTools::class,
    ];

    /**
     * Tools that change or destroy content wholesale. When the loop hits one
     * without a matching approval hash, it halts and asks the user instead of
     * executing. The approval hash is deterministic (name + args), so the
     * user's "approve" survives the next model turn.
     */
    private const GUARDED_TOOLS = [
        'set_field'    => 'Replaces the entire existing field. This is the tool you should reserve for genuinely new content or full-page rewrites.',
        'delete_faq'   => 'Permanently deletes an FAQ from the site.',
    ];

    public static function definitions(): array {
        $defs = [];
        foreach (self::TOOL_CLASSES as $class) {
            $defs = array_merge($defs, $class::definitions());
        }
        return $defs;
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
        return sha1($name . ':' . json_encode($args));
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
    public static function execute(string $name, array $args, array $approved = []): array {
        $callId = self::callId($name, $args);

        if (isset(self::GUARDED_TOOLS[$name]) && !in_array($callId, $approved, true)) {
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
                'reason' => self::GUARDED_TOOLS[$name],
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
