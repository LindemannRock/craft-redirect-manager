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
 * @since     5.0.0
 */
class AnalyticsController extends Controller
{
    use LoggingTrait;

    /**
     * Security probe URL patterns (vulnerability scanning attempts)
     * These patterns are used for DETECTION in the dashboard, not exclusion.
     * They can be slightly broader than exclude patterns since they just flag, not hide.
     */
    private const SECURITY_PROBE_PATTERNS = [
        // Database dumps
        '/\.sql($|\.)/i',
        '/\/(dump|backup|database|db)\.(sql|zip|tar|gz|rar|7z)/i',

        // Config/sensitive files
        '/\/\.env($|\.)/i',
        '/\/\.git($|\/)/i',
        '/\/\.htaccess$/i',
        '/\/\.htpasswd$/i',
        '/\/\.aws($|\/)/i',
        '/\/\.ssh($|\/)/i',
        '/\/\.DS_Store$/i',
        '/^\/composer\.(json|lock)$/i',
        '/^\/package(-lock)?\.json$/i',
        '/\/wp-config\.php/i',

        // Admin panels / tools
        '/\/phpmyadmin/i',
        '/\/adminer\.php/i',
        '/^\/pma($|\/)/i',
        '/^\/mysql($|\/)/i',
        '/^\/myadmin($|\/)/i',

        // Shell/exploit attempts
        '/\/shell\.php/i',
        '/\/cmd\.php/i',
        '/\/c99\.php/i',
        '/\/r57\.php/i',
        '/\/webshell/i',
        '/\/cgi-bin\//i',
        '/\/eval-stdin/i',

        // Common scanner paths
        '/\/sftp(-config)?\.json/i',
        '/^\/debug($|\/)/i',
        '/\/phpinfo\.php/i',
        '/^\/server-status($|\/)/i',
        '/\.axd$/i',
        '/\/web\.config$/i',
        '/\/xmlrpc\.php$/i',
    ];

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('redirect-manager');
    }

    /**
     * Detect the type of a 404 request
     *
     * @param array $stat Analytics record data
     * @return string 'probe', 'bot', or 'normal'
     */
    private function _detectRequestType(array $stat): string
    {
        // Check if it's a security probe based on URL patterns
        $url = $stat['url'] ?? '';
        foreach (self::SECURITY_PROBE_PATTERNS as $pattern) {
            if (preg_match($pattern, $url)) {
                return 'probe';
            }
        }

        // Check if it's a bot
        if (!empty($stat['isRobot'])) {
            return 'bot';
        }

        return 'normal';
    }

    /**
     * Dashboard - 404 list with filters
     *
     * @return Response
     */
    public function actionDashboard(): Response
    {
        $user = Craft::$app->getUser();

        // If user doesn't have viewAnalytics permission, redirect to appropriate section
        if (!$user->checkPermission('redirectManager:viewAnalytics')) {
            // Check what they do have access to and redirect there
            if ($user->checkPermission('redirectManager:viewRedirects') ||
                $user->checkPermission('redirectManager:createRedirects') ||
                $user->checkPermission('redirectManager:editRedirects') ||
                $user->checkPermission('redirectManager:deleteRedirects')) {
                return $this->redirect('redirect-manager/redirects');
            }
            if ($user->checkPermission('redirectManager:manageImportExport')) {
                return $this->redirect('redirect-manager/import-export');
            }
            if ($user->checkPermission('redirectManager:viewLogs')) {
                return $this->redirect('redirect-manager/logs');
            }
            if ($user->checkPermission('redirectManager:manageSettings')) {
                return $this->redirect('redirect-manager/settings');
            }
            // No permissions at all - show forbidden
            $this->requirePermission('redirectManager:viewAnalytics');
        }

        $request = Craft::$app->getRequest();
        $settings = RedirectManager::$plugin->getSettings();

        // Get filter parameters
        $search = $request->getQueryParam('search', '');
        $handledFilter = $request->getQueryParam('handled', 'all');
        $typeFilter = $request->getQueryParam('type', 'all');
        $sort = $request->getQueryParam('sort', 'lastHit');
        $dir = strtolower($request->getQueryParam('dir', 'desc'));
        // Whitelist sort direction to prevent SQL injection
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }
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

        // Apply type filter (bot only - probes are detected dynamically)
        if ($typeFilter === 'bot') {
            $query->andWhere(['isRobot' => true]);
        } elseif ($typeFilter === 'normal') {
            $query->andWhere(['or', ['isRobot' => false], ['isRobot' => null]]);
        }
        // Note: 'probe' filter is applied post-query since it's pattern-based

        // Apply search
        if (!empty($search)) {
            $query->andWhere(['like', 'url', $search]);
        }

        // Apply sorting (array-based to prevent SQL injection)
        $sortDirection = $dir === 'asc' ? SORT_ASC : SORT_DESC;
        $orderBy = match ($sort) {
            'url' => ['url' => $sortDirection],
            'count' => ['count' => $sortDirection],
            'lastHit' => ['lastHit' => $sortDirection],
            'siteId' => ['siteId' => $sortDirection],
            'handled' => ['handled' => $sortDirection],
            'deviceType' => ['deviceType' => $sortDirection],
            'browser' => ['browser' => $sortDirection],
            'requestType' => ['isRobot' => $sortDirection], // requestType maps to isRobot in DB
            default => ['lastHit' => $sortDirection],
        };
        $query->orderBy($orderBy);

        // For probe filter, we need to fetch all and filter in PHP
        // For other filters, use pagination
        if ($typeFilter === 'probe') {
            $allAnalytics = $query->all();

            // Filter to only probes
            $probeAnalytics = array_filter($allAnalytics, function($stat) {
                return $this->_detectRequestType($stat) === 'probe';
            });

            $totalCount = count($probeAnalytics);
            $analytics = array_slice($probeAnalytics, $offset, $limit);
        } else {
            // Get total count for pagination
            $totalCount = $query->count();

            // Apply pagination
            $query->limit($limit)->offset($offset);

            // Get analytics
            $analytics = $query->all();
        }

        // Counters for type filter
        $botCount = 0;
        $probeCount = 0;
        $normalCount = 0;

        // Add redirect ID, detect type, and convert timezone
        // Use key-based iteration to ensure modifications persist
        foreach ($analytics as $key => $stat) {
            // Convert lastHit from UTC to user's timezone
            if (!empty($stat['lastHit'])) {
                $utcDate = new \DateTime($stat['lastHit'], new \DateTimeZone('UTC'));
                $utcDate->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
                $analytics[$key]['lastHit'] = $utcDate;
            }

            // Detect request type (probe, bot, or normal)
            $analytics[$key]['requestType'] = $this->_detectRequestType($stat);

            if ($stat['handled']) {
                $redirectId = (new \craft\db\Query())
                    ->select('id')
                    ->from(\lindemannrock\redirectmanager\records\RedirectRecord::tableName())
                    ->where(['sourceUrlParsed' => $stat['urlParsed'], 'enabled' => true])
                    ->scalar();
                $analytics[$key]['redirectId'] = $redirectId ?: null;
            }
        }

        // Get overall counts
        $allCount = RedirectManager::$plugin->analytics->getAnalyticsCount();
        $handledCount = RedirectManager::$plugin->analytics->getAnalyticsCount(null, true);
        $unhandledCount = RedirectManager::$plugin->analytics->getAnalyticsCount(null, false);

        // Get type counts (for filter display)
        $allAnalyticsForCounts = (new \craft\db\Query())
            ->from(\lindemannrock\redirectmanager\records\AnalyticsRecord::tableName())
            ->all();

        foreach ($allAnalyticsForCounts as $stat) {
            $type = $this->_detectRequestType($stat);
            if ($type === 'probe') {
                $probeCount++;
            } elseif ($type === 'bot') {
                $botCount++;
            } else {
                $normalCount++;
            }
        }

        return $this->renderTemplate('redirect-manager/dashboard/index', [
            'analytics' => $analytics,
            'settings' => $settings,
            'totalCount' => $totalCount,
            'allCount' => $allCount,
            'handledCount' => $handledCount,
            'unhandledCount' => $unhandledCount,
            'typeFilter' => $typeFilter,
            'botCount' => $botCount,
            'probeCount' => $probeCount,
            'normalCount' => $normalCount,
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
        $siteId = $siteId ? (int)$siteId : null; // Convert empty string to null
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

        // Get geographic analytics
        $topCountries = RedirectManager::$plugin->analytics->getTopCountries($siteId, $days);
        $topCities = RedirectManager::$plugin->analytics->getTopCities($siteId, $days);

        // Get all sites for site selector
        $sites = Craft::$app->getSites()->getAllSites();

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
            'topCountries' => $topCountries,
            'topCities' => $topCities,
            'dateRange' => $dateRange,
            'siteId' => $siteId,
            'sites' => $sites,
        ]);
    }

    /**
     * Get dashboard data via AJAX (for auto-refresh without full page reload)
     *
     * @return Response
     */
    public function actionGetDashboardData(): Response
    {
        $this->requirePermission('redirectManager:viewAnalytics');

        $request = Craft::$app->getRequest();
        $settings = RedirectManager::$plugin->getSettings();

        // Get filter parameters
        $search = $request->getQueryParam('search', '');
        $handledFilter = $request->getQueryParam('handled', 'all');
        $typeFilter = $request->getQueryParam('type', 'all');
        $sort = $request->getQueryParam('sort', 'lastHit');
        $dir = strtolower($request->getQueryParam('dir', 'desc'));
        // Whitelist sort direction to prevent SQL injection
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }
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

        // Apply type filter (bot only - probes are detected dynamically)
        if ($typeFilter === 'bot') {
            $query->andWhere(['isRobot' => true]);
        } elseif ($typeFilter === 'normal') {
            $query->andWhere(['or', ['isRobot' => false], ['isRobot' => null]]);
        }

        // Apply search
        if (!empty($search)) {
            $query->andWhere(['like', 'url', $search]);
        }

        // Apply sorting (array-based to prevent SQL injection)
        $sortDirection = $dir === 'asc' ? SORT_ASC : SORT_DESC;
        $orderBy = match ($sort) {
            'url' => ['url' => $sortDirection],
            'count' => ['count' => $sortDirection],
            'lastHit' => ['lastHit' => $sortDirection],
            'siteId' => ['siteId' => $sortDirection],
            'handled' => ['handled' => $sortDirection],
            'deviceType' => ['deviceType' => $sortDirection],
            'browser' => ['browser' => $sortDirection],
            'requestType' => ['isRobot' => $sortDirection], // requestType maps to isRobot in DB
            default => ['lastHit' => $sortDirection],
        };
        $query->orderBy($orderBy);

        // For probe filter, we need to fetch all and filter in PHP
        if ($typeFilter === 'probe') {
            $allAnalytics = $query->all();

            // Filter to only probes
            $probeAnalytics = array_filter($allAnalytics, function($stat) {
                return $this->_detectRequestType($stat) === 'probe';
            });

            $totalCount = count($probeAnalytics);
            $analytics = array_slice(array_values($probeAnalytics), $offset, $limit);
        } else {
            // Get total count for pagination
            $totalCount = $query->count();

            // Apply pagination
            $query->limit($limit)->offset($offset);

            // Get analytics
            $analytics = $query->all();
        }

        // Counters for type filter
        $botCount = 0;
        $probeCount = 0;
        $normalCount = 0;

        // Process analytics data
        // Use key-based iteration to ensure modifications persist
        foreach ($analytics as $key => $stat) {
            // Convert lastHit from UTC to user's timezone
            if (!empty($stat['lastHit'])) {
                $utcDate = new \DateTime($stat['lastHit'], new \DateTimeZone('UTC'));
                $utcDate->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
                $analytics[$key]['lastHit'] = $utcDate->format('Y-m-d H:i:s');
            }

            // Detect request type
            $analytics[$key]['requestType'] = $this->_detectRequestType($stat);

            // Get site name
            $site = Craft::$app->getSites()->getSiteById($stat['siteId']);
            $analytics[$key]['siteName'] = $site ? $site->name : '-';

            // Get redirect ID for handled analytics
            if ($stat['handled']) {
                $redirectId = (new \craft\db\Query())
                    ->select('id')
                    ->from(\lindemannrock\redirectmanager\records\RedirectRecord::tableName())
                    ->where(['sourceUrlParsed' => $stat['urlParsed'], 'enabled' => true])
                    ->scalar();
                $analytics[$key]['redirectId'] = $redirectId ?: null;
            } else {
                $analytics[$key]['redirectId'] = null;
            }
        }

        // Get overall counts
        $allCount = RedirectManager::$plugin->analytics->getAnalyticsCount();
        $handledCount = RedirectManager::$plugin->analytics->getAnalyticsCount(null, true);
        $unhandledCount = RedirectManager::$plugin->analytics->getAnalyticsCount(null, false);

        // Get type counts (for filter display)
        $allAnalyticsForCounts = (new \craft\db\Query())
            ->from(\lindemannrock\redirectmanager\records\AnalyticsRecord::tableName())
            ->all();

        foreach ($allAnalyticsForCounts as $stat) {
            $type = $this->_detectRequestType($stat);
            if ($type === 'probe') {
                $probeCount++;
            } elseif ($type === 'bot') {
                $botCount++;
            } else {
                $normalCount++;
            }
        }

        return $this->asJson([
            'success' => true,
            'analytics' => array_values($analytics),
            'totalCount' => $totalCount,
            'allCount' => $allCount,
            'handledCount' => $handledCount,
            'unhandledCount' => $unhandledCount,
            'botCount' => $botCount,
            'probeCount' => $probeCount,
            'normalCount' => $normalCount,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'totalPages' => (int)ceil($totalCount / $limit),
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
        $this->requirePermission('redirectManager:clearAnalytics');

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
        $siteId = $siteId ? (int)$siteId : null;

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
        $this->requirePermission('redirectManager:exportAnalytics');

        $request = Craft::$app->getRequest();
        $siteId = $request->getQueryParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;

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

        try {
            $csv = RedirectManager::$plugin->analytics->exportToCsv($siteId, $analyticsIds, $days, $startDate, $endDate, $format);

            // Build filename with site name
            $settings = RedirectManager::$plugin->getSettings();
            $filenamePart = strtolower(str_replace(' ', '-', $settings->getLowerDisplayName()));

            // Get site name for filename
            $sitePart = 'all';
            if ($siteId) {
                $site = Craft::$app->getSites()->getSiteById((int)$siteId);
                if ($site) {
                    $sitePart = strtolower(preg_replace('/[^a-zA-Z0-9-_]/', '', str_replace(' ', '-', $site->name)));
                }
            }

            // Use "alltime" instead of "all" for clearer filename
            $dateRangeLabel = $dateRange === 'all' ? 'alltime' : $dateRange;
            $filename = $filenamePart . '-analytics-' . $sitePart . '-' . $dateRangeLabel . '-' . date('Y-m-d') . '.' . $format;

            return Craft::$app->getResponse()
                ->sendContentAsFile($csv, $filename, [
                    'mimeType' => $format === 'csv' ? 'text/csv' : 'application/json',
                ]);
        } catch (\Exception $e) {
            Craft::$app->getSession()->setError($e->getMessage());
            return $this->redirect('redirect-manager/analytics?dateRange=' . $dateRange);
        }
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
        $siteId = $siteId ? (int)$siteId : null;
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
        $siteId = $siteId ? (int)$siteId : null; // Convert empty string to null
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

                // Get geographic data
                $topCountries = RedirectManager::$plugin->analytics->getTopCountries($siteId, $days);
                $topCities = RedirectManager::$plugin->analytics->getTopCities($siteId, $days);

                $data = [
                    'totalCount' => $totalCount,
                    'handledCount' => $handledCount,
                    'unhandledCount' => $unhandledCount,
                    'mostCommon' => $mostCommon,
                    'recentUnhandled' => $recentUnhandled,
                    'topBots' => $botStats['topBots'] ?? [],
                    'topCountries' => $topCountries,
                    'topCities' => $topCities,
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

            case 'countries':
                $data = RedirectManager::$plugin->analytics->getTopCountries($siteId, $days);
                break;

            case 'cities':
                $data = RedirectManager::$plugin->analytics->getTopCities($siteId, $days);
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
