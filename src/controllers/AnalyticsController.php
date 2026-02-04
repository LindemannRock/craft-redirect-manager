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
use lindemannrock\base\helpers\CpNavHelper;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\records\RedirectRecord;
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
        $this->setLoggingHandle(RedirectManager::$plugin->id);
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
        $settings = RedirectManager::$plugin->getSettings();

        // If user doesn't have viewAnalytics permission, redirect to appropriate section
        if (!$user->checkPermission('redirectManager:viewAnalytics')) {
            $sections = RedirectManager::$plugin->getCpSections($settings, false, true);
            $route = CpNavHelper::firstAccessibleRoute($user, $settings, $sections);
            if ($route) {
                return $this->redirect($route);
            }
            // No permissions at all - show forbidden
            $this->requirePermission('redirectManager:viewAnalytics');
        }

        $request = Craft::$app->getRequest();

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
        $dateRange = $request->getQueryParam('dateRange', DateRangeHelper::getDefaultDateRange(RedirectManager::$plugin->id));

        // Convert date range to days + bounds
        $days = $this->_convertDateRangeToDays($dateRange);
        $dateFilter = $this->_getDateRangeFilter($dateRange);
        $startDate = $dateFilter['start'] ?? null;
        $endDate = $dateFilter['end'] ?? null;

        // Get chart data
        $chartData = RedirectManager::$plugin->analytics->getChartData($siteId, $days, $startDate, $endDate);
        $chartData = $this->_normalizeChartData($chartData, $startDate, $endDate);

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
        $deviceBreakdown = RedirectManager::$plugin->analytics->getDeviceBreakdown($siteId, $days, $startDate, $endDate);
        $browserBreakdown = RedirectManager::$plugin->analytics->getBrowserBreakdown($siteId, $days, $startDate, $endDate);
        $osBreakdown = RedirectManager::$plugin->analytics->getOsBreakdown($siteId, $days, $startDate, $endDate);
        $botStats = RedirectManager::$plugin->analytics->getBotStats($siteId, $days, $startDate, $endDate);

        // Get geographic analytics
        $topCountries = RedirectManager::$plugin->analytics->getTopCountries($siteId, $days, 15, $startDate, $endDate);
        $topCities = RedirectManager::$plugin->analytics->getTopCities($siteId, $days, 15, $startDate, $endDate);

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
            'pluginHandle' => RedirectManager::$plugin->id,
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
     * Export analytics
     *
     * @return Response
     */
    public function actionExport(): Response
    {
        $this->requirePermission('redirectManager:exportAnalytics');

        $request = Craft::$app->getRequest();
        $siteId = $request->getQueryParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;
        $redirectId = $request->getQueryParam('redirectId');
        $redirectId = $redirectId ? (int)$redirectId : null;

        // Check if specific analytics were selected (from query param or body param)
        $analyticIdsJson = $request->getQueryParam('analyticIds') ?? $request->getBodyParam('analyticIds');
        $analyticIds = $analyticIdsJson ? json_decode($analyticIdsJson, true) : null;

        // Get date range and format from query params
        // Accept both 'range' and 'dateRange' parameter names
        $dateRange = $request->getQueryParam('range') ?? $request->getQueryParam('dateRange', 'all');
        $format = $request->getQueryParam('format', 'csv');

        // Convert date range to days and get date filter
        $days = $this->_convertDateRangeToDays($dateRange);
        $dateFilter = $this->_getDateRangeFilter($dateRange);
        $startDate = $dateFilter['start'] ?? null;
        $endDate = $dateFilter['end'] ?? null;

        // Get analytics data
        $analyticsData = RedirectManager::$plugin->analytics->getExportData($siteId, $analyticIds, $days, $startDate, $endDate, $redirectId);

        // Check for empty data
        if (empty($analyticsData)) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'No analytics data to export.'));
            return $this->redirect(Craft::$app->getRequest()->getReferrer() ?? 'redirect-manager/analytics');
        }

        $settings = RedirectManager::$plugin->getSettings();

        // Build filename parts
        $dateRangeLabel = $dateRange === 'all' ? 'alltime' : $dateRange;
        $filenameParts = ['analytics'];

        // Add redirect source to filename if filtered
        if ($redirectId) {
            $redirect = RedirectRecord::findOne($redirectId);
            if ($redirect) {
                // Clean the source URL for filename (remove special chars, limit length)
                $cleanSource = preg_replace('/[^a-zA-Z0-9-_]/', '-', $redirect->sourceUrl);
                $cleanSource = preg_replace('/-+/', '-', $cleanSource); // Remove multiple dashes
                $cleanSource = trim($cleanSource, '-');
                $cleanSource = substr($cleanSource, 0, 50); // Limit length
                $filenameParts[] = $cleanSource;
            }
        }

        // Add site to filename if filtered
        if ($siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $filenameParts[] = strtolower(preg_replace('/[^a-zA-Z0-9-_]/', '', str_replace(' ', '-', $site->name)));
            }
        }

        $filenameParts[] = $dateRangeLabel;

        // CSV/Excel headers
        $headers = [
            'url' => Craft::t('redirect-manager', 'URL'),
            'siteId' => Craft::t('redirect-manager', 'Site ID'),
            'siteName' => Craft::t('redirect-manager', 'Site'),
            'count' => Craft::t('redirect-manager', 'Hits'),
            'handled' => Craft::t('redirect-manager', 'Handled'),
            'referrer' => Craft::t('redirect-manager', 'Referrer'),
            'deviceType' => Craft::t('redirect-manager', 'Device'),
            'browser' => Craft::t('redirect-manager', 'Browser'),
            'os' => Craft::t('redirect-manager', 'OS'),
            'country' => Craft::t('redirect-manager', 'Country'),
            'city' => Craft::t('redirect-manager', 'City'),
            'isRobot' => Craft::t('redirect-manager', 'Is Bot'),
            'botName' => Craft::t('redirect-manager', 'Bot Name'),
            'lastHit' => Craft::t('redirect-manager', 'Last Hit'),
        ];

        // Date columns for formatting
        $dateColumns = ['lastHit'];

        // Export based on format
        $extension = $format === 'excel' ? 'xlsx' : $format;
        $filename = ExportHelper::filename($settings, $filenameParts, $extension);

        return match ($format) {
            'json' => ExportHelper::toJson($analyticsData, $filename, $dateColumns),
            'excel' => ExportHelper::toExcel($analyticsData, $headers, $filename, $dateColumns, [
                'sheetTitle' => Craft::t('redirect-manager', 'Analytics'),
            ]),
            default => ExportHelper::toCsv($analyticsData, $headers, $filename, $dateColumns),
        };
    }

    /**
     * Export analytics to CSV
     *
     * Used by dashboard for bulk export of selected items.
     *
     * @return Response
     */
    public function actionExportCsv(): Response
    {
        return $this->actionExport();
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
        $chartData = $this->_normalizeChartData($chartData, $this->_getDaysStartDate($days), null);

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
        $dateRange = $request->getBodyParam('dateRange', DateRangeHelper::getDefaultDateRange(RedirectManager::$plugin->id));
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
                $botStats = RedirectManager::$plugin->analytics->getBotStats($siteId, $days, $startDate, $endDate);

                // Get geographic data
                $topCountries = RedirectManager::$plugin->analytics->getTopCountries($siteId, $days, 15, $startDate, $endDate);
                $topCities = RedirectManager::$plugin->analytics->getTopCities($siteId, $days, 15, $startDate, $endDate);

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
                $data = RedirectManager::$plugin->analytics->getChartData($siteId, $days, $startDate, $endDate);
                $data = $this->_normalizeChartData($data, $startDate, $endDate);
                break;

            case 'devices':
                $data = RedirectManager::$plugin->analytics->getDeviceBreakdown($siteId, $days, $startDate, $endDate);
                break;

            case 'browsers':
                $data = RedirectManager::$plugin->analytics->getBrowserBreakdown($siteId, $days, $startDate, $endDate);
                break;

            case 'os':
                $data = RedirectManager::$plugin->analytics->getOsBreakdown($siteId, $days, $startDate, $endDate);
                break;

            case 'bots':
                $data = RedirectManager::$plugin->analytics->getBotStats($siteId, $days, $startDate, $endDate);
                break;

            case 'countries':
                $data = RedirectManager::$plugin->analytics->getTopCountries($siteId, $days, 15, $startDate, $endDate);
                break;

            case 'cities':
                $data = RedirectManager::$plugin->analytics->getTopCities($siteId, $days, 15, $startDate, $endDate);
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
        $bounds = DateRangeHelper::getBounds($dateRange);
        $start = $bounds['start'] ?? null;
        $end = $bounds['end'] ?? null;

        if (!$start && !$end) {
            return 36500; // ~100 years (effectively all data)
        }

        $end = $end ?? new \DateTime('now', new \DateTimeZone('UTC'));

        if (!$start) {
            return 30;
        }

        $days = (int)$start->diff($end)->format('%a');
        return max(1, $days);
    }

    /**
     * Get date range filter for SQL queries
     *
     * @param string $dateRange
     * @return array|null ['start' => DateTime, 'end' => DateTime] or null for no filter
     */
    private function _getDateRangeFilter(string $dateRange): ?array
    {
        $bounds = DateRangeHelper::getBounds($dateRange);
        if (!$bounds['start'] && !$bounds['end']) {
            return null;
        }

        return [
            'start' => $bounds['start'] ?? null,
            'end' => $bounds['end'] ?? null,
        ];
    }

    /**
     * Normalize chart rows into a local-time label range.
     *
     * @param array $rows
     * @param \DateTime|null $startDateUtc
     * @param \DateTime|null $endDateUtc
     * @return array
     */
    private function _normalizeChartData(array $rows, ?\DateTime $startDateUtc, ?\DateTime $endDateUtc): array
    {
        if (empty($rows)) {
            return [];
        }

        $tz = new \DateTimeZone(Craft::$app->getTimeZone());
        $startLocal = $startDateUtc ? (clone $startDateUtc)->setTimezone($tz) : null;
        $endLocal = $endDateUtc ? (clone $endDateUtc)->setTimezone($tz)->modify('-1 day') : new \DateTime('now', $tz);

        $map = [];
        foreach ($rows as $row) {
            $rowDate = new \DateTime($row['date'], new \DateTimeZone('UTC'));
            $localKey = $rowDate->setTimezone($tz)->format('Y-m-d');
            if (!isset($map[$localKey])) {
                $map[$localKey] = [
                    'total' => 0,
                    'handled' => 0,
                    'unhandled' => 0,
                ];
            }
            $map[$localKey]['total'] += (int)($row['total'] ?? 0);
            $map[$localKey]['handled'] += (int)($row['handled'] ?? 0);
            $map[$localKey]['unhandled'] += (int)($row['unhandled'] ?? 0);
        }

        if ($startLocal === null) {
            ksort($map);
            $normalized = [];
            foreach ($map as $date => $counts) {
                $normalized[] = ['date' => $date] + $counts;
            }
            return $normalized;
        }

        $startLocal->setTime(0, 0, 0);
        $endLocal->setTime(0, 0, 0);
        $cursor = clone $startLocal;

        $normalized = [];
        while ($cursor <= $endLocal) {
            $key = $cursor->format('Y-m-d');
            $counts = $map[$key] ?? ['total' => 0, 'handled' => 0, 'unhandled' => 0];
            $normalized[] = ['date' => $key] + $counts;
            $cursor->modify('+1 day');
        }

        return $normalized;
    }

    /**
     * Build a UTC start date for "last X days" ranges.
     *
     * @param int $days
     * @return \DateTime|null
     */
    private function _getDaysStartDate(int $days): ?\DateTime
    {
        if ($days >= 36500) {
            return null;
        }

        $tz = new \DateTimeZone(Craft::$app->getTimeZone());
        $startLocal = new \DateTime('now', $tz);
        $startLocal->modify("-{$days} days")->setTime(0, 0, 0);

        return $startLocal->setTimezone(new \DateTimeZone('UTC'));
    }
}
