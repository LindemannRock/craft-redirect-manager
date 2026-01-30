<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * Console controller for backup management commands
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use lindemannrock\redirectmanager\RedirectManager;
use yii\console\ExitCode;

/**
 * Backup management commands
 *
 * @since 5.23.0
 */
class BackupController extends Controller
{
    /**
     * @var string|null The reason for the backup
     */
    public ?string $reason = null;

    /**
     * @var bool Whether to clean old backups
     */
    public bool $clean = true;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        switch ($actionID) {
            case 'create':
                $options[] = 'reason';
                $options[] = 'clean';
                break;
        }

        return $options;
    }

    /**
     * Creates a backup of all redirects
     *
     * @return int
     * @since 5.23.0
     */
    public function actionCreate(): int
    {
        $this->stdout("Creating redirect backup...\n", Console::FG_YELLOW);

        $settings = RedirectManager::getInstance()->getSettings();

        if (!$settings->backupEnabled) {
            $this->stderr("Backups are disabled in settings\n", Console::FG_RED);
            return ExitCode::CONFIG;
        }

        $reason = $this->reason ?? 'console';

        try {
            $backupService = RedirectManager::getInstance()->backup;
            $backupPath = $backupService->createBackup($reason);

            if ($backupPath) {
                $this->stdout("✓ Backup created successfully\n", Console::FG_GREEN);
                $this->stdout("  Path: " . basename($backupPath) . "\n");

                if ($this->clean && $settings->backupRetentionDays > 0) {
                    $this->stdout("\nCleaning old backups...\n", Console::FG_YELLOW);
                    $deleted = $backupService->cleanupOldBackups();
                    if ($deleted > 0) {
                        $this->stdout("✓ Deleted $deleted old backup(s)\n", Console::FG_GREEN);
                    } else {
                        $this->stdout("  No old backups to clean\n");
                    }
                }

                return ExitCode::OK;
            }

            $this->stderr("✗ Failed to create backup\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        } catch (\Throwable $e) {
            $this->stderr("✗ Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Runs scheduled backup based on settings
     *
     * @return int
     * @since 5.23.0
     */
    public function actionScheduled(): int
    {
        $settings = RedirectManager::getInstance()->getSettings();

        if (!$settings->backupEnabled) {
            $this->stdout("Backups are disabled\n");
            return ExitCode::OK;
        }

        if ($settings->backupSchedule === 'manual') {
            $this->stdout("Backup schedule is set to manual\n");
            return ExitCode::OK;
        }

        $this->stdout("Checking backup schedule...\n", Console::FG_YELLOW);

        $lastBackupTime = $this->getLastScheduledBackupTime();
        $currentTime = time();

        $shouldBackup = match ($settings->backupSchedule) {
            'daily' => ($currentTime - $lastBackupTime) >= 86400,
            'weekly' => ($currentTime - $lastBackupTime) >= 604800,
            'monthly' => ($currentTime - $lastBackupTime) >= 2592000,
            default => false,
        };

        if ($shouldBackup) {
            $this->stdout("Running scheduled backup...\n", Console::FG_GREEN);
            $this->reason = 'scheduled';
            return $this->actionCreate();
        }

        $this->stdout("No backup needed at this time\n");
        return ExitCode::OK;
    }

    /**
     * Lists all backups
     *
     * @return int
     * @since 5.23.0
     */
    public function actionList(): int
    {
        $this->stdout("Available backups:\n\n", Console::FG_YELLOW);

        $backups = RedirectManager::getInstance()->backup->getBackups();

        if (empty($backups)) {
            $this->stdout("No backups found\n");
            return ExitCode::OK;
        }

        $this->stdout(str_pad("Date", 20) . str_pad("Reason", 15) . str_pad("Size", 12) . "Redirects\n");
        $this->stdout(str_repeat("-", 60) . "\n");

        foreach ($backups as $backup) {
            $timestamp = is_int($backup['timestamp'] ?? null)
                ? date('Y-m-d H:i:s', $backup['timestamp'])
                : (string) ($backup['timestamp'] ?? '');
            $this->stdout(
                str_pad($timestamp, 20) .
                str_pad((string)($backup['reason'] ?? ''), 15) .
                str_pad((string)($backup['formattedSize'] ?? ''), 12) .
                ($backup['redirectCount'] ?? 0) . "\n"
            );
        }

        return ExitCode::OK;
    }

    /**
     * Cleans old backups based on retention settings
     *
     * @return int
     * @since 5.23.0
     */
    public function actionClean(): int
    {
        $settings = RedirectManager::getInstance()->getSettings();

        if ($settings->backupRetentionDays <= 0) {
            $this->stdout("Backup retention is disabled (set to keep forever)\n");
            return ExitCode::OK;
        }

        $this->stdout("Cleaning backups older than {$settings->backupRetentionDays} days...\n", Console::FG_YELLOW);

        try {
            $deleted = RedirectManager::getInstance()->backup->cleanupOldBackups();

            if ($deleted > 0) {
                $this->stdout("✓ Deleted $deleted old backup(s)\n", Console::FG_GREEN);
            } else {
                $this->stdout("No old backups to clean\n");
            }

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("✗ Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Get the timestamp of the last scheduled backup
     *
     * @return int
     */
    private function getLastScheduledBackupTime(): int
    {
        $backups = RedirectManager::getInstance()->backup->getBackups();

        foreach ($backups as $backup) {
            if (($backup['reason'] ?? '') === 'scheduled') {
                return is_int($backup['timestamp'] ?? null) ? $backup['timestamp'] : 0;
            }
        }

        return 0;
    }
}
