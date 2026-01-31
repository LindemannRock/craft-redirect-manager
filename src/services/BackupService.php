<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Backup Service
 *
 * @since 5.23.0
 */
class BackupService extends Component
{
    use LoggingTrait;

    /**
     * Backup subfolders by type
     *
     * @since 5.23.0
     */
    private const BACKUP_FOLDERS = ['scheduled', 'imports', 'maintenance', 'manual', 'other'];

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

    /**
     * Create backup of existing redirects
     *
     * @param string $reason Reason for backup (import, restore, manual, scheduled)
     * @return string|null Backup directory path or null on failure
     * @since 5.23.0
     */
    public function createBackup(string $reason = 'import'): ?string
    {
        $settings = RedirectManager::$plugin->getSettings();

        if (!$settings->backupEnabled) {
            return null;
        }

        try {
            // Get all redirects
            $redirects = (new \craft\db\Query())
                ->from('{{%redirectmanager_redirects}}')
                ->all();

            if (empty($redirects)) {
                return null; // No redirects to backup
            }

            $timestamp = date('Y-m-d_H-i-s');
            $folder = $this->getFolderForReason($reason);
            $backupDir = $this->getBackupRoot() . '/' . $folder . '/' . $timestamp;
            FileHelper::createDirectory($backupDir);

            $identity = Craft::$app->getUser()->getIdentity();
            $metadata = [
                'date' => $timestamp,
                'timestamp' => time(),
                'reason' => $reason,
                'user' => $identity?->username ?? 'system',
                'userId' => $identity?->id ?? null,
                'redirectCount' => count($redirects),
                'craftVersion' => Craft::$app->getVersion(),
                'pluginVersion' => RedirectManager::$plugin->getVersion(),
            ];

            file_put_contents($backupDir . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
            file_put_contents($backupDir . '/redirects.json', json_encode($redirects, JSON_PRETTY_PRINT));

            $this->logInfo('Backup created', [
                'path' => $backupDir,
                'count' => count($redirects),
                'reason' => $reason,
            ]);

            // Cleanup old automatic backups (manual backups are never deleted)
            if ($settings->backupRetentionDays > 0 && $this->isAutomaticReason($reason)) {
                $deleted = $this->cleanupOldBackups();
                if ($deleted > 0) {
                    $this->logInfo('Cleaned old backups', ['deleted' => $deleted]);
                }
            }

            return $backupDir;
        } catch (\Throwable $e) {
            $this->logError('Backup failed', [
                'error' => $e->getMessage(),
                'reason' => $reason,
            ]);
            return null;
        }
    }

    /**
     * Get all backups from filesystem
     *
     * @return array
     * @since 5.23.0
     */
    public function getBackups(): array
    {
        $backupDir = $this->getBackupRoot();

        if (!is_dir($backupDir)) {
            return [];
        }

        $backups = [];
        // Legacy backups stored directly under backup root
        $rootDirs = FileHelper::findDirectories($backupDir, ['recursive' => false]);
        foreach ($rootDirs as $dir) {
            $dirName = basename($dir);
            if (in_array($dirName, self::BACKUP_FOLDERS, true)) {
                continue;
            }
            $this->addBackupFromDir($backups, $dir, null);
        }

        // New backups stored under subfolders
        foreach (self::BACKUP_FOLDERS as $folder) {
            $folderPath = $backupDir . '/' . $folder;
            if (!is_dir($folderPath)) {
                continue;
            }
            $dirs = FileHelper::findDirectories($folderPath, ['recursive' => false]);
            foreach ($dirs as $dir) {
                $this->addBackupFromDir($backups, $dir, $folder);
            }
        }

        // Sort by timestamp descending (newest first)
        usort($backups, function($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });

        return $backups;
    }

    /**
     * Clean old automatic backups based on retention settings
     *
     * @return int Number of backups deleted
     * @since 5.23.0
     */
    public function cleanupOldBackups(): int
    {
        $settings = RedirectManager::$plugin->getSettings();

        if ($settings->backupRetentionDays <= 0) {
            return 0;
        }

        $cutoff = time() - ($settings->backupRetentionDays * 86400);
        $deleted = 0;

        foreach ($this->getBackups() as $backup) {
            $timestamp = is_int($backup['timestamp'] ?? null) ? $backup['timestamp'] : 0;
            $reason = $backup['reason'] ?? 'import';

            if ($timestamp > 0 && $timestamp < $cutoff && $this->isAutomaticReason($reason)) {
                try {
                    FileHelper::removeDirectory($backup['path']);
                    $deleted++;
                } catch (\Throwable $e) {
                    $this->logError('Failed to delete old backup', [
                        'path' => $backup['path'] ?? '',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $deleted;
    }

    /**
     * Validate backup directory name to prevent path traversal attacks
     *
     * @param string|null $dirname
     * @return string|null Validated absolute path or null if invalid
     * @since 5.23.0
     */
    public function validateBackupDirname(?string $dirname): ?string
    {
        if ($dirname === null || $dirname === '') {
            return null;
        }

        $folder = null;
        $name = $dirname;

        if (str_contains($dirname, '/')) {
            [$folder, $name] = explode('/', $dirname, 2);
            if (!in_array($folder, self::BACKUP_FOLDERS, true)) {
                $this->logWarning('Invalid backup folder', ['dirname' => $dirname]);
                return null;
            }
        }

        // Must match timestamp format: 2025-01-21_14-30-45
        if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', $name)) {
            $this->logWarning('Invalid backup dirname format', ['dirname' => $dirname]);
            return null;
        }

        $backupRoot = $this->getBackupRoot();

        if (!is_dir($backupRoot)) {
            return null;
        }

        $realBackupRoot = realpath($backupRoot);
        if ($realBackupRoot === false) {
            return null;
        }

        $backupDir = $folder
            ? $realBackupRoot . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $name
            : $realBackupRoot . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($backupDir)) {
            if ($folder === null) {
                $fallbackDir = $realBackupRoot . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $name;
                if (is_dir($fallbackDir)) {
                    $backupDir = $fallbackDir;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        if (!is_dir($backupDir)) {
            return null;
        }

        $realBackupDir = realpath($backupDir);
        if ($realBackupDir === false || !str_starts_with($realBackupDir, $realBackupRoot)) {
            $this->logWarning('Path traversal attempt blocked', ['dirname' => $dirname]);
            return null;
        }

        return $realBackupDir;
    }

    /**
     * Get the base backup directory
     *
     * @return string
     * @since 5.23.0
     */
    public function getBackupRoot(): string
    {
        $settings = RedirectManager::$plugin->getSettings();
        return rtrim($settings->getBackupPath(), '/');
    }

    /**
     * Get relative backup name from absolute path
     *
     * @param string $backupDir
     * @return string
     * @since 5.23.0
     */
    public function getRelativeBackupName(string $backupDir): string
    {
        $root = realpath($this->getBackupRoot());
        $real = realpath($backupDir);
        if ($root && $real && str_starts_with($real, $root)) {
            $relative = ltrim(substr($real, strlen($root)), DIRECTORY_SEPARATOR);
            return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }
        return basename($backupDir);
    }

    /**
     * Determine if a backup reason should be treated as automatic
     *
     * @param string|null $reason
     * @return bool
     */
    private function isAutomaticReason(?string $reason): bool
    {
        $reason = strtolower((string)$reason);
        return $reason !== '' && !in_array($reason, ['manual', 'console'], true);
    }

    /**
     * Map backup reason to storage folder
     *
     * @param string $reason
     * @return string
     */
    private function getFolderForReason(string $reason): string
    {
        $reason = strtolower($reason);

        return match ($reason) {
            'import' => 'imports',
            'restore', 'before_restore' => 'maintenance',
            'scheduled' => 'scheduled',
            'manual', 'console' => 'manual',
            default => 'other',
        };
    }

    /**
     * Read metadata from a backup directory and add to list
     *
     * @param array $backups
     * @param string $dir
     * @param string|null $folder
     * @return void
     */
    private function addBackupFromDir(array &$backups, string $dir, ?string $folder): void
    {
        $metadataFile = $dir . '/metadata.json';
        if (!file_exists($metadataFile)) {
            return;
        }

        try {
            $metadata = json_decode(file_get_contents($metadataFile), true) ?? [];
            $metadata['path'] = $dir;
            $metadata['dirname'] = $folder ? ($folder . '/' . basename($dir)) : basename($dir);

            // Calculate total size of backup directory
            $totalSize = 0;
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                }
            }

            $metadata['size'] = $totalSize;
            $metadata['formattedSize'] = Craft::$app->getFormatter()->asShortSize($totalSize, 2);

            $backups[] = $metadata;
        } catch (\Throwable $e) {
            $this->logError('Failed to read backup metadata', [
                'dir' => $dir,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
