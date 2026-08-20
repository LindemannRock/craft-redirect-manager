<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use Craft;
use craft\base\FsInterface;
use craft\fs\Local;
use craft\helpers\Json;
use craft\models\Volume;
use craft\services\Config;
use craft\services\Volumes;
use Generator;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\BackupService;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use yii\base\UserException;

/**
 * Pins canonical Craft volume subpaths and bounded legacy compatibility.
 *
 * @since 5.41.0
 */
final class BackupVolumeSubpathTest extends TestCase
{
    private const SUBPATH = 'customer-assets/redirect-backups';
    private const CANONICAL_ROOT = self::SUBPATH . '/redirect-manager/backups';
    private const LEGACY_ROOT = 'redirect-manager/backups';

    private Local $filesystem;
    private string $filesystemRoot;
    private Volume $volume;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEmptyPluginConfig();
        $this->filesystemRoot = $this->createTrackedTempDirectory('redirect-volume-subpath-');
        $this->filesystem = new Local([
            'name' => 'Backup volume subpath filesystem',
            'handle' => 'backupVolumeSubpathFilesystem',
            'path' => $this->filesystemRoot,
        ]);
        $this->volume = $this->volume($this->filesystem, self::SUBPATH);
        $this->installVolume($this->volume);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupVolumeUid = 'backup-volume-subpath';
        $this->settings()->backupRetentionDays = 0;
    }

    public function testNonEmptyVolumeSubpathOwnsTheCompleteCanonicalLifecycle(): void
    {
        $this->seedRedirect();

        $name = $this->backup()->createBackup('manual');

        self::assertIsString($name);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::LEGACY_ROOT . '/' . $name);

        $backups = $this->backup()->getBackups();
        self::assertCount(1, $backups);
        self::assertSame($name, $backups[0]['dirname']);
        self::assertSame('canonical-volume', $backups[0]['storageType']);
        self::assertSame(
            'Volume: Backup volume subpath/' . self::CANONICAL_ROOT,
            $backups[0]['storageLocation'],
        );
        self::assertGreaterThan(0, $backups[0]['size']);
        self::assertStringContainsString('checksum', (string)$this->backup()->readVolumeBackupFile($name, 'metadata.json'));
        self::assertStringContainsString('sourceUrl', (string)$this->backup()->readVolumeBackupFile($name, 'redirects.json'));

        self::assertTrue($this->backup()->deleteVolumeBackup($name));
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
    }

    public function testRemoteLikeFilesystemUsesTheSameWrapperBackedLifecycle(): void
    {
        $remoteRoot = $this->createTrackedTempDirectory('redirect-remote-volume-subpath-');
        $delegate = new Local([
            'name' => 'Remote-like delegate',
            'handle' => 'remoteLikeDelegate',
            'path' => $remoteRoot,
        ]);
        $remote = $this->remoteLikeFilesystem($delegate);
        $this->installVolume($this->volume($remote, self::SUBPATH));
        $this->seedRedirect();

        $name = $this->backup()->createBackup('manual');

        self::assertIsString($name);
        self::assertDirectoryExists($remoteRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
        self::assertSame($name, $this->backup()->getBackups()[0]['dirname']);
        self::assertNotNull($this->backup()->readVolumeBackupFile($name, 'redirects.json'));
        self::assertTrue($this->backup()->deleteVolumeBackup($name));
        self::assertDirectoryDoesNotExist($remoteRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
    }

    public function testPartialRemotePromotionFailureRemovesOnlyRequestOwnedCanonicalArtifacts(): void
    {
        $remoteRoot = $this->createTrackedTempDirectory('redirect-partial-remote-volume-');
        $filesystem = new PartialPromotionFilesystem([
            'name' => 'Partial promotion filesystem',
            'handle' => 'partialPromotionFilesystem',
            'path' => $remoteRoot,
        ]);
        $filesystem->canonicalRoot = self::CANONICAL_ROOT;
        $filesystem->failAfterMovingOneFile = true;
        $unrelatedPath = $remoteRoot . '/' . self::CANONICAL_ROOT . '/manual/unrelated-sibling/preserve.bin';
        $historicalPath = $remoteRoot . '/' . self::LEGACY_ROOT . '/manual/historical-sibling/preserve.bin';
        \craft\helpers\FileHelper::createDirectory(dirname($unrelatedPath));
        \craft\helpers\FileHelper::createDirectory(dirname($historicalPath));
        file_put_contents($unrelatedPath, "unrelated\0canonical\n");
        file_put_contents($historicalPath, "historical\0prefix\n");
        $unrelatedHash = hash_file('sha256', $unrelatedPath);
        $historicalHash = hash_file('sha256', $historicalPath);
        $this->installVolume($this->volume($filesystem, self::SUBPATH));
        $this->seedRedirect();

        try {
            $this->backup()->createBackup('manual');
            self::fail('Expected a partial remote promotion failure.');
        } catch (UserException $exception) {
            self::assertStringContainsString('configured backup volume', $exception->getMessage());
        }

        self::assertSame(1, $filesystem->movedFiles);
        self::assertNotNull($filesystem->stagingPath);
        self::assertNotNull($filesystem->finalPath);
        self::assertDirectoryDoesNotExist($remoteRoot . '/' . $filesystem->stagingPath);
        self::assertDirectoryDoesNotExist($remoteRoot . '/' . $filesystem->finalPath);
        self::assertSame($unrelatedHash, hash_file('sha256', $unrelatedPath));
        self::assertSame($historicalHash, hash_file('sha256', $historicalPath));
        self::assertSame(1, $filesystem->renameCalls);
        $this->assertCanonicalOperationsUseOneWrapperSubpath($filesystem);
    }

    public function testLatePromotionCollisionPreservesTheCompleteForeignBackup(): void
    {
        $remoteRoot = $this->createTrackedTempDirectory('redirect-colliding-remote-volume-');
        $filesystem = new PartialPromotionFilesystem([
            'name' => 'Colliding promotion filesystem',
            'handle' => 'collidingPromotionFilesystem',
            'path' => $remoteRoot,
        ]);
        $filesystem->canonicalRoot = self::CANONICAL_ROOT;
        $filesystem->createCompleteCollisionOnFinalRecheck = true;
        $this->installVolume($this->volume($filesystem, self::SUBPATH));
        $this->seedRedirect();

        try {
            $this->backup()->createBackup('manual');
            self::fail('Expected a late promotion collision.');
        } catch (UserException $exception) {
            self::assertStringContainsString('configured backup volume', $exception->getMessage());
        }

        self::assertNotNull($filesystem->stagingPath);
        self::assertNotNull($filesystem->finalPath);
        self::assertDirectoryDoesNotExist($remoteRoot . '/' . $filesystem->stagingPath);
        self::assertDirectoryExists($remoteRoot . '/' . $filesystem->finalPath);
        self::assertSame($filesystem->collisionMetadata, file_get_contents($remoteRoot . '/' . $filesystem->finalPath . '/metadata.json'));
        self::assertSame($filesystem->collisionRedirects, file_get_contents($remoteRoot . '/' . $filesystem->finalPath . '/redirects.json'));
        self::assertSame(0, $filesystem->renameCalls);
        $this->assertCanonicalOperationsUseOneWrapperSubpath($filesystem);
    }

    public function testExactLegacyPrefixSupportsTimestampNamesAndEveryReadAction(): void
    {
        $folderName = 'manual/2026-08-19_12-00-00';
        $rootName = '2026-08-19_11-00-00';
        $this->writeBackup($this->filesystem, self::LEGACY_ROOT, $folderName, 'legacy folder');
        $this->writeBackup($this->filesystem, self::LEGACY_ROOT, $rootName, 'legacy root', 1_755_601_200);

        $backups = $this->backup()->getBackups();

        self::assertCount(2, $backups);
        self::assertSame([$folderName, $rootName], array_column($backups, 'dirname'));
        self::assertSame(['legacy-volume', 'legacy-volume'], array_column($backups, 'storageType'));
        self::assertSame(
            ['Volume: Backup volume subpath/' . self::LEGACY_ROOT, 'Volume: Backup volume subpath/' . self::LEGACY_ROOT],
            array_column($backups, 'storageLocation'),
        );
        self::assertStringContainsString('legacy folder', (string)$this->backup()->readVolumeBackupFile($folderName, 'redirects.json'));
        self::assertStringContainsString('legacy root', (string)$this->backup()->readVolumeBackupFile($rootName, 'redirects.json'));
        self::assertTrue($this->backup()->deleteVolumeBackup($folderName));
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::LEGACY_ROOT . '/' . $folderName);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::LEGACY_ROOT . '/' . $rootName);
    }

    public function testCanonicalDuplicateWinsWithoutOverwritingOrDeletingLegacyData(): void
    {
        $name = 'manual/2026-08-19_12-00-00';
        $this->writeBackup($this->filesystem, self::CANONICAL_ROOT, $name, 'canonical copy');
        $this->writeBackup($this->filesystem, self::LEGACY_ROOT, $name, 'legacy copy');

        $backups = $this->backup()->getBackups();

        self::assertCount(1, $backups);
        self::assertSame('canonical-volume', $backups[0]['storageType']);
        self::assertStringContainsString('canonical copy', (string)$this->backup()->readVolumeBackupFile($name, 'redirects.json'));

        self::assertTrue($this->backup()->deleteVolumeBackup($name));
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::LEGACY_ROOT . '/' . $name);
        self::assertStringContainsString('legacy copy', (string)$this->backup()->readVolumeBackupFile($name, 'redirects.json'));
    }

    public function testRetentionDeletesOnlyEligibleExactPrefixBackups(): void
    {
        $oldName = 'scheduled/2020-01-01_00-00-00';
        $manualName = 'manual/2020-01-01_00-00-00';
        $this->writeBackup($this->filesystem, self::LEGACY_ROOT, $oldName, 'expired scheduled', 1_577_836_800, 'scheduled');
        $this->writeBackup($this->filesystem, self::LEGACY_ROOT, $manualName, 'preserved manual', 1_577_836_800, 'manual');
        $this->writeBackup($this->filesystem, 'unrelated/' . self::LEGACY_ROOT, $oldName, 'unrelated', 1_577_836_800, 'scheduled');
        $this->settings()->backupRetentionDays = 1;

        self::assertSame(1, $this->backup()->cleanupOldBackups());
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::LEGACY_ROOT . '/' . $oldName);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::LEGACY_ROOT . '/' . $manualName);
        self::assertDirectoryExists($this->filesystemRoot . '/unrelated/' . self::LEGACY_ROOT . '/' . $oldName);
    }

    public function testCanonicalRetentionUsesOnlyTheConfiguredVolumeSubpath(): void
    {
        $oldName = 'scheduled/2020-01-01_00-00-00';
        $this->writeBackup($this->filesystem, self::CANONICAL_ROOT, $oldName, 'expired canonical', 1_577_836_800, 'scheduled');
        $this->writeBackup($this->filesystem, self::LEGACY_ROOT, 'manual/2020-01-01_00-00-00', 'preserved legacy', 1_577_836_800, 'manual');
        $this->settings()->backupRetentionDays = 1;

        self::assertSame(1, $this->backup()->cleanupOldBackups());
        self::assertDirectoryDoesNotExist($this->filesystemRoot . '/' . self::CANONICAL_ROOT . '/' . $oldName);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::LEGACY_ROOT . '/manual/2020-01-01_00-00-00');
    }

    public function testConfigOverrideSelectsTheWrapperAndItsConfiguredSubpath(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getConfigFromFile')->willReturnCallback(
            static fn(string $handle): array => $handle === 'redirect-manager'
                ? ['backupVolumeUid' => 'backup-volume-subpath']
                : [],
        );
        Craft::$app->set('config', $config);
        $this->settings()->backupVolumeUid = null;
        PluginHelper::applyConfigOverridesToSettings($this->settings(), 'redirect-manager');
        $this->seedRedirect();

        $name = $this->backup()->createBackup('manual');

        self::assertSame('backup-volume-subpath', $this->settings()->backupVolumeUid);
        self::assertIsString($name);
        self::assertDirectoryExists($this->filesystemRoot . '/' . self::CANONICAL_ROOT . '/' . $name);
    }

    public function testEmptySubpathDoesNotTreatCanonicalObjectsAsASecondLegacyLocation(): void
    {
        $this->installVolume($this->volume($this->filesystem, ''));
        $name = 'manual/2026-08-19_12-00-00';
        $this->writeBackup($this->filesystem, self::LEGACY_ROOT, $name, 'single location');

        $backups = $this->backup()->getBackups();

        self::assertCount(1, $backups);
        self::assertSame('canonical-volume', $backups[0]['storageType']);
        self::assertStringContainsString('single location', (string)$this->backup()->readVolumeBackupFile($name, 'redirects.json'));
    }

    public function testSettingsLocationIncludesTheConfiguredVolumeSubpath(): void
    {
        self::assertSame(
            'Volume: Backup volume subpath/' . self::CANONICAL_ROOT,
            $this->settings()->getBackupLocationLabel(),
        );
    }

    private function backup(): BackupService
    {
        return RedirectManager::getInstance()->backup;
    }

    private function volume(FsInterface $fs, string $subpath): Volume
    {
        $volume = new Volume([
            'name' => 'Backup volume subpath',
            'handle' => 'backupVolumeSubpath',
            'uid' => 'backup-volume-subpath',
            'subpath' => $subpath,
        ]);

        $property = new \ReflectionProperty(Volume::class, '_fs');
        $property->setValue($volume, $fs);

        return $volume;
    }

    private function installVolume(Volume $volume): void
    {
        $volumes = $this->createMock(Volumes::class);
        $volumes->method('getVolumeByUid')->willReturn($volume);
        Craft::$app->set('volumes', $volumes);
    }

    private function installEmptyPluginConfig(): void
    {
        $original = Craft::$app->getConfig();
        $config = $this->createMock(Config::class);
        $config->method('getGeneral')->willReturn($original->getGeneral());
        $config->method('getConfigFromFile')->willReturn([]);
        Craft::$app->set('config', $config);
    }

    private function writeBackup(
        Local $filesystem,
        string $root,
        string $name,
        string $marker,
        int $timestamp = 1_755_604_800,
        string $reason = 'manual',
    ): void {
        $path = $root . '/' . $name;
        $filesystem->createDirectory($path);
        $redirects = Json::encode([['sourceUrl' => '/' . $marker]], JSON_PRETTY_PRINT);
        $metadata = Json::encode([
            'date' => basename($name),
            'timestamp' => $timestamp,
            'reason' => $reason,
            'redirectCount' => 1,
            'checksum' => hash('sha256', $redirects),
            'checksumAlgorithm' => 'sha256',
        ], JSON_PRETTY_PRINT);
        $filesystem->write($path . '/metadata.json', $metadata);
        $filesystem->write($path . '/redirects.json', $redirects);
    }

    private function remoteLikeFilesystem(Local $delegate): FsInterface & MockObject
    {
        /** @var FsInterface&MockObject $filesystem */
        $filesystem = $this->createMock(FsInterface::class);
        $filesystem->method('directoryExists')->willReturnCallback(
            static fn(string $path): bool => $delegate->directoryExists($path),
        );
        $filesystem->method('createDirectory')->willReturnCallback(
            static fn(string $path, array $config = []) => $delegate->createDirectory($path, $config),
        );
        $filesystem->method('deleteDirectory')->willReturnCallback(
            static fn(string $path) => $delegate->deleteDirectory($path),
        );
        $filesystem->method('renameDirectory')->willReturnCallback(
            static fn(string $path, string $newName) => $delegate->renameDirectory($path, $newName),
        );
        $filesystem->method('write')->willReturnCallback(
            static fn(string $path, string $contents, array $config = []) => $delegate->write($path, $contents, $config),
        );
        $filesystem->method('read')->willReturnCallback(
            static fn(string $path): string => $delegate->read($path),
        );
        $filesystem->method('fileExists')->willReturnCallback(
            static fn(string $path): bool => $delegate->fileExists($path),
        );
        $filesystem->method('getFileSize')->willReturnCallback(
            static fn(string $path): int => $delegate->getFileSize($path),
        );
        $filesystem->method('getFileList')->willReturnCallback(
            static fn(string $path = '', bool $recursive = true): Generator => $delegate->getFileList($path, $recursive),
        );

        return $filesystem;
    }

    private function assertCanonicalOperationsUseOneWrapperSubpath(PartialPromotionFilesystem $filesystem): void
    {
        self::assertNotSame([], $filesystem->operationPaths);
        foreach ($filesystem->operationPaths as $path) {
            self::assertStringStartsWith(self::SUBPATH . '/redirect-manager', $path);
            self::assertSame(1, substr_count($path, self::SUBPATH));
            self::assertFalse(str_starts_with($path, self::LEGACY_ROOT));
        }
    }
}

