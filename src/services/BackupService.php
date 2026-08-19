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
use craft\base\MissingComponentInterface;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\models\FsListing;
use craft\models\Volume;
use lindemannrock\base\helpers\StorageVolumeHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;
use Throwable;
use yii\base\UserException;

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

    private const STORAGE_UNAVAILABLE_MESSAGE = 'The configured backup volume cannot currently be used. Backup operations are unavailable until the volume is restored or the effective setting is changed.';

    private const CREATION_FAILED_MESSAGE = 'The backup could not be completed. Check the configured backup storage and permissions, then try again.';

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
     *
     * A path/name means a complete backup was promoted into final storage.
     * Null means the operation was a legitimate no-op because backups are
     * disabled or there are no redirects. Storage and creation failures throw.
     *
     * @return string|null Complete backup directory path/name, or null for a successful no-op
     * @throws UserException when storage or finalization fails
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
            $backupName = $folder . '/' . $timestamp . '-' . bin2hex(random_bytes(6));

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
                try {
                    $deleted = $this->cleanupOldBackups();
                    if ($deleted > 0) {
                        $this->logInfo('Cleaned old backups', ['deleted' => $deleted]);
                    }
                } catch (Throwable $e) {
                    $this->logError('Backup retention failed after successful creation', [
                        'path' => $backupPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $backupPath;
        } catch (Throwable $e) {
            $this->logError('Backup failed', [
                'error' => $e->getMessage(),
                'reason' => $reason,
            ]);

            if ($e instanceof UserException) {
                throw $e;
            }

            if (trim((string)$settings->backupVolumeUid) !== '') {
                throw new UserException(Craft::t('redirect-manager', self::STORAGE_UNAVAILABLE_MESSAGE), previous: $e);
            }

            throw new UserException(Craft::t('redirect-manager', self::CREATION_FAILED_MESSAGE), previous: $e);
        }
    }

    /**
     * Get all backups from filesystem
     *
     * @return array
     */
    public function getBackups(): array
    {
        try {
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
                if (in_array($dirName, self::BACKUP_FOLDERS, true) || $this->validateBackupName($dirName) === null) {
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
                    if ($this->validateBackupName($folder . '/' . basename($dir)) === null) {
                        continue;
                    }
                    $this->addBackupFromDir($backups, $dir, $folder);
                }
            }

            // Sort by timestamp descending (newest first)
            usort($backups, function($a, $b) {
                return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
            });

            return $backups;
        } catch (Throwable $e) {
            $this->throwReadableStorageFailure('list', $e);
        }
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

        try {
            foreach ($this->getBackups() as $backup) {
                $timestamp = is_int($backup['timestamp'] ?? null) ? $backup['timestamp'] : 0;
                $reason = $backup['reason'] ?? 'import';

                if ($timestamp > 0 && $timestamp < $cutoff && $this->isAutomaticReason($reason)) {
                    if ($this->isUsingVolumeStorage()) {
                        if (!$this->deleteVolumeBackup((string)($backup['dirname'] ?? ''))) {
                            throw new \RuntimeException('Owned volume backup could not be deleted.');
                        }
                    } else {
                        FileHelper::removeDirectory($backup['path']);
                        if (is_dir($backup['path'])) {
                            throw new \RuntimeException('Owned local backup could not be deleted.');
                        }
                    }
                    $deleted++;
                }
            }
        } catch (Throwable $e) {
            $this->throwReadableStorageFailure('retention', $e);
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

        // Accept legacy timestamps and the collision-safe suffixed form.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:-[a-f0-9]{12})?$/', $name)) {
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
        if (trim((string)$settings->backupVolumeUid) === '') {
            return false;
        }

        $this->getVolumeFs();
        return true;
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

        $path = self::VOLUME_BACKUP_ROOT . '/' . $backupName . '/' . $filename;
        try {
            if (!$fs->fileExists($path)) {
                return null;
            }

            return $fs->read($path);
        } catch (Throwable $e) {
            $this->throwStorageUnavailable('read', $e);
        }
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

        $path = self::VOLUME_BACKUP_ROOT . '/' . $backupName;
        try {
            if (!$fs->directoryExists($path)) {
                return false;
            }

            $fs->deleteDirectory($path);
            if ($fs->directoryExists($path)) {
                throw new \RuntimeException('Volume backup remained after deletion.');
            }
            return true;
        } catch (Throwable $e) {
            $this->throwStorageUnavailable('delete', $e);
        }
    }

    /**
     * Get the base backup directory
     *
     * @return string
     */
    public function getBackupRoot(): string
    {
        $settings = RedirectManager::$plugin->getSettings();
        if (trim((string)$settings->backupVolumeUid) !== '') {
            $this->getVolumeFs();
            throw new \LogicException('A local backup root is unavailable while volume storage is configured.');
        }
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

        if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:-[a-f0-9]{12})?$/', $name)) {
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
        $redirectsContent = Json::encode($redirects, JSON_PRETTY_PRINT);
        $metadata = $this->withChecksumMetadata($metadata, $redirectsContent);
        $metadataContent = Json::encode($metadata, JSON_PRETTY_PRINT);

        $finalDir = $this->getBackupRoot() . '/' . $backupName;
        $parentDir = dirname($finalDir);
        $stagingDir = $parentDir . '/.' . basename($finalDir) . '.staging-' . bin2hex(random_bytes(6));
        $promotionSucceeded = false;

        try {
            $this->createLocalDirectory($parentDir);
            if (is_dir($finalDir)) {
                throw new \RuntimeException('Unique backup path already exists.');
            }
            $this->createLocalDirectory($stagingDir);

            $this->writeCompleteLocalFile($stagingDir . '/metadata.json', $metadataContent);
            $this->writeCompleteLocalFile($stagingDir . '/redirects.json', $redirectsContent);
            $this->validateStagedLocalBackup($stagingDir, $metadataContent, $redirectsContent, $backupName);

            if (!$this->moveLocalDirectory($stagingDir, $finalDir)) {
                throw new \RuntimeException('Backup promotion failed.');
            }
            $promotionSucceeded = true;
            if (!is_dir($finalDir) || is_dir($stagingDir)) {
                throw new \RuntimeException('Backup promotion failed.');
            }

            return $finalDir;
        } catch (Throwable $e) {
            $ownsFinal = $promotionSucceeded
                || (!$this->localDirectoryExists($stagingDir)
                    && $this->localBackupMatchesSnapshot($finalDir, $metadataContent, $redirectsContent));
            $this->removeOwnedLocalDirectory($stagingDir);
            if ($ownsFinal) {
                $this->removeOwnedLocalDirectory($finalDir);
            }
            throw $e;
        }
    }

    protected function createLocalDirectory(string $path): void
    {
        FileHelper::createDirectory($path);
        if (!is_dir($path)) {
            throw new \RuntimeException('Backup directory could not be created.');
        }
    }

    protected function writeLocalFile(string $path, string $content): int|false
    {
        return file_put_contents($path, $content);
    }

    protected function moveLocalDirectory(string $source, string $destination): bool
    {
        return rename($source, $destination);
    }

    protected function removeLocalDirectory(string $path): void
    {
        FileHelper::removeDirectory($path);
    }

    protected function localDirectoryExists(string $path): bool
    {
        clearstatcache(true, $path);
        return is_dir($path);
    }

    private function writeCompleteLocalFile(string $path, string $content): void
    {
        $written = $this->writeLocalFile($path, $content);
        if ($written === false || $written !== strlen($content)) {
            throw new \RuntimeException('Backup file write was incomplete.');
        }
    }

    private function validateStagedLocalBackup(
        string $stagingDir,
        string $metadataContent,
        string $redirectsContent,
        string $backupName,
    ): void {
        $writtenMetadata = file_get_contents($stagingDir . '/metadata.json');
        $writtenRedirects = file_get_contents($stagingDir . '/redirects.json');
        if ($writtenMetadata !== $metadataContent || $writtenRedirects !== $redirectsContent
            || !$this->validateBackupIntegrity($writtenMetadata, $writtenRedirects, $backupName)) {
            throw new \RuntimeException('Staged backup validation failed.');
        }
    }

    private function removeOwnedLocalDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $this->removeLocalDirectory($path);
        if ($this->localDirectoryExists($path)) {
            $this->logError('Failed to remove owned backup directory', ['path' => $path]);
        }
    }

    private function localBackupMatchesSnapshot(string $path, string $metadataContent, string $redirectsContent): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        return file_get_contents($path . '/metadata.json') === $metadataContent
            && file_get_contents($path . '/redirects.json') === $redirectsContent;
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
        $redirectsContent = Json::encode($redirects, JSON_PRETTY_PRINT);
        $metadata = $this->withChecksumMetadata($metadata, $redirectsContent);
        $metadataContent = Json::encode($metadata, JSON_PRETTY_PRINT);

        $finalPath = self::VOLUME_BACKUP_ROOT . '/' . $backupName;
        $parentPath = dirname($finalPath);
        $stagingPath = $parentPath . '/.' . basename($finalPath) . '.staging-' . bin2hex(random_bytes(6));
        $promotionSucceeded = false;

        try {
            $this->createVolumeDirectory($parentPath, $fs);
            if ($fs->directoryExists($finalPath)) {
                throw new \RuntimeException('Unique backup path already exists.');
            }
            $this->createVolumeDirectory($stagingPath, $fs);

            $fs->write($stagingPath . '/metadata.json', $metadataContent);
            $fs->write($stagingPath . '/redirects.json', $redirectsContent);
            $writtenMetadata = $fs->read($stagingPath . '/metadata.json');
            $writtenRedirects = $fs->read($stagingPath . '/redirects.json');
            if ($writtenMetadata !== $metadataContent || $writtenRedirects !== $redirectsContent
                || !$this->validateBackupIntegrity($writtenMetadata, $writtenRedirects, $backupName)) {
                throw new \RuntimeException('Staged backup validation failed.');
            }

            $fs->renameDirectory($stagingPath, basename($finalPath));
            $promotionSucceeded = true;
            if (!$fs->directoryExists($finalPath) || $fs->directoryExists($stagingPath)) {
                throw new \RuntimeException('Backup promotion failed.');
            }

            return $backupName;
        } catch (Throwable $e) {
            $ownsFinal = $promotionSucceeded
                || (!$this->volumeDirectoryExists($stagingPath, $fs)
                    && $this->volumeBackupMatchesSnapshot($finalPath, $metadataContent, $redirectsContent, $fs));
            $this->removeOwnedVolumeDirectory($stagingPath, $fs);
            if ($ownsFinal) {
                $this->removeOwnedVolumeDirectory($finalPath, $fs);
            }
            throw $e;
        }
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

    private function getVolumeFs(): FsInterface
    {
        $settings = RedirectManager::$plugin->getSettings();
        $volumeUid = trim((string)$settings->backupVolumeUid);
        if ($volumeUid === '') {
            throw new \LogicException('Volume storage was requested without an effective volume UID.');
        }

        try {
            $volumeErrors = StorageVolumeHelper::validateVolume($volumeUid);
            if ($volumeErrors !== []) {
                throw new \RuntimeException('Backup volume failed validation: ' . implode('; ', $volumeErrors));
            }

            $volume = Craft::$app->getVolumes()->getVolumeByUid($volumeUid);
            if (!$volume instanceof Volume) {
                throw new \RuntimeException('Configured backup volume could not be resolved.');
            }

            $fs = $volume->getFs();
            if (!$fs instanceof FsInterface || $fs instanceof MissingComponentInterface) {
                throw new \RuntimeException('Configured backup volume filesystem is unavailable.');
            }

            return $fs;
        } catch (Throwable $e) {
            $this->throwStorageUnavailable('resolve', $e, $volumeUid);
        }
    }

    private function createVolumeDirectory(string $path, FsInterface $fs): void
    {
        $currentPath = '';
        foreach (explode('/', $path) as $part) {
            if ($part === '') {
                continue;
            }

            $currentPath = $currentPath === '' ? $part : $currentPath . '/' . $part;
            if (!$fs->directoryExists($currentPath)) {
                $fs->createDirectory($currentPath);
                if (!$fs->directoryExists($currentPath)) {
                    throw new \RuntimeException('Backup volume directory could not be created.');
                }
            }
        }
    }

    private function volumeDirectoryExists(string $path, FsInterface $fs): bool
    {
        try {
            return $fs->directoryExists($path);
        } catch (Throwable $e) {
            $this->logError('Failed to inspect owned volume backup directory', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function removeOwnedVolumeDirectory(string $path, FsInterface $fs): void
    {
        try {
            if (!$fs->directoryExists($path)) {
                return;
            }
            $fs->deleteDirectory($path);
            if ($fs->directoryExists($path)) {
                $this->logError('Failed to remove owned volume backup directory', ['path' => $path]);
            }
        } catch (Throwable $e) {
            $this->logError('Failed to remove owned volume backup directory', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function volumeBackupMatchesSnapshot(
        string $path,
        string $metadataContent,
        string $redirectsContent,
        FsInterface $fs,
    ): bool {
        try {
            return $fs->directoryExists($path)
                && $fs->fileExists($path . '/metadata.json')
                && $fs->fileExists($path . '/redirects.json')
                && $fs->read($path . '/metadata.json') === $metadataContent
                && $fs->read($path . '/redirects.json') === $redirectsContent;
        } catch (Throwable $e) {
            $this->logError('Failed to verify owned promoted backup', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function throwStorageUnavailable(string $operation, Throwable $e, ?string $volumeUid = null): never
    {
        $settings = RedirectManager::$plugin->getSettings();
        $this->logError('Backup volume operation failed', [
            'operation' => $operation,
            'backupVolumeUid' => $volumeUid ?? $settings->backupVolumeUid,
            'error' => $e->getMessage(),
        ]);

        if ($e instanceof UserException && $e->getMessage() === Craft::t('redirect-manager', self::STORAGE_UNAVAILABLE_MESSAGE)) {
            throw $e;
        }

        throw new UserException(Craft::t('redirect-manager', self::STORAGE_UNAVAILABLE_MESSAGE), previous: $e);
    }

    private function throwReadableStorageFailure(string $operation, Throwable $e): never
    {
        $settings = RedirectManager::$plugin->getSettings();
        if (trim((string)$settings->backupVolumeUid) !== '') {
            $this->throwStorageUnavailable($operation, $e);
        }

        $this->logError('Local backup storage operation failed', [
            'operation' => $operation,
            'error' => $e->getMessage(),
        ]);
        throw new UserException(Craft::t('redirect-manager', 'The local backup storage could not be accessed. Check the configured path and permissions, then try again.'), previous: $e);
    }

    /**
     * Get all backups from volume storage.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getVolumeBackups(): array
    {
        $fs = $this->getVolumeFs();
        if (!$fs->directoryExists(self::VOLUME_BACKUP_ROOT)) {
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
                if ($this->validateBackupName($backupName) === null) {
                    continue;
                }
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
        } catch (Throwable $e) {
            $this->logError('Failed to read volume backup metadata', [
                'backup' => $backupName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
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
            $metadataContent = file_get_contents($metadataFile);
            if (!is_string($metadataContent)) {
                throw new \RuntimeException('Backup metadata could not be read.');
            }
            $metadata = json_decode($metadataContent, true) ?? [];
            if (!is_array($metadata)) {
                return;
            }
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
            throw $e;
        }
    }
}
