<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Cleanup Analytics Job
 *
 * Automatically cleans up old analytics based on retention settings
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
class CleanupAnalyticsJob extends BaseJob
{
    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $settings = RedirectManager::$plugin->getSettings();

        // Only run if retention is enabled
        if ($settings->analyticsRetention <= 0) {
            return;
        }

        // Clean up old analytics
        $deleted = RedirectManager::$plugin->analytics->cleanupOldAnalytics();

        // Also trim if auto-trim is enabled
        if ($settings->autoTrimAnalytics) {
            $trimmed = RedirectManager::$plugin->analytics->trimAnalytics();
        }

        // Re-queue this job to run again in 24 hours
        Craft::$app->queue->delay(86400)->push(new self());
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $settings = RedirectManager::$plugin->getSettings();
        return Craft::t('redirect-manager', '{pluginName}: Trimming redirect analytics ({limit})', [
            'pluginName' => $settings->pluginName,
            'limit' => $settings->analyticsLimit,
        ]);
    }
}
