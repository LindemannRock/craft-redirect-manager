<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\redirectmanager\controllers\ImportExportController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\BackupService;
use lindemannrock\redirectmanager\tests\TestCase;
use RuntimeException;
use yii\base\UserException;
use yii\web\Response;

/**
 * Pins one-request ZIP ownership and exact response/interruption cleanup.
 *
 * @since 5.41.0
 */
final class BackupDownloadCleanupTest extends TestCase
{
    private string $archiveRoot;
    private DownloadLifecycleController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->archiveRoot = $this->createTrackedTempDirectory('redirect-download-cleanup-');
        $this->controller = new DownloadLifecycleController(
            'import-export',
            RedirectManager::getInstance(),
            $this->archiveRoot,
        );
    }

    public function testLocalDownloadCreatesACompleteArchiveUntilResponseCompletion(): void
    {
        $response = $this->controller->prepareArchive('local metadata', 'local redirects', 'local.zip');
        $path = $this->controller->lastOwnedPath();

        self::assertFileExists($path);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));
        self::assertSame('local metadata', $zip->getFromName('metadata.json'));
        self::assertSame('local redirects', $zip->getFromName('redirects.json'));
        self::assertTrue($zip->close());

        $response->trigger(Response::EVENT_AFTER_SEND);
        self::assertFileDoesNotExist($path);
    }

    public function testVolumeDownloadUsesBothRequiredMembersAndCleansExactly(): void
    {
        $backup = new DownloadMemberBackupService();
        $backup->members = [
            'metadata.json' => 'volume metadata',
            'redirects.json' => 'volume redirects',
        ];
        [$metadata, $redirects] = $this->controller->readMembers($backup, true, null, 'manual/2026-08-19_12-00-00');

        $response = $this->controller->prepareArchive($metadata, $redirects, 'volume.zip');
        $path = $this->controller->lastOwnedPath();
        self::assertFileExists($path);
        $response->trigger(Response::EVENT_AFTER_SEND);
        self::assertFileDoesNotExist($path);
    }

    public function testMissingLocalMetadataOrRedirectsNeverCreatesAnArchive(): void
    {
        $backupDir = $this->createTrackedTempDirectory('redirect-download-members-');
        file_put_contents($backupDir . '/metadata.json', '{}');

        $this->expectException(UserException::class);
        try {
            $this->controller->readMembers(new BackupService(), false, $backupDir, null);
        } finally {
            self::assertSame([], $this->controller->ownedPaths);
        }
    }

    public function testVolumeReadFailurePropagatesBeforeArchiveOwnership(): void
    {
        $backup = new DownloadMemberBackupService();
        $backup->failure = new RuntimeException('volume read failure');

        $this->expectException(RuntimeException::class);
        try {
            $this->controller->readMembers($backup, true, null, 'manual/2026-08-19_12-00-00');
        } finally {
            self::assertSame([], $this->controller->ownedPaths);
        }
    }

    public function testMissingVolumeMemberNeverCreatesAnArchive(): void
    {
        $backup = new DownloadMemberBackupService();
        $backup->members = ['metadata.json' => '{}'];

        $this->expectException(UserException::class);
        try {
            $this->controller->readMembers($backup, true, null, 'manual/2026-08-19_12-00-00');
        } finally {
            self::assertSame([], $this->controller->ownedPaths);
        }
    }

    public function testZipOpenFailureRemovesOnlyItsOwnedTemporaryFile(): void
    {
        $this->controller->failOpen = true;
        $unrelated = $this->archiveRoot . '/unrelated.zip';
        file_put_contents($unrelated, 'preserve');

        $this->expectException(RuntimeException::class);
        try {
            $this->controller->prepareArchive('metadata', 'redirects', 'failure.zip');
        } finally {
            self::assertFileDoesNotExist($this->controller->lastOwnedPath());
            self::assertSame('preserve', file_get_contents($unrelated));
        }
    }

    public function testZipConstructionFailureRemovesTheAllocatedOwnedFile(): void
    {
        $this->controller->failCreate = true;

        $this->expectException(RuntimeException::class);
        try {
            $this->controller->prepareArchive('metadata', 'redirects', 'construction-failure.zip');
        } finally {
            self::assertFileDoesNotExist($this->controller->lastOwnedPath());
        }
    }

    public function testEachMemberAddFailureClosesAndCleansTheOwnedArchive(): void
    {
        foreach ([1, 2] as $memberCall) {
            $controller = new DownloadLifecycleController(
                'import-export',
                RedirectManager::getInstance(),
                $this->archiveRoot,
            );
            $controller->failMemberCall = $memberCall;

            try {
                $controller->prepareArchive('metadata', 'redirects', "member-{$memberCall}.zip");
                self::fail("Expected member {$memberCall} failure.");
            } catch (RuntimeException) {
                self::assertGreaterThanOrEqual(1, $controller->closeCalls);
                self::assertFileDoesNotExist($controller->lastOwnedPath());
            }
        }
    }

    public function testCloseFailureRetriesClosureAndRemovesTheExactArchive(): void
    {
        $this->controller->failClose = true;

        try {
            $this->controller->prepareArchive('metadata', 'redirects', 'close-failure.zip');
            self::fail('Expected close failure.');
        } catch (RuntimeException) {
            self::assertSame(2, $this->controller->closeCalls);
            self::assertFileDoesNotExist($this->controller->lastOwnedPath());
        }
    }

    public function testResponsePreparationFailureCleansAnAlreadyFinalizedArchive(): void
    {
        $this->controller->failResponse = true;

        $this->expectException(RuntimeException::class);
        try {
            $this->controller->prepareArchive('metadata', 'redirects', 'response-failure.zip');
        } finally {
            self::assertSame(1, $this->controller->closeCalls);
            self::assertFileDoesNotExist($this->controller->lastOwnedPath());
        }
    }

    public function testConcurrentSameTimestampDownloadsHaveUniqueIndependentOwnership(): void
    {
        $first = $this->controller->prepareArchive('manual metadata', 'manual redirects', 'manual-2026-08-19_12-00-00.zip');
        $firstPath = $this->controller->lastOwnedPath();
        $second = $this->controller->prepareArchive('scheduled metadata', 'scheduled redirects', 'scheduled-2026-08-19_12-00-00.zip');
        $secondPath = $this->controller->lastOwnedPath();

        self::assertNotSame($firstPath, $secondPath);
        self::assertFileExists($firstPath);
        self::assertFileExists($secondPath);
        $first->trigger(Response::EVENT_AFTER_SEND);
        self::assertFileDoesNotExist($firstPath);
        self::assertFileExists($secondPath);
        $second->trigger(Response::EVENT_AFTER_SEND);
        self::assertFileDoesNotExist($secondPath);
    }

    public function testInterruptionSafeguardRemovesOnlyTheCapturedOwnedFile(): void
    {
        $this->controller->prepareArchive('metadata', 'redirects', 'interrupted.zip');
        $owned = $this->controller->lastOwnedPath();
        $unrelated = $this->archiveRoot . '/other-request.zip';
        file_put_contents($unrelated, 'other request');

        $this->controller->runLastShutdownCleanup();

        self::assertFileDoesNotExist($owned);
        self::assertSame('other request', file_get_contents($unrelated));
    }
}

