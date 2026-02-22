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
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\GeoHelper;
use lindemannrock\redirectmanager\records\AnalyticsRecord;

/**
 * Analytics Query Service
 *
 * Fetches, lists, counts, and charts analytics records.
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.7.0
 */
class AnalyticsQueryService
{
    use AnalyticsQueryTrait;

    /**
     * Get all analytics
     *
     * @param int|array<int>|null $siteId
     * @param int|null $limit
     * @param string $orderBy
     * @return array
     * @since 5.7.0
     */
    public function getAllAnalytics(int|array|null $siteId = null, ?int $limit = null, string $orderBy = 'lastHit DESC'): array
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
     * @since 5.7.0
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
     * @param int|array<int>|null $siteId
     * @param int|null $limit
     * @return array
     * @since 5.7.0
     */
    public function getUnhandled404s(int|array|null $siteId = null, ?int $limit = null): array
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
     * @param int|array<int>|null $siteId
     * @param int|null $limit
     * @return array
     * @since 5.7.0
     */
    public function getHandled404s(int|array|null $siteId = null, ?int $limit = null): array
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
     * @param int|array<int>|null $siteId
     * @param bool|null $handled
     * @param int|null $days Number of days to look back (null for all time)
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return int
     * @since 5.7.0
     */
    public function getAnalyticsCount(int|array|null $siteId = null, ?bool $handled = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null): int
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
     * @param int|array<int>|null $siteId
     * @param int $days Number of days to look back
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.7.0
     */
    public function getChartData(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $localDate = DateFormatHelper::localDateExpression('lastHit');

        $query = (new Query())
            ->select([
                'date' => $localDate,
                'COUNT(*) as total',
                'SUM(CASE WHEN handled = 1 THEN 1 ELSE 0 END) as handled',
                'SUM(CASE WHEN handled = 0 THEN 1 ELSE 0 END) as unhandled',
            ])
            ->from(AnalyticsRecord::tableName())
            ->groupBy($localDate)
            ->orderBy('date ASC');

        $this->applyDateFilter($query, $days, $startDate, $endDate);
        $this->applySiteFilter($query, $siteId);

        return $query->all();
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
     * @since 5.7.0
     */
    public function getMostCommon404s(int|array|null $siteId = null, int $limit = 10, ?bool $handled = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
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
     * @param int|array<int>|null $siteId
     * @param int $limit
     * @param bool|null $handled
     * @param int|null $days Number of days to look back (null for all time)
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     * @since 5.7.0
     */
    public function getRecent404s(int|array|null $siteId = null, int $limit = 10, ?bool $handled = null, ?int $days = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
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
