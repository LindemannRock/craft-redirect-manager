<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\controllers;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\base\helpers\SettingsPostHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\RedirectManager;
use yii\web\Response;

/**
 * Settings Controller
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class SettingsController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

    /**
     * Settings index
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->actionGeneral();
    }

    /**
     * General settings
     *
     * @return Response
     */
    public function actionGeneral(): Response
    {
        $this->requirePermission('redirectManager:manageSettings');

        $settings = RedirectManager::$plugin->getSettings();

        return $this->renderTemplate('redirect-manager/settings/general', [
            'settings' => $settings,
        ]);
    }

    /**
     * Analytics settings
     *
     * @return Response
     * @since 5.1.0
     */
    public function actionAnalytics(): Response
    {
        $this->requirePermission('redirectManager:manageSettings');

        $settings = RedirectManager::$plugin->getSettings();

        return $this->renderTemplate('redirect-manager/settings/analytics', [
            'settings' => $settings,
        ]);
    }

    /**
     * Interface settings
     *
     * @return Response
     */
    public function actionInterface(): Response
    {
        $this->requirePermission('redirectManager:manageSettings');

        $settings = RedirectManager::$plugin->getSettings();

        return $this->renderTemplate('redirect-manager/settings/interface', [
            'settings' => $settings,
        ]);
    }

    /**
     * Cache settings
     *
     * @return Response
     * @since 5.1.0
     */
    public function actionCache(): Response
    {
        $this->requirePermission('redirectManager:manageSettings');

        $settings = RedirectManager::$plugin->getSettings();

        return $this->renderTemplate('redirect-manager/settings/cache', [
            'settings' => $settings,
        ]);
    }

    /**
     * Advanced settings
     *
     * @return Response
     */
    public function actionAdvanced(): Response
    {
        $this->requirePermission('redirectManager:manageSettings');

        $settings = RedirectManager::$plugin->getSettings();

        return $this->renderTemplate('redirect-manager/settings/advanced', [
            'settings' => $settings,
        ]);
    }

    /**
     * Backup settings
     *
     * @return Response
     * @since 5.23.0
     */
    public function actionBackup(): Response
    {
        $this->requirePermission('redirectManager:manageSettings');

        $settings = RedirectManager::$plugin->getSettings();
        $settings->validate(['backupPath']);

        return $this->renderTemplate('redirect-manager/settings/backup', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save settings
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageSettings');

        // Load current settings from database
        $settings = Settings::loadFromDatabase();

        // Capture old backup settings before applying new values (for schedule change detection)
        $oldBackupEnabled = $settings->backupEnabled;
        $oldBackupSchedule = $settings->backupSchedule;

        // Get only the posted settings (fields from the current page)
        $settingsData = Craft::$app->getRequest()->getBodyParam('settings', []);
        $section = $this->_validSettingsSection(
            $this->request->getBodyParam('section', 'general'),
        );
        $sectionAttributes = $this->_validationAttributesForSection($section);

        $result = SettingsPostHelper::apply(
            model: $settings,
            postedValues: is_array($settingsData) ? $settingsData : [],
            allowedAttributes: $sectionAttributes,
            shouldSkipAttribute: fn(string $attribute): bool => $settings->isOverriddenByConfig($attribute),
        );

        $attributesToValidate = $result->attributesToValidate;

        // Validate only the current section
        $isValid = $settings->validate($attributesToValidate);
        if (!$isValid || $result->hasErrors || $settings->hasErrors()) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Could not save settings.'));

            $template = "redirect-manager/settings/{$section}";

            return $this->renderTemplate($template, [
                'settings' => $settings,
            ]);
        }

        // Save only current section attributes to database
        if ($settings->saveToDatabase($attributesToValidate)) {
            // Detect backup schedule changes and update queue jobs
            if ($oldBackupEnabled !== $settings->backupEnabled ||
                $oldBackupSchedule !== $settings->backupSchedule
            ) {
                RedirectManager::$plugin->handleBackupScheduleChange($settings);
            }

            Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'Settings saved.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Could not save settings.'));
            return null;
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Apply recommended exclude patterns and headers
     *
     * @return Response|null
     */
    public function actionApplyRecommended(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageSettings');

        // Load current settings from database
        $settings = Settings::loadFromDatabase();

        // Recommended exclude patterns
        $recommendedExcludePatterns = [
            ['pattern' => '^/admin'],
            ['pattern' => '^/cms'],
            ['pattern' => '^/cpresources'],
            ['pattern' => '^/actions'],
            ['pattern' => '^/\\.well-known'],
            ['pattern' => '^/dist/.*/assets'],  // Versioned build assets (Vite/Webpack)
        ];

        // Recommended additional headers
        $recommendedHeaders = [
            ['name' => 'X-Robots-Tag', 'value' => 'noindex, nofollow'], // SEO: Prevent indexing old URLs
            ['name' => 'X-Redirect-By', 'value' => 'Redirect Manager'],  // Tracking/debugging
        ];

        // Add recommended patterns (avoid duplicates)
        $addedPatterns = 0;
        if (!$settings->isOverriddenByConfig('excludePatterns')) {
            $existingPatterns = array_column($settings->excludePatterns, 'pattern');
            foreach ($recommendedExcludePatterns as $recommended) {
                if (!in_array($recommended['pattern'], $existingPatterns)) {
                    $settings->excludePatterns[] = $recommended;
                    $addedPatterns++;
                }
            }
        }

        // Add recommended headers (avoid duplicates)
        $addedHeaders = 0;
        if (!$settings->isOverriddenByConfig('additionalHeaders')) {
            $existingHeaders = array_column($settings->additionalHeaders, 'name');
            foreach ($recommendedHeaders as $recommended) {
                if (!in_array($recommended['name'], $existingHeaders)) {
                    $settings->additionalHeaders[] = $recommended;
                    $addedHeaders++;
                }
            }
        }

        // Save settings
        if ($settings->saveToDatabase(['excludePatterns', 'additionalHeaders'])) {
            if ($addedPatterns > 0 || $addedHeaders > 0) {
                $message = Craft::t('redirect-manager', 'Added {patterns} exclude pattern(s) and {headers} header(s).', [
                    'patterns' => $addedPatterns,
                    'headers' => $addedHeaders,
                ]);
                Craft::$app->getSession()->setNotice($message);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'All recommended settings are already applied.'));
            }
        } else {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Could not apply recommended settings'));
        }

        return $this->redirect('redirect-manager/settings/advanced');
    }

    /**
     * Apply WordPress migration filters
     *
     * @return Response|null
     */
    public function actionApplyWordpressFilters(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageSettings');

        // Load current settings from database
        $settings = Settings::loadFromDatabase();

        // WordPress bot/spam patterns to exclude
        $wordpressExcludePatterns = [
            ['pattern' => '/wp-includes'],       // WP core files probing
            ['pattern' => '/wp-content/themes'], // WP theme files
            ['pattern' => '/wp-content/plugins'], // WP plugin files
            ['pattern' => '/wp-json'],           // WP REST API
            ['pattern' => '/feed'],              // RSS feeds
            ['pattern' => '\\?p=\\d+'],          // WP permalink format (?p=123)
            ['pattern' => '/xmlrpc\\.php'],      // XML-RPC attacks
            ['pattern' => '/wp-login\\.php'],    // WP login page
            ['pattern' => '/wp-admin'],          // WP admin panel
            ['pattern' => '/wp-config\\.php'],   // WP config file (also in security probes)
        ];

        // Add WordPress patterns (avoid duplicates)
        $addedPatterns = 0;
        if (!$settings->isOverriddenByConfig('excludePatterns')) {
            $existingPatterns = array_column($settings->excludePatterns, 'pattern');
            foreach ($wordpressExcludePatterns as $recommended) {
                if (!in_array($recommended['pattern'], $existingPatterns)) {
                    $settings->excludePatterns[] = $recommended;
                    $addedPatterns++;
                }
            }
        }

        // Save settings
        if ($settings->saveToDatabase(['excludePatterns'])) {
            if ($addedPatterns > 0) {
                $message = Craft::t('redirect-manager', 'Added {patterns} WordPress exclude pattern(s). Note: /wp-content/uploads patterns are NOT excluded - those may need redirects for migrated media files.', [
                    'patterns' => $addedPatterns,
                ]);
                Craft::$app->getSession()->setNotice($message);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'All WordPress migration filters are already applied.'));
            }
        } else {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Could not apply WordPress filters'));
        }

        return $this->redirect('redirect-manager/settings/advanced');
    }

    /**
     * Apply security probe filters (block common vulnerability scanning)
     *
     * @return Response|null
     */
    public function actionApplySecurityFilters(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageSettings');

        // Load current settings from database
        $settings = Settings::loadFromDatabase();

        // Security probe patterns to exclude (common vulnerability scanning)
        // Patterns are specific to avoid false positives on legitimate URLs
        $securityExcludePatterns = [
            // Database dumps - specific file patterns
            ['pattern' => '\\.sql($|\\.(gz|zip|tar|rar|7z|bz2))'],  // .sql, .sql.gz, .sql.zip, etc.
            ['pattern' => '/(dump|backup|database|db)\\.(sql|zip|tar|gz|rar|7z)'],  // dump.sql, backup.zip, etc.

            // Config/sensitive files - must start with dot or be specific files
            ['pattern' => '/\\.env($|\\.)'],          // .env, .env.local, .env.production
            ['pattern' => '/\\.git($|/)'],            // .git, .git/config (not .github)
            ['pattern' => '/\\.htaccess$'],           // .htaccess
            ['pattern' => '/\\.htpasswd$'],           // .htpasswd
            ['pattern' => '/\\.aws($|/)'],            // AWS credentials
            ['pattern' => '/\\.ssh($|/)'],            // SSH keys
            ['pattern' => '/\\.DS_Store$'],           // macOS files
            ['pattern' => '^/composer\\.(json|lock)$'], // Root composer files
            ['pattern' => '^/package(-lock)?\\.json$'], // Root NPM files
            ['pattern' => '/wp-config\\.php'],        // WordPress config

            // Admin panels / tools - specific paths
            ['pattern' => '/phpmyadmin'],             // phpMyAdmin (any path containing it)
            ['pattern' => '/adminer\\.php'],          // Adminer specifically
            ['pattern' => '^/pma($|/)'],              // /pma or /pma/... only
            ['pattern' => '^/mysql($|/)'],            // /mysql or /mysql/... only (not /mysql-tips)
            ['pattern' => '^/myadmin($|/)'],          // /myadmin

            // Shell/exploit attempts - specific file extensions
            ['pattern' => '/shell\\.php'],            // shell.php specifically
            ['pattern' => '/cmd\\.php'],              // cmd.php specifically
            ['pattern' => '/c99\\.php'],              // c99 shell
            ['pattern' => '/r57\\.php'],              // r57 shell
            ['pattern' => '/webshell'],               // webshell in path
            ['pattern' => '/cgi-bin/'],               // CGI directory
            ['pattern' => '/eval-stdin'],             // PHP eval exploits

            // Common scanner paths - specific
            ['pattern' => '/sftp(-config)?\\.json'],  // VS Code SFTP config
            ['pattern' => '^/debug($|/)'],            // /debug only (not /debugging-guide)
            ['pattern' => '/phpinfo\\.php'],          // phpinfo.php specifically
            ['pattern' => '^/server-status($|/)'],    // Apache status
            ['pattern' => '\\.axd$'],                 // .NET handlers (.axd files)
            ['pattern' => '/web\\.config$'],          // IIS config
            ['pattern' => '/xmlrpc\\.php'],           // XML-RPC attacks (also in WordPress)
        ];

        // Add security patterns (avoid duplicates)
        $addedPatterns = 0;
        if (!$settings->isOverriddenByConfig('excludePatterns')) {
            $existingPatterns = array_column($settings->excludePatterns, 'pattern');
            foreach ($securityExcludePatterns as $recommended) {
                if (!in_array($recommended['pattern'], $existingPatterns)) {
                    $settings->excludePatterns[] = $recommended;
                    $addedPatterns++;
                }
            }
        }

        // Save settings
        if ($settings->saveToDatabase(['excludePatterns'])) {
            if ($addedPatterns > 0) {
                $message = Craft::t('redirect-manager', 'Added {patterns} security filter pattern(s). Vulnerability scanning requests will now be ignored.', [
                    'patterns' => $addedPatterns,
                ]);
                Craft::$app->getSession()->setNotice($message);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'All security filters are already applied.'));
            }
        } else {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Could not apply security filters'));
        }

        return $this->redirect('redirect-manager/settings/advanced');
    }

    /**
     * Test redirects page
     *
     * @return Response
     */
    public function actionTest(): Response
    {
        $this->requirePermission('redirectManager:manageSettings');

        $settings = RedirectManager::$plugin->getSettings();

        return $this->renderTemplate('redirect-manager/settings/test/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Test a URL to see if it matches any redirects
     *
     * @return Response
     */
    public function actionTestUrl(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageSettings');
        $this->requireAcceptsJson();

        $testUrl = Craft::$app->getRequest()->getBodyParam('testUrl');

        if (empty($testUrl)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('redirect-manager', 'Please enter a URL to test'),
            ]);
        }

        // Parse the URL
        $parsedUrl = parse_url($testUrl);
        $fullUrl = $testUrl;
        $pathOnly = ($parsedUrl['path'] ?? '/') . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '');

        // Find ALL matching redirects (not just the first one)
        $allMatches = [];
        $redirects = RedirectManager::$plugin->redirects->getEnabledRedirects(
            Craft::$app->getSites()->getEditableSiteIds(),
        );

        foreach ($redirects as $redirect) {
            $matchType = $redirect['matchType'];
            $sourceUrlParsed = $redirect['sourceUrlParsed'];
            $redirectSrcMatch = $redirect['redirectSrcMatch'] ?? 'pathonly';

            // Use pathOnly or fullUrl based on per-redirect setting
            $urlToMatch = $redirectSrcMatch === 'fullurl' ? $fullUrl : $pathOnly;

            // Check if this redirect matches
            $result = RedirectManager::$plugin->matching->matchWithCaptures($matchType, $sourceUrlParsed, $urlToMatch);

            if ($result['matched']) {
                // Apply capture groups to get resolved destination
                $resolvedDestination = $redirect['destinationUrl'];
                if (!empty($result['captures'])) {
                    $resolvedDestination = RedirectManager::$plugin->matching->applyCaptures(
                        $redirect['destinationUrl'],
                        $result['captures']
                    );
                }

                $allMatches[] = [
                    'id' => $redirect['id'],
                    'sourceUrl' => $redirect['sourceUrl'],
                    'destinationUrl' => $redirect['destinationUrl'],
                    'resolvedDestinationUrl' => $resolvedDestination,
                    'matchType' => $redirect['matchType'],
                    'redirectSrcMatch' => $redirect['redirectSrcMatch'] ?? 'pathonly',
                    'statusCode' => $redirect['statusCode'],
                    'priority' => $redirect['priority'],
                ];
            }
        }

        if (!empty($allMatches)) {
            // Sort by priority (already sorted from getEnabledRedirects, but be explicit)
            usort($allMatches, fn($a, $b) => $a['priority'] <=> $b['priority'] ?: $a['id'] <=> $b['id']);

            // First match is the winner
            $winningRedirect = array_shift($allMatches);

            return $this->asJson([
                'success' => true,
                'matched' => true,
                'redirect' => $winningRedirect,
                'alsoMatches' => $allMatches, // Other matches that were skipped due to priority
                'message' => Craft::t('redirect-manager', 'Match found! This URL would redirect.'),
            ]);
        }

        return $this->asJson([
            'success' => true,
            'matched' => false,
            'message' => Craft::t('redirect-manager', 'No matching redirect found. This URL would show a 404.'),
        ]);
    }

    /**
     * Run a test request against the read-only JSON API endpoint.
     *
     * @since 5.33.0
     */
    public function actionRunApiTest(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageSettings');
        $this->requireAcceptsJson();

        $settings = RedirectManager::$plugin->getSettings();
        if (!$settings->apiEndpointEnabled) {
            return $this->asJson([
                'error' => Craft::t('redirect-manager', 'The JSON API endpoint is disabled. Enable it in Advanced settings before running endpoint tests.'),
            ]);
        }

        $request = Craft::$app->getRequest();
        $token = trim((string) ($settings->apiEndpointToken ?? ''));
        if ($token === '') {
            return $this->asJson([
                'error' => Craft::t('redirect-manager', 'REDIRECT_MANAGER_API_TOKEN is not configured. Add it to your environment before running endpoint tests.'),
            ]);
        }

        $site = trim((string) $request->getBodyParam('testSite', ''));

        $query = [];
        if ($site !== '') {
            $query['site'] = $site;
        }

        $path = '/actions/redirect-manager/api/get-redirects';
        $queryString = http_build_query($query);
        $pathWithQuery = $path . ($queryString !== '' ? '?' . $queryString : '');
        $baseUrl = rtrim(Craft::$app->getSites()->getCurrentSite()->getBaseUrl() ?? Craft::$app->getRequest()->getHostInfo(), '/');
        $url = $baseUrl . $pathWithQuery;

        $headers = ['Accept' => 'application/json'];
        if ($token !== '') {
            $headers['X-Redirect-Manager-Key'] = $token;
        }

        $client = Craft::createGuzzleClient(['http_errors' => false, 'timeout' => 15]);
        $start = microtime(true);

        try {
            $response = $client->request('GET', $url, [
                'headers' => $headers,
            ]);
            $timeMs = (int) ((microtime(true) - $start) * 1000);

            $responseHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $responseHeaders[$name] = implode(', ', $values);
            }

            $bodyRaw = (string) $response->getBody();
            $bodyDecoded = Json::decodeIfJson($bodyRaw);
            $body = is_array($bodyDecoded)
                ? Json::encode($bodyDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : $bodyRaw;

            return $this->asJson([
                'status' => $response->getStatusCode(),
                'timeMs' => $timeMs,
                'headers' => $responseHeaders,
                'body' => $body,
                'curl' => $this->buildApiCurl($url, true),
            ]);
        } catch (\Throwable $e) {
            return $this->asJson(['error' => $e->getMessage()]);
        }
    }

    /**
     * Download the bundled Postman collection and environment template.
     *
     * @since 5.35.0
     */
    public function actionDownloadPostmanCollection(): Response
    {
        $this->requirePermission('redirectManager:manageSettings');

        $postmanPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'postman';
        $files = [];

        foreach ([
            'Redirect-Manager.postman_collection.json',
            'Redirect-Manager.postman_environment.json',
            'README.md',
        ] as $filename) {
            $path = $postmanPath . DIRECTORY_SEPARATOR . $filename;
            if (is_file($path)) {
                $content = file_get_contents($path);
                if ($content !== false) {
                    $files[$filename] = $content;
                }
            }
        }

        return ExportHelper::toZip($files, 'redirect-manager-postman.zip');
    }

    /**
     * Clear redirect cache
     *
     * @return Response
     * @since 5.1.0
     */
    public function actionClearRedirectCache(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:clearCache');

        try {
            $settings = RedirectManager::$plugin->getSettings();
            $cleared = RedirectManager::$plugin->localCache->clearRedirectCache();

            $message = $settings->cacheStorageMethod === 'redis'
                ? Craft::t('redirect-manager', 'Redirect cache cleared successfully.')
                : Craft::t('redirect-manager', 'Cleared {count, plural, =1{# redirect cache} other{# redirect caches}}.', ['count' => $cleared]);

            return $this->asJson([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function buildApiCurl(string $url, bool $includeToken): string
    {
        $parts = [
            'curl',
            '-H ' . escapeshellarg('Accept: application/json'),
        ];

        if ($includeToken) {
            $parts[] = '-H ' . escapeshellarg('X-Redirect-Manager-Key: $REDIRECT_MANAGER_API_TOKEN');
        }

        $parts[] = escapeshellarg($url);

        return implode(' ', $parts);
    }

    /**
     * Clear device detection cache
     *
     * @return Response
     * @since 5.1.0
     */
    public function actionClearDeviceCache(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:clearCache');

        try {
            $settings = RedirectManager::$plugin->getSettings();
            $cleared = RedirectManager::$plugin->localCache->clearDeviceCache();

            $message = $settings->cacheStorageMethod === 'redis'
                ? Craft::t('redirect-manager', 'Device cache cleared successfully.')
                : Craft::t('redirect-manager', 'Cleared {count, plural, =1{# device cache} other{# device caches}}.', ['count' => $cleared]);

            return $this->asJson([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Clear all caches
     *
     * @return Response
     * @since 5.1.0
     */
    public function actionClearAllCaches(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:clearCache');

        try {
            $settings = RedirectManager::$plugin->getSettings();
            if ($settings->cacheStorageMethod === 'redis') {
                RedirectManager::$plugin->localCache->clearAllCaches();
                $message = Craft::t('redirect-manager', 'All caches cleared successfully.');
            } else {
                $redirectCount = RedirectManager::$plugin->localCache->clearRedirectCache();
                $deviceCount = RedirectManager::$plugin->localCache->clearDeviceCache();
                $message = Craft::t('redirect-manager', 'Cleared {redirectCount, plural, =1{# redirect cache} other{# redirect caches}} and {deviceCount, plural, =1{# device cache} other{# device caches}}.', [
                    'redirectCount' => $redirectCount,
                    'deviceCount' => $deviceCount,
                ]);
            }

            return $this->asJson([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Clear all analytics data
     *
     * @return Response
     * @since 5.1.0
     */
    public function actionClearAllAnalytics(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:clearAnalytics');

        try {
            $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
            $count = $editableSiteIds === []
                ? 0
                : RedirectManager::$plugin->analytics->clearAnalytics($editableSiteIds);

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('redirect-manager', 'Cleared {count, plural, =1{# analytics record} other{# analytics records}}.', ['count' => $count]),
            ]);
        } catch (\Exception $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Validate and sanitize the settings section parameter
     *
     * @param string $section The section from POST data
     * @return string A validated section name
     */
    private function _validSettingsSection(string $section): string
    {
        $allowed = ['general', 'analytics', 'interface', 'cache', 'advanced', 'backup'];

        return in_array($section, $allowed, true) ? $section : 'general';
    }

    /**
     * Get validation attributes for a settings section.
     */
    private function _validationAttributesForSection(string $section): array
    {
        return match ($section) {
            'general' => [
                'pluginName',
                'autoCreateRedirects',
                'undoWindowMinutes',
                'redirectSrcMatch',
                'stripQueryString',
                'preserveQueryString',
                'setNoCacheHeaders',
                'logLevel',
            ],
            'analytics' => [
                'enableAnalytics',
                'enableGeoDetection',
                'geoProvider',
                'geoApiKey',
                'anonymizeIpAddress',
                'stripQueryStringFromStats',
                'analyticsRetention',
                'analyticsLimit',
                'autoTrimAnalytics',
            ],
            'interface' => [
                'itemsPerPage',
                'refreshIntervalSecs',
                'timeFormat',
                'monthFormat',
                'dateOrder',
                'dateSeparator',
                'showSeconds',
                'defaultDateRange',
                'exportsCsv',
                'exportsJson',
                'exportsExcel',
            ],
            'cache' => [
                'cacheStorageMethod',
                'cacheDeviceDetection',
                'deviceDetectionCacheDuration',
                'enableRedirectCache',
                'redirectCacheDuration',
            ],
            'advanced' => [
                'apiEndpointEnabled',
                'apiEndpointRateLimit',
                'excludePatterns',
                'additionalHeaders',
            ],
            'backup' => [
                'backupEnabled',
                'backupOnImport',
                'backupSchedule',
                'backupRetentionDays',
                'backupVolumeUid',
                'backupPath',
            ],
            default => [],
        };
    }
}
