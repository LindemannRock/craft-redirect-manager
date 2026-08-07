<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services\analytics;

use craft\base\Component;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\RecurringQueueHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\redirectmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Owns analytics cleanup eligibility, execution, and recurring scheduling.
 *
 * @since 5.41.0
 */
class AnalyticsMaintenanceService extends Component
{
    public function __construct(
        private readonly AnalyticsExportService $exportService,
        array $config = [],
    ) {
        parent::__construct($config);
    }

    /**
     * Resolve the independent retention and limit cleanup policy.
     *
     * @return array{retention: bool, limit: bool, eligible: bool}
     */
    public function getPolicy(?Settings $settings = null): array
    {
        $settings ??= RedirectManager::$plugin->getSettings();
        $retention = $settings->enableAnalytics && $settings->analyticsRetention > 0;
        $limit = $settings->enableAnalytics && $settings->autoTrimAnalytics;

        return [
            'retention' => $retention,
            'limit' => $limit,
            'eligible' => $retention || $limit,
        ];
    }

    /**
     * Run every currently eligible cleanup operation once.
     *
     * @return array{retentionDeleted: int, limitDeleted: int}
     */
    public function runCleanup(): array
    {
        $policy = $this->getPolicy();

        return [
            'retentionDeleted' => $policy['retention'] ? $this->exportService->cleanupOldAnalytics() : 0,
            'limitDeleted' => $policy['limit'] ? $this->exportService->trimAnalytics() : 0,
        ];
    }

    /**
     * Synchronize the single recurring cleanup family with current settings.
     */
    public function synchronizeRecurringCleanup(?Settings $settings = null): void
    {
        $settings ??= RedirectManager::$plugin->getSettings();
        if (!$this->getPolicy($settings)['eligible']) {
            RecurringQueueHelper::deletePending('redirectmanager', CleanupAnalyticsJob::class);
            return;
        }

        $nextRun = ScheduleHelper::calculateNext('daily');
        if ($nextRun === null) {
            RecurringQueueHelper::deletePending('redirectmanager', CleanupAnalyticsJob::class);
            return;
        }

        $delay = max(0, $nextRun->getTimestamp() - DateFormatHelper::now()->getTimestamp());
        $nextRunTime = DateFormatHelper::formatCompactDatetimeFromSettings(
            $nextRun,
            $settings,
            null,
            false,
            pluginHandle: 'redirect-manager',
        );

        RecurringQueueHelper::ensurePending(
            pluginToken: 'redirectmanager',
            jobClass: CleanupAnalyticsJob::class,
            delay: $delay,
            jobFactory: fn() => new CleanupAnalyticsJob([
                'reschedule' => true,
                'nextRunTime' => $nextRunTime,
            ]),
        );
    }
}
