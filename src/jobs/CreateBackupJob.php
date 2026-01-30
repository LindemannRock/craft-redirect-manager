<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * Queue job for creating redirect backups
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Create Backup Job
 *
 * @since 5.23.0
 */
class CreateBackupJob extends BaseJob
{
    use LoggingTrait;

    /**
     * @var string The reason for the backup
     */
    public string $reason = 'scheduled';

    /**
     * @var bool Whether to reschedule after completion
     * @deprecated Use cron for scheduling instead
     */
    public bool $reschedule = false;

    /**
     * @var string|null Next run time display string
     */
    public ?string $nextRunTime = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('redirect-manager');

        if ($this->reschedule && !$this->nextRunTime) {
            $settings = RedirectManager::getInstance()->getSettings();
            if ($settings->backupEnabled && $settings->backupSchedule !== 'manual') {
                $delay = $this->calculateNextRunDelay($settings->backupSchedule);
                if ($delay > 0) {
                    $this->nextRunTime = date('M j, g:ia', time() + $delay);
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
            throw new \Exception('Failed to create scheduled backup');
        }
    }

    /**
     * Schedule the next backup based on settings
     */
    private function scheduleNextBackup(): void
    {
        $settings = RedirectManager::getInstance()->getSettings();

        if (!$settings->backupEnabled || $settings->backupSchedule === 'manual') {
            return;
        }

        $existingJob = (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'redirectmanager'])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->exists();

        if ($existingJob) {
            $this->logDebug('Skipping reschedule - backup job already exists');
            return;
        }

        $delay = $this->calculateNextRunDelay($settings->backupSchedule);

        if ($delay > 0) {
            $nextRunTime = date('M j, g:ia', time() + $delay);

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

    /**
     * Calculate the delay in seconds for the next backup
     */
    private function calculateNextRunDelay(string $schedule): int
    {
        return match ($schedule) {
            'daily' => 86400,
            'weekly' => 604800,
            'monthly' => 2592000,
            default => 0,
        };
    }
}
