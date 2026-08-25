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
     * @var string Stable recurring queue owner
     * @since 5.41.0
     */
    public string $recurringOwner = '';

    /**
     * @var string Effective cadence for this recurring occurrence
     * @since 5.41.0
     */
    public string $schedule = '';

    /**
     * @var int|null Intended Unix timestamp for this recurring occurrence
     * @since 5.41.0
     */
    public ?int $targetTimestamp = null;

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

        if ($this->isRecurringScheduledBackup() && !$this->nextRunTime && RedirectManager::$plugin !== null) {
            $this->nextRunTime = RedirectManager::$plugin->scheduledBackups->getNextRunTime();
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
        if ($this->isRecurringScheduledBackup()) {
            RedirectManager::$plugin->scheduledBackups->runOccurrence(fn() => $this->createBackup());
            return;
        }

        $this->createBackup();
    }

    private function createBackup(): void
    {
        $backupService = RedirectManager::getInstance()->backup;
        $backupPath = $backupService->createBackup($this->reason);

        if ($backupPath !== null) {
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
        } else {
            $this->logInfo('Scheduled backup completed with no redirects to back up');
        }
    }

    private function isRecurringScheduledBackup(): bool
    {
        return $this->reason === 'scheduled' && $this->reschedule;
    }
}
