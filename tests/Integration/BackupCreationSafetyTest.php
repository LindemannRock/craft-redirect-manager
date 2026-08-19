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
use lindemannrock\redirectmanager\console\controllers\BackupController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\BackupService;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use yii\base\UserException;
use yii\console\ExitCode;

/**
 * Pins complete snapshot ownership, staging, validation, and promotion.
 *
 * @since 5.41.0
 */
final class BackupCreationSafetyTest extends TestCase
{
    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $original = Craft::$app->getConfig();
        $config = $this->createMock(Config::class);
        $config->method('getGeneral')->willReturn($original->getGeneral());
        $config->method('getConfigFromFile')->willReturn([]);
        Craft::$app->set('config', $config);
        $storage = Craft::getAlias('@storage');
        self::assertIsString($storage);
        $this->backupRoot = $storage . '/backup-creation';
        \craft\helpers\FileHelper::createDirectory($this->backupRoot);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupVolumeUid = null;
        $this->settings()->backupPath = '@storage/backup-creation';
        $this->settings()->backupRetentionDays = 0;
    }

    public function testEmptyRedirectLibraryIsASuccessfulNoOpWithoutAnArtifact(): void
    {
        self::assertNull($this->backup()->createBackup('manual'));
        self::assertSame([], $this->visibleEntries($this->backupRoot));
    }

    public function testConsoleReportsAnEmptyLibraryAsASuccessfulNoOp(): void
    {
        $controller = new RecordingBackupConsoleController('backup', RedirectManager::getInstance());

        self::assertSame(ExitCode::OK, $controller->actionCreate());
        self::assertStringContainsString('No redirects found to back up.', implode('', $controller->stdout));
        self::assertSame([], $controller->stderr);
        self::assertSame([], $this->visibleEntries($this->backupRoot));
    }

    public function testConsoleReportsUnavailableStorageWithoutALocalFallback(): void
    {
        $this->settings()->backupVolumeUid = 'missing-console-volume';
        $volumes = $this->createMock(Volumes::class);
        $volumes->method('getVolumeByUid')->willReturn(null);
        Craft::$app->set('volumes', $volumes);
        $this->seedRedirect();
        $controller = new RecordingBackupConsoleController('backup', RedirectManager::getInstance());

        self::assertSame(ExitCode::UNSPECIFIED_ERROR, $controller->actionCreate());
        self::assertStringContainsString('configured backup volume cannot currently be used', implode('', $controller->stderr));
        self::assertSame([], $this->visibleEntries($this->backupRoot));
    }

    public function testSameSecondBackupsHaveDistinctFinalOwnership(): void
    {
        $this->seedRedirect();

        $first = $this->backup()->createBackup('manual');
        $second = $this->backup()->createBackup('manual');

        self::assertIsString($first);
        self::assertIsString($second);
        self::assertNotSame($first, $second);
        self::assertSame(dirname($first), dirname($second));
        self::assertMatchesRegularExpression('/-([a-f0-9]{12})$/', basename($first));
        self::assertMatchesRegularExpression('/-([a-f0-9]{12})$/', basename($second));
        self::assertDirectoryExists($first);
        self::assertDirectoryExists($second);
    }

    public function testLegacyTimestampOnlyBackupRemainsListableReadableAndDeletable(): void
    {
        $legacyName = 'manual/2026-08-19_12-00-00';
        $legacyPath = $this->writeBackupFixture($legacyName);

        $backups = $this->backup()->getBackups();

        self::assertCount(1, $backups);
        self::assertSame($legacyName, $backups[0]['dirname']);
        self::assertSame($legacyPath, $this->backup()->validateBackupDirname($legacyName));
        \craft\helpers\FileHelper::removeDirectory($legacyPath);
        self::assertDirectoryDoesNotExist($legacyPath);
    }

    public function testSuccessfulLocalBackupWritesExactValidatedMembersBeforeVisibility(): void
    {
        $service = new ControlledLocalBackupService();
        $service->inspectBeforePromotion = true;
        $this->replacePluginComponent('backup', $service);
        $this->seedRedirect();

        $path = $service->createBackup('manual');

        self::assertIsString($path);
        self::assertTrue($service->finalWasHiddenBeforePromotion);
        $metadata = file_get_contents($path . '/metadata.json');
        $redirects = file_get_contents($path . '/redirects.json');
        self::assertIsString($metadata);
        self::assertIsString($redirects);
        self::assertTrue($service->validateBackupIntegrity($metadata, $redirects, basename($path)));
        self::assertSame([], $this->stagingEntries(dirname($path)));
    }

    #[DataProvider('localWriteFailureProvider')]
    public function testIncompleteLocalWritesFailAndRemoveOnlyOwnedArtifacts(int $call, int|false $result): void
    {
        $service = new ControlledLocalBackupService();
        $service->failureWriteCall = $call;
        $service->failureWriteResult = $result;
        $this->replacePluginComponent('backup', $service);
        $this->seedRedirect();
        $ownerFile = $this->backupRoot . '/owner-file.txt';
        file_put_contents($ownerFile, 'preserve');

        try {
            $service->createBackup('manual');
            self::fail('Expected incomplete local write failure.');
        } catch (UserException $exception) {
            self::assertStringContainsString('could not be completed', $exception->getMessage());
        }

        self::assertFileExists($ownerFile);
        self::assertSame('preserve', file_get_contents($ownerFile));
        self::assertSame([], $this->stagingEntries($this->backupRoot . '/manual'));
        self::assertSame([], $this->ownedBackupEntries($this->backupRoot . '/manual'));
    }

    /** @return iterable<string, array{int, int|false}> */
    public static function localWriteFailureProvider(): iterable
    {
        yield 'metadata false' => [1, false];
        yield 'metadata zero bytes' => [1, 0];
        yield 'metadata partial bytes' => [1, 5];
        yield 'redirects false' => [2, false];
        yield 'redirects zero bytes' => [2, 0];
        yield 'redirects partial bytes' => [2, 5];
    }

    public function testDirectoryAndPromotionFailuresLeaveNoVisibleOrStagedBackup(): void
    {
        foreach (['directory', 'promotion'] as $failure) {
            $service = new ControlledLocalBackupService();
            $service->failStagingDirectory = $failure === 'directory';
            $service->failPromotion = $failure === 'promotion';
            $this->replacePluginComponent('backup', $service);
            $this->seedRedirect(['sourceUrl' => '/creation-' . $failure]);

            try {
                $service->createBackup('manual');
                self::fail("Expected {$failure} failure.");
            } catch (UserException) {
                self::assertSame([], $this->stagingEntries($this->backupRoot . '/manual'));
                self::assertSame([], $this->ownedBackupEntries($this->backupRoot . '/manual'));
            }
        }
    }

    public function testLocalPromotionCollisionNeverDeletesTheForeignFinalDirectory(): void
    {
        $service = new ControlledLocalBackupService();
        $service->failPromotion = true;
        $service->createForeignFinalOnPromotionFailure = true;
        $this->replacePluginComponent('backup', $service);
        $this->seedRedirect();

        try {
            $service->createBackup('manual');
            self::fail('Expected promotion collision failure.');
        } catch (UserException) {
            self::assertNotNull($service->foreignFinalPath);
            self::assertSame('foreign owner', file_get_contents($service->foreignFinalPath . '/owner.txt'));
            self::assertSame([], $this->stagingEntries($this->backupRoot . '/manual'));
        }
    }

    #[DataProvider('volumeWriteFailureProvider')]
    public function testVolumeMemberFailureRemovesOnlyItsOwnedStagingDirectory(int $failureCall): void
    {
        $volumeRoot = $this->createTrackedTempDirectory('redirect-backup-volume-write-');
        $fs = new ControlledVolumeFilesystem([
            'name' => 'Controlled backup filesystem',
            'handle' => 'controlledBackupFilesystem',
            'path' => $volumeRoot,
        ]);
        $fs->failureWriteCall = $failureCall;
        $this->settings()->backupVolumeUid = 'controlled-volume';
        $this->installVolume($this->volume($fs));
        $this->seedRedirect();
        $ownerFile = $volumeRoot . '/owner-file.txt';
        file_put_contents($ownerFile, 'preserve');

        try {
            $this->backup()->createBackup('manual');
            self::fail('Expected volume write failure.');
        } catch (UserException $exception) {
            self::assertStringContainsString('configured backup volume', $exception->getMessage());
        }

        self::assertSame('preserve', file_get_contents($ownerFile));
        self::assertSame([], $this->stagingEntries($volumeRoot . '/redirect-manager/backups/manual'));
        self::assertSame([], $this->ownedBackupEntries($volumeRoot . '/redirect-manager/backups/manual'));
    }

    /** @return iterable<string, array{int}> */
    public static function volumeWriteFailureProvider(): iterable
    {
        yield 'first member' => [1];
        yield 'second member' => [2];
    }

    public function testVolumePromotionFailureDoesNotExposeAPartialBackup(): void
    {
        $volumeRoot = $this->createTrackedTempDirectory('redirect-backup-volume-promotion-');
        $fs = new ControlledVolumeFilesystem([
            'name' => 'Controlled backup filesystem',
            'handle' => 'controlledBackupFilesystem',
            'path' => $volumeRoot,
        ]);
        $fs->failPromotion = true;
        $this->settings()->backupVolumeUid = 'controlled-volume';
        $this->installVolume($this->volume($fs));
        $this->seedRedirect();

        $this->expectException(UserException::class);
        try {
            $this->backup()->createBackup('manual');
        } finally {
            $parent = $volumeRoot . '/redirect-manager/backups/manual';
            self::assertSame([], $this->stagingEntries($parent));
            self::assertSame([], $this->ownedBackupEntries($parent));
        }
    }

    public function testVolumePromotionCollisionNeverDeletesTheForeignFinalDirectory(): void
    {
        $volumeRoot = $this->createTrackedTempDirectory('redirect-backup-volume-collision-');
        $fs = new ControlledVolumeFilesystem([
            'name' => 'Controlled backup filesystem',
            'handle' => 'controlledBackupFilesystem',
            'path' => $volumeRoot,
        ]);
        $fs->failPromotion = true;
        $fs->createForeignFinalOnPromotionFailure = true;
        $this->settings()->backupVolumeUid = 'controlled-volume';
        $this->installVolume($this->volume($fs));
        $this->seedRedirect();

        try {
            $this->backup()->createBackup('manual');
            self::fail('Expected volume promotion collision failure.');
        } catch (UserException) {
            self::assertNotNull($fs->foreignFinalPath);
            self::assertSame('foreign owner', file_get_contents($volumeRoot . '/' . $fs->foreignFinalPath . '/owner.txt'));
            self::assertSame([], $this->stagingEntries($volumeRoot . '/redirect-manager/backups/manual'));
        }
    }

    private function backup(): BackupService
    {
        return RedirectManager::getInstance()->backup;
    }

    private function writeBackupFixture(string $name): string
    {
        $path = $this->backupRoot . '/' . $name;
        \craft\helpers\FileHelper::createDirectory($path);
        $redirects = Json::encode([['sourceUrl' => '/legacy']], JSON_PRETTY_PRINT);
        $metadata = Json::encode([
            'date' => basename($name),
            'timestamp' => 1_755_604_800,
            'reason' => 'manual',
            'redirectCount' => 1,
            'checksum' => hash('sha256', $redirects),
            'checksumAlgorithm' => 'sha256',
        ], JSON_PRETTY_PRINT);
        file_put_contents($path . '/metadata.json', $metadata);
        file_put_contents($path . '/redirects.json', $redirects);
        return $path;
    }

    private function volume(FsInterface $fs): Volume
    {
        $volume = $this->createMock(Volume::class);
        $volume->method('getFs')->willReturn($fs);
        return $volume;
    }

    private function installVolume(Volume $volume): void
    {
        $volumes = $this->createMock(Volumes::class);
        $volumes->method('getVolumeByUid')->willReturn($volume);
        Craft::$app->set('volumes', $volumes);
    }

    /** @return list<string> */
    private function visibleEntries(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        return array_values(array_filter(
            scandir($directory) ?: [],
            static fn(string $entry): bool => !in_array($entry, ['.', '..'], true),
        ));
    }

    /** @return list<string> */
    private function stagingEntries(string $directory): array
    {
        return array_values(array_filter(
            $this->visibleEntries($directory),
            static fn(string $entry): bool => str_contains($entry, '.staging-'),
        ));
    }

    /** @return list<string> */
    private function ownedBackupEntries(string $directory): array
    {
        return array_values(array_filter(
            $this->visibleEntries($directory),
            static fn(string $entry): bool => preg_match('/^\d{4}-\d{2}-\d{2}_/', $entry) === 1,
        ));
    }
}

