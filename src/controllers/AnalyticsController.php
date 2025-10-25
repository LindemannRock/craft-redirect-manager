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

        $siteId = Craft::$app->getRequest()->getQueryParam('siteId');

        // Get chart data (last 30 days)
        $chartData = RedirectManager::$plugin->analytics->getChartData($siteId, 30);

        // Get most common 404s
        $mostCommon = RedirectManager::$plugin->analytics->getMostCommon404s($siteId, 10);

        // Get recent 404s
        $recentHandled = RedirectManager::$plugin->analytics->getRecent404s($siteId, 5, true);
        $recentUnhandled = RedirectManager::$plugin->analytics->getRecent404s($siteId, 5, false);

        // Get counts
        $totalCount = RedirectManager::$plugin->analytics->getAnalyticsCount($siteId);
        $handledCount = RedirectManager::$plugin->analytics->getAnalyticsCount($siteId, true);
        $unhandledCount = RedirectManager::$plugin->analytics->getAnalyticsCount($siteId, false);

        // Get device analytics
        $deviceBreakdown = RedirectManager::$plugin->analytics->getDeviceBreakdown($siteId, 30);
        $browserBreakdown = RedirectManager::$plugin->analytics->getBrowserBreakdown($siteId, 30);
        $osBreakdown = RedirectManager::$plugin->analytics->getOsBreakdown($siteId, 30);
        $botStats = RedirectManager::$plugin->analytics->getBotStats($siteId, 30);

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
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:viewAnalytics');

        // Check if specific analytics were selected
        $analyticsIdsJson = Craft::$app->getRequest()->getBodyParam('analyticsIds');
        $analyticsIds = $analyticsIdsJson ? json_decode($analyticsIdsJson, true) : null;

        $csv = RedirectManager::$plugin->analytics->exportToCsv(null, $analyticsIds);

        $filename = 'redirect-analytics-' . date('Y-m-d-His') . '.csv';

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
        $this->requirePermission('redirectManager:viewAnalytics');

        $siteId = Craft::$app->getRequest()->getQueryParam('siteId');
        $days = (int)Craft::$app->getRequest()->getQueryParam('days', 30);

        $chartData = RedirectManager::$plugin->analytics->getChartData($siteId, $days);

        return $this->asJson([
            'success' => true,
            'data' => $chartData,
        ]);
    }
}
