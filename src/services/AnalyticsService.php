<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\GeoHelper;
use lindemannrock\base\traits\GeoLookupTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\records\AnalyticsRecord;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Analytics Service
 *
 * Tracks 404 errors and provides analytics data
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class AnalyticsService extends Component
{
    use LoggingTrait;
    use GeoLookupTrait;

    /**
     * Initialize the service
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

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
        $settings = RedirectManager::$plugin->getSettings();

        // Check if analytics is enabled (master switch)
        if (!$settings->enableAnalytics) {
            return;
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        // Extract source plugin and redirectId from context
        $sourcePlugin = $context['source'] ?? 'redirect-manager';
        $redirectId = $context['redirectId'] ?? null;

        // Strip query string from URL if configured
        $urlParsed = $settings->stripQueryStringFromStats
            ? strtok($url, '?')
            : $url;

        // Clean the URL
        $urlParsed = $this->cleanUrl($urlParsed);

        // Get referrer, IP, and user agent
        $request = Craft::$app->getRequest();
        $referrer = $request->getReferrer();
        $userAgent = $request->getUserAgent();

        // Detect device information using Matomo DeviceDetector
        $deviceInfo = RedirectManager::$plugin->deviceDetection->detectDevice($userAgent);

        // Multi-step IP processing for privacy and geo detection
        // IP is always captured when analytics is enabled (like smart-links)
        $ip = null;
        $geoData = null;
        $rawIp = $request->getUserIP();

        // Step 1: Subnet masking (if anonymizeIpAddress enabled)
        if ($settings->anonymizeIpAddress && $rawIp) {
            $rawIp = $this->_anonymizeIp($rawIp);
        }

        // Step 2: Get geo location (BEFORE hashing, using anonymized or full IP)
        if ($settings->enableGeoDetection && $rawIp) {
            $geoData = $this->getLocationFromIp($rawIp);
        }

        // Step 3: Hash with salt for storage
        if ($rawIp) {
            try {
                $ip = $this->_hashIpWithSalt($rawIp);
            } catch (\Exception $e) {
                $this->logError('Failed to hash IP address', ['error' => $e->getMessage()]);
                $ip = null;
            }
        }

        // Check if record exists
        $existing = (new Query())
            ->from(AnalyticsRecord::tableName())
            ->where([
                'urlParsed' => $urlParsed,
                'siteId' => $siteId,
            ])
            ->one();

        if ($existing) {
            // Update existing record
            Craft::$app->getDb()->createCommand()
                ->update(
                    AnalyticsRecord::tableName(),
                    [
                        'count' => new \yii\db\Expression('[[count]] + 1'),
                        'url' => $url, // Update to latest URL (preserves most recent query string)
                        'handled' => $handled,
                        'redirectId' => $redirectId,
                        'sourcePlugin' => $sourcePlugin,
                        'referrer' => $referrer,
                        'ip' => $ip,
                        'userAgent' => $userAgent,
                        // Device detection fields
                        'deviceType' => $deviceInfo['deviceType'],
                        'deviceBrand' => $deviceInfo['deviceBrand'],
                        'deviceModel' => $deviceInfo['deviceModel'],
                        'browser' => $deviceInfo['browser'],
                        'browserVersion' => $deviceInfo['browserVersion'],
                        'browserEngine' => $deviceInfo['browserEngine'],
                        'osName' => $deviceInfo['osName'],
                        'osVersion' => $deviceInfo['osVersion'],
                        'clientType' => $deviceInfo['clientType'],
                        'isRobot' => $deviceInfo['isRobot'],
                        'isMobileApp' => $deviceInfo['isMobileApp'],
                        'botName' => $deviceInfo['botName'],
                        // Geographic data
                        'country' => $geoData['countryCode'] ?? null,
                        'city' => $geoData['city'] ?? null,
                        'region' => $geoData['region'] ?? null,
                        'latitude' => $geoData['lat'] ?? null,
                        'longitude' => $geoData['lon'] ?? null,
                        'lastHit' => Db::prepareDateForDb(new \DateTime()),
                        'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
                    ],
                    ['id' => $existing['id']]
                )
                ->execute();

            $this->logDebug('Updated 404 analytics record', ['url' => $url, 'urlParsed' => $urlParsed, 'count' => $existing['count'] + 1, 'source' => $sourcePlugin]);
        } else {
            // Create new record
            $record = new AnalyticsRecord();
            $record->siteId = $siteId;
            $record->url = $url;
            $record->urlParsed = $urlParsed;
            $record->handled = $handled;
            $record->redirectId = $redirectId;
            $record->sourcePlugin = $sourcePlugin;
            $record->count = 1;
            $record->referrer = $referrer;
            $record->ip = $ip;
            $record->userAgent = $userAgent;
            // Device detection fields
            $record->deviceType = $deviceInfo['deviceType'];
            $record->deviceBrand = $deviceInfo['deviceBrand'];
            $record->deviceModel = $deviceInfo['deviceModel'];
            $record->browser = $deviceInfo['browser'];
            $record->browserVersion = $deviceInfo['browserVersion'];
            $record->browserEngine = $deviceInfo['browserEngine'];
            $record->osName = $deviceInfo['osName'];
            $record->osVersion = $deviceInfo['osVersion'];
            $record->clientType = $deviceInfo['clientType'];
            $record->isRobot = $deviceInfo['isRobot'];
            $record->isMobileApp = $deviceInfo['isMobileApp'];
            $record->botName = $deviceInfo['botName'];
            // Geographic data
            $record->country = $geoData['countryCode'] ?? null;
            $record->city = $geoData['city'] ?? null;
            $record->region = $geoData['region'] ?? null;
            $record->latitude = $geoData['lat'] ?? null;
            $record->longitude = $geoData['lon'] ?? null;
            $record->lastHit = Db::prepareDateForDb(new \DateTime());

            if ($record->save()) {
                $this->logDebug('Created 404 analytics record', ['url' => $url, 'urlParsed' => $urlParsed, 'source' => $sourcePlugin]);

                // Check if we need to trim analytics
                if ($settings->autoTrimAnalytics) {
                    $this->trimAnalytics();
                }
            } else {
                $this->logError('Failed to save 404 analytics record', ['errors' => $record->getErrors()]);
            }
        }
    }

    /**
     * Get all analytics
     *
     * @param int|null $siteId
     * @param int|null $limit
     * @param string $orderBy
     * @return array
     * @since 5.1.0
     */
    public function getAllAnalytics(?int $siteId = null, ?int $limit = null, string $orderBy = 'lastHit DESC'): array
    {
        $query = (new Query())
            ->from(AnalyticsRecord::tableName());

        if ($siteId !== null) {
            $query->where(['siteId' => $siteId]);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->orderBy($orderBy);

        return $query->all();
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
        $dateCondition = $this->getDateRangeCondition($dateRange);

        // Get total hits
        $query = (new Query())
            ->from(AnalyticsRecord::tableName())
            ->where(['redirectId' => $redirectId]);

        if ($dateCondition) {
            $query->andWhere($dateCondition);
        }

        $records = $query->all();

        $totalHits = 0;
        $deviceBreakdown = [];
        $browserBreakdown = [];
        $countryBreakdown = [];
        $referrerBreakdown = [];
        $botVsHuman = ['human' => 0, 'bot' => 0];

        foreach ($records as $record) {
            $totalHits += (int)$record['count'];

            // Device breakdown
            $device = $record['deviceType'] ?: 'Unknown';
            $deviceBreakdown[$device] = ($deviceBreakdown[$device] ?? 0) + (int)$record['count'];

            // Browser breakdown
            $browser = $record['browser'] ?: 'Unknown';
            $browserBreakdown[$browser] = ($browserBreakdown[$browser] ?? 0) + (int)$record['count'];

            // Country breakdown - convert code to name
            $countryCode = $record['country'] ?: '';
            $country = $countryCode ? GeoHelper::getCountryName($countryCode) : 'Unknown';
            $countryBreakdown[$country] = ($countryBreakdown[$country] ?? 0) + (int)$record['count'];

            // Referrer breakdown
            if ($record['referrer']) {
                $referrerHost = parse_url($record['referrer'], PHP_URL_HOST) ?: $record['referrer'];
                $referrerBreakdown[$referrerHost] = ($referrerBreakdown[$referrerHost] ?? 0) + (int)$record['count'];
            }

            // Bot vs human
            if ($record['isRobot']) {
                $botVsHuman['bot'] += (int)$record['count'];
            } else {
                $botVsHuman['human'] += (int)$record['count'];
            }
        }

        // Sort breakdowns by count descending
        arsort($deviceBreakdown);
        arsort($browserBreakdown);
        arsort($countryBreakdown);
        arsort($referrerBreakdown);

        return [
            'totalHits' => $totalHits,
            'recordCount' => count($records),
            'deviceBreakdown' => array_slice($deviceBreakdown, 0, 10, true),
            'browserBreakdown' => array_slice($browserBreakdown, 0, 10, true),
            'countryBreakdown' => array_slice($countryBreakdown, 0, 10, true),
            'referrerBreakdown' => array_slice($referrerBreakdown, 0, 10, true),
            'botVsHuman' => $botVsHuman,
        ];
    }

    /**
     * Get unhandled 404s (not resolved by redirects)
     *
     * @param int|null $siteId
     * @param int|null $limit
     * @return array
     * @since 5.1.0
     */
    public function getUnhandled404s(?int $siteId = null, ?int $limit = null): array
    {
        $query = (new Query())
            ->from(AnalyticsRecord::tableName())
            ->where(['handled' => false]);

        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->orderBy(['count' => SORT_DESC, 'lastHit' => SORT_DESC]);

        return $query->all();
    }

    /**
     * Get handled 404s (resolved by redirects)
     *
     * @param int|null $siteId
     * @param int|null $limit
     * @return array
     * @since 5.1.0
     */
    public function getHandled404s(?int $siteId = null, ?int $limit = null): array
    {
        $query = (new Query())
            ->from(AnalyticsRecord::tableName())
            ->where(['handled' => true]);

        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->orderBy(['count' => SORT_DESC, 'lastHit' => SORT_DESC]);

        return $query->all();
    }

    /**
     * Get analytics count
     *
     * @param int|null $siteId
     * @param bool|null $handled
     * @param int|null $days Number of days to look back (null for all time)
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return int
     * @since 5.1.0
     */
    public function getAnalyticsCount(?int $siteId = null, ?bool $handled = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null): int
    {
        $query = (new Query())
            ->from(AnalyticsRecord::tableName());

        if ($siteId !== null) {
            $query->where(['siteId' => $siteId]);
        }

        if ($handled !== null) {
            $query->andWhere(['handled' => $handled]);
        }

        // Use explicit date range if provided
        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        }
        // Otherwise fall back to days parameter
        elseif ($days !== null && $days < 36500) { // 36500 = "all time"
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        return (int)$query->count();
    }

    /**
     * Get analytics for dashboard charts
     *
     * @param int|null $siteId
     * @param int $days Number of days to look back
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getChartData(?int $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select([
                'DATE(lastHit) as date',
                'COUNT(*) as total',
                'SUM(CASE WHEN handled = 1 THEN 1 ELSE 0 END) as handled',
                'SUM(CASE WHEN handled = 0 THEN 1 ELSE 0 END) as unhandled',
            ])
            ->from(AnalyticsRecord::tableName())
            ->groupBy('DATE(lastHit)')
            ->orderBy('date ASC');

        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        }
        // Otherwise fall back to days parameter
        elseif ($days < 36500) {
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }

        return $query->all();
    }

    /**
     * Get most common 404s
     *
     * @param int|null $siteId
     * @param int $limit
     * @param bool|null $handled
     * @param int|null $days Number of days to look back (null for all time)
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getMostCommon404s(?int $siteId = null, int $limit = 10, ?bool $handled = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->from(AnalyticsRecord::tableName())
            ->orderBy(['count' => SORT_DESC])
            ->limit($limit);

        if ($siteId !== null) {
            $query->where(['siteId' => $siteId]);
        }

        if ($handled !== null) {
            $query->andWhere(['handled' => $handled]);
        }

        // Use explicit date range if provided
        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        }
        // Otherwise fall back to days parameter
        elseif ($days !== null && $days < 36500) { // 36500 = "all time"
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        $results = $query->all();

        // Convert lastHit dates from UTC to user's timezone
        foreach ($results as &$result) {
            if (!empty($result['lastHit'])) {
                $utcDate = new \DateTime($result['lastHit'], new \DateTimeZone('UTC'));
                $utcDate->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
                $result['lastHit'] = $utcDate;
                $result['lastHitFormatted'] = Craft::$app->getFormatter()->asDatetime($utcDate, 'short');
            }
        }

        return $results;
    }

    /**
     * Get recent 404s
     *
     * @param int|null $siteId
     * @param int $limit
     * @param bool|null $handled
     * @param int|null $days Number of days to look back (null for all time)
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getRecent404s(?int $siteId = null, int $limit = 10, ?bool $handled = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->from(AnalyticsRecord::tableName())
            ->orderBy(['lastHit' => SORT_DESC])
            ->limit($limit);

        if ($siteId !== null) {
            $query->where(['siteId' => $siteId]);
        }

        if ($handled !== null) {
            $query->andWhere(['handled' => $handled]);
        }

        // Use explicit date range if provided
        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        }
        // Otherwise fall back to days parameter
        elseif ($days !== null && $days < 36500) { // 36500 = "all time"
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        $results = $query->all();

        // Convert lastHit dates from UTC to user's timezone
        foreach ($results as &$result) {
            if (!empty($result['lastHit'])) {
                $utcDate = new \DateTime($result['lastHit'], new \DateTimeZone('UTC'));
                $utcDate->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
                $result['lastHit'] = $utcDate;
                $result['lastHitFormatted'] = Craft::$app->getFormatter()->asDatetime($utcDate, 'short');
            }
        }

        return $results;
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
        $record = AnalyticsRecord::findOne($id);

        if (!$record) {
            $this->logError('Analytics record not found', ['id' => $id]);
            return false;
        }

        if ($record->delete()) {
            $this->logInfo('Analytics record deleted', ['id' => $id]);
            return true;
        }

        return false;
    }

    /**
     * Clear all analytics
     *
     * @param int|null $siteId
     * @return int Number of records deleted
     * @since 5.1.0
     */
    public function clearAnalytics(?int $siteId = null): int
    {
        if ($siteId !== null) {
            $count = Craft::$app->getDb()->createCommand()
                ->delete(AnalyticsRecord::tableName(), ['siteId' => $siteId])
                ->execute();
        } else {
            $count = Craft::$app->getDb()->createCommand()
                ->delete(AnalyticsRecord::tableName())
                ->execute();
        }

        $this->logInfo('Analytics cleared', ['count' => $count, 'siteId' => $siteId]);

        return $count;
    }

    /**
     * Trim analytics to respect the limit
     *
     * @return int Number of records deleted
     * @since 5.1.0
     */
    public function trimAnalytics(): int
    {
        $settings = RedirectManager::$plugin->getSettings();
        $limit = $settings->analyticsLimit;

        // Get current count
        $currentCount = (new Query())
            ->from(AnalyticsRecord::tableName())
            ->count();

        if ($currentCount <= $limit) {
            return 0;
        }

        // Get IDs to delete (oldest by lastHit, lowest count)
        $idsToDelete = (new Query())
            ->select(['id'])
            ->from(AnalyticsRecord::tableName())
            ->orderBy(['lastHit' => SORT_ASC, 'count' => SORT_ASC])
            ->limit($currentCount - $limit)
            ->column();

        if (empty($idsToDelete)) {
            return 0;
        }

        // Delete the records
        $deleted = Craft::$app->getDb()->createCommand()
            ->delete(AnalyticsRecord::tableName(), ['in', 'id', $idsToDelete])
            ->execute();

        $this->logInfo('Trimmed analytics', ['deleted' => $deleted]);

        return $deleted;
    }

    /**
     * Clean up old analytics based on retention setting
     *
     * @return int Number of records deleted
     * @since 5.1.0
     */
    public function cleanupOldAnalytics(): int
    {
        $settings = RedirectManager::$plugin->getSettings();
        $retention = $settings->analyticsRetention;

        if ($retention <= 0) {
            return 0;
        }

        $date = (new \DateTime())->modify("-{$retention} days");

        $deleted = Craft::$app->getDb()->createCommand()
            ->delete(
                AnalyticsRecord::tableName(),
                ['<', 'lastHit', Db::prepareDateForDb($date)]
            )
            ->execute();

        if ($deleted > 0) {
            $this->logInfo('Cleaned up old analytics', ['deleted' => $deleted, 'retention' => $retention]);
        }

        return $deleted;
    }

    /**
     * Get analytics data formatted for export
     *
     * @param int|null $siteId Filter by site ID
     * @param array|null $analyticsIds Filter by specific analytics IDs
     * @param int|null $days Number of days to look back
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array Array of analytics records formatted for export
     * @since 5.0.0
     */
    public function getExportData(?int $siteId = null, ?array $analyticsIds = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null, ?int $redirectId = null): array
    {
        // Build query
        $query = (new \craft\db\Query())
            ->from(AnalyticsRecord::tableName())
            ->orderBy(['lastHit' => SORT_DESC]);

        // Filter by specific IDs if provided
        if (!empty($analyticsIds)) {
            $query->where(['in', 'id', $analyticsIds]);
        }

        // Filter by redirect
        if ($redirectId !== null) {
            $query->andWhere(['redirectId' => $redirectId]);
        }

        // Filter by site
        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }

        // Apply date filtering
        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        } elseif ($days !== null && $days < 36500) {
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        $analytics = $query->all();

        // Format data for export
        $exportData = [];
        foreach ($analytics as $stat) {
            // Get site name
            $site = Craft::$app->sites->getSiteById($stat['siteId']);
            $siteName = $site ? $site->name : '-';

            $exportData[] = [
                'url' => $stat['url'],
                'siteId' => $stat['siteId'],
                'siteName' => $siteName,
                'count' => (int)$stat['count'],
                'handled' => $stat['handled'] ? 'Yes' : 'No',
                'referrer' => $stat['referrer'] ?? '',
                'deviceType' => $stat['deviceType'] ?? '',
                'browser' => $stat['browser'] ?? '',
                'os' => $stat['osName'] ?? '',
                'country' => GeoHelper::getCountryName($stat['country'] ?? ''),
                'city' => $stat['city'] ?? '',
                'isRobot' => $stat['isRobot'] ? 'Yes' : 'No',
                'botName' => $stat['botName'] ?? '',
                'lastHit' => $stat['lastHit'],
            ];
        }

        return $exportData;
    }

    /**
     * Export analytics to CSV or JSON string
     *
     * @param int|null $siteId
     * @param array|null $analyticsIds
     * @param int|null $days Number of days to look back
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @param string $format Export format ('csv' or 'json')
     * @return string CSV or JSON content
     * @since 5.1.0
     */
    public function exportToCsv(?int $siteId = null, ?array $analyticsIds = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null, string $format = 'csv'): string
    {
        // If specific IDs provided, fetch only those
        if (!empty($analyticsIds)) {
            $query = (new \craft\db\Query())
                ->from(AnalyticsRecord::tableName())
                ->where(['in', 'id', $analyticsIds])
                ->orderBy(['lastHit' => SORT_DESC]);

            if ($siteId) {
                $query->andWhere(['siteId' => $siteId]);
            }

            $analytics = $query->all();
        } else {
            // Build query with date range filtering
            $query = (new \craft\db\Query())
                ->from(AnalyticsRecord::tableName())
                ->orderBy(['lastHit' => SORT_DESC]);

            if ($siteId !== null) {
                $query->where(['siteId' => $siteId]);
            }

            // Use explicit date range if provided
            if ($startDate !== null) {
                $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
            }
            if ($endDate !== null) {
                $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
            }
            // Otherwise fall back to days parameter
            elseif ($days !== null && $days < 36500) { // 36500 = "all time"
                $date = (new \DateTime())->modify("-{$days} days");
                $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
            }

            $analytics = $query->all();
        }

        // Check if there's any data to export
        if (empty($analytics)) {
            throw new \Exception('No data to export for the selected period.');
        }

        // Handle JSON format
        if ($format === 'json') {
            return $this->_exportAsJson($analytics);
        }

        $csv = "URL,Referrer,Hits,Last Hit,Site,Handled,Device Type,Device Brand,Device Model,Browser,Browser Version,Browser Engine,OS Name,OS Version,Bot,Bot Name,Country,City,IP Hash,User Agent,Date Created\n";

        foreach ($analytics as $stat) {
            // Get site name
            $site = Craft::$app->sites->getSiteById($stat['siteId']);
            $siteName = $site ? $site->name : '-';

            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                str_replace('"', '""', $stat['url']),
                str_replace('"', '""', $stat['referrer'] ?? ''),
                $stat['count'],
                $stat['lastHit'],
                str_replace('"', '""', $siteName),
                $stat['handled'] ? 'Yes' : 'No',
                $stat['deviceType'] ?? '',
                $stat['deviceBrand'] ?? '',
                $stat['deviceModel'] ?? '',
                $stat['browser'] ?? '',
                $stat['browserVersion'] ?? '',
                $stat['browserEngine'] ?? '',
                $stat['osName'] ?? '',
                $stat['osVersion'] ?? '',
                $stat['isRobot'] ? 'Yes' : 'No',
                $stat['botName'] ?? '',
                GeoHelper::getCountryName($stat['country'] ?? ''),
                $stat['city'] ?? '',
                $stat['ip'] ?? '',
                str_replace('"', '""', $stat['userAgent'] ?? ''),
                $stat['dateCreated']
            );
        }

        return $csv;
    }

    /**
     * Get device type breakdown
     *
     * @param int|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getDeviceBreakdown(?int $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['deviceType', 'COUNT(*) as count'])
            ->from(AnalyticsRecord::tableName())
            ->where(['not', ['deviceType' => null]])
            ->groupBy('deviceType')
            ->orderBy(['count' => SORT_DESC]);

        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        }
        // Otherwise fall back to days parameter
        elseif ($days < 36500) {
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $results = $query->all();

        return [
            'labels' => array_map(function($type) {
                return ucfirst($type);
            }, array_column($results, 'deviceType')),
            'values' => array_map('intval', array_column($results, 'count')),
        ];
    }

    /**
     * Get browser breakdown
     *
     * @param int|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getBrowserBreakdown(?int $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['browser', 'COUNT(*) as count'])
            ->from(AnalyticsRecord::tableName())
            ->where(['not', ['browser' => null]])
            ->groupBy('browser')
            ->orderBy(['count' => SORT_DESC])
            ->limit(10);

        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        }
        // Otherwise fall back to days parameter
        elseif ($days < 36500) {
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $results = $query->all();

        return [
            'labels' => array_column($results, 'browser'),
            'values' => array_map('intval', array_column($results, 'count')),
        ];
    }

    /**
     * Get OS breakdown
     *
     * @param int|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getOsBreakdown(?int $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['osName', 'COUNT(*) as count'])
            ->from(AnalyticsRecord::tableName())
            ->where(['not', ['osName' => null]])
            ->groupBy('osName')
            ->orderBy(['count' => SORT_DESC]);

        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        }
        // Otherwise fall back to days parameter
        elseif ($days < 36500) {
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $results = $query->all();

        return [
            'labels' => array_column($results, 'osName'),
            'values' => array_map('intval', array_column($results, 'count')),
        ];
    }

    /**
     * Get bot traffic stats
     *
     * @param int|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.1.0
     */
    public function getBotStats(?int $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->from(AnalyticsRecord::tableName());

        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        }
        // Otherwise fall back to days parameter
        elseif ($days < 36500) {
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $total = (int) $query->count();
        $botCount = (int) (clone $query)->andWhere(['isRobot' => true])->count();
        $humanCount = $total - $botCount;

        $botPercentage = $total > 0 ? round(($botCount / $total) * 100, 1) : 0;

        // Get top bots
        $topBots = (clone $query)
            ->select(['botName', 'COUNT(*) as count'])
            ->where(['isRobot' => true])
            ->andWhere(['not', ['botName' => null]])
            ->groupBy('botName')
            ->orderBy(['count' => SORT_DESC])
            ->limit(5)
            ->all();

        return [
            'total' => $total,
            'botCount' => $botCount,
            'humanCount' => $humanCount,
            'botPercentage' => $botPercentage,
            'topBots' => $topBots,
            'chart' => [
                'labels' => ['Human Traffic', 'Bot Traffic'],
                'values' => [$humanCount, $botCount],
            ],
        ];
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
        // Handle private/local IPs with default location for development
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $this->getDefaultLocation();
        }

        // Use centralized geo lookup from base plugin
        $geoData = $this->lookupGeoIp($ip, $this->getGeoConfig());

        if ($geoData === null) {
            return null;
        }

        // Normalize response to match expected format (lat/lon keys)
        return [
            'countryCode' => $geoData['countryCode'] ?? null,
            'country' => $geoData['country'] ?? null,
            'city' => $geoData['city'] ?? null,
            'region' => $geoData['region'] ?? null,
            'lat' => $geoData['latitude'] ?? null,
            'lon' => $geoData['longitude'] ?? null,
        ];
    }

    /**
     * Get geo config from plugin settings
     *
     * @return array<string, mixed>
     */
    protected function getGeoConfig(): array
    {
        $settings = RedirectManager::$plugin->getSettings();

        return [
            'provider' => $settings->geoProvider ?? 'ip-api.com',
            'apiKey' => $settings->geoApiKey ?? null,
        ];
    }

    /**
     * Get default location for private/local IPs
     *
     * @return array<string, mixed>
     */
    private function getDefaultLocation(): array
    {
        $settings = RedirectManager::$plugin->getSettings();
        $defaultCountry = $settings->defaultCountry ?: (getenv('REDIRECT_MANAGER_DEFAULT_COUNTRY') ?: 'AE');
        $defaultCity = $settings->defaultCity ?: (getenv('REDIRECT_MANAGER_DEFAULT_CITY') ?: 'Dubai');

        // Predefined locations for common cities worldwide
        $locations = [
            'US' => [
                'New York' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'New York', 'region' => 'New York', 'lat' => 40.7128, 'lon' => -74.0060],
                'Los Angeles' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'Los Angeles', 'region' => 'California', 'lat' => 34.0522, 'lon' => -118.2437],
                'Chicago' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'Chicago', 'region' => 'Illinois', 'lat' => 41.8781, 'lon' => -87.6298],
                'San Francisco' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'San Francisco', 'region' => 'California', 'lat' => 37.7749, 'lon' => -122.4194],
            ],
            'GB' => [
                'London' => ['countryCode' => 'GB', 'country' => 'United Kingdom', 'city' => 'London', 'region' => 'England', 'lat' => 51.5074, 'lon' => -0.1278],
                'Manchester' => ['countryCode' => 'GB', 'country' => 'United Kingdom', 'city' => 'Manchester', 'region' => 'England', 'lat' => 53.4808, 'lon' => -2.2426],
            ],
            'AE' => [
                'Dubai' => ['countryCode' => 'AE', 'country' => 'United Arab Emirates', 'city' => 'Dubai', 'region' => 'Dubai', 'lat' => 25.2048, 'lon' => 55.2708],
                'Abu Dhabi' => ['countryCode' => 'AE', 'country' => 'United Arab Emirates', 'city' => 'Abu Dhabi', 'region' => 'Abu Dhabi', 'lat' => 24.4539, 'lon' => 54.3773],
            ],
            'SA' => [
                'Riyadh' => ['countryCode' => 'SA', 'country' => 'Saudi Arabia', 'city' => 'Riyadh', 'region' => 'Riyadh Province', 'lat' => 24.7136, 'lon' => 46.6753],
                'Jeddah' => ['countryCode' => 'SA', 'country' => 'Saudi Arabia', 'city' => 'Jeddah', 'region' => 'Makkah Province', 'lat' => 21.5433, 'lon' => 39.1728],
            ],
            'DE' => [
                'Berlin' => ['countryCode' => 'DE', 'country' => 'Germany', 'city' => 'Berlin', 'region' => 'Berlin', 'lat' => 52.5200, 'lon' => 13.4050],
                'Munich' => ['countryCode' => 'DE', 'country' => 'Germany', 'city' => 'Munich', 'region' => 'Bavaria', 'lat' => 48.1351, 'lon' => 11.5820],
            ],
            'FR' => [
                'Paris' => ['countryCode' => 'FR', 'country' => 'France', 'city' => 'Paris', 'region' => 'Île-de-France', 'lat' => 48.8566, 'lon' => 2.3522],
            ],
            'CA' => [
                'Toronto' => ['countryCode' => 'CA', 'country' => 'Canada', 'city' => 'Toronto', 'region' => 'Ontario', 'lat' => 43.6532, 'lon' => -79.3832],
                'Vancouver' => ['countryCode' => 'CA', 'country' => 'Canada', 'city' => 'Vancouver', 'region' => 'British Columbia', 'lat' => 49.2827, 'lon' => -123.1207],
            ],
            'AU' => [
                'Sydney' => ['countryCode' => 'AU', 'country' => 'Australia', 'city' => 'Sydney', 'region' => 'New South Wales', 'lat' => -33.8688, 'lon' => 151.2093],
                'Melbourne' => ['countryCode' => 'AU', 'country' => 'Australia', 'city' => 'Melbourne', 'region' => 'Victoria', 'lat' => -37.8136, 'lon' => 144.9631],
            ],
            'JP' => [
                'Tokyo' => ['countryCode' => 'JP', 'country' => 'Japan', 'city' => 'Tokyo', 'region' => 'Tokyo', 'lat' => 35.6762, 'lon' => 139.6503],
            ],
            'SG' => [
                'Singapore' => ['countryCode' => 'SG', 'country' => 'Singapore', 'city' => 'Singapore', 'region' => 'Singapore', 'lat' => 1.3521, 'lon' => 103.8198],
            ],
            'IN' => [
                'Mumbai' => ['countryCode' => 'IN', 'country' => 'India', 'city' => 'Mumbai', 'region' => 'Maharashtra', 'lat' => 19.0760, 'lon' => 72.8777],
                'Delhi' => ['countryCode' => 'IN', 'country' => 'India', 'city' => 'Delhi', 'region' => 'Delhi', 'lat' => 28.7041, 'lon' => 77.1025],
            ],
        ];

        // Return the configured location if it exists
        if (isset($locations[$defaultCountry][$defaultCity])) {
            return $locations[$defaultCountry][$defaultCity];
        }

        // Fallback to Dubai if configuration not found
        return $locations['AE']['Dubai'];
    }

    /**
     * Get top countries
     *
     * @param int|null $siteId
     * @param int $days
     * @param int $limit
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.9.0
     */
    public function getTopCountries(?int $siteId = null, int $days = 30, int $limit = 15, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['country', 'COUNT(*) as count'])
            ->from(AnalyticsRecord::tableName())
            ->where(['not', ['country' => null]])
            ->andWhere(['not', ['country' => '']])
            ->groupBy(['country'])
            ->orderBy(['count' => SORT_DESC])
            ->limit($limit);

        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        }
        // Otherwise fall back to days parameter
        elseif ($days < 36500) {
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $results = $query->all();
        $total = array_sum(array_column($results, 'count'));

        $countries = [];
        foreach ($results as $row) {
            $countries[] = [
                'country' => $row['country'],
                'name' => GeoHelper::getCountryName($row['country'] ?? ''),
                'count' => (int)$row['count'],
                'percentage' => $total > 0 ? round(($row['count'] / $total) * 100, 1) : 0,
            ];
        }

        return $countries;
    }

    /**
     * Get top cities
     *
     * @param int|null $siteId
     * @param int $days
     * @param int $limit
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.9.0
     */
    public function getTopCities(?int $siteId = null, int $days = 30, int $limit = 15, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['city', 'country', 'COUNT(*) as count'])
            ->from(AnalyticsRecord::tableName())
            ->where(['not', ['city' => null]])
            ->andWhere(['not', ['city' => '']])
            ->groupBy(['city', 'country'])
            ->orderBy(['count' => SORT_DESC])
            ->limit($limit);

        if ($startDate !== null) {
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', 'lastHit', Db::prepareDateForDb($endDate)]);
        }
        // Otherwise fall back to days parameter
        elseif ($days < 36500) {
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', 'lastHit', Db::prepareDateForDb($date)]);
        }

        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $results = $query->all();
        $total = array_sum(array_column($results, 'count'));

        $cities = [];
        foreach ($results as $row) {
            $cities[] = [
                'city' => $row['city'],
                'country' => $row['country'],
                'countryName' => GeoHelper::getCountryName($row['country'] ?? ''),
                'count' => (int)$row['count'],
                'percentage' => $total > 0 ? round(($row['count'] / $total) * 100, 1) : 0,
            ];
        }

        return $cities;
    }

    /**
     * Clean and normalize URL
     *
     * @param string $url
     * @return string
     */
    private function cleanUrl(string $url): string
    {
        $url = trim($url);
        $url = str_replace(["\r", "\n", "\t"], '', $url);
        $url = preg_replace('#/+#', '/', $url);
        return $url;
    }

    /**
     * Anonymize IP address (keep first 3 octets for IPv4, first 4 segments for IPv6)
     *
     * @param string|null $ip
     * @return string|null
     */
    private function _anonymizeIp(?string $ip): ?string
    {
        if (empty($ip)) {
            return null;
        }

        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            // Keep first 4 segments, anonymize the rest
            $parts = array_slice($parts, 0, 4);
            return implode(':', $parts) . '::';
        }

        return null;
    }

    /**
     * Hash IP address with salt for privacy
     *
     * Uses SHA256 with a secret salt to hash IPs. This prevents rainbow table attacks
     * while still allowing unique visitor tracking (same IP = same hash).
     *
     * @param string $ip The IP address to hash
     * @return string Hashed IP address (64 characters)
     * @throws \Exception If salt is not configured
     */
    private function _hashIpWithSalt(string $ip): string
    {
        $settings = RedirectManager::$plugin->getSettings();
        $salt = $settings->ipHashSalt;

        if (!$salt || $salt === '$REDIRECT_MANAGER_IP_SALT' || trim($salt) === '') {
            $this->logWarning('IP hash salt not configured - IP tracking disabled', [
                'ip' => 'hidden',
                'saltValue' => $salt ?? 'NULL',
            ]);
            throw new \Exception('IP hash salt not configured. Run: php craft redirect-manager/security/generate-salt');
        }

        return hash('sha256', $ip . $salt);
    }

    /**
     * Export analytics data as JSON
     *
     * @param array $analytics Raw analytics data
     * @return string JSON string
     */
    private function _exportAsJson(array $analytics): string
    {
        $data = [];

        foreach ($analytics as $stat) {
            // Get site name
            $site = Craft::$app->sites->getSiteById($stat['siteId']);
            $siteName = $site ? $site->name : null;

            $item = [
                'url' => $stat['url'],
                'referrer' => $stat['referrer'] ?? null,
                'hits' => (int)$stat['count'],
                'lastHit' => $stat['lastHit'],
                'siteId' => $stat['siteId'] ? (int)$stat['siteId'] : null,
                'siteName' => $siteName,
                'handled' => (bool)$stat['handled'],
                'device' => [
                    'type' => $stat['deviceType'] ?? null,
                    'brand' => $stat['deviceBrand'] ?? null,
                    'model' => $stat['deviceModel'] ?? null,
                ],
                'browser' => [
                    'name' => $stat['browser'] ?? null,
                    'version' => $stat['browserVersion'] ?? null,
                    'engine' => $stat['browserEngine'] ?? null,
                ],
                'os' => [
                    'name' => $stat['osName'] ?? null,
                    'version' => $stat['osVersion'] ?? null,
                ],
                'bot' => [
                    'isBot' => (bool)$stat['isRobot'],
                    'name' => $stat['botName'] ?? null,
                ],
                'location' => [
                    'country' => $stat['country'] ?? null,
                    'countryName' => GeoHelper::getCountryName($stat['country'] ?? ''),
                    'city' => $stat['city'] ?? null,
                ],
                'ipHash' => $stat['ip'] ?? null,
                'userAgent' => $stat['userAgent'] ?? null,
                'dateCreated' => $stat['dateCreated'],
            ];

            $data[] = $item;
        }

        return json_encode([
            'exported' => date('c'),
            'count' => count($data),
            'data' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get date range condition for queries
     *
     * @param string $dateRange
     * @return array|null
     */
    private function getDateRangeCondition(string $dateRange): ?array
    {
        $bounds = DateRangeHelper::getBounds($dateRange);
        $start = $bounds['start'] ?? null;
        $end = $bounds['end'] ?? null;

        if (!$start && !$end) {
            return null;
        }

        $conditions = ['and'];
        if ($start) {
            $conditions[] = ['>=', 'lastHit', Db::prepareDateForDb($start)];
        }
        if ($end) {
            $conditions[] = ['<', 'lastHit', Db::prepareDateForDb($end)];
        }

        return $conditions;
    }
}