/** Local creation seam with exact injected outcomes. */
final class ControlledLocalBackupService extends BackupService
{
    public ?int $failureWriteCall = null;
    public int|false $failureWriteResult = false;
    public bool $failStagingDirectory = false;
    public bool $failPromotion = false;
    public bool $createForeignFinalOnPromotionFailure = false;
    public bool $inspectBeforePromotion = false;
    public bool $finalWasHiddenBeforePromotion = false;
    public ?string $foreignFinalPath = null;
    private int $writeCalls = 0;
    private int $directoryCalls = 0;

    protected function createLocalDirectory(string $path): void
    {
        $this->directoryCalls++;
        if ($this->failStagingDirectory && $this->directoryCalls === 2) {
            throw new RuntimeException('Injected staging directory failure.');
        }
        parent::createLocalDirectory($path);
    }

    protected function writeLocalFile(string $path, string $content): int|false
    {
        $this->writeCalls++;
        if ($this->failureWriteCall !== $this->writeCalls) {
            return parent::writeLocalFile($path, $content);
        }
        if ($this->failureWriteResult === false) {
            return false;
        }

        file_put_contents($path, substr($content, 0, $this->failureWriteResult));
        return $this->failureWriteResult;
    }

    protected function moveLocalDirectory(string $source, string $destination): bool
    {
        if ($this->inspectBeforePromotion) {
            $this->finalWasHiddenBeforePromotion = !is_dir($destination) && is_dir($source);
        }
        if ($this->failPromotion) {
            if ($this->createForeignFinalOnPromotionFailure) {
                \craft\helpers\FileHelper::createDirectory($destination);
                file_put_contents($destination . '/owner.txt', 'foreign owner');
                $this->foreignFinalPath = $destination;
            }
            return false;
        }
        return parent::moveLocalDirectory($source, $destination);
    }
}

