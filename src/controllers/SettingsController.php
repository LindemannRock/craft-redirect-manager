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
use lindemannrock\base\helpers\PluginHelper;
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
     */
    public function actionBackup(): Response
    {
        $this->requirePermission('redirectManager:manageSettings');

        $settings = RedirectManager::$plugin->getSettings();

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

        // Get only the posted settings (fields from the current page)
        $settingsData = Craft::$app->getRequest()->getBodyParam('settings', []);

        // Only update fields that were posted and are not overridden by config
        foreach ($settingsData as $key => $value) {
            if (!$settings->isOverriddenByConfig($key) && property_exists($settings, $key)) {
                // Check for setter method first (handles array conversions, etc.)
                $setterMethod = 'set' . ucfirst($key);
                if (method_exists($settings, $setterMethod)) {
                    $settings->$setterMethod($value);
                } else {
                    $settings->$key = $value;
                }
            }
        }

        // Validate
        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Could not save settings.'));

            // Get the section to re-render the correct template with errors
            $section = $this->request->getBodyParam('section', 'general');
            $template = "redirect-manager/settings/{$section}";

            return $this->renderTemplate($template, [
                'settings' => $settings,
            ]);
        }

        // Save settings to database
        if ($settings->saveToDatabase()) {
            // Update the plugin's cached settings (CRITICAL - forces Craft to refresh)
            RedirectManager::$plugin->setSettings($settings->getAttributes());

            Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'Settings saved.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Could not save settings'));
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
        if ($settings->saveToDatabase()) {
            RedirectManager::$plugin->setSettings($settings->getAttributes());

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
        if ($settings->saveToDatabase()) {
            RedirectManager::$plugin->setSettings($settings->getAttributes());

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
        if ($settings->saveToDatabase()) {
            RedirectManager::$plugin->setSettings($settings->getAttributes());

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

        return $this->renderTemplate('redirect-manager/settings/test', [
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
                'error' => 'Please enter a URL to test',
            ]);
        }

        // Parse the URL
        $parsedUrl = parse_url($testUrl);
        $fullUrl = $testUrl;
        $pathOnly = ($parsedUrl['path'] ?? '/') . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '');

        // Find ALL matching redirects (not just the first one)
        $allMatches = [];
        $redirects = RedirectManager::$plugin->redirects->getEnabledRedirects();

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
                'message' => 'Match found! This URL would redirect.',
            ]);
        }

        return $this->asJson([
            'success' => true,
            'matched' => false,
            'message' => 'No matching redirect found. This URL would show a 404.',
        ]);
    }

    /**
     * Clear redirect cache
     */
    public function actionClearRedirectCache(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:clearCache');

        try {
            $settings = RedirectManager::$plugin->getSettings();

            if ($settings->cacheStorageMethod === 'redis') {
                $cache = Craft::$app->cache;
                if ($cache instanceof \yii\redis\Cache) {
                    $redis = $cache->redis;

                    // Get all redirect cache keys from tracking set
                    $keys = $redis->executeCommand('SMEMBERS', [PluginHelper::getCacheKeySet(RedirectManager::$plugin->id, 'redirect')]) ?: [];

                    // Delete redirect cache keys
                    foreach ($keys as $key) {
                        $cache->delete($key);
                    }

                    // Clear the tracking set
                    $redis->executeCommand('DEL', [PluginHelper::getCacheKeySet(RedirectManager::$plugin->id, 'redirect')]);
                }
            } else {
                // Clear file cache
                RedirectManager::$plugin->redirects->invalidateCaches();
            }

            $message = $settings->cacheStorageMethod === 'redis'
                ? Craft::t('redirect-manager', 'Redirect cache cleared successfully.')
                : Craft::t('redirect-manager', 'Redirect cache cleared.');

            return $this->asJson([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Clear device detection cache
     */
    public function actionClearDeviceCache(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:clearCache');

        try {
            $settings = RedirectManager::$plugin->getSettings();
            $cleared = 0;

            if ($settings->cacheStorageMethod === 'redis') {
                $cache = Craft::$app->cache;
                if ($cache instanceof \yii\redis\Cache) {
                    $redis = $cache->redis;

                    // Get all device cache keys from tracking set
                    $keys = $redis->executeCommand('SMEMBERS', ['redirectmanager-device-keys']) ?: [];

                    // Delete device cache keys
                    foreach ($keys as $key) {
                        $cache->delete($key);
                    }

                    // Clear the tracking set
                    $redis->executeCommand('DEL', ['redirectmanager-device-keys']);
                }
            } else {
                // Clear file cache
                $cachePath = PluginHelper::getCachePath(RedirectManager::$plugin, 'device');
                if (is_dir($cachePath)) {
                    $files = glob($cachePath . '*.cache');
                    foreach ($files as $file) {
                        if (@unlink($file)) {
                            $cleared++;
                        }
                    }
                }
            }

            $message = $settings->cacheStorageMethod === 'redis'
                ? Craft::t('redirect-manager', 'Device cache cleared successfully.')
                : Craft::t('redirect-manager', 'Cleared {count} device caches.', ['count' => $cleared]);

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
     */
    public function actionClearAllCaches(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:clearCache');

        try {
            $settings = RedirectManager::$plugin->getSettings();
            $cleared = 0;

            if ($settings->cacheStorageMethod === 'redis') {
                $cache = Craft::$app->cache;
                if ($cache instanceof \yii\redis\Cache) {
                    $redis = $cache->redis;

                    // Get all cache keys from tracking sets
                    $redirectKeys = $redis->executeCommand('SMEMBERS', [PluginHelper::getCacheKeySet(RedirectManager::$plugin->id, 'redirect')]) ?: [];
                    $deviceKeys = $redis->executeCommand('SMEMBERS', ['redirectmanager-device-keys']) ?: [];

                    // Delete redirect cache keys
                    foreach ($redirectKeys as $key) {
                        $cache->delete($key);
                    }

                    // Delete device cache keys
                    foreach ($deviceKeys as $key) {
                        $cache->delete($key);
                    }

                    // Clear the tracking sets
                    $redis->executeCommand('DEL', [PluginHelper::getCacheKeySet(RedirectManager::$plugin->id, 'redirect')]);
                    $redis->executeCommand('DEL', ['redirectmanager-device-keys']);
                }
            } else {
                // Clear redirect cache
                RedirectManager::$plugin->redirects->invalidateCaches();

                // Clear device detection file cache
                $devicePath = PluginHelper::getCachePath(RedirectManager::$plugin, 'device');
                if (is_dir($devicePath)) {
                    $files = glob($devicePath . '*.cache');
                    foreach ($files as $file) {
                        if (@unlink($file)) {
                            $cleared++;
                        }
                    }
                }
            }

            $message = $settings->cacheStorageMethod === 'redis'
                ? Craft::t('redirect-manager', 'All caches cleared successfully.')
                : Craft::t('redirect-manager', 'Cleared redirect cache and {count} device caches.', ['count' => $cleared]);

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
     */
    public function actionClearAllAnalytics(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:clearAnalytics');

        try {
            // Get count before deleting
            $count = (new \craft\db\Query())
                ->from('{{%redirectmanager_analytics}}')
                ->count();

            // Delete all analytics records
            Craft::$app->db->createCommand()
                ->delete('{{%redirectmanager_analytics}}')
                ->execute();

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('redirect-manager', 'Deleted {count} analytics records.', ['count' => $count]),
            ]);
        } catch (\Exception $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