/** Remote-like filesystem with non-atomic directory-promotion failure seams. */
final class PartialPromotionFilesystem extends Local
{
    public string $canonicalRoot = '';
    public bool $failAfterMovingOneFile = false;
    public bool $createCompleteCollisionOnFinalRecheck = false;
    public int $movedFiles = 0;
    public int $renameCalls = 0;
    public ?string $stagingPath = null;
    public ?string $finalPath = null;
    public string $collisionMetadata = '';
    public string $collisionRedirects = '';
    /** @var list<string> */
    public array $operationPaths = [];
    private int $finalChecks = 0;

    public function directoryExists(string $path): bool
    {
        $this->record($path);
        if ($this->isFinalBackupPath($path)) {
            $this->finalPath = $path;
            $this->finalChecks++;
            if ($this->createCompleteCollisionOnFinalRecheck && $this->finalChecks === 2) {
                $this->writeCompleteCollision($path);
            }
        }

        return parent::directoryExists($path);
    }

    public function createDirectory(string $path, array $config = []): void
    {
        $this->record($path);
        if (str_contains($path, '.staging-')) {
            $this->stagingPath = $path;
        }
        parent::createDirectory($path, $config);
    }

    public function deleteDirectory(string $path): void
    {
        $this->record($path);
        parent::deleteDirectory($path);
    }

