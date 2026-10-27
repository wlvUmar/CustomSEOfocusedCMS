<?php
// path: ./models/Analytics.php
// Thin facade over models/analytics/* — keeps every existing caller working.
// Read queries: PageStats / ChartData / RotationStats / NavigationStats / UtmStats / BotStats.
// Write path: VisitTracker (events) + TrackingStore (throttle/cooldown/rollups/GC).

require_once __DIR__ . '/analytics/AnalyticsQuery.php';
require_once __DIR__ . '/analytics/PageStats.php';
require_once __DIR__ . '/analytics/ChartData.php';
require_once __DIR__ . '/analytics/RotationStats.php';
require_once __DIR__ . '/analytics/NavigationStats.php';
require_once __DIR__ . '/analytics/UtmStats.php';
require_once __DIR__ . '/analytics/BotStats.php';
require_once __DIR__ . '/analytics/CtaStats.php';
require_once __DIR__ . '/analytics/TrackingStore.php';
require_once __DIR__ . '/analytics/VisitTracker.php';

class Analytics {
    public const SITE_SLUG = '__site__';
    public const HOME_SLUG = 'main';

    private $pages;
    private $charts;
    private $rotations;
    private $navigation;
    private $utm;
    private $bots;
    private $cta;
    private $visits;
    private $store;

    public function __construct() {
        $this->pages = new PageStats();
        $this->charts = new ChartData();
        $this->rotations = new RotationStats();
        $this->navigation = new NavigationStats();
        $this->utm = new UtmStats();
        $this->bots = new BotStats();
        $this->cta = new CtaStats();
        $this->store = new TrackingStore();
        $this->visits = new VisitTracker();
    }

    public function getMonthlyData($months = 6, $slug = '', $utmSource = '') {
        return $this->pages->getMonthlyData($months, $slug, $utmSource);
    }

    public function getPageStats($months = 6, $slug = '', $utmSource = '') {
        return $this->pages->getPageStats($months, $slug, $utmSource);
    }

    public function getRangePageStats($startDate, $endDate, $slug = '', $utmSource = '') {
        return $this->pages->getRangePageStats($startDate, $endDate, $slug, $utmSource);
    }

    public function getTotalStats($months = null, $slug = '', $utmSource = '') {
        return $this->pages->getTotalStats($months, $slug, $utmSource);
    }

    public function getRangeTotalStats($startDate, $endDate, $slug = '', $utmSource = '') {
        return $this->pages->getRangeTotalStats($startDate, $endDate, $slug, $utmSource);
    }

    public function getCurrentMonthStats() {
        return $this->pages->getCurrentMonthStats();
    }

    public function getChartData($type = 'visits', $months = 6, $slug = '', $utmSource = '') {
        return $this->charts->getChartData($type, $months, $slug, $utmSource);
    }

    public function getSitewideChartData($months = 6, $pageSlug = self::SITE_SLUG, $slug = '', $utmSource = '') {
        return $this->charts->getSitewideChartData($months, $pageSlug, $slug, $utmSource);
    }

    public function getSitewideDailyChartData($type = 'visits', $months = 1, $pageSlug = self::SITE_SLUG, $slug = '', $utmSource = '') {
        return $this->charts->getSitewideDailyChartData($type, $months, $pageSlug, $slug, $utmSource);
    }

    public function getSitewideWeeklyChartData($months = 3, $pageSlug = self::SITE_SLUG, $slug = '', $utmSource = '') {
        return $this->charts->getSitewideWeeklyChartData($months, $pageSlug, $slug, $utmSource);
    }

    public function getSitewideRangeChartData($startDate, $endDate, $labelMode = 'date', $slug = '', $utmSource = '') {
        return $this->charts->getSitewideRangeChartData($startDate, $endDate, $labelMode, $slug, $utmSource);
    }

    public function getSitewideHourlyChartDataForDate($date, $slug = '', $utmSource = '') {
        return $this->charts->getSitewideHourlyChartDataForDate($date, $slug, $utmSource);
    }

    public function getRangeChartData($type, $startDate, $endDate, $labelMode = 'date', $slug = '', $utmSource = '') {
        return $this->charts->getRangeChartData($type, $startDate, $endDate, $labelMode, $slug, $utmSource);
    }