/** Volume seam that fails one exact write or final promotion. */
final class ControlledVolumeFilesystem extends Local
{
    public ?int $failureWriteCall = null;
    public bool $failPromotion = false;
    public bool $createForeignFinalOnPromotionFailure = false;
    public ?string $foreignFinalPath = null;
    private int $writeCalls = 0;

    public function write(string $path, string $contents, array $config = []): void
    {
        $this->writeCalls++;
        if ($this->failureWriteCall === $this->writeCalls) {
            throw new RuntimeException('Injected volume write failure.');
        }
        parent::write($path, $contents, $config);
    }

    public function renameDirectory(string $path, string $newName): void
    {
        if ($this->failPromotion) {
            if ($this->createForeignFinalOnPromotionFailure) {
                $this->foreignFinalPath = dirname($path) . '/' . $newName;
                parent::createDirectory($this->foreignFinalPath);
                parent::write($this->foreignFinalPath . '/owner.txt', 'foreign owner');
            }
            throw new RuntimeException('Injected volume promotion failure.');
        }
        parent::renameDirectory($path, $newName);
    }
}

/** Console output seam for creation outcomes. */
final class RecordingBackupConsoleController extends BackupController
{
    /** @var list<string> */
    public array $stdout = [];
    /** @var list<string> */
    public array $stderr = [];

    public function stdout($string)
    {
        $this->stdout[] = (string)$string;
        return strlen((string)$string);
    }

    public function stderr($string)
    {
        $this->stderr[] = (string)$string;
        return strlen((string)$string);
    }
}
