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
use craft\console\User;
use craft\fs\Local;
use craft\fs\MissingFs;
use craft\models\Volume;
use craft\services\Config;
use craft\services\Volumes;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\redirectmanager\controllers\ImportExportController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\BackupService;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use yii\base\UserException;
use yii\web\ForbiddenHttpException;

/**
 * Pins the effective backup-storage decision independently of storage health.
 *
 * @since 5.41.0
 */
final class BackupStorageResolutionTest extends TestCase
{
    private const UNAVAILABLE = 'The configured backup volume cannot currently be used. Backup operations are unavailable until the volume is restored or the effective setting is changed.';

    private string $localRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEmptyPluginConfig();
        $storage = Craft::getAlias('@storage');
        self::assertIsString($storage);
        $this->localRoot = $storage . '/backup-resolution';
        \craft\helpers\FileHelper::createDirectory($this->localRoot);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupPath = '@storage/backup-resolution';
        $this->settings()->backupVolumeUid = null;
        $this->settings()->backupRetentionDays = 0;
    }

    public function testNoConfiguredVolumeUsesTheExplicitLocalPath(): void
    {
        $volumes = $this->createMock(Volumes::class);
        $volumes->expects(self::never())->method('getVolumeByUid');
        Craft::$app->set('volumes', $volumes);

        self::assertFalse($this->backup()->isUsingVolumeStorage());
        self::assertSame($this->localRoot, $this->backup()->getBackupRoot());
    }

    public function testBackupListingStillRequiresABackupPermission(): void
    {
        $this->installJsonRequest();
        $user = $this->createMock(User::class);
        $user->method('checkPermission')->willReturn(false);
        Craft::$app->set('user', $user);
        $controller = new PermissionBackupController('import-export', RedirectManager::getInstance());

        $this->expectException(ForbiddenHttpException::class);
        $controller->actionGetBackups();
    }

    public function testSpecificBackupPermissionStillAllowsBackupListing(): void
    {
        $this->installJsonRequest();
        $user = $this->createMock(User::class);
        $user->method('checkPermission')->willReturnCallback(
            static fn(string $permission): bool => $permission === 'redirectManager:downloadBackups',
        );
        Craft::$app->set('user', $user);
        $controller = new PermissionBackupController('import-export', RedirectManager::getInstance());

        $response = $controller->actionGetBackups();

        self::assertIsArray($response->data);
        self::assertTrue($response->data['success']);
        self::assertSame([], $response->data['backups']);
    }

    public function testValidConfiguredVolumeUsesItsUnderlyingFilesystem(): void
    {
        $volumeRoot = $this->createTrackedTempDirectory('redirect-backup-volume-');
        $this->settings()->backupVolumeUid = 'backup-volume';
        $this->installVolume($this->volume($this->localFilesystem($volumeRoot)));
        $this->seedRedirect();

        $result = $this->backup()->createBackup('manual');

        self::assertIsString($result);
        self::assertMatchesRegularExpression('#^manual/\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}-[a-f0-9]{12}$#', $result);
        self::assertDirectoryExists($volumeRoot . '/redirect-manager/backups/' . $result);
        self::assertDirectoryDoesNotExist($this->localRoot . '/manual');
    }

    public function testMissingVolumeUidFailsClosedWithoutCreatingALocalFallback(): void
    {
        $this->settings()->backupVolumeUid = 'deleted-volume';
        $this->installVolume(null);
        $this->seedRedirect();

        try {
            $this->backup()->createBackup('manual');
            self::fail('Expected unavailable storage failure.');
        } catch (UserException $exception) {
            self::assertSame(self::UNAVAILABLE, $exception->getMessage());
        }

        self::assertSame([], $this->visibleEntries($this->localRoot));
        self::assertSame('deleted-volume', $this->settings()->backupVolumeUid);
    }

    public function testValidationInvalidVolumeFailsClosed(): void
    {
        $webroot = Craft::getAlias('@webroot');
        self::assertIsString($webroot);
        $this->settings()->backupVolumeUid = 'invalid-volume';
        $this->installVolume($this->volume($this->localFilesystem($webroot . '/redirect-manager-invalid-volume')));

        $this->expectException(UserException::class);
        $this->expectExceptionMessage(self::UNAVAILABLE);
        $this->backup()->isUsingVolumeStorage();
    }

    public function testMissingFilesystemComponentFailsClosed(): void
    {
        $this->settings()->backupVolumeUid = 'missing-filesystem';
        $this->installVolume($this->volume(new MissingFs(['handle' => 'missing-filesystem'])));

        $this->expectException(UserException::class);
        $this->expectExceptionMessage(self::UNAVAILABLE);
        $this->backup()->isUsingVolumeStorage();
    }

    public function testThrowingFilesystemResolutionContainsEveryThrowable(): void
    {
        $this->settings()->backupVolumeUid = 'throwing-volume';
        $volume = $this->createMock(Volume::class);
        $volume->method('getFs')->willThrowException(new \Error('resolution failure'));
        $this->installVolume($volume);

        $this->expectException(UserException::class);
        $this->expectExceptionMessage(self::UNAVAILABLE);
        $this->backup()->isUsingVolumeStorage();
    }

    public function testOperationalFilesystemFailureFailsTheRequestedAction(): void
    {
        $this->settings()->backupVolumeUid = 'read-only-volume';
        $fs = $this->createMock(FsInterface::class);
        $fs->method('directoryExists')->willThrowException(new RuntimeException('read-only storage'));
        $this->installVolume($this->volume($fs));

        $this->expectException(UserException::class);
        $this->expectExceptionMessage(self::UNAVAILABLE);
        $this->backup()->getBackups();
    }

    public function testConfigOverriddenUidControlsTheOperationalDecision(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getConfigFromFile')->willReturnCallback(
            static fn(string $handle): array => $handle === 'redirect-manager'
                ? ['backupVolumeUid' => 'configured-missing-volume']
                : [],
        );
        Craft::$app->set('config', $config);
        PluginHelper::applyConfigOverridesToSettings($this->settings(), 'redirect-manager');
        $this->installVolume(null);

        self::assertSame('configured-missing-volume', $this->settings()->backupVolumeUid);
        $this->expectException(UserException::class);
        $this->backup()->getBackups();
    }

    public function testSameUidRecoversWhenTheVolumeLaterResolves(): void
    {
        $this->settings()->backupVolumeUid = 'recovering-volume';
        $volumeRoot = $this->createTrackedTempDirectory('redirect-backup-recovery-');
        $volume = $this->volume($this->localFilesystem($volumeRoot));
        $this->installVolumeSequence(null, $volume, $volume);

        try {
            $this->backup()->isUsingVolumeStorage();
            self::fail('Expected first resolution to fail.');
        } catch (UserException) {
            self::assertSame('recovering-volume', $this->settings()->backupVolumeUid);
        }

        self::assertTrue($this->backup()->isUsingVolumeStorage());
    }

    public function testAllCreationReasonsShareTheFailClosedDecision(): void
    {
        $this->settings()->backupVolumeUid = 'unavailable-volume';
        $this->seedRedirect();

        foreach (['manual', 'scheduled', 'console', 'import', 'restore', 'maintenance'] as $reason) {
            $this->installVolume(null);
            try {
                $this->backup()->createBackup($reason);
                self::fail("Expected {$reason} backup to fail closed.");
            } catch (UserException $exception) {
                self::assertSame(self::UNAVAILABLE, $exception->getMessage());
            }
        }

        self::assertSame([], $this->visibleEntries($this->localRoot));
    }

    public function testListDownloadRestoreDeleteAndRetentionSeamsNeverResolveLocally(): void
    {
        $this->settings()->backupVolumeUid = 'unavailable-volume';
        $this->settings()->backupRetentionDays = 30;

        foreach (['list', 'download', 'restore', 'delete', 'retention'] as $operation) {
            $this->installVolume(null);
            try {
                match ($operation) {
                    'list' => $this->backup()->getBackups(),
                    'download' => $this->backup()->readVolumeBackupFile('manual/2026-08-19_12-00-00', 'metadata.json'),
                    'restore' => $this->backup()->validateVolumeBackupName('manual/2026-08-19_12-00-00'),
                    'delete' => $this->backup()->deleteVolumeBackup('manual/2026-08-19_12-00-00'),
                    'retention' => $this->backup()->cleanupOldBackups(),
                };
                self::fail("Expected {$operation} to fail closed.");
            } catch (UserException $exception) {
                self::assertSame(self::UNAVAILABLE, $exception->getMessage());
            }
        }

        self::assertSame([], $this->visibleEntries($this->localRoot));
    }

    public function testSettingsPathResolutionDoesNotReinterpretAnInvalidVolumeAsStorage(): void
    {
        $this->settings()->backupVolumeUid = 'missing-volume';
        $this->installVolume(null);

        $this->expectException(UserException::class);
        $this->expectExceptionMessage(self::UNAVAILABLE);
        $this->settings()->getBackupPath();
    }

    private function backup(): BackupService
    {
        return RedirectManager::getInstance()->backup;
    }

    private function localFilesystem(string $root): Local
    {
        return new Local([
            'name' => 'Redirect backup test filesystem',
            'handle' => 'redirectBackupTest',
            'path' => $root,
        ]);
    }

    private function volume(FsInterface $fs): Volume
    {
        $volume = $this->createMock(Volume::class);
        $volume->method('getFs')->willReturn($fs);
        return $volume;
    }

    private function installVolume(?Volume $volume): void
    {
        $this->installVolumeSequence($volume);
    }

    private function installVolumeSequence(?Volume ...$sequence): void
    {
        /** @var Volumes&MockObject $volumes */
        $volumes = $this->createMock(Volumes::class);
        $index = 0;
        $volumes->method('getVolumeByUid')->willReturnCallback(
            static function(string $uid) use (&$index, $sequence): ?Volume {
                $position = min($index, count($sequence) - 1);
                $index++;
                return $sequence[$position];
            },
        );
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

    private function installJsonRequest(): void
    {
        Craft::$app->set('request', new class() extends \craft\web\Request {
            public function getAcceptsJson(): bool
            {
                return true;
            }
        });
    }

    /** @return list<string> */
    private function visibleEntries(string $directory): array
    {
        $entries = array_values(array_filter(
            scandir($directory) ?: [],
            static fn(string $entry): bool => !in_array($entry, ['.', '..'], true),
        ));
        sort($entries);
        return $entries;
    }
}

/** Web-response seam for controller permission checks in the console fixture. */
final class PermissionBackupController extends ImportExportController
{
    public function asJson($data)
    {
        $response = new \yii\web\Response();
        $response->format = \yii\web\Response::FORMAT_JSON;
        $response->data = $data;
        return $response;
    }
}
