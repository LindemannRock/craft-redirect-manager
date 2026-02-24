<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services\analytics;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use lindemannrock\base\helpers\GeoHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\records\AnalyticsRecord;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Analytics Export Service
 *
 * Export, delete, clear, trim, and cleanup analytics records.
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.7.0
 */
class AnalyticsExportService
{
    use AnalyticsQueryTrait;
    use LoggingTrait;

    public function __construct()
    {
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

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
     */
    public function getExportData(int|array|null $siteId = null, ?array $analyticsIds = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null, ?int $redirectId = null): array
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
     * @param int|array<int>|null $siteId
     * @param array|null $analyticsIds
     * @param int|null $days Number of days to look back
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @param string $format Export format ('csv' or 'json')
     * @return string CSV or JSON content
     */
    public function exportToCsv(int|array|null $siteId = null, ?array $analyticsIds = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null, string $format = 'csv'): string
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
     * Delete an analytic by ID
     *
     * @param int $id
     * @return bool
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
     * @param int|array<int>|null $siteId
     * @return int Number of records deleted
     */
    public function clearAnalytics(int|array|null $siteId = null): int
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
}
