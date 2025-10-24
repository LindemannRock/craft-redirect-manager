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
     * @var bool Whether to reschedule after completion
     */
    public bool $reschedule = false;

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

        Craft::info('Analytics cleanup completed', 'redirect-manager', ['deleted' => $deleted]);

        // Reschedule if needed
        if ($this->reschedule) {
            $this->scheduleNextCleanup();
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $settings = RedirectManager::$plugin->getSettings();
        return Craft::t('redirect-manager', '{pluginName}: Cleaning up old analytics', [
            'pluginName' => $settings->pluginName,
        ]);
    }

    /**
     * Schedule the next cleanup (runs every 24 hours)
     */
    private function scheduleNextCleanup(): void
    {
        $settings = RedirectManager::$plugin->getSettings();

        // Only reschedule if analytics is enabled and retention is set
        if (!$settings->enableAnalytics || $settings->analyticsRetention <= 0) {
            return;
        }

        // Schedule for 24 hours from now
        $delay = 86400; // 24 hours

        $job = new self([
            'reschedule' => true,
        ]);

        Craft::$app->getQueue()->delay($delay)->push($job);
    }
}
