<?php
// path: ./models/GscClient.php
// Google Search Console API client for AI Studio (MCP-like).
// Thin facade — implementation lives in models/gsc/ (one domain per file):
//   GscAuth (OAuth URL/exchange/refresh+lock), GscTokenStore (HKDF
//   encrypt/decrypt, token CRUD), GscCache (file cache + GC),
//   GscSearchApi (searchAnalytics/listSites/slugFromPage).
// Existing callers (AiStudio, GscAuthController, GscTools) use this class unchanged.
// Env: GSC_CLIENT_ID, GSC_CLIENT_SECRET, GSC_SITE_URL (e.g. https://kuplyu-tashkent.uz/ or sc-domain:kuplyu-tashkent.uz)
// Token store: `gsc_tokens` table (single row id=1, encrypted at rest if GSC_ENCRYPTION_KEY set).

require_once BASE_PATH . '/models/gsc/GscTokenStore.php';
require_once BASE_PATH . '/models/gsc/GscAuth.php';
require_once BASE_PATH . '/models/gsc/GscCache.php';
require_once BASE_PATH . '/models/gsc/GscSearchApi.php';

class GscClient {

    public static function isConfigured(): bool {
        return GscAuth::isConfigured();
    }

    public static function getClientId(): string {
        return GscAuth::getClientId();
    }

    public static function getClientSecret(): string {
        return GscAuth::getClientSecret();
    }

    public static function getSiteUrl(): string {
        return GscTokenStore::getSiteUrl();
    }

    public static function getSiteUrlFor(?string $override): string {
        return GscTokenStore::getSiteUrlFor($override);
    }

    public static function getRedirectUri(): string {
        return GscAuth::getRedirectUri();
    }

    public static function getAuthUrl(string $state): string {
        return GscAuth::getAuthUrl($state);
    }

    public static function deriveKey(string $key): string {
        return GscTokenStore::deriveKey($key);
    }

    public static function saveRefreshToken(string $refreshToken, ?string $siteUrl = null, ?string $email = null): void {
        GscTokenStore::saveRefreshToken($refreshToken, $siteUrl, $email);
    }

    public static function getRefreshToken(): ?string {
        return GscTokenStore::getRefreshToken();
    }

    public static function isConnected(): bool {
        return GscTokenStore::isConnected();
    }

    public static function getStatus(): array {
        return GscTokenStore::getStatus();
    }

    public static function disconnect(): void {
        GscTokenStore::disconnect();
    }

    public static function getAccessToken(): ?string {
        return GscTokenStore::getAccessToken();
    }

    public static function exchangeCode(string $code): ?array {
        return GscAuth::exchangeCode($code);
    }

    public static function clearCache(): void {
        GscCache::clearCache();
    }

    /**
     * Query Search Analytics.
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param string[] $dimensions e.g. ['query'], ['page'], ['query','page']
     * @param array $dimensionFilterGroups GSC filter groups
     * @param int $rowLimit max 25000 (we default 1000-5000)
     * @return array|null rows or null on auth failure
     */
    public static function searchAnalytics(string $startDate, string $endDate, array $dimensions = [], array $dimensionFilterGroups = [], int $rowLimit = 1000, string $aggregationType = 'auto', ?string $siteUrlOverride = null, array $options = []): ?array {
        return GscSearchApi::searchAnalytics($startDate, $endDate, $dimensions, $dimensionFilterGroups, $rowLimit, $aggregationType, $siteUrlOverride, $options);
    }

    public static function sitemaps(?string $siteUrlOverride = null): ?array {
        return GscSearchApi::sitemaps($siteUrlOverride);
    }

    public static function inspectUrl(string $inspectionUrl, ?string $siteUrlOverride = null, string $languageCode = 'ru'): ?array {
        return GscSearchApi::inspectUrl($inspectionUrl, $siteUrlOverride, $languageCode);
    }

    public static function listSites(): ?array {
        return GscSearchApi::listSites();
    }

    public static function slugFromPage(string $pageUrl): string {
        return GscSearchApi::slugFromPage($pageUrl);
    }
}
