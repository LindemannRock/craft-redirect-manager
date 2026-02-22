<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services\analytics;

use craft\db\Query;
use craft\helpers\Db;

/**
 * Shared date and site filtering for analytics queries
 *
 * @since 5.7.0
 */
trait AnalyticsQueryTrait
{
    /**
     * Apply date filtering to a query
     *
     * Supports explicit start/end dates or a days-ago fallback.
     *
     * @param Query $query
     * @param int $days Number of days to look back (ignored when explicit dates given)
     * @param \DateTime|null $startDate
     * @param \DateTime|null $endDate
     * @param string $column Date column name
     */
    protected function applyDateFilter(Query $query, int $days, ?\DateTime $startDate, ?\DateTime $endDate, string $column = 'lastHit'): void
    {
        if ($startDate !== null) {
            $query->andWhere(['>=', $column, Db::prepareDateForDb($startDate)]);
        }
        if ($endDate !== null) {
            $query->andWhere(['<', $column, Db::prepareDateForDb($endDate)]);
        } elseif ($days < 36500) {
            $date = (new \DateTime())->modify("-{$days} days");
            $query->andWhere(['>=', $column, Db::prepareDateForDb($date)]);
        }
    }

    /**
     * Apply site filtering to a query
     *
     * @param Query $query
     * @param int|array<int>|null $siteId
     */
    protected function applySiteFilter(Query $query, int|array|null $siteId): void
    {
        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }
    }
}
