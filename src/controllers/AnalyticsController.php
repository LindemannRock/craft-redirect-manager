<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\controllers;

use Craft;
use craft\db\Query;
use craft\web\Controller;
use lindemannrock\base\helpers\CpNavHelper;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\records\AnalyticsRecord;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use yii\web\ForbiddenHttpException;
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
     * Validate and resolve a siteId against the user's editable sites.
     *
     * @param int|null $siteId The requested site ID (null = all editable sites)
     * @return int|array<int> Validated site ID, or array of editable site IDs
     * @throws ForbiddenHttpException if user doesn't have access to the requested site
     */
    private function _resolveSiteId(?int $siteId): int|array
    {
        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();

        if ($siteId !== null) {
            if (!in_array($siteId, $editableSiteIds, true)) {
                throw new ForbiddenHttpException(Craft::t('redirect-manager', 'User does not have permission to view analytics for this site.'));
            }
            return $siteId;
        }

        return $editableSiteIds;
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
     * Resolve redirect IDs for handled analytics rows in one query.
     *
     * @param array<int, array<string, mixed>> $analytics
     * @return array<string, int>
     */
    private function _getRedirectIdMap(array $analytics): array
    {
        $urlParsedValues = [];
        foreach ($analytics as $stat) {
            if (empty($stat['handled']) || empty($stat['urlParsed'])) {
                continue;
            }
    
            $urlParsedValues[] = (string)$stat['urlParsed'];
        }
    
        $urlParsedValues = array_values(array_unique($urlParsedValues));
        if ($urlParsedValues === []) {
            return [];
        }
    
        $rows = (new Query())
                ->select(['sourceUrlParsed', 'id'])
                ->from(RedirectRecord::tableName())
                ->where(['sourceUrlParsed' => $urlParsedValues, 'enabled' => true])
                ->all();
    
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['sourceUrlParsed']] = (int)$row['id'];
        }
    
        return $map;
    }
    
    /**
     * Count request types while loading only the columns required by the classifier.
     *
     * @param int|array<int> $siteIds
     * @return array{bot: int, probe: int, normal: int}
     */
    private function _getRequestTypeCounts(int|array $siteIds): array
    {
        $counts = [
                'bot' => 0,
                'probe' => 0,
                'normal' => 0,
            ];
    
        $query = (new Query())
                ->select(['url', 'isRobot'])
                ->from(AnalyticsRecord::tableName())
                ->andWhere(['siteId' => $siteIds]);
    
        foreach ($query->each() as $stat) {
            $type = $this->_detectRequestType($stat);
            $counts[$type]++;
        }
    
        return $counts;
    }

    /**
     * Parse and allowlist dashboard query parameters.
     *
     * @return array{handledFilter: string, typeFilter: string, search: string, sort: string, dir: string, page: int, limit: int, offset: int}
     */
    private function _getDashboardParams(int $itemsPerPage): array
    {
        $request = Craft::$app->getRequest();

        $handledFilter = (string) $request->getQueryParam('handled', 'all');
        if (!in_array($handledFilter, ['all', 'handled', 'unhandled'], true)) {
            $handledFilter = 'all';
        }

        $typeFilter = (string) $request->getQueryParam('type', 'all');
        if (!in_array($typeFilter, ['all', 'normal', 'bot', 'probe'], true)) {
            $typeFilter = 'all';
        }

        $search = trim((string) $request->getQueryParam('search', ''));
        if (mb_strlen($search) > 64) {
            $search = mb_substr($search, 0, 64);
        }

        $validSortFields = ['url', 'count', 'lastHit', 'siteId', 'handled', 'deviceType', 'browser', 'requestType'];
        $sort = (string) $request->getQueryParam('sort', 'lastHit');
        if (!in_array($sort, $validSortFields, true)) {
            $sort = 'lastHit';
        }

        $dir = strtolower((string) $request->getQueryParam('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->getQueryParam('page', 1));
        $limit = max(1, $itemsPerPage);

        return [
            'handledFilter' => $handledFilter,
            'typeFilter' => $typeFilter,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];
    }

    /**
     * Fetch filtered dashboard analytics and total count.
     *
     * @param array{handledFilter: string, typeFilter: string, search: string, sort: string, dir: string, page: int, limit: int, offset: int} $params
     * @param array<int> $editableSiteIds
     * @return array{analytics: array<int, array<string, mixed>>, totalCount: int}
     */
    private function _getDashboardAnalytics(array $params, array $editableSiteIds): array
    {
        $query = (new Query())
            ->from(AnalyticsRecord::tableName())
            ->andWhere(['siteId' => $editableSiteIds]);

        if ($params['handledFilter'] === 'handled') {
            $query->andWhere(['handled' => true]);
        } elseif ($params['handledFilter'] === 'unhandled') {
            $query->andWhere(['handled' => false]);
        }

        // Bot/normal map to DB columns; probe is pattern-based and filtered post-query.
        if ($params['typeFilter'] === 'bot') {
            $query->andWhere(['isRobot' => true]);
        } elseif ($params['typeFilter'] === 'normal') {
            $query->andWhere(['or', ['isRobot' => false], ['isRobot' => null]]);
        }

        if ($params['search'] !== '') {
            $query->andWhere(['like', 'url', $params['search']]);
        }

        // Sort: requestType has no DB column, so it maps to isRobot.
        $sortDirection = $params['dir'] === 'asc' ? SORT_ASC : SORT_DESC;
        $sortColumn = $params['sort'] === 'requestType' ? 'isRobot' : $params['sort'];
        $query->orderBy([$sortColumn => $sortDirection]);

        if ($params['typeFilter'] === 'probe') {
            $probeAnalytics = array_filter(
                $query->all(),
                fn(array $stat): bool => $this->_detectRequestType($stat) === 'probe'
            );

            return [
                'analytics' => array_slice(array_values($probeAnalytics), $params['offset'], $params['limit']),
                'totalCount' => count($probeAnalytics),
            ];
        }

        $totalCount = (int)$query->count();
        $analytics = $query
            ->limit($params['limit'])
            ->offset($params['offset'])
            ->all();

        return [
            'analytics' => $analytics,
            'totalCount' => $totalCount,
        ];
    }

    /**
     * Add request type, redirect ID, local last-hit date, and optional site name to analytics rows.
     *
     * @param array<int, array<string, mixed>> $analytics
     * @return array<int, array<string, mixed>>
     */
    private function _prepareDashboardAnalyticsRows(array $analytics, bool $includeSiteName = false): array
    {
        $redirectIdMap = $this->_getRedirectIdMap($analytics);

        foreach ($analytics as $key => $stat) {
            if (!empty($stat['lastHit'])) {
                $utcDate = new \DateTime($stat['lastHit'], new \DateTimeZone('UTC'));
                $utcDate->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
                $analytics[$key]['lastHit'] = $utcDate;
            }

            $analytics[$key]['requestType'] = $this->_detectRequestType($stat);

            if ($includeSiteName) {
                $site = Craft::$app->getSites()->getSiteById($stat['siteId']);
                $analytics[$key]['siteName'] = $site ? $site->name : '-';
            }

            $analytics[$key]['redirectId'] = $stat['handled']
                ? ($redirectIdMap[(string)$stat['urlParsed']] ?? null)
                : null;
        }

        return $analytics;
    }

    /**
     * Build dashboard count values shared by the page and AJAX response.
     *
     * @param array<int> $editableSiteIds
     * @return array{allCount: int, handledCount: int, unhandledCount: int, botCount: int, probeCount: int, normalCount: int}
     */
    private function _getDashboardCounts(array $editableSiteIds): array
    {
        $typeCounts = $this->_getRequestTypeCounts($editableSiteIds);

        return [
            'allCount' => RedirectManager::$plugin->analytics->getAnalyticsCount($editableSiteIds),
            'handledCount' => RedirectManager::$plugin->analytics->getAnalyticsCount($editableSiteIds, true),
            'unhandledCount' => RedirectManager::$plugin->analytics->getAnalyticsCount($editableSiteIds, false),
            'botCount' => $typeCounts['bot'],
            'probeCount' => $typeCounts['probe'],
            'normalCount' => $typeCounts['normal'],
        ];
    }

    /**
     * Dashboard - 404 list with filters
     *
     * @return Response
     * @since 5.1.0
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

        // Scope to user's editable sites
        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
        $params = $this->_getDashboardParams((int)$settings->itemsPerPage);
        $result = $this->_getDashboardAnalytics($params, $editableSiteIds);
        $analytics = $this->_prepareDashboardAnalyticsRows($result['analytics']);
        $counts = $this->_getDashboardCounts($editableSiteIds);

        return $this->renderTemplate('redirect-manager/dashboard/index', [
            'analytics' => $analytics,
            'settings' => $settings,
            'totalCount' => $result['totalCount'],
            'allCount' => $counts['allCount'],
            'handledCount' => $counts['handledCount'],
            'unhandledCount' => $counts['unhandledCount'],
            'handledFilter' => $params['handledFilter'],
            'typeFilter' => $params['typeFilter'],
            'search' => $params['search'],
            'sort' => $params['sort'],
            'dir' => $params['dir'],
            'botCount' => $counts['botCount'],
            'probeCount' => $counts['probeCount'],
            'normalCount' => $counts['normalCount'],
            'page' => $params['page'],
            'limit' => $params['limit'],
            'offset' => $params['offset'],
            'canCreate' => $user->checkPermission('redirectManager:createRedirects'),
            'canEdit' => $user->checkPermission('redirectManager:editRedirects'),
            'canClearAnalytics' => $user->checkPermission('redirectManager:clearAnalytics'),
            'canExportAnalytics' => $user->checkPermission('redirectManager:exportAnalytics'),
        ]);
    }

    /**
     * Analytics index - Charts and analytics
     *
     * @return Response
     * @since 5.1.0
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('redirectManager:viewAnalytics');

        $request = Craft::$app->getRequest();
        $siteId = $request->getQueryParam('siteId');
        $siteId = $siteId ? (int)$siteId : null; // Convert empty string to null
        $effectiveSiteId = $this->_resolveSiteId($siteId);
        $dateRange = $request->getQueryParam('dateRange', DateRangeHelper::getDefaultDateRange(RedirectManager::$plugin->id));

        // Convert date range to days + bounds
        $days = $this->_convertDateRangeToDays($dateRange);
        $dateFilter = $this->_getDateRangeFilter($dateRange);
        $startDate = $dateFilter['start'] ?? null;
        $endDate = $dateFilter['end'] ?? null;

        // Overview tab: counts + most common (server-rendered)
        // Everything else is lazy-loaded via AJAX on tab click
        $mostCommon = RedirectManager::$plugin->analytics->getMostCommon404s($effectiveSiteId, 15, null, $days, $startDate, $endDate);
        $totalCount = RedirectManager::$plugin->analytics->getAnalyticsCount($effectiveSiteId, null, $days, $startDate, $endDate);
        $handledCount = RedirectManager::$plugin->analytics->getAnalyticsCount($effectiveSiteId, true, $days, $startDate, $endDate);
        $unhandledCount = RedirectManager::$plugin->analytics->getAnalyticsCount($effectiveSiteId, false, $days, $startDate, $endDate);

        // Get editable sites for site selector
        $sites = Craft::$app->getSites()->getEditableSites();

        return $this->renderTemplate('redirect-manager/analytics/index', [
            'mostCommon' => $mostCommon,
            'totalCount' => $totalCount,
            'handledCount' => $handledCount,
            'unhandledCount' => $unhandledCount,
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
     * @since 5.1.0
     */
    public function actionGetDashboardData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:viewAnalytics');

        $settings = RedirectManager::$plugin->getSettings();

        // Scope to user's editable sites
        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
        $params = $this->_getDashboardParams((int)$settings->itemsPerPage);
        $result = $this->_getDashboardAnalytics($params, $editableSiteIds);
        $analytics = $this->_prepareDashboardAnalyticsRows($result['analytics'], true);
        $counts = $this->_getDashboardCounts($editableSiteIds);

        return $this->asJson([
            'success' => true,
            'analytics' => array_values($analytics),
            'totalCount' => $result['totalCount'],
            'allCount' => $counts['allCount'],
            'handledCount' => $counts['handledCount'],
            'unhandledCount' => $counts['unhandledCount'],
            'botCount' => $counts['botCount'],
            'probeCount' => $counts['probeCount'],
            'normalCount' => $counts['normalCount'],
            'page' => $params['page'],
            'limit' => $params['limit'],
            'offset' => $params['offset'],
            'totalPages' => (int)ceil($result['totalCount'] / $params['limit']),
        ]);
    }

    /**
     * Create redirect from 404
     *
     * @return Response
     * @since 5.1.0
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
     * @since 5.1.0
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:clearAnalytics');

        $analyticId = Craft::$app->getRequest()->getRequiredBodyParam('analyticId');

        if (RedirectManager::$plugin->analytics->deleteAnalytic($analyticId)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['success' => false, 'error' => Craft::t('redirect-manager', 'Could not delete analytics record')]);
    }

    /**
     * Clear all analytics
     *
     * @return Response
     * @since 5.1.0
     */
    public function actionClearAll(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:clearAnalytics');

        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;
        $effectiveSiteId = $this->_resolveSiteId($siteId);

        $deleted = RedirectManager::$plugin->analytics->clearAnalytics($effectiveSiteId);

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
     * @since 5.1.0
     */
    public function actionExport(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:exportAnalytics');

        $request = Craft::$app->getRequest();
        $siteId = $request->getBodyParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;
        $effectiveSiteId = $this->_resolveSiteId($siteId);
        $redirectId = $request->getBodyParam('redirectId');
        $redirectId = $redirectId ? (int)$redirectId : null;

        // Check if specific analytics were selected
        $analyticIdsJson = $request->getBodyParam('analyticIds');
        $analyticIds = $analyticIdsJson ? json_decode($analyticIdsJson, true) : null;

        // Get date range and format from body params
        // Accept both 'range' and 'dateRange' parameter names
        $dateRange = $request->getBodyParam('range') ?? $request->getBodyParam('dateRange', 'all');
        $format = $request->getBodyParam('format', 'csv');

        // Convert date range to days and get date filter
        $days = $this->_convertDateRangeToDays($dateRange);
        $dateFilter = $this->_getDateRangeFilter($dateRange);
        $startDate = $dateFilter['start'] ?? null;
        $endDate = $dateFilter['end'] ?? null;

        // Get analytics data
        $analyticsData = RedirectManager::$plugin->analytics->getExportData($effectiveSiteId, $analyticIds, $days, $startDate, $endDate, $redirectId);

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
                $filenameParts[] = $redirect->sourceUrl;
            }
        }

        // Add site to filename if filtered
        if ($siteId) {
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site) {
                $filenameParts[] = $site->name;
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
        $extension = ExportHelper::extensionForFormat($format);
        $filename = ExportHelper::filename($settings, $filenameParts, $extension);

        return ExportHelper::dispatchTable(
            rows: $analyticsData,
            headers: $headers,
            format: $format,
            filename: $filename,
            dateColumns: $dateColumns,
            excelOptions: [
                'sheetTitle' => Craft::t('redirect-manager', 'Analytics'),
            ],
        );
    }

    /**
     * Export analytics to CSV
     *
     * Used by dashboard for bulk export of selected items.
     *
     * @return Response
     * @since 5.1.0
     */
    public function actionExportCsv(): Response
    {
        return $this->actionExport();
    }

    /**
     * Get chart data (AJAX)
     *
     * @return Response
     * @since 5.1.0
     */
    public function actionGetChartData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:viewAnalytics');

        $siteId = Craft::$app->getRequest()->getQueryParam('siteId');
        $siteId = $siteId ? (int)$siteId : null;
        $effectiveSiteId = $this->_resolveSiteId($siteId);
        $days = (int)Craft::$app->getRequest()->getQueryParam('days', 30);

        $chartData = RedirectManager::$plugin->analytics->getChartData($effectiveSiteId, $days);
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
     * @since 5.1.0
     */
    public function actionGetData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:viewAnalytics');

        $request = Craft::$app->getRequest();
        $siteId = $request->getBodyParam('siteId');
        $siteId = $siteId ? (int)$siteId : null; // Convert empty string to null
        $effectiveSiteId = $this->_resolveSiteId($siteId);
        $dateRange = $request->getBodyParam('dateRange', DateRangeHelper::getDefaultDateRange(RedirectManager::$plugin->id));
        $type = $request->getBodyParam('type', 'summary');

        $validTypes = ['summary', 'chart', 'devices', 'browsers', 'os', 'bots', 'bot-stats', 'countries', 'cities', 'recent-handled', 'recent-unhandled'];
        if (!in_array($type, $validTypes, true)) {
            throw new \yii\web\BadRequestHttpException('Invalid data type.');
        }

        // Convert date range to days
        $days = $this->_convertDateRangeToDays($dateRange);

        // Get explicit date range filter (for today/yesterday)
        $dateFilter = $this->_getDateRangeFilter($dateRange);
        $startDate = $dateFilter['start'] ?? null;
        $endDate = $dateFilter['end'] ?? null;

        $data = null;

        switch ($type) {
            case 'summary':
                // Overview tab: counts + most common table only
                $totalCount = RedirectManager::$plugin->analytics->getAnalyticsCount($effectiveSiteId, null, $days, $startDate, $endDate);
                $handledCount = RedirectManager::$plugin->analytics->getAnalyticsCount($effectiveSiteId, true, $days, $startDate, $endDate);
                $unhandledCount = RedirectManager::$plugin->analytics->getAnalyticsCount($effectiveSiteId, false, $days, $startDate, $endDate);

                $mostCommon = RedirectManager::$plugin->analytics->getMostCommon404s($effectiveSiteId, 15, null, $days, $startDate, $endDate);

                $data = [
                    'totalCount' => $totalCount,
                    'handledCount' => $handledCount,
                    'unhandledCount' => $unhandledCount,
                    'mostCommon' => $mostCommon,
                ];
                break;

            case 'chart':
                $data = RedirectManager::$plugin->analytics->getChartData($effectiveSiteId, $days, $startDate, $endDate);
                $data = $this->_normalizeChartData($data, $startDate, $endDate);
                break;

            case 'devices':
                $data = RedirectManager::$plugin->analytics->getDeviceBreakdown($effectiveSiteId, $days, $startDate, $endDate);
                break;

            case 'browsers':
                $data = RedirectManager::$plugin->analytics->getBrowserBreakdown($effectiveSiteId, $days, $startDate, $endDate);
                break;

            case 'os':
                $data = RedirectManager::$plugin->analytics->getOsBreakdown($effectiveSiteId, $days, $startDate, $endDate);
                break;

            case 'bots':
                $data = RedirectManager::$plugin->analytics->getBotStats($effectiveSiteId, $days, $startDate, $endDate);
                break;

            case 'bot-stats':
                $data = RedirectManager::$plugin->analytics->getBotStats($effectiveSiteId, $days, $startDate, $endDate);
                break;

            case 'countries':
                $data = RedirectManager::$plugin->analytics->getTopCountries($effectiveSiteId, $days, 15, $startDate, $endDate);
                break;

            case 'cities':
                $data = RedirectManager::$plugin->analytics->getTopCities($effectiveSiteId, $days, 15, $startDate, $endDate);
                break;

            case 'recent-handled':
                $data = $this->_formatRecentForAjax(
                    RedirectManager::$plugin->analytics->getRecent404s($effectiveSiteId, 5, true, $days, $startDate, $endDate)
                );
                break;

            case 'recent-unhandled':
                $data = $this->_formatRecentForAjax(
                    RedirectManager::$plugin->analytics->getRecent404s($effectiveSiteId, 5, false, $days, $startDate, $endDate)
                );
                break;
        }

        return $this->asJson([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Format recent 404s data for AJAX response.
     *
     * Resolves site names and formats dates server-side so the JS
     * render functions only need to escape and display.
     *
     * @param array $results Raw results from AnalyticsService::getRecent404s()
     * @return array Formatted results with date/time strings and site names
     */
    private function _formatRecentForAjax(array $results): array
    {
        $formatted = [];

        foreach ($results as $stat) {
            $date = $stat['lastHit'] ?? null;

            // Resolve site name server-side (avoids N+1 in JS)
            $siteName = null;
            if (!empty($stat['siteId'])) {
                $site = Craft::$app->getSites()->getSiteById((int)$stat['siteId']);
                $siteName = $site ? $site->name : null;
            }

            $formatted[] = [
                'url' => $stat['url'] ?? '',
                'count' => (int)($stat['count'] ?? 0),
                'referrer' => $stat['referrer'] ?? null,
                'siteId' => $stat['siteId'] ?? null,
                'siteName' => $siteName ?? '—',
                'date' => $date instanceof \DateTime
                    ? DateFormatHelper::formatDate($date, 'cascade', true, false)
                    : null,
                'time' => $date instanceof \DateTime
                    ? DateFormatHelper::formatTime($date, 'cascade', null, false)
                    : null,
            ];
        }

        return $formatted;
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
