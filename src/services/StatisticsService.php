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
use lindemannrock\redirectmanager\records\StatisticRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Statistics Service
 *
 * Tracks 404 errors and provides statistics data
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
class StatisticsService extends Component
{
    use LoggingTrait;

    /**
     * Initialize the service
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('redirect-manager');
    }

    /**
     * Record a 404 error
     *
     * @param string $url
     * @param bool $handled Whether the 404 was handled by a redirect
     * @return void
     */
    public function record404(string $url, bool $handled): void
    {
        $settings = RedirectManager::$plugin->getSettings();
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        // Strip query string from URL if configured
        $urlParsed = $settings->stripQueryStringFromStats
            ? strtok($url, '?')
            : $url;

        // Clean the URL
        $urlParsed = $this->cleanUrl($urlParsed);

        // Get referrer, IP, and user agent
        $request = Craft::$app->getRequest();
        $referrer = $request->getReferrer();
        $remoteIp = $settings->recordRemoteIp ? $this->anonymizeIp($request->getUserIP()) : null;
        $userAgent = $request->getUserAgent();

        // Check if record exists
        $existing = (new Query())
            ->from(StatisticRecord::tableName())
            ->where([
                'urlParsed' => $urlParsed,
                'siteId' => $siteId,
            ])
            ->one();

        if ($existing) {
            // Update existing record
            Craft::$app->getDb()->createCommand()
                ->update(
                    StatisticRecord::tableName(),
                    [
                        'count' => new \yii\db\Expression('[[count]] + 1'),
                        'handled' => $handled,
                        'referrer' => $referrer,
                        'remoteIp' => $remoteIp,
                        'userAgent' => $userAgent,
                        'lastHit' => Db::prepareDateForDb(new \DateTime()),
                        'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
                    ],
                    ['id' => $existing['id']]
                )
                ->execute();

            $this->logDebug('Updated 404 statistic', ['url' => $urlParsed, 'count' => $existing['count'] + 1]);
        } else {
            // Create new record
            $record = new StatisticRecord();
            $record->siteId = $siteId;
            $record->url = $url;
            $record->urlParsed = $urlParsed;
            $record->handled = $handled;
            $record->count = 1;
            $record->referrer = $referrer;
            $record->remoteIp = $remoteIp;
            $record->userAgent = $userAgent;
            $record->lastHit = Db::prepareDateForDb(new \DateTime());

            if ($record->save()) {
                $this->logDebug('Created 404 statistic', ['url' => $urlParsed]);

                // Check if we need to trim statistics
                if ($settings->autoTrimStatistics) {
                    $this->trimStatistics();
                }
            } else {
                $this->logError('Failed to save 404 statistic', ['errors' => $record->getErrors()]);
            }
        }
    }