    public function getHourlyChartDataForDate($type, $date, $slug = '', $utmSource = '') {
        return $this->charts->getHourlyChartDataForDate($type, $date, $slug, $utmSource);
    }

    public function getDailyChartData($type = 'visits', $months = 1, $slug = '', $utmSource = '') {
        return $this->charts->getDailyChartData($type, $months, $slug, $utmSource);
    }

    public function getWeeklyChartData($type = 'visits', $months = 3, $slug = '', $utmSource = '') {
        return $this->charts->getWeeklyChartData($type, $months, $slug, $utmSource);
    }

    public function getRotationEffectiveness($months = 6) {
        return $this->rotations->getRotationEffectiveness($months);
    }

    public function getRotationDailyData($pageSlug, $year, $month) {
        return $this->rotations->getRotationDailyData($pageSlug, $year, $month);
    }

    public function getRotationDailyDataLast($pageSlug, $days = 30) {
        return $this->rotations->getRotationDailyDataLast($pageSlug, $days);
    }

    public function getRotationWeeklyDataLast($pageSlug, $months = 3) {
        return $this->rotations->getRotationWeeklyDataLast($pageSlug, $months);
    }

    public function getRotationMonthlyDataLast($pageSlug, $months = 12) {
        return $this->rotations->getRotationMonthlyDataLast($pageSlug, $months);
    }

    public function getCrawlFrequency($days = 30) {
        return $this->bots->getCrawlFrequency($days);
    }

    public function getDailyBotActivity($days = 30) {
        return $this->bots->getDailyBotActivity($days);
    }

    public function getBotVisitSummary($days = 30) {
        return $this->bots->getBotVisitSummary($days);
    }

    public function trackRotationShown($slug, $rotationMonth, $language) {
        return $this->rotations->trackRotationShown($slug, $rotationMonth, $language);
    }

    public function getRotationComparison($pageSlug, $months = 3) {
        return $this->rotations->getRotationComparison($pageSlug, $months);
    }

    public function getPerformanceTrends($pageSlug = null, $slug = '', $utmSource = '', $months = null) {
        return $this->utm->getPerformanceTrends($pageSlug, $slug, $utmSource, $months);
    }

    public function getPerformanceTrendsByDateRange($startDate, $endDate, $pageSlug = null, $slug = '', $utmSource = '') {
        return $this->utm->getPerformanceTrendsByDateRange($startDate, $endDate, $pageSlug, $slug, $utmSource);
    }

    public function getDailyActivity($pageSlug = null, $days = 30) {
        return $this->charts->getDailyActivity($pageSlug, $days);
    }

     public function getTopPerformers($months = 3, $limit = 500, $slug = '', $utmSource = '') {
        return $this->pages->getTopPerformers($months, $limit, $slug, $utmSource);
    }

    public function getRangeTopPerformers($startDate, $endDate, $slug = '', $utmSource = '') {
        return $this->pages->getRangeTopPerformers($startDate, $endDate, $slug, $utmSource);
    }

    public function getLanguageStats($months = 3, $slug = '', $utmSource = '') {
        return $this->pages->getLanguageStats($months, $slug, $utmSource);
    }

    public function getRangeLanguageStats($startDate, $endDate, $slug = '', $utmSource = '') {
        return $this->pages->getRangeLanguageStats($startDate, $endDate, $slug, $utmSource);
    }

    public function getPopularPaths($months = 3, $limit = 20) {
        return $this->navigation->getPopularPaths($months, $limit);
    }

    public function getOutboundLinks($slug, $months = 3) {
        return $this->navigation->getOutboundLinks($slug, $months);
    }

    public function getInboundLinks($slug, $months = 3) {
        return $this->navigation->getInboundLinks($slug, $months);
    }

    public function getLinkEffectiveness($months = 3) {
        return $this->navigation->getLinkEffectiveness($months);
    }

    public function getLinkEffectivenessStats($months = 3) {
        return $this->navigation->getLinkEffectivenessStats($months);
    }

    public function getNavigationFlow($days = 30) {
        return $this->navigation->getNavigationFlow($days);
    }

    public function getDailyNavigationTrends($days = 30) {
        return $this->navigation->getDailyNavigationTrends($days);
    }

