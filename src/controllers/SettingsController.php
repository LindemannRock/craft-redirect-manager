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
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\logginglibrary\traits\LoggingTrait;
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
     * Statistics settings
     *
     * @return Response
     */
    public function actionStatistics(): Response
    {
        $this->requirePermission('redirectManager:manageSettings');

        $settings = RedirectManager::$plugin->getSettings();

        return $this->renderTemplate('redirect-manager/settings/statistics', [
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
        if (!$settings) {
            $settings = new Settings();
        }

        // Get only the posted settings (fields from the current page)
        $settingsData = Craft::$app->getRequest()->getBodyParam('settings', []);

        // Only update fields that were posted and are not overridden by config
        foreach ($settingsData as $key => $value) {
            if (!$settings->isOverriddenByConfig($key) && property_exists($settings, $key)) {
                $settings->$key = $value;
            }
        }

        // Validate
        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Could not save settings.'));
            return null;
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
        if (!$settings) {
            $settings = new Settings();
        }

        // Recommended exclude patterns
        $recommendedExcludePatterns = [
            ['pattern' => '^/admin'],
            ['pattern' => '^/cms'],
            ['pattern' => '^/cpresources'],
            ['pattern' => '^/actions'],
            ['pattern' => '^/\\.well-known'],
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
}
