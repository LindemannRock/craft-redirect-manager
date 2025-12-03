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
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\RedirectManager;
use yii\web\Response;

/**
 * Settings Controller
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
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
        $this->setLoggingHandle('redirect-manager');
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

            Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'Settings saved successfully'));
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

        // Find matching redirect
        $redirect = RedirectManager::$plugin->redirects->findRedirect($fullUrl, $pathOnly);

        if ($redirect) {
            return $this->asJson([
                'success' => true,
                'matched' => true,
                'redirect' => [
                    'id' => $redirect['id'],
                    'sourceUrl' => $redirect['sourceUrl'],
                    'destinationUrl' => $redirect['destinationUrl'],
                    'matchType' => $redirect['matchType'],
                    'redirectSrcMatch' => $redirect['redirectSrcMatch'],
                    'statusCode' => $redirect['statusCode'],
                    'priority' => $redirect['priority'],
                ],
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

        try {
            // Clear redirect cache using Craft's cache component
            Craft::$app->cache->delete('redirect-manager-redirects');

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('redirect-manager', 'Redirect cache cleared.'),
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

        try {
            $cachePath = Craft::$app->path->getRuntimePath() . '/redirect-manager/cache/device/';
            $cleared = 0;

            if (is_dir($cachePath)) {
                $files = glob($cachePath . '*.cache');
                foreach ($files as $file) {
                    if (@unlink($file)) {
                        $cleared++;
                    }
                }
            }

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('redirect-manager', 'Cleared {count} device caches.', ['count' => $cleared]),
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

        try {
            $cleared = 0;

            // Clear redirect cache
            Craft::$app->cache->delete('redirect-manager-redirects');

            // Clear device detection cache
            $devicePath = Craft::$app->path->getRuntimePath() . '/redirect-manager/cache/device/';
            if (is_dir($devicePath)) {
                $files = glob($devicePath . '*.cache');
                foreach ($files as $file) {
                    if (@unlink($file)) {
                        $cleared++;
                    }
                }
            }

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('redirect-manager', 'Cleared redirect cache and {count} device caches.', ['count' => $cleared]),
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

        // Require admin permission
        if (!Craft::$app->getUser()->getIsAdmin()) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('redirect-manager', 'Only administrators can clear analytics data.'),
            ]);
        }

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
