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
use craft\console\User;
use craft\elements\User as UserIdentity;
use craft\fs\Local;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\models\Volume;
use craft\services\Config;
use craft\services\Volumes;
use lindemannrock\redirectmanager\controllers\ImportExportController;
use lindemannrock\redirectmanager\jobs\CreateBackupJob;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\BackupService;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins backup creator attribution at storage and presentation boundaries.
 *
 * @since 5.42.0
 */
final class BackupAttributionTest extends TestCase
{
    private const USER_ID = 4242;
    private const USERNAME = 'queue-browser@example.test';

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
        $this->backupRoot = $storage . '/backup-attribution';
        FileHelper::createDirectory($this->backupRoot);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupVolumeUid = null;
        $this->settings()->backupPath = '@storage/backup-attribution';
        $this->settings()->backupRetentionDays = 0;
        $this->seedRedirect();
    }

    public function testAuthenticatedQueueExecutionStoresScheduledBackupAsSystemLocally(): void
    {
        $this->installUser(self::USERNAME, self::USER_ID);

        (new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => false,
        ]))->execute(Craft::$app->getQueue());

        $backups = $this->backup()->getBackups();
        self::assertCount(1, $backups);
        $metadata = $this->decodeMetadata((string)file_get_contents($backups[0]['path'] . '/metadata.json'));
        self::assertSame('scheduled', $metadata['reason']);
        self::assertSame('system', $metadata['user']);
        self::assertNull($metadata['userId']);
    }

    public function testAuthenticatedQueueExecutionStoresScheduledBackupAsSystemOnAVolume(): void
    {
        $volumeRoot = $this->createTrackedTempDirectory('redirect-attribution-volume-');
        $filesystem = new Local([
            'name' => 'Backup attribution filesystem',
            'handle' => 'backupAttributionFilesystem',
            'path' => $volumeRoot,
        ]);
        $volume = new Volume([
            'name' => 'Backup attribution volume',
            'handle' => 'backupAttributionVolume',
            'uid' => 'backup-attribution-volume',
        ]);
        $volume->setFs($filesystem);
        $volumes = $this->createMock(Volumes::class);
        $volumes->method('getVolumeByUid')->willReturn($volume);
        Craft::$app->set('volumes', $volumes);
        $this->settings()->backupVolumeUid = 'backup-attribution-volume';
        $this->installUser(self::USERNAME, self::USER_ID);

        (new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => false,
        ]))->execute(Craft::$app->getQueue());

        $backups = $this->backup()->getBackups();
        self::assertCount(1, $backups);
        $metadataContent = $this->backup()->readVolumeBackupFile((string)$backups[0]['dirname'], 'metadata.json');
        self::assertIsString($metadataContent);
        $metadata = $this->decodeMetadata($metadataContent);
        self::assertSame('scheduled', $metadata['reason']);
        self::assertSame('system', $metadata['user']);
        self::assertNull($metadata['userId']);
    }

    #[DataProvider('userInitiatedReasonProvider')]
    public function testUserInitiatedBackupsPreserveTheirAuthenticatedCreator(string $reason): void
    {
        $this->installUser(self::USERNAME, self::USER_ID);

        $path = $this->backup()->createBackup($reason);

        self::assertIsString($path);
        $metadata = $this->decodeMetadata((string)file_get_contents($path . '/metadata.json'));
        self::assertSame($reason, $metadata['reason']);
        self::assertSame(self::USERNAME, $metadata['user']);
        self::assertSame(self::USER_ID, $metadata['userId']);
    }

    public function testAnonymousUserInitiatedBackupRetainsTheSystemFallback(): void
    {
        $this->installUser(null, null);

        $path = $this->backup()->createBackup('manual');

        self::assertIsString($path);
        $metadata = $this->decodeMetadata((string)file_get_contents($path . '/metadata.json'));
        self::assertSame('system', $metadata['user']);
        self::assertNull($metadata['userId']);
    }

    public function testHistoricalScheduledCreatorIsNormalizedForDisplayWithoutRewritingMetadata(): void
    {
        $name = 'scheduled/2026-08-30_09-18-27';
        $path = $this->backupRoot . '/' . $name;
        FileHelper::createDirectory($path);
        $redirects = Json::encode([['sourceUrl' => '/historical']], JSON_PRETTY_PRINT);
        $metadata = Json::encode([
            'date' => '2026-08-30_09-18-27',
            'timestamp' => 1_788_070_707,
            'reason' => 'scheduled',
            'user' => self::USERNAME,
            'userId' => self::USER_ID,
            'redirectCount' => 1,
            'checksum' => hash('sha256', $redirects),
            'checksumAlgorithm' => 'sha256',
        ], JSON_PRETTY_PRINT);
        file_put_contents($path . '/metadata.json', $metadata);
        file_put_contents($path . '/redirects.json', $redirects);
        $metadataHash = hash_file('sha256', $path . '/metadata.json');
        Craft::$app->set('request', new class() extends \craft\web\Request {
            public function getAcceptsJson(): bool
            {
                return true;
            }
        });
        $this->installUser(self::USERNAME, self::USER_ID);
        $controller = new AttributionBackupController('import-export', RedirectManager::getInstance());

        $response = $controller->actionGetBackups();

        self::assertIsArray($response->data);
        self::assertTrue($response->data['success']);
        self::assertCount(1, $response->data['backups']);
        self::assertSame(Craft::t('redirect-manager', 'System'), $response->data['backups'][0]['user']);
        self::assertNull($response->data['backups'][0]['userId']);
        self::assertSame($metadataHash, hash_file('sha256', $path . '/metadata.json'));
        self::assertSame($metadata, file_get_contents($path . '/metadata.json'));
    }

    /** @return iterable<string, array{string}> */
    public static function userInitiatedReasonProvider(): iterable
    {
        yield 'manual' => ['manual'];
        yield 'import' => ['import'];
        yield 'maintenance' => ['maintenance'];
    }

    private function backup(): BackupService
    {
        return RedirectManager::getInstance()->backup;
    }

    private function installUser(?string $username, ?int $id): void
    {
        $identity = null;
        if ($username !== null && $id !== null) {
            $identity = new UserIdentity();
            $identity->username = $username;
            $identity->id = $id;
        }

        $user = $this->createMock(User::class);
        $user->method('getIdentity')->willReturn($identity);
        $user->method('checkPermission')->willReturn(true);
        Craft::$app->set('user', $user);
    }

    /** @return array<string, mixed> */
    private function decodeMetadata(string $content): array
    {
        $metadata = Json::decode($content);
        self::assertIsArray($metadata);
        return $metadata;
    }
}

/** Web-response seam for historical attribution output in the console fixture. */
final class AttributionBackupController extends ImportExportController
{
    public function asJson($data)
    {
        $response = new \yii\web\Response();
        $response->format = \yii\web\Response::FORMAT_JSON;
        $response->data = $data;
        return $response;
    }
}