    public function renameDirectory(string $path, string $newName): void
    {
        $this->record($path);
        $this->renameCalls++;
        $this->stagingPath = $path;
        $this->finalPath = dirname($path) . '/' . $newName;
        if (!$this->failAfterMovingOneFile) {
            parent::renameDirectory($path, $newName);
            return;
        }

        parent::createDirectory($this->finalPath);
        $metadata = parent::read($path . '/metadata.json');
        parent::write($this->finalPath . '/metadata.json', $metadata);
        parent::deleteFile($path . '/metadata.json');
        $this->movedFiles++;
        throw new RuntimeException('Injected remote failure after one promoted file.');
    }

    public function write(string $path, string $contents, array $config = []): void
    {
        $this->record($path);
        parent::write($path, $contents, $config);
    }

    public function read(string $path): string
    {
        $this->record($path);
        return parent::read($path);
    }

    public function fileExists(string $path): bool
    {
        $this->record($path);
        return parent::fileExists($path);
    }

    private function isFinalBackupPath(string $path): bool
    {
        return preg_match('#^' . preg_quote($this->canonicalRoot, '#') . '/manual/\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}-[a-f0-9]{12}$#', $path) === 1;
    }

    private function writeCompleteCollision(string $path): void
    {
        $this->collisionRedirects = Json::encode([['sourceUrl' => '/foreign-complete-backup']], JSON_PRETTY_PRINT);
        $this->collisionMetadata = Json::encode([
            'date' => basename($path),
            'timestamp' => 1_755_604_800,
            'reason' => 'manual',
            'redirectCount' => 1,
            'checksum' => hash('sha256', $this->collisionRedirects),
            'checksumAlgorithm' => 'sha256',
        ], JSON_PRETTY_PRINT);
        parent::createDirectory($path);
        parent::write($path . '/metadata.json', $this->collisionMetadata);
        parent::write($path . '/redirects.json', $this->collisionRedirects);
    }

    private function record(string $path): void
    {
        $this->operationPaths[] = $path;
    }
}
