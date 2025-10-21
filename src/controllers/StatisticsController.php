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
use lindemannrock\redirectmanager\RedirectManager;
use yii\web\Response;

/**
 * Statistics Controller
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
class StatisticsController extends Controller
{
    /**
     * Dashboard - 404 list with filters
     *
     * @return Response
     */
    public function actionDashboard(): Response
    {
        $this->requirePermission('redirectManager:viewStatistics');

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
            ->from(\lindemannrock\redirectmanager\records\StatisticRecord::tableName());

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
        $orderBy = match($sort) {
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

        // Get statistics
        $statistics = $query->all();

        // Add redirect ID to each handled statistic
        foreach ($statistics as &$stat) {
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
        $allCount = RedirectManager::$plugin->statistics->getStatisticsCount();
        $handledCount = RedirectManager::$plugin->statistics->getStatisticsCount(null, true);
        $unhandledCount = RedirectManager::$plugin->statistics->getStatisticsCount(null, false);

        return $this->renderTemplate('redirect-manager/dashboard/index', [
            'statistics' => $statistics,
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
     * Statistics - Charts and analytics
     *
     * @return Response
     */
    public function actionStatistics(): Response
    {
        $this->requirePermission('redirectManager:viewStatistics');

        $siteId = Craft::$app->getRequest()->getQueryParam('siteId');

        // Get chart data (last 30 days)
        $chartData = RedirectManager::$plugin->statistics->getChartData($siteId, 30);

        // Get most common 404s
        $mostCommon = RedirectManager::$plugin->statistics->getMostCommon404s($siteId, 10);

        // Get recent 404s
        $recentHandled = RedirectManager::$plugin->statistics->getRecent404s($siteId, 5, true);
        $recentUnhandled = RedirectManager::$plugin->statistics->getRecent404s($siteId, 5, false);

        // Get counts
        $totalCount = RedirectManager::$plugin->statistics->getStatisticsCount($siteId);
        $handledCount = RedirectManager::$plugin->statistics->getStatisticsCount($siteId, true);
        $unhandledCount = RedirectManager::$plugin->statistics->getStatisticsCount($siteId, false);

        return $this->renderTemplate('redirect-manager/statistics/index', [
            'chartData' => $chartData,
            'mostCommon' => $mostCommon,
            'recentHandled' => $recentHandled,
            'recentUnhandled' => $recentUnhandled,
            'totalCount' => $totalCount,
            'handledCount' => $handledCount,
            'unhandledCount' => $unhandledCount,
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
     * Delete a statistic
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:viewStatistics');

        $statisticId = Craft::$app->getRequest()->getRequiredBodyParam('statisticId');

        if (RedirectManager::$plugin->statistics->deleteStatistic($statisticId)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['success' => false, 'error' => 'Could not delete statistic']);
    }

    /**
     * Clear all statistics
     *
     * @return Response
     */
    public function actionClearAll(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageSettings');

        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');

        $deleted = RedirectManager::$plugin->statistics->clearStatistics($siteId);

        Craft::$app->getSession()->setNotice(
            Craft::t('redirect-manager', '{count} statistics cleared', ['count' => $deleted])
        );

        return $this->asJson([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }

    /**
     * Export statistics to CSV
     *
     * @return Response
     */
    public function actionExportCsv(): Response
    {
        $this->requirePermission('redirectManager:viewStatistics');

        $siteId = Craft::$app->getRequest()->getQueryParam('siteId');

        $csv = RedirectManager::$plugin->statistics->exportToCsv($siteId);

        $filename = 'redirect-statistics-' . date('Y-m-d-His') . '.csv';

        return Craft::$app->getResponse()
            ->sendContentAsFile($csv, $filename, [
                'mimeType' => 'text/csv',
            ]);
    }

    /**
     * Get chart data (AJAX)
     *
     * @return Response
     */
    public function actionGetChartData(): Response
    {
        $this->requirePermission('redirectManager:viewStatistics');

        $siteId = Craft::$app->getRequest()->getQueryParam('siteId');
        $days = (int)Craft::$app->getRequest()->getQueryParam('days', 30);

        $chartData = RedirectManager::$plugin->statistics->getChartData($siteId, $days);

        return $this->asJson([
            'success' => true,
            'data' => $chartData,
        ]);
    }
}
