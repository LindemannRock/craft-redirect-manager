<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use Craft;
use craft\base\Component;
use craft\base\FsInterface;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\models\FsListing;
use lindemannrock\base\helpers\StorageVolumeHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Backup Service
 *
 * @since 5.24.0
 */
class BackupService extends Component
{
    use LoggingTrait;

    /**
     * Backup subfolders by type
     */
    private const BACKUP_FOLDERS = ['scheduled', 'imports', 'maintenance', 'manual', 'other'];

    private const VOLUME_BACKUP_ROOT = 'redirect-manager/backups';

    private const CHECKSUM_ALGORITHM = 'sha256';

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
            $backupName = $folder . '/' . $timestamp;

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

            if ($this->isUsingVolumeStorage()) {
                $backupPath = $this->createVolumeBackup($backupName, $metadata, $redirects);
            } else {
                $backupPath = $this->createLocalBackup($backupName, $metadata, $redirects);
            }

            $this->logInfo('Backup created', [
                'path' => $backupPath,
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

            return $backupPath;
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
     */
    public function getBackups(): array
    {
        if ($this->isUsingVolumeStorage()) {
            return $this->getVolumeBackups();
        }

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
                    if ($this->isUsingVolumeStorage()) {
                        $this->deleteVolumeBackup((string)($backup['dirname'] ?? ''));
                    } else {
                        FileHelper::removeDirectory($backup['path']);
                    }
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
     * Validate backup name for volume storage operations.
     *
     * @since 5.32.0
     */
    public function validateVolumeBackupName(?string $dirname): ?string
    {
        if (!$this->isUsingVolumeStorage()) {
            return null;
        }

        return $this->validateBackupName($dirname);
    }

    /**
     * Return whether backups are configured to use a Craft volume.
     *
     * @since 5.32.0
     */
    public function isUsingVolumeStorage(): bool
    {
        $settings = RedirectManager::$plugin->getSettings();
        if (!$settings->backupVolumeUid) {
            return false;
        }

        return $this->getVolumeFs() instanceof FsInterface;
    }

    /**
     * Read a file from a volume backup.
     *
     * @since 5.32.0
     */
    public function readVolumeBackupFile(string $backupName, string $filename): ?string
    {
        $backupName = $this->validateBackupName($backupName);
        if ($backupName === null) {
            return null;
        }

        $fs = $this->getVolumeFs();
        if (!$fs instanceof FsInterface) {
            return null;
        }

        $path = self::VOLUME_BACKUP_ROOT . '/' . $backupName . '/' . $filename;
        if (!$fs->fileExists($path)) {
            return null;
        }

        return $fs->read($path);
    }

    /**
     * Delete a backup from volume storage.
     *
     * @since 5.32.0
     */
    public function deleteVolumeBackup(string $backupName): bool
    {
        $backupName = $this->validateBackupName($backupName);
        if ($backupName === null) {
            return false;
        }

        $fs = $this->getVolumeFs();
        if (!$fs instanceof FsInterface) {
            return false;
        }

        $path = self::VOLUME_BACKUP_ROOT . '/' . $backupName;
        if (!$fs->directoryExists($path)) {
            return false;
        }

        $fs->deleteDirectory($path);
        return true;
    }

    /**
     * Get the base backup directory
     *
     * @return string
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
     * @since 5.32.0
     */
    public function getRelativeBackupName(string $backupDir): string
    {
        if ($this->isUsingVolumeStorage()) {
            return $this->validateBackupName($backupDir) ?? basename($backupDir);
        }

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
     * Validate a backup name in either legacy or folder/timestamp form.
     */
    private function validateBackupName(?string $dirname): ?string
    {
        if ($dirname === null || $dirname === '') {
            return null;
        }

        $folder = null;
        $name = $dirname;

        if (str_contains($dirname, '/')) {
            [$folder, $name] = explode('/', $dirname, 2);
            if (!in_array($folder, self::BACKUP_FOLDERS, true)) {
                return null;
            }
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/', $name)) {
            return null;
        }

        return $folder ? ($folder . '/' . $name) : $name;
    }

    /**
     * Create backup files in local storage.
     *
     * @param array<string, mixed> $metadata
     * @param array<int, array<string, mixed>> $redirects
     */
    private function createLocalBackup(string $backupName, array $metadata, array $redirects): string
    {
        $backupDir = $this->getBackupRoot() . '/' . $backupName;
        FileHelper::createDirectory($backupDir);

        $redirectsContent = Json::encode($redirects, JSON_PRETTY_PRINT);
        $metadata = $this->withChecksumMetadata($metadata, $redirectsContent);

        file_put_contents($backupDir . '/metadata.json', Json::encode($metadata, JSON_PRETTY_PRINT));
        file_put_contents($backupDir . '/redirects.json', $redirectsContent);

        return $backupDir;
    }

    /**
     * Create backup files in volume storage.
     *
     * @param array<string, mixed> $metadata
     * @param array<int, array<string, mixed>> $redirects
     */
    private function createVolumeBackup(string $backupName, array $metadata, array $redirects): string
    {
        $fs = $this->getVolumeFs();
        if (!$fs instanceof FsInterface) {
            throw new \RuntimeException('Backup volume is not available.');
        }

        $backupPath = self::VOLUME_BACKUP_ROOT . '/' . $backupName;
        $this->createVolumeDirectory($backupPath);

        $redirectsContent = Json::encode($redirects, JSON_PRETTY_PRINT);
        $metadata = $this->withChecksumMetadata($metadata, $redirectsContent);

        $fs->write($backupPath . '/metadata.json', Json::encode($metadata, JSON_PRETTY_PRINT));
        $fs->write($backupPath . '/redirects.json', $redirectsContent);

        return $backupName;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function withChecksumMetadata(array $metadata, string $redirectsContent): array
    {
        $metadata['checksum'] = hash(self::CHECKSUM_ALGORITHM, $redirectsContent);
        $metadata['checksumAlgorithm'] = self::CHECKSUM_ALGORITHM;

        return $metadata;
    }

    /**
     * Validate backup checksum metadata against redirects content.
     *
     * @since 5.32.0
     */
    public function validateBackupIntegrity(string $metadataContent, string $redirectsContent, string $backupName): bool
    {
        $metadata = Json::decode($metadataContent);

        if (!is_array($metadata) || !isset($metadata['checksum'])) {
            $this->logError('Backup checksum is missing', [
                'backup' => $backupName,
            ]);

            return false;
        }

        $expectedChecksum = $metadata['checksum'];
        $actualChecksum = hash(self::CHECKSUM_ALGORITHM, $redirectsContent);

        if ($expectedChecksum !== $actualChecksum) {
            $this->logError('Backup checksum validation failed', [
                'backup' => $backupName,
                'expected' => substr((string)$expectedChecksum, 0, 16) . '...',
                'actual' => substr($actualChecksum, 0, 16) . '...',
            ]);

            return false;
        }

        return true;
    }

    private function getVolumeFs(): ?FsInterface
    {
        $settings = RedirectManager::$plugin->getSettings();
        if (!$settings->backupVolumeUid) {
            return null;
        }

        $volumeErrors = StorageVolumeHelper::validateVolume($settings->backupVolumeUid);
        if ($volumeErrors !== []) {
            $this->logWarning('Backup volume failed validation.', [
                'backupVolumeUid' => $settings->backupVolumeUid,
                'errors' => $volumeErrors,
            ]);
            return null;
        }

        $volume = Craft::$app->getVolumes()->getVolumeByUid($settings->backupVolumeUid);
        return $volume?->getFs();
    }

    private function createVolumeDirectory(string $path): void
    {
        $fs = $this->getVolumeFs();
        if (!$fs instanceof FsInterface) {
            throw new \RuntimeException('Backup volume is not available.');
        }

        $currentPath = '';
        foreach (explode('/', $path) as $part) {
            if ($part === '') {
                continue;
            }

            $currentPath = $currentPath === '' ? $part : $currentPath . '/' . $part;
            if (!$fs->directoryExists($currentPath)) {
                $fs->createDirectory($currentPath);
            }
        }
    }

    /**
     * Get all backups from volume storage.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getVolumeBackups(): array
    {
        $fs = $this->getVolumeFs();
        if (!$fs instanceof FsInterface || !$fs->directoryExists(self::VOLUME_BACKUP_ROOT)) {
            return [];
        }

        $backups = [];
        foreach (self::BACKUP_FOLDERS as $folder) {
            $folderPath = self::VOLUME_BACKUP_ROOT . '/' . $folder;
            if (!$fs->directoryExists($folderPath)) {
                continue;
            }

            foreach ($fs->getFileList($folderPath, false) as $listing) {
                if (!$listing instanceof FsListing || !$listing->getIsDir()) {
                    continue;
                }

                $backupName = $folder . '/' . $listing->getBasename();
                $this->addVolumeBackup($backups, $backupName, $fs);
            }
        }

        usort($backups, function($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });

        return $backups;
    }

    /**
     * @param array<int, array<string, mixed>> $backups
     */
    private function addVolumeBackup(array &$backups, string $backupName, FsInterface $fs): void
    {
        $metadataPath = self::VOLUME_BACKUP_ROOT . '/' . $backupName . '/metadata.json';
        if (!$fs->fileExists($metadataPath)) {
            return;
        }

        try {
            $metadata = Json::decode($fs->read($metadataPath)) ?? [];
            if (!is_array($metadata)) {
                return;
            }

            $metadata['path'] = self::VOLUME_BACKUP_ROOT . '/' . $backupName;
            $metadata['dirname'] = $backupName;
            $metadata['size'] = $this->calculateVolumeBackupSize($backupName, $fs);
            $metadata['formattedSize'] = Craft::$app->getFormatter()->asShortSize((int)$metadata['size'], 2);

            $backups[] = $metadata;
        } catch (\Throwable $e) {
            $this->logError('Failed to read volume backup metadata', [
                'backup' => $backupName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function calculateVolumeBackupSize(string $backupName, FsInterface $fs): int
    {
        $size = 0;
        foreach (['metadata.json', 'redirects.json'] as $filename) {
            $path = self::VOLUME_BACKUP_ROOT . '/' . $backupName . '/' . $filename;
            if ($fs->fileExists($path)) {
                $size += $fs->getFileSize($path);
            }
        }

        return $size;
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
