<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use craft\base\Component;
use lindemannrock\base\traits\GeoLookupTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\analytics\AnalyticsBreakdownService;
use lindemannrock\redirectmanager\services\analytics\AnalyticsExportService;
use lindemannrock\redirectmanager\services\analytics\AnalyticsQueryService;
use lindemannrock\redirectmanager\services\analytics\AnalyticsTrackingService;

/**
 * Analytics Service
 *
 * Facade that delegates to focused sub-services for tracking,
 * querying, breakdowns, and export/maintenance.
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class AnalyticsService extends Component
{
    use LoggingTrait;
    use GeoLookupTrait;

    public AnalyticsTrackingService $tracking;
    public AnalyticsQueryService $query;
    public AnalyticsBreakdownService $breakdown;
    public AnalyticsExportService $export;

    /**
     * Initialize the service and sub-services
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);

        $this->export = new AnalyticsExportService();
        $this->query = new AnalyticsQueryService();
        $this->breakdown = new AnalyticsBreakdownService();
        $this->tracking = new AnalyticsTrackingService($this->export);
    }

    // ── Tracking ──────────────────────────────────────────────

    /**
     * Record a 404 error
     *
     * @param string $url
     * @param bool $handled Whether the 404 was handled by a redirect
     * @param array $context Optional context data (source plugin, metadata)
     * @return void
     * @since 5.1.0
     */
    public function record404(string $url, bool $handled, array $context = []): void
    {
        $this->tracking->record404($url, $handled, $context);
    }

    // ── Query / Listing ───────────────────────────────────────

    /**
     * Get all analytics
     *
     * @param int|array<int>|null $siteId
     * @param int|null $limit
     * @param string $orderBy
     * @return array
     * @since 5.1.0
     */
    public function getAllAnalytics(int|array|null $siteId = null, ?int $limit = null, string $orderBy = 'lastHit DESC'): array
    {
        return $this->query->getAllAnalytics($siteId, $limit, $orderBy);
    }

    /**
     * Get analytics for a specific redirect
     *
     * @param int $redirectId
     * @param string $dateRange
     * @return array
     * @since 5.1.0
     */
    public function getRedirectAnalytics(int $redirectId, string $dateRange = 'last30days'): array
    {
        return $this->query->getRedirectAnalytics($redirectId, $dateRange);
    }

    /**
     * Get unhandled 404s (not resolved by redirects)
     *
     * @param int|array<int>|null $siteId
     * @param int|null $limit
     * @return array
     * @since 5.1.0
     */
    public function getUnhandled404s(int|array|null $siteId = null, ?int $limit = null): array
    {
        return $this->query->getUnhandled404s($siteId, $limit);
    }

    /**
     * Get handled 404s (resolved by redirects)
     *
     * @param int|array<int>|null $siteId
     * @param int|null $limit
     * @return array
     * @since 5.1.0
     */
    public function getHandled404s(int|array|null $siteId = null, ?int $limit = null): array
    {
        return $this->query->getHandled404s($siteId, $limit);
    }

    /**
     * Get analytics count
     *
     * @param int|array<int>|null $siteId
     * @param bool|null $handled
     * @param int|null $days Number of days to look back (null for all time)
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return int
     * @since 5.1.0
     */
    public function getAnalyticsCount(int|array|null $siteId = null, ?bool $handled = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null): int
    {
        return $this->query->getAnalyticsCount($siteId, $handled, $days, $startDate, $endDate);
    }

    /**
     * Get analytics for dashboard charts
     *
     * @param int|array<int>|null $siteId
     * @param int $days Number of days to look back
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getChartData(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->query->getChartData($siteId, $days, $startDate, $endDate);
    }

    /**
     * Get most common 404s
     *
     * @param int|array<int>|null $siteId
     * @param int $limit
     * @param bool|null $handled
     * @param int|null $days Number of days to look back (null for all time)
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getMostCommon404s(int|array|null $siteId = null, int $limit = 10, ?bool $handled = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->query->getMostCommon404s($siteId, $limit, $handled, $days, $startDate, $endDate);
    }

    /**
     * Get recent 404s
     *
     * @param int|array<int>|null $siteId
     * @param int $limit
     * @param bool|null $handled
     * @param int|null $days Number of days to look back (null for all time)
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getRecent404s(int|array|null $siteId = null, int $limit = 10, ?bool $handled = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->query->getRecent404s($siteId, $limit, $handled, $days, $startDate, $endDate);
    }

    // ── Breakdowns ────────────────────────────────────────────

    /**
     * Get device type breakdown
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getDeviceBreakdown(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->breakdown->getDeviceBreakdown($siteId, $days, $startDate, $endDate);
    }

    /**
     * Get browser breakdown
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getBrowserBreakdown(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->breakdown->getBrowserBreakdown($siteId, $days, $startDate, $endDate);
    }

    /**
     * Get OS breakdown
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getOsBreakdown(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->breakdown->getOsBreakdown($siteId, $days, $startDate, $endDate);
    }

    /**
     * Get bot traffic stats
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getBotStats(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->breakdown->getBotStats($siteId, $days, $startDate, $endDate);
    }

    /**
     * Get top countries
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param int $limit
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.9.0
     */
    public function getTopCountries(int|array|null $siteId = null, int $days = 30, int $limit = 15, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->breakdown->getTopCountries($siteId, $days, $limit, $startDate, $endDate);
    }

    /**
     * Get top cities
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param int $limit
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.9.0
     */
    public function getTopCities(int|array|null $siteId = null, int $days = 30, int $limit = 15, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        return $this->breakdown->getTopCities($siteId, $days, $limit, $startDate, $endDate);
    }

    /**
     * Get location data from IP address
     *
     * @param string $ip
     * @return array|null
     * @since 5.9.0
     */
    public function getLocationFromIp(string $ip): ?array
    {
        return $this->breakdown->getLocationFromIp($ip);
    }

    /**
     * Get geo config from plugin settings
     *
     * @return array<string, mixed>
     */
    protected function getGeoConfig(): array
    {
        return $this->breakdown->getGeoConfig();
    }

    // ── Export / Maintenance ──────────────────────────────────

    /**
     * Get analytics data formatted for export
     *
     * @param int|array<int>|null $siteId Filter by site ID
     * @param array|null $analyticsIds Filter by specific analytics IDs
     * @param int|null $days Number of days to look back
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @param int|null $redirectId Filter by redirect ID
     * @return array Array of analytics records formatted for export
     * @since 5.0.0
     */
    public function getExportData(int|array|null $siteId = null, ?array $analyticsIds = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null, ?int $redirectId = null): array
    {
        return $this->export->getExportData($siteId, $analyticsIds, $days, $startDate, $endDate, $redirectId);
    }

    /**
     * Export analytics to CSV or JSON string
     *
     * @param int|array<int>|null $siteId
     * @param array|null $analyticsIds
     * @param int|null $days Number of days to look back
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @param string $format Export format ('csv' or 'json')
     * @return string CSV or JSON content
     * @since 5.1.0
     */
    public function exportToCsv(int|array|null $siteId = null, ?array $analyticsIds = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null, string $format = 'csv'): string
    {
        return $this->export->exportToCsv($siteId, $analyticsIds, $days, $startDate, $endDate, $format);
    }

    /**
     * Delete an analytic by ID
     *
     * @param int $id
     * @return bool
     * @since 5.1.0
     */
    public function deleteAnalytic(int $id): bool
    {
        return $this->export->deleteAnalytic($id);
    }

    /**
     * Clear all analytics
     *
     * @param int|array<int>|null $siteId
     * @return int Number of records deleted
     * @since 5.1.0
     */
    public function clearAnalytics(int|array|null $siteId = null): int
    {
        return $this->export->clearAnalytics($siteId);
    }

    /**
     * Trim analytics to respect the limit
     *
     * @return int Number of records deleted
     * @since 5.1.0
     */
    public function trimAnalytics(): int
    {
        return $this->export->trimAnalytics();
    }

    /**
     * Clean up old analytics based on retention setting
     *
     * @return int Number of records deleted
     * @since 5.1.0
     */
    public function cleanupOldAnalytics(): int
    {
        return $this->export->cleanupOldAnalytics();
    }
}
