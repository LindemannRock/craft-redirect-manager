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
 * Cleanup Statistics Job
 *
 * Automatically cleans up old statistics based on retention settings
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
class CleanupStatisticsJob extends BaseJob
{
    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $settings = RedirectManager::$plugin->getSettings();

        // Only run if retention is enabled
        if ($settings->statisticsRetention <= 0) {
            return;
        }

        // Clean up old statistics
        $deleted = RedirectManager::$plugin->statistics->cleanupOldStatistics();

        // Also trim if auto-trim is enabled
        if ($settings->autoTrimStatistics) {
            $trimmed = RedirectManager::$plugin->statistics->trimStatistics();
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
        return Craft::t('redirect-manager', '{pluginName}: Trimming redirect statistics ({limit})', [
            'pluginName' => $settings->pluginName,
            'limit' => $settings->statisticsLimit,
        ]);
    }
}
