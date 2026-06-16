<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\base\traits\QueueTtrTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;
use yii\queue\RetryableJobInterface;

/**
 * Cleanup Analytics Job
 *
 * Automatically cleans up old analytics based on retention settings
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class CleanupAnalyticsJob extends BaseJob implements RetryableJobInterface
{
    use QueueTtrTrait;
    use LoggingTrait;

    /**
     * @var bool Whether to reschedule cleanup after completion
     */
    public bool $reschedule = false;

    /**
     * @var string|null Next run time display string for queued jobs
     */
    public ?string $nextRunTime = null;

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);

        if ($this->reschedule && !$this->nextRunTime) {
            $settings = RedirectManager::$plugin->getSettings();
            $nextRun = ScheduleHelper::calculateNext('daily');
            if ($nextRun !== null) {
                $this->nextRunTime = DateFormatHelper::formatCompactDatetimeFromSettings(
                    $nextRun,
                    $settings,
                    null,
                    false,
                    pluginHandle: 'redirect-manager',
                );
            }
        }
    }

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
            RedirectManager::$plugin->analytics->trimAnalytics();
        }

        $this->logInfo('Analytics cleanup completed', ['deleted' => $deleted]);

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
        $description = Craft::t('redirect-manager', '{pluginName}: Cleaning up old analytics', [
            'pluginName' => $settings->getDisplayName(),
        ]);

        if ($this->nextRunTime) {
            $description .= " ({$this->nextRunTime})";
        }

        return $description;
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

        $nextRun = ScheduleHelper::calculateNext('daily');

        if ($nextRun !== null) {
            $delay = max(0, $nextRun->getTimestamp() - DateFormatHelper::now()->getTimestamp());
            $nextRunTime = DateFormatHelper::formatCompactDatetimeFromSettings(
                $nextRun,
                $settings,
                null,
                false,
                pluginHandle: 'redirect-manager',
            );
            $job = new self([
                'reschedule' => true,
                'nextRunTime' => $nextRunTime,
            ]);

            Craft::$app->getQueue()->delay($delay)->push($job);

            $this->logDebug('Scheduled next analytics cleanup', [
                'delay' => $delay,
                'nextRun' => $nextRunTime,
            ]);
        }
    }
}