/** Controller seam exposing exact archive lifecycle injection points. */
final class DownloadLifecycleController extends ImportExportController
{
    public bool $failCreate = false;
    public bool $failOpen = false;
    public ?int $failMemberCall = null;
    public bool $failClose = false;
    public bool $failResponse = false;
    public int $closeCalls = 0;
    /** @var list<string> */
    public array $ownedPaths = [];
    /** @var list<callable(): void> */
    private array $shutdownCleanups = [];
    private int $memberCalls = 0;

    public function __construct(string $id, \yii\base\Module $module, private readonly string $archiveRoot, array $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    public function prepareArchive(string $metadata, string $redirects, string $filename): Response
    {
        return $this->prepareOwnedBackupDownload($metadata, $redirects, $filename);
    }

    /** @return array{string, string} */
    public function readMembers(
        BackupService $backup,
        bool $volume,
        ?string $directory,
        ?string $name,
    ): array {
        return $this->readBackupDownloadMembers($backup, $volume, $directory, $name);
    }

    public function lastOwnedPath(): string
    {
        $path = end($this->ownedPaths);
        if (!is_string($path)) {
            throw new RuntimeException('No owned download path was recorded.');
        }
        return $path;
    }

    public function runLastShutdownCleanup(): void
    {
        $cleanup = end($this->shutdownCleanups);
        if (!is_callable($cleanup)) {
            throw new RuntimeException('No shutdown cleanup was recorded.');
        }
        $cleanup();
    }

    protected function createOwnedBackupZipPath(): string
    {
        $path = tempnam($this->archiveRoot, 'owned-download-');
        if (!is_string($path)) {
            throw new RuntimeException('Unable to create test-owned archive.');
        }
        $this->ownedPaths[] = $path;
        return $path;
    }

    protected function createBackupZip(): \ZipArchive
    {
        if ($this->failCreate) {
            throw new RuntimeException('Injected ZIP construction failure.');
        }
        return parent::createBackupZip();
    }

    protected function openBackupZip(\ZipArchive $zip, string $path): bool
    {
        return !$this->failOpen && parent::openBackupZip($zip, $path);
    }

    protected function addBackupZipMember(\ZipArchive $zip, string $name, string $contents): bool
    {
        $this->memberCalls++;
        return $this->failMemberCall !== $this->memberCalls
            && parent::addBackupZipMember($zip, $name, $contents);
    }

    protected function closeBackupZip(\ZipArchive $zip): bool
    {
        $this->closeCalls++;
        if ($this->failClose && $this->closeCalls === 1) {
            return false;
        }
        return parent::closeBackupZip($zip);
    }

    protected function prepareBackupDownloadResponse(string $path, string $filename): Response
    {
        if ($this->failResponse) {
            throw new RuntimeException('Injected response preparation failure.');
        }

        $response = new Response();
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        return $response;
    }

    protected function registerBackupDownloadShutdown(callable $cleanup): void
    {
        $this->shutdownCleanups[] = $cleanup;
    }
}

/** Volume-member seam for read success, absence, and operational failure. */
final class DownloadMemberBackupService extends BackupService
{
    /** @var array<string, string> */
    public array $members = [];
    public ?\Throwable $failure = null;

    public function readVolumeBackupFile(string $backupName, string $filename): ?string
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        return $this->members[$filename] ?? null;
    }
}