    public function getPageSpeed($months = 1) {
        return $this->navigation->getPageSpeed($months);
    }

    public function getBounceRates($months = 3) {
        return $this->navigation->getBounceRates($months);
    }

    public function getGrowthTrends($months = 6) {
        return $this->pages->getGrowthTrends($months);
    }

     public function getRotationImpact($months = 3) {
        return $this->rotations->getRotationImpact($months);
    }

    public function getRotationStats($months = 3) {
        return $this->rotations->getRotationStats($months);
    }

    public function getNavigationStats($months = 3) {
        return $this->navigation->getNavigationStats($months);
    }

    public function getCrawlInsights($days = 7) {
        return $this->bots->getCrawlInsights($days);
    }

    public function getUtmSourceStats($startDate, $endDate, $slug = '', $utmSource = '') {
        return $this->utm->getUtmSourceStats($startDate, $endDate, $slug, $utmSource);
    }

    public function getUtmSourceByPage($startDate, $endDate, $pageSlug = null) {
        return $this->utm->getUtmSourceByPage($startDate, $endDate, $pageSlug);
    }

    public function getUtmSourceMonthlyTrends($months = 6) {
        return $this->utm->getUtmSourceMonthlyTrends($months);
    }

    public function getTopUtmSources($limit = 5, $days = 30, $slug = '', $utmSource = '') {
        return $this->utm->getTopUtmSources($limit, $days, $slug, $utmSource);
    }

    public function getUtmSourceComparison($startDate, $endDate, $slug = '', $utmSource = '') {
        return $this->utm->getUtmSourceComparison($startDate, $endDate, $slug, $utmSource);
    }

    public function trackingRateLimit(string $action, int $maxAttempts, int $windowSeconds): bool
    {
        return $this->store->trackingRateLimit($action, $maxAttempts, $windowSeconds);
    }

    public function trackSiteVisit($language) {
        $this->visits->trackSiteVisit($language);
    }

    public function shouldCountClickThisSession(string $slug, string $language, int $cooldownSeconds = 5): bool
    {
        return $this->store->shouldCountClickThisSession($slug, $language, $cooldownSeconds);
    }

    public function bumpMonthlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $isNewDay, $utmSource = ''): void
    {
        $this->store->bumpMonthlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $isNewDay, $utmSource);
    }

    public function bumpHourlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource = ''): void
    {
        $this->store->bumpHourlySummary($slug, $language, $deltaVisits, $deltaClicks, $deltaPhoneCalls, $utmSource);
    }

    public function trackVisit($slug, $language) {
        $this->visits->trackVisit($slug, $language);
    }

    public function trackBotVisit($slug, $language) {
        $this->visits->trackBotVisit($slug, $language);
    }

    public function trackClick($slug, $language, $utmSource = '') {
        $this->visits->trackClick($slug, $language, $utmSource);
    }

    public function trackPhoneCall($slug, $language, $utmSource = '') {
        $this->visits->trackPhoneCall($slug, $language, $utmSource);
    }

    public function trackCta($slug, $language, $ctaId, $event, $utmSource = '') {
        $this->visits->trackCta($slug, $language, $ctaId, $event, $utmSource);
    }

    public function getCtaStats($startDate, $endDate, $slug = '', $utmSource = '') {
        return $this->cta->getCtaStats($startDate, $endDate, $slug, $utmSource);
    }

    public function trackInternalLink($fromSlug, $toSlug, $language) {
        $this->visits->trackInternalLink($fromSlug, $toSlug, $language);
    }

    public function bumpInternalLinkMonthlySummary($fromSlug, $toSlug, $language, $deltaClicks, $isNewDay): void
    {
        $this->store->bumpInternalLinkMonthlySummary($fromSlug, $toSlug, $language, $deltaClicks, $isNewDay);
    }

    /**
     * @deprecated Legacy recompute retained for admin repair scripts; prefer bumpInternalLinkMonthlySummary.
     */
    public function updateInternalLinkMonthlySummary($fromSlug, $toSlug, $language) {
        $this->store->updateInternalLinkMonthlySummary($fromSlug, $toSlug, $language);
    }

    public function cleanupDedupTables(): void
    {
        $this->store->cleanupDedupTables();
    }
}
