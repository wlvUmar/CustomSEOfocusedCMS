<?php
// models/analytics/CtaStats.php
// Per-CTA catalog + read queries (views, clicks, CTR per placement). Writes live in VisitTracker::trackCta.
// Legacy totals (analytics.clicks / phone_calls) are separate and unchanged; this is an additional breakdown.

class CtaStats {
    /** id => [label for the admin table, group]. The ids are also the whitelist for POST /track-cta. */
    public const CTAS = [
        'hero_call'    => ['Герой: Позвонить',            'Герой'],
        'hero_chat'    => ['Герой: Telegram / соцсеть',   'Герой'],
        'mid_call'     => ['После 1-го блока: Позвонить', 'После 1-го блока'],
        'mid_chat'     => ['После 1-го блока: Telegram',  'После 1-го блока'],
        'bot_card'     => ['Карточка бота',               'Бот'],
        'bot_modal'    => ['Всплывающее окно бота',       'Бот'],
        'end_call'     => ['После FAQ: Позвонить',        'После FAQ'],
        'end_chat'     => ['После FAQ: Telegram',         'После FAQ'],
        'end_bot'      => ['После FAQ: Бот',              'После FAQ'],
        'review_strip' => ['Полоса отзыва',               'Отзывы'],
        'review_modal' => ['Окно отзыва после звонка',    'Отзывы'],
    ];

    public const EVENTS = ['view', 'click'];

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public static function isValid(string $ctaId, string $event): bool {
        return isset(self::CTAS[$ctaId]) && in_array($event, self::EVENTS, true);
    }

    /**
     * One row per known CTA (zeros included): views, clicks, CTR % (unique visitors per day).
     * Also returns the first date with CTA data so the admin can say "collected since".
     *
     * @return array{rows:array<int,array>,since:?string}
     */
    public function getCtaStats($startDate, $endDate, $slug = '', $utmSource = '') {
        $params = [$startDate, $endDate];
        $sql = "SELECT cta_id, event, SUM(cnt) AS total
                FROM analytics_cta
                WHERE date BETWEEN ? AND ?";
        $sql .= AnalyticsQuery::slugSql($slug, $params);
        $sql .= AnalyticsQuery::utmSql($utmSource, $params);
        $sql .= " GROUP BY cta_id, event";

        $totals = [];
        try {
            foreach ($this->db->fetchAll($sql, $params) as $r) {
                $totals[$r['cta_id']][$r['event']] = (int)$r['total'];
            }
            $first = $this->db->fetchOne("SELECT MIN(date) AS d FROM analytics_cta");
        } catch (Throwable $e) {
            // Table missing (migration not applied yet): behave as "no data" instead of breaking the page.
            error_log('CtaStats: ' . $e->getMessage());
            $first = null;
        }

        $rows = [];
        foreach (self::CTAS as $id => [$label, $group]) {
            $views = $totals[$id]['view'] ?? 0;
            $clicks = $totals[$id]['click'] ?? 0;
            $rows[] = [
                'cta_id' => $id,
                'label'  => $label,
                'group'  => $group,
                'views'  => $views,
                'clicks' => $clicks,
                'ctr'    => $views > 0 ? round(($clicks / $views) * 100, 2) : null,
            ];
        }
        return ['rows' => $rows, 'since' => $first['d'] ?? null];
    }
}