    /**
     * Get all statistics
     *
     * @param int|null $siteId
     * @param int|null $limit
     * @param string $orderBy
     * @return array
     */
    public function getAllStatistics(?int $siteId = null, ?int $limit = null, string $orderBy = 'lastHit DESC'): array
    {
        $query = (new Query())
            ->from(StatisticRecord::tableName());

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
     * Get unhandled 404s (not resolved by redirects)
     *
     * @param int|null $siteId
     * @param int|null $limit
     * @return array
     */
    public function getUnhandled404s(?int $siteId = null, ?int $limit = null): array
    {
        $query = (new Query())
            ->from(StatisticRecord::tableName())
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
     */
    public function getHandled404s(?int $siteId = null, ?int $limit = null): array
    {
        $query = (new Query())
            ->from(StatisticRecord::tableName())
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
     * Get statistics count
     *
     * @param int|null $siteId
     * @param bool|null $handled
     * @return int
     */
    public function getStatisticsCount(?int $siteId = null, ?bool $handled = null): int
    {
        $query = (new Query())
            ->from(StatisticRecord::tableName());

        if ($siteId !== null) {
            $query->where(['siteId' => $siteId]);
        }

        if ($handled !== null) {
            $query->andWhere(['handled' => $handled]);
        }

        return (int)$query->count();
    }

    /**
     * Get statistics for dashboard charts
     *
     * @param int|null $siteId
     * @param int $days Number of days to look back
     * @return array
     */
    public function getChartData(?int $siteId = null, int $days = 30): array
    {
        $date = (new \DateTime())->modify("-{$days} days");

        $query = (new Query())
            ->select([
                'DATE(lastHit) as date',
                'COUNT(*) as total',
                'SUM(CASE WHEN handled = 1 THEN 1 ELSE 0 END) as handled',
                'SUM(CASE WHEN handled = 0 THEN 1 ELSE 0 END) as unhandled',
            ])
            ->from(StatisticRecord::tableName())
            ->where(['>=', 'lastHit', Db::prepareDateForDb($date)])
            ->groupBy('DATE(lastHit)')
            ->orderBy('date ASC');

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
     * @return array
     */
    public function getMostCommon404s(?int $siteId = null, int $limit = 10, ?bool $handled = null): array
    {
        $query = (new Query())
            ->from(StatisticRecord::tableName())
            ->orderBy(['count' => SORT_DESC])
            ->limit($limit);

        if ($siteId !== null) {
            $query->where(['siteId' => $siteId]);
        }

        if ($handled !== null) {
            $query->andWhere(['handled' => $handled]);
        }

        return $query->all();
    }

    /**
     * Get recent 404s
     *
     * @param int|null $siteId
     * @param int $limit
     * @param bool|null $handled
     * @return array
     */
    public function getRecent404s(?int $siteId = null, int $limit = 10, ?bool $handled = null): array
    {
        $query = (new Query())
            ->from(StatisticRecord::tableName())
            ->orderBy(['lastHit' => SORT_DESC])
            ->limit($limit);

        if ($siteId !== null) {
            $query->where(['siteId' => $siteId]);
        }

        if ($handled !== null) {
            $query->andWhere(['handled' => $handled]);
        }

        return $query->all();
    }

    /**
     * Delete a statistic by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteStatistic(int $id): bool
    {
        $record = StatisticRecord::findOne($id);

        if (!$record) {
            $this->logError('Statistic not found', ['id' => $id]);
            return false;
        }

        if ($record->delete()) {
            $this->logInfo('Statistic deleted', ['id' => $id]);
            return true;
        }

        return false;
    }

    /**
     * Clear all statistics
     *
     * @param int|null $siteId
     * @return int Number of records deleted
     */
    public function clearStatistics(?int $siteId = null): int
    {
        $command = Craft::$app->getDb()->createCommand()
            ->delete(StatisticRecord::tableName());

        if ($siteId !== null) {
            $command->where(['siteId' => $siteId]);
        }

        $count = $command->execute();

        $this->logInfo('Statistics cleared', ['count' => $count, 'siteId' => $siteId]);

        return $count;
    }

    /**
     * Trim statistics to respect the limit
     *
     * @return int Number of records deleted
     */
    public function trimStatistics(): int
    {
        $settings = RedirectManager::$plugin->getSettings();
        $limit = $settings->statisticsLimit;

        // Get current count
        $currentCount = (new Query())
            ->from(StatisticRecord::tableName())
            ->count();

        if ($currentCount <= $limit) {
            return 0;
        }

        // Get IDs to delete (oldest by lastHit, lowest count)
        $idsToDelete = (new Query())
            ->select(['id'])
            ->from(StatisticRecord::tableName())
            ->orderBy(['lastHit' => SORT_ASC, 'count' => SORT_ASC])
            ->limit($currentCount - $limit)
            ->column();

        if (empty($idsToDelete)) {
            return 0;
        }

        // Delete the records
        $deleted = Craft::$app->getDb()->createCommand()
            ->delete(StatisticRecord::tableName(), ['in', 'id', $idsToDelete])
            ->execute();

        $this->logInfo('Trimmed statistics', ['deleted' => $deleted]);

        return $deleted;
    }

    /**
     * Clean up old statistics based on retention setting
     *
     * @return int Number of records deleted
     */
    public function cleanupOldStatistics(): int
    {
        $settings = RedirectManager::$plugin->getSettings();
        $retention = $settings->statisticsRetention;

        if ($retention <= 0) {
            return 0;
        }

        $date = (new \DateTime())->modify("-{$retention} days");

        $deleted = Craft::$app->getDb()->createCommand()
            ->delete(
                StatisticRecord::tableName(),
                ['<', 'lastHit', Db::prepareDateForDb($date)]
            )
            ->execute();

        if ($deleted > 0) {
            $this->logInfo('Cleaned up old statistics', ['deleted' => $deleted, 'retention' => $retention]);
        }

        return $deleted;
    }

    /**
     * Export statistics to CSV
     *
     * @param int|null $siteId
     * @return string CSV content
     */
    public function exportToCsv(?int $siteId = null): string
    {
        $statistics = $this->getAllStatistics($siteId);

        $csv = "URL,Handled,Count,Referrer,Remote IP,User Agent,Last Hit,Date Created\n";

        foreach ($statistics as $stat) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                str_replace('"', '""', $stat['url']),
                $stat['handled'] ? 'Yes' : 'No',
                $stat['count'],
                str_replace('"', '""', $stat['referrer'] ?? ''),
                $stat['remoteIp'] ?? '',
                str_replace('"', '""', $stat['userAgent'] ?? ''),
                $stat['lastHit'],
                $stat['dateCreated']
            );
        }

        return $csv;
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
    private function anonymizeIp(?string $ip): ?string
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
}
