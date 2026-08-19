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
use craft\db\Query;
use craft\fs\Local;
use craft\helpers\Json;
use craft\models\Volume;
use craft\services\Config;
use craft\services\Volumes;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\redirectmanager\controllers\ImportExportController;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\BackupService;
use lindemannrock\redirectmanager\services\RedirectsService;
use lindemannrock\redirectmanager\tests\TestCase;
use RuntimeException;
use yii\base\UserException;

/**
 * Pins target-first validation and mandatory recoverable pre-restore state.
 *
 * @since 5.41.0
 */
final class BackupRestoreSafetyTest extends TestCase
{
    private const BLOCKED = 'Restore was stopped because a safety backup of the current redirects could not be completed. The backup could not be completed. Check the configured backup storage and permissions, then try again.';

    private TestableBackupController $controller;
    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $original = Craft::$app->getConfig();
        $config = $this->createMock(Config::class);
        $config->method('getGeneral')->willReturn($original->getGeneral());
        $config->method('getConfigFromFile')->willReturn([]);
        Craft::$app->set('config', $config);
        $this->controller = new TestableBackupController('import-export', RedirectManager::getInstance());
        $storage = Craft::getAlias('@storage');
        self::assertIsString($storage);
        $this->backupRoot = $storage . '/restore-safety';
        \craft\helpers\FileHelper::createDirectory($this->backupRoot);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupVolumeUid = null;
        $this->settings()->backupPath = '@storage/restore-safety';
        $this->settings()->backupRetentionDays = 0;
    }

    public function testTargetChecksumFailureOccursBeforeSafetyBackupOrMutation(): void
    {
        $existing = $this->seedRedirect();
        $backup = new RecordingRestoreBackupService();

        try {
            $this->controller->prepareRestore($backup, $this->metadata('wrong'), $this->redirectContent(), 'manual/2026-08-19_12-00-00');
            self::fail('Expected checksum failure.');
        } catch (UserException $exception) {
            self::assertStringContainsString('integrity check failed', $exception->getMessage());
        }

        self::assertSame(0, $backup->createCalls);
        self::assertNotNull($this->redirectRow((int)$existing->id));
    }

    public function testCurrentRedirectsRequireACompleteSafetyBackup(): void
    {
        $this->seedRedirect();
        $backup = new RecordingRestoreBackupService();
        $backup->result = '/owned/maintenance/backup';

        $redirects = $this->controller->prepareRestore(
            $backup,
            $this->metadata(hash('sha256', $this->redirectContent())),
            $this->redirectContent(),
            'manual/2026-08-19_12-00-00',
        );

        self::assertCount(1, $redirects);
        self::assertSame(1, $backup->createCalls);
        self::assertSame(['restore'], $backup->reasons);
    }

    public function testSafetyBackupFailureLeavesEveryCurrentRedirectUnchanged(): void
    {
        $first = $this->seedRedirect();
        $second = $this->seedRedirect();
        $before = $this->redirectFingerprint();
        $backup = new RecordingRestoreBackupService();
        $backup->failure = new RuntimeException('storage failure');

        try {
            $this->controller->prepareRestore(
                $backup,
                $this->metadata(hash('sha256', $this->redirectContent())),
                $this->redirectContent(),
                'manual/2026-08-19_12-00-00',
            );
            self::fail('Expected restore safety failure.');
        } catch (UserException $exception) {
            self::assertSame(self::BLOCKED, $exception->getMessage());
        }

        self::assertSame($before, $this->redirectFingerprint());
        self::assertNotNull($this->redirectRow((int)$first->id));
        self::assertNotNull($this->redirectRow((int)$second->id));
    }

    public function testEmptyCurrentStateNeedsNoSafetyArtifact(): void
    {
        $backup = new RecordingRestoreBackupService();
        $backup->failure = new RuntimeException('must not be called');

        $redirects = $this->controller->prepareRestore(
            $backup,
            $this->metadata(hash('sha256', $this->redirectContent())),
            $this->redirectContent(),
            'manual/2026-08-19_12-00-00',
        );

        self::assertCount(1, $redirects);
        self::assertSame(0, $backup->createCalls);
    }

    public function testSuccessfulReplacementRetainsTransactionAndCacheInvalidation(): void
    {
        $existing = $this->seedRedirect();
        $row = $this->redirectRow((int)$existing->id);
        self::assertIsArray($row);
        $row['sourceUrl'] = '/restored-source';
        $row['sourceUrlParsed'] = '/restored-source';
        $row['destinationUrl'] = '/restored-destination';
        $redirects = new RecordingRedirectsService();
        $this->replacePluginComponent('redirects', $redirects);

        $count = $this->controller->replaceRows([$row]);

        self::assertSame(1, $count);
        self::assertSame(1, $redirects->invalidateCalls);
        self::assertNull($this->redirectRow((int)$existing->id));
        self::assertSame('/restored-destination', (new Query())
            ->select(['destinationUrl'])
            ->from(RedirectRecord::tableName())
            ->where(['sourceUrl' => '/restored-source'])
            ->scalar());
    }

    public function testReplacementFailureRollsBackAndDoesNotInvalidateCaches(): void
    {
        $existing = $this->seedRedirect();
        $before = $this->redirectFingerprint();
        $redirects = new RecordingRedirectsService();
        $this->replacePluginComponent('redirects', $redirects);

        try {
            $this->controller->replaceRows([['sourceUrl' => null]]);
            self::fail('Expected transactional replacement failure.');
        } catch (\Throwable) {
            self::assertSame($before, $this->redirectFingerprint());
            self::assertNotNull($this->redirectRow((int)$existing->id));
            self::assertSame(0, $redirects->invalidateCalls);
        }
    }

    public function testLocalAndVolumeSafetyBackupsBothCompleteBeforeRestore(): void
    {
        foreach (['local', 'volume'] as $storage) {
            $this->seedRedirect(['sourceUrl' => '/restore-' . $storage]);
            if ($storage === 'volume') {
                $volumeRoot = $this->createTrackedTempDirectory('redirect-restore-volume-');
                $this->settings()->backupVolumeUid = 'restore-volume';
                $this->installVolume($this->volume(new Local([
                    'name' => 'Restore safety filesystem',
                    'handle' => 'restoreSafetyFilesystem',
                    'path' => $volumeRoot,
                ])));
            } else {
                $this->settings()->backupVolumeUid = null;
            }

            $this->controller->prepareRestore(
                RedirectManager::getInstance()->backup,
                $this->metadata(hash('sha256', $this->redirectContent())),
                $this->redirectContent(),
                'manual/2026-08-19_12-00-00',
            );

            $backups = RedirectManager::getInstance()->backup->getBackups();
            self::assertNotEmpty($backups);
            self::assertSame('restore', $backups[0]['reason']);
        }
    }

    public function testConfigOverriddenUnavailableVolumeBlocksRestoreWithoutLocalFallback(): void
    {
        $this->seedRedirect();
        $config = $this->createMock(Config::class);
        $config->method('getConfigFromFile')->willReturnCallback(
            static fn(string $handle): array => $handle === 'redirect-manager'
                ? ['backupVolumeUid' => 'missing-restore-volume']
                : [],
        );
        Craft::$app->set('config', $config);
        PluginHelper::applyConfigOverridesToSettings($this->settings(), 'redirect-manager');
        $volumes = $this->createMock(Volumes::class);
        $volumes->method('getVolumeByUid')->willReturn(null);
        Craft::$app->set('volumes', $volumes);

        try {
            $this->controller->prepareRestore(
                RedirectManager::getInstance()->backup,
                $this->metadata(hash('sha256', $this->redirectContent())),
                $this->redirectContent(),
                'manual/2026-08-19_12-00-00',
            );
            self::fail('Expected unavailable safety backup to block restore.');
        } catch (UserException $exception) {
            self::assertSame(self::BLOCKED, $exception->getMessage());
        }

        self::assertSame([], array_values(array_filter(
            scandir($this->backupRoot) ?: [],
            static fn(string $entry): bool => !in_array($entry, ['.', '..'], true),
        )));
    }

    private function redirectContent(): string
    {
        return Json::encode([[
            'sourceUrl' => '/from-backup',
            'sourceUrlParsed' => '/from-backup',
            'destinationUrl' => '/restored',
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
            'redirectSrcMatch' => 'pathonly',
            'matchType' => 'exact',
            'statusCode' => 301,
            'priority' => 0,
            'enabled' => true,
            'creationType' => 'manual',
            'sourcePlugin' => 'redirect-manager',
            'hitCount' => 0,
        ]], JSON_PRETTY_PRINT);
    }

    private function metadata(string $checksum): string
    {
        return Json::encode([
            'checksum' => $checksum,
            'checksumAlgorithm' => 'sha256',
        ], JSON_PRETTY_PRINT);
    }

    /** @return array<string, mixed>|null */
    private function redirectRow(int $id): ?array
    {
        $row = (new Query())->from(RedirectRecord::tableName())->where(['id' => $id])->one();
        return is_array($row) ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function redirectFingerprint(): array
    {
        return (new Query())->from(RedirectRecord::tableName())->orderBy(['id' => SORT_ASC])->all();
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
}

/** Controller seam exposing only the restore lifecycle under test. */
final class TestableBackupController extends ImportExportController
{
    /** @return array<int, array<string, mixed>> */
    public function prepareRestore(BackupService $backup, string $metadata, string $redirects, string $name): array
    {
        return $this->validateRestoreTargetAndRequireSafetyBackup($backup, $metadata, $redirects, $name);
    }

    /** @param array<int, array<string, mixed>> $redirects */
    public function replaceRows(array $redirects): int
    {
        return $this->replaceRedirectsFromBackup($redirects);
    }
}

/** Records whether the restore lifecycle requested its mandatory snapshot. */
final class RecordingRestoreBackupService extends BackupService
{
    public int $createCalls = 0;
    public ?string $result = null;
    public ?\Throwable $failure = null;
    /** @var list<string> */
    public array $reasons = [];

    public function createBackup(string $reason = 'import'): ?string
    {
        $this->createCalls++;
        $this->reasons[] = $reason;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        return $this->result;
    }
}

/** Records cache invalidation after a committed restore transaction. */
final class RecordingRedirectsService extends RedirectsService
{
    public int $invalidateCalls = 0;

    public function invalidateCaches(): void
    {
        $this->invalidateCalls++;
    }
}
