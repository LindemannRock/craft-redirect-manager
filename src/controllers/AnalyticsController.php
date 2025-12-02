<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;
use yii\web\Response;

/**
 * Analytics Controller
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
class AnalyticsController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('redirect-manager');
    }

    /**
     * Dashboard - 404 list with filters
     *
     * @return Response
     */
    public function actionDashboard(): Response
    {
        $this->requirePermission('redirectManager:viewAnalytics');

        $request = Craft::$app->getRequest();
        $settings = RedirectManager::$plugin->getSettings();

        // Get filter parameters
        $search = $request->getQueryParam('search', '');
        $handledFilter = $request->getQueryParam('handled', 'all');
        $sort = $request->getQueryParam('sort', 'lastHit');
        $dir = $request->getQueryParam('dir', 'desc');
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $limit = $settings->itemsPerPage ?? 100;
        $offset = ($page - 1) * $limit;

        // Build query
        $query = (new \craft\db\Query())
            ->from(\lindemannrock\redirectmanager\records\AnalyticsRecord::tableName());

        // Apply handled filter
        if ($handledFilter === 'handled') {
            $query->andWhere(['handled' => true]);
        } elseif ($handledFilter === 'unhandled') {
            $query->andWhere(['handled' => false]);
        }

        // Apply search
        if (!empty($search)) {
            $query->andWhere(['like', 'url', $search]);
        }

        // Apply sorting
        $orderBy = match ($sort) {
            'url' => "url $dir",
            'count' => "count $dir",
            'lastHit' => "lastHit $dir",
            default => "lastHit $dir",
        };
        $query->orderBy($orderBy);

        // Get total count for pagination
        $totalCount = $query->count();

        // Apply pagination
        $query->limit($limit)->offset($offset);

        // Get analytics
        $analytics = $query->all();

        // Add redirect ID to each handled analytic
        foreach ($analytics as &$stat) {
            if ($stat['handled']) {
                $redirectId = (new \craft\db\Query())
                    ->select('id')
                    ->from(\lindemannrock\redirectmanager\records\RedirectRecord::tableName())
                    ->where(['sourceUrlParsed' => $stat['urlParsed'], 'enabled' => true])
                    ->scalar();
                $stat['redirectId'] = $redirectId ?: null;
            }
        }

        // Get overall counts
        $allCount = RedirectManager::$plugin->analytics->getAnalyticsCount();
        $handledCount = RedirectManager::$plugin->analytics->getAnalyticsCount(null, true);
        $unhandledCount = RedirectManager::$plugin->analytics->getAnalyticsCount(null, false);

        return $this->renderTemplate('redirect-manager/dashboard/index', [
            'analytics' => $analytics,
            'settings' => $settings,
            'totalCount' => $totalCount,
            'allCount' => $allCount,
            'handledCount' => $handledCount,
            'unhandledCount' => $unhandledCount,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Analytics index - Charts and analytics
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('redirectManager:viewAnalytics');

        $request = Craft::$app->getRequest();
        $siteId = $request->getQueryParam('siteId');
        $dateRange = $request->getQueryParam('dateRange', 'last30days');

        // Convert date range to days
        $days = $this->_convertDateRangeToDays($dateRange);

        // Get explicit date range filter (for today/yesterday)
        $dateFilter = $this->_getDateRangeFilter($dateRange);
        $startDate = $dateFilter['start'] ?? null;
        $endDate = $dateFilter['end'] ?? null;

        // Get chart data
        $chartData = RedirectManager::$plugin->analytics->getChartData($siteId, $days);

        // Get most common 404s
        $mostCommon = RedirectManager::$plugin->analytics->getMostCommon404s($siteId, 15, null, $days, $startDate, $endDate);

        // Get recent 404s
        $recentHandled = RedirectManager::$plugin->analytics->getRecent404s($siteId, 5, true, $days, $startDate, $endDate);
        $recentUnhandled = RedirectManager::$plugin->analytics->getRecent404s($siteId, 5, false, $days, $startDate, $endDate);

        // Get counts
        $totalCount = RedirectManager::$plugin->analytics->getAnalyticsCount($siteId, null, $days, $startDate, $endDate);
        $handledCount = RedirectManager::$plugin->analytics->getAnalyticsCount($siteId, true, $days, $startDate, $endDate);
        $unhandledCount = RedirectManager::$plugin->analytics->getAnalyticsCount($siteId, false, $days, $startDate, $endDate);

        // Get device analytics
        $deviceBreakdown = RedirectManager::$plugin->analytics->getDeviceBreakdown($siteId, $days);
        $browserBreakdown = RedirectManager::$plugin->analytics->getBrowserBreakdown($siteId, $days);
        $osBreakdown = RedirectManager::$plugin->analytics->getOsBreakdown($siteId, $days);
        $botStats = RedirectManager::$plugin->analytics->getBotStats($siteId, $days);

        return $this->renderTemplate('redirect-manager/analytics/index', [
            'chartData' => $chartData,
            'mostCommon' => $mostCommon,
            'recentHandled' => $recentHandled,
            'recentUnhandled' => $recentUnhandled,
            'totalCount' => $totalCount,
            'handledCount' => $handledCount,
            'unhandledCount' => $unhandledCount,
            'deviceBreakdown' => $deviceBreakdown,
            'browserBreakdown' => $browserBreakdown,
            'osBreakdown' => $osBreakdown,
            'botStats' => $botStats,
            'dateRange' => $dateRange,
        ]);
    }

    /**
     * Create redirect from 404
     *
     * @return Response
     */
    public function actionCreateRedirectFrom404(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:createRedirects');

        $sourceUrl = Craft::$app->getRequest()->getRequiredBodyParam('sourceUrl');
        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');

        // Redirect to edit page with pre-filled source URL
        return $this->redirect('redirect-manager/redirects/new?' . http_build_query([
            'sourceUrl' => $sourceUrl,
            'siteId' => $siteId,
        ]));
    }

    /**
     * Delete an analytic
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:viewAnalytics');

        $analyticId = Craft::$app->getRequest()->getRequiredBodyParam('analyticId');

        if (RedirectManager::$plugin->analytics->deleteAnalytic($analyticId)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['success' => false, 'error' => 'Could not delete analytic']);
    }

    /**
     * Clear all analytics
     *
     * @return Response
     */
    public function actionClearAll(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageSettings');

        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');

        $deleted = RedirectManager::$plugin->analytics->clearAnalytics($siteId);

        Craft::$app->getSession()->setNotice(
            Craft::t('redirect-manager', '{count} analytics cleared', ['count' => $deleted])
        );

        return $this->asJson([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }

    /**
     * Export analytics to CSV
     *
     * @return Response
     */
    public function actionExportCsv(): Response
    {
        $this->requirePermission('redirectManager:viewAnalytics');

        $request = Craft::$app->getRequest();
        $siteId = $request->getQueryParam('siteId');

        // Check if specific analytics were selected
        $analyticsIdsJson = $request->getBodyParam('analyticsIds');
        $analyticsIds = $analyticsIdsJson ? json_decode($analyticsIdsJson, true) : null;

        // Get date range from query params
        $dateRange = $request->getQueryParam('dateRange', 'all');
        $format = $request->getQueryParam('format', 'csv');

        // Convert date range to days and get date filter
        $days = $this->_convertDateRangeToDays($dateRange);
        $dateFilter = $this->_getDateRangeFilter($dateRange);
        $startDate = $dateFilter['start'] ?? null;
        $endDate = $dateFilter['end'] ?? null;

        $csv = RedirectManager::$plugin->analytics->exportToCsv($siteId, $analyticsIds, $days, $startDate, $endDate);

        // Build filename following shortlink pattern
        $settings = RedirectManager::$plugin->getSettings();
        $filenamePart = strtolower(str_replace(' ', '-', $settings->getPluralLowerDisplayName()));
        $filename = $filenamePart . '-analytics-' . $dateRange . '-' . date('Y-m-d') . '.' . $format;

        return Craft::$app->getResponse()
            ->sendContentAsFile($csv, $filename, [
                'mimeType' => $format === 'csv' ? 'text/csv' : 'application/json',
            ]);
    }

    /**
     * Get chart data (AJAX)
     *
     * @return Response
     */
    public function actionGetChartData(): Response
    {
        $this->requirePermission('redirectManager:viewAnalytics');

        $siteId = Craft::$app->getRequest()->getQueryParam('siteId');
        $days = (int)Craft::$app->getRequest()->getQueryParam('days', 30);

        $chartData = RedirectManager::$plugin->analytics->getChartData($siteId, $days);

        return $this->asJson([
            'success' => true,
            'data' => $chartData,
        ]);
    }

    /**
     * Get analytics data via AJAX (for date range filtering)
     *
     * @return Response
     */
    public function actionGetData(): Response
    {
        $this->requirePermission('redirectManager:viewAnalytics');

        $request = Craft::$app->getRequest();
        $siteId = $request->getBodyParam('siteId');
        $dateRange = $request->getBodyParam('dateRange', 'last30days');
        $type = $request->getBodyParam('type', 'summary');

        // Convert date range to days
        $days = $this->_convertDateRangeToDays($dateRange);

        // Get explicit date range filter (for today/yesterday)
        $dateFilter = $this->_getDateRangeFilter($dateRange);
        $startDate = $dateFilter['start'] ?? null;
        $endDate = $dateFilter['end'] ?? null;

        $data = null;

        switch ($type) {
            case 'summary':
                // Get counts
                $totalCount = RedirectManager::$plugin->analytics->getAnalyticsCount($siteId, null, $days, $startDate, $endDate);
                $handledCount = RedirectManager::$plugin->analytics->getAnalyticsCount($siteId, true, $days, $startDate, $endDate);
                $unhandledCount = RedirectManager::$plugin->analytics->getAnalyticsCount($siteId, false, $days, $startDate, $endDate);

                // Get tables data
                $mostCommon = RedirectManager::$plugin->analytics->getMostCommon404s($siteId, 15, null, $days, $startDate, $endDate);
                $recentUnhandled = RedirectManager::$plugin->analytics->getRecent404s($siteId, 5, false, $days, $startDate, $endDate);

                // Get bot stats
                $botStats = RedirectManager::$plugin->analytics->getBotStats($siteId, $days);

                $data = [
                    'totalCount' => $totalCount,
                    'handledCount' => $handledCount,
                    'unhandledCount' => $unhandledCount,
                    'mostCommon' => $mostCommon,
                    'recentUnhandled' => $recentUnhandled,
                    'topBots' => $botStats['topBots'] ?? [],
                ];
                break;

            case 'chart':
                $data = RedirectManager::$plugin->analytics->getChartData($siteId, $days);
                break;

            case 'devices':
                $data = RedirectManager::$plugin->analytics->getDeviceBreakdown($siteId, $days);
                break;

            case 'browsers':
                $data = RedirectManager::$plugin->analytics->getBrowserBreakdown($siteId, $days);
                break;

            case 'os':
                $data = RedirectManager::$plugin->analytics->getOsBreakdown($siteId, $days);
                break;

            case 'bots':
                $data = RedirectManager::$plugin->analytics->getBotStats($siteId, $days);
                break;
        }

        return $this->asJson([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Convert date range string to number of days
     *
     * @param string $dateRange
     * @return int
     */
    private function _convertDateRangeToDays(string $dateRange): int
    {
        return match ($dateRange) {
            'today' => 1,
            'yesterday' => 2, // Need 2 days to include yesterday's data
            'last7days' => 7,
            'last30days' => 30,
            'last90days' => 90,
            'all' => 36500, // ~100 years (effectively all data)
            default => 30,
        };
    }

    /**
     * Get date range filter for SQL queries
     *
     * @param string $dateRange
     * @return array|null ['start' => DateTime, 'end' => DateTime] or null for no filter
     */
    private function _getDateRangeFilter(string $dateRange): ?array
    {
        return match ($dateRange) {
            'today' => [
                'start' => (new \DateTime())->setTime(0, 0, 0),
                'end' => null, // up to now
            ],
            'yesterday' => [
                'start' => (new \DateTime())->modify('-1 day')->setTime(0, 0, 0),
                'end' => (new \DateTime())->setTime(0, 0, 0),
            ],
            'last7days' => [
                'start' => (new \DateTime())->modify('-7 days'),
                'end' => null,
            ],
            'last30days' => [
                'start' => (new \DateTime())->modify('-30 days'),
                'end' => null,
            ],
            'last90days' => [
                'start' => (new \DateTime())->modify('-90 days'),
                'end' => null,
            ],
            'all' => null,
            default => [
                'start' => (new \DateTime())->modify('-30 days'),
                'end' => null,
            ],
        };
    }
}
