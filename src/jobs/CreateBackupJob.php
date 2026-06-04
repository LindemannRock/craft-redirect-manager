<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * Queue job for creating redirect backups
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
 * Create Backup Job
 *
 * @since 5.24.0
 */
class CreateBackupJob extends BaseJob implements RetryableJobInterface
{
    use QueueTtrTrait;
    use LoggingTrait;

    /**
     * @var string The reason for the backup
     */
    public string $reason = 'scheduled';

    /**
     * @var bool Whether to reschedule after completion
     */
    public bool $reschedule = false;

    /**
     * @var string|null Next run time display string
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
            $settings = RedirectManager::getInstance()->getSettings();
            $schedule = $settings->getEffectiveBackupSchedule();
            if ($settings->backupEnabled && $schedule !== 'disabled') {
                $nextRun = ScheduleHelper::calculateNext($schedule);
                if ($nextRun !== null) {
                    $this->nextRunTime = DateFormatHelper::formatCompactDatetimeFromSettings(
                        $nextRun,
                        $settings,
                        false,
                        false,
                    );
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        $pluginName = RedirectManager::$plugin->getSettings()->getDisplayName();
        $description = Craft::t('redirect-manager', '{pluginName}: Scheduled auto backup', ['pluginName' => $pluginName]);

        if ($this->nextRunTime) {
            $description .= " ({$this->nextRunTime})";
        }

        return $description;
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $backupService = RedirectManager::getInstance()->backup;
        $backupPath = $backupService->createBackup($this->reason);

        if ($backupPath) {
            $this->logInfo('Scheduled backup created successfully', [
                'filename' => basename($backupPath),
            ]);

            $settings = RedirectManager::getInstance()->getSettings();
            if ($settings->backupRetentionDays > 0) {
                $deleted = $backupService->cleanupOldBackups();
                if ($deleted > 0) {
                    $this->logInfo('Cleaned old backups', ['deleted' => $deleted]);
                }
            }

            if ($this->reschedule) {
                $this->scheduleNextBackup();
            }
        } else {
            throw new \Exception(Craft::t('redirect-manager', 'Failed to create scheduled backup'));
        }
    }

    /**
     * Schedule the next backup based on settings
     */
    private function scheduleNextBackup(): void
    {
        $settings = RedirectManager::getInstance()->getSettings();
        $schedule = $settings->getEffectiveBackupSchedule();

        if (!$settings->backupEnabled || $schedule === 'disabled') {
            return;
        }

        $nextRun = ScheduleHelper::calculateNext($schedule);

        if ($nextRun !== null) {
            $delay = max(0, $nextRun->getTimestamp() - DateFormatHelper::now()->getTimestamp());
            $nextRunTime = DateFormatHelper::formatCompactDatetimeFromSettings(
                $nextRun,
                $settings,
                false,
                false,
            );

            $job = new self([
                'reason' => 'scheduled',
                'reschedule' => true,
                'nextRunTime' => $nextRunTime,
            ]);

            Craft::$app->getQueue()->delay($delay)->push($job);

            $this->logInfo('Next backup scheduled', [
                'delay_seconds' => $delay,
                'next_run' => $nextRunTime,
            ]);
        }
    }
}
