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
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\queue\Queue;
use craft\services\Config;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\base\queue\DeferredQueueJob;
use lindemannrock\base\queue\PortableQueueScheduler;
use lindemannrock\redirectmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\redirectmanager\jobs\CreateBackupJob;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\BackupService;
use lindemannrock\redirectmanager\services\ScheduledBackupScheduler;
use lindemannrock\redirectmanager\tests\Support\InstalledBasePackage;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionProperty;
use yii\log\Logger;
use yii\mutex\Mutex;
use yii\queue\sqs\Queue as SqsQueue;

/**
 * Pins Redirect Manager's portable scheduled-backup queue lifecycle.
 *
 * @since 5.41.0
 */
final class ScheduledBackupQueueTest extends TestCase
{
    private const START_TIMESTAMP = 1_800_000_000;

    private ?RecordingBackupSqsQueue $proxyQueue = null;
    private bool $timePaused = false;
    private ?int $originalLogFlushInterval = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLogFlushInterval = Craft::getLogger()->flushInterval;
        Craft::getLogger()->flushInterval = PHP_INT_MAX;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->timePaused) {
                DateTimeHelper::resume();
                $this->timePaused = false;
            }
        } finally {
            if ($this->originalLogFlushInterval !== null) {
                Craft::getLogger()->flushInterval = $this->originalLogFlushInterval;
            }
            parent::tearDown();
        }
    }

    public function testApprovedBaseQueueRuntimeIsAvailableThroughReflection(): void
    {
        self::assertSame('lindemannrock/craft-plugin-base', InstalledBasePackage::name());
        $helper = new ReflectionClass(\lindemannrock\base\helpers\RecurringQueueHelper::class);
        $scheduler = new ReflectionClass(PortableQueueScheduler::class);
        $handoff = new ReflectionClass(DeferredQueueJob::class);

        self::assertTrue($helper->hasMethod('ensurePending'));
        self::assertTrue($helper->hasMethod('deletePending'));
        self::assertTrue($scheduler->hasMethod('pushAt'));
        self::assertTrue($scheduler->hasMethod('continue'));
        self::assertTrue($scheduler->isFinal());
        self::assertTrue($handoff->isFinal());
        self::assertSame(
            InstalledBasePackage::sourceFile('helpers/RecurringQueueHelper.php'),
            InstalledBasePackage::reflectedClassFile(\lindemannrock\base\helpers\RecurringQueueHelper::class),
        );
        self::assertSame(
            InstalledBasePackage::sourceFile('queue/PortableQueueScheduler.php'),
            InstalledBasePackage::reflectedClassFile(PortableQueueScheduler::class),
        );
        self::assertSame(
            InstalledBasePackage::sourceFile('queue/DeferredQueueJob.php'),
            InstalledBasePackage::reflectedClassFile(DeferredQueueJob::class),
        );
    }

    public function testScheduleTokensLabelsAndLegacyManualNormalizationRemainStable(): void
    {
        self::assertSame([
            ['value' => 'disabled', 'label' => 'Disabled'],
            ['value' => 'daily', 'label' => 'Daily'],
            ['value' => 'weekly', 'label' => 'Weekly'],
            ['value' => 'monthly', 'label' => 'Monthly'],
        ], $this->settings()->getBackupScheduleOptions());

        $this->settings()->backupSchedule = 'manual';
        self::assertSame('disabled', $this->settings()->getEffectiveBackupSchedule());
        self::assertSame([
            'enabled' => false,
            'schedule' => 'disabled',
        ], $this->scheduledBackups->getEffectiveState($this->settings()));
    }

    #[DataProvider('scheduleBoundaryProvider')]
    public function testScheduleHelperRetainsFixedWallClockBoundaries(string $schedule, string $from, string $expected): void
    {
        $timezone = new \DateTimeZone(Craft::$app->getTimeZone());
        $next = ScheduleHelper::calculateNext($schedule, new \DateTime($from, $timezone));

        self::assertNotNull($next);
        self::assertSame($expected, $next->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function scheduleBoundaryProvider(): iterable
    {
        $timezone = new \DateTimeZone(Craft::$app->getTimeZone());
        $weekStart = DateRangeHelper::getWeekStartIsoDay();
        $weekly = new \DateTime('2026-08-17 15:45:30', $timezone);
        $days = ($weekStart - (int)$weekly->format('N') + 7) % 7;
        if ($days === 0) {
            $days = 7;
        }
        $weekly->modify("+$days days")->setTime(0, 0, 0);

        yield 'daily midnight' => ['daily', '2026-08-17 15:45:30', '2026-08-18 00:00:00'];
        yield 'configured weekly boundary' => ['weekly', '2026-08-17 15:45:30', $weekly->format('Y-m-d H:i:s')];
        yield 'monthly same wall clock' => ['monthly', '2026-01-31 15:45:30', '2026-02-28 15:45:30'];
    }

    #[DataProvider('portableBoundaryProvider')]
    public function testSqsDelayBoundaryUsesADeferredHandoffOnlyAboveNineHundredSeconds(int $delay, string $expectedClass): void
    {
        $queue = $this->installPortableQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);

        PortableQueueScheduler::push(
            job: $this->recurringJob('boundary'),
            delay: $delay,
            identityTokens: $this->identityTokens(),
            mutexName: ScheduledBackupScheduler::PORTABLE_MUTEX,
            priority: 1024,
            ttr: 1800,
            queue: $queue,
        );

        $row = $this->onlyOwnerRow();
        self::assertInstanceOf($expectedClass, $this->unserializeJob($row));
        self::assertSame($delay <= 900 ? $delay : 900, (int)$row['delay']);
        self::assertSame([$delay <= 900 ? $delay : 900], $this->proxyDelays());
    }

    /**
     * @return iterable<string, array{int, class-string}>
     */
    public static function portableBoundaryProvider(): iterable
    {
        yield '900 seconds' => [900, CreateBackupJob::class];
        yield '901 seconds' => [901, DeferredQueueJob::class];
    }

    #[DataProvider('longScheduleProvider')]
    public function testLongSchedulesUseMultipleHandoffsWithoutCreatingAnEarlyBackup(string $schedule, int $delay): void
    {
        $queue = $this->installPortableQueue(true);
        $backup = new RecordingBackupService();
        $this->replacePluginComponent('backup', $backup);
        $this->pauseAt(self::START_TIMESTAMP);
        $target = self::START_TIMESTAMP + $delay;

        $firstId = PortableQueueScheduler::pushAt(
            job: $this->recurringJob($schedule),
            targetTimestamp: $target,
            identityTokens: $this->identityTokens(),
            mutexName: ScheduledBackupScheduler::PORTABLE_MUTEX,
            priority: 1024,
            ttr: 1800,
            queue: $queue,
        );
        self::assertNotNull($firstId);

        $this->pauseAt(self::START_TIMESTAMP + 900);
        self::assertTrue($queue->executeJob($firstId));
        self::assertInstanceOf(DeferredQueueJob::class, $this->unserializeJob($this->onlyOwnerRow()));
        self::assertSame(0, $backup->createCalls);

        $secondId = (string)$this->onlyOwnerRow()['id'];
        $this->pauseAt(self::START_TIMESTAMP + 1_800);
        self::assertTrue($queue->executeJob($secondId));
        self::assertInstanceOf(DeferredQueueJob::class, $this->unserializeJob($this->onlyOwnerRow()));
        self::assertSame(0, $backup->createCalls);

        $thirdId = (string)$this->onlyOwnerRow()['id'];
        $this->pauseAt($target - 300);
        self::assertTrue($queue->executeJob($thirdId));
        $consumerRow = $this->onlyOwnerRow();
        self::assertInstanceOf(CreateBackupJob::class, $this->unserializeJob($consumerRow));
        self::assertSame(300, (int)$consumerRow['delay']);
        self::assertSame(1024, (int)$consumerRow['priority']);
        self::assertSame(1800, (int)$consumerRow['ttr']);
        self::assertSame(0, $backup->createCalls);
        self::assertLessThanOrEqual(900, max($this->proxyDelays()));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function longScheduleProvider(): iterable
    {
        yield 'daily' => ['daily', 86_400];
        yield 'weekly' => ['weekly', 604_800];
        yield 'monthly' => ['monthly', 2_678_400];
    }

    public function testLateHandoffQueuesTheFinalConsumerWithZeroDelay(): void
    {
        $queue = $this->installPortableQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);
        $jobId = PortableQueueScheduler::push(
            job: $this->recurringJob('late'),
            delay: 901,
            identityTokens: $this->identityTokens(),
            mutexName: ScheduledBackupScheduler::PORTABLE_MUTEX,
            priority: 1024,
            ttr: 1800,
            queue: $queue,
        );
        self::assertNotNull($jobId);

        $this->pauseAt(self::START_TIMESTAMP + 950);
        self::assertTrue($queue->executeJob($jobId));

        $row = $this->onlyOwnerRow();
        self::assertInstanceOf(CreateBackupJob::class, $this->unserializeJob($row));
        self::assertSame(0, (int)$row['delay']);
        self::assertSame([900, 0], $this->proxyDelays());
    }

    public function testLocalQueueRetainsTheCompleteNativeDelay(): void
    {
        $queue = $this->installPortableQueue(false);
        $this->pauseAt(self::START_TIMESTAMP);

        PortableQueueScheduler::push(
            job: $this->recurringJob('local'),
            delay: 604_800,
            identityTokens: $this->identityTokens(),
            mutexName: ScheduledBackupScheduler::PORTABLE_MUTEX,
            priority: 1024,
            ttr: 1800,
            queue: $queue,
        );

        $row = $this->onlyOwnerRow();
        self::assertInstanceOf(CreateBackupJob::class, $this->unserializeJob($row));
        self::assertSame(604_800, (int)$row['delay']);
        self::assertSame([], $this->proxyDelays());
    }

    public function testRecurringConsumerDescriptionAndSerializedQueueContractRemainExact(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $from = new \DateTime('2026-08-17 15:45:30', new \DateTimeZone(Craft::$app->getTimeZone()));
        $nextRunTime = $this->scheduledBackups->getNextRunTime($this->settings(), $from);
        self::assertNotNull($nextRunTime);
        $job = new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
            'recurringOwner' => ScheduledBackupScheduler::RECURRING_OWNER,
            'schedule' => 'daily',
            'targetTimestamp' => self::START_TIMESTAMP,
            'nextRunTime' => $nextRunTime,
        ]);

        self::assertSame(
            $this->settings()->getDisplayName() . ": Scheduled auto backup ($nextRunTime)",
            $job->getDescription(),
        );
        self::assertFalse($job->canRetry(1, new \RuntimeException('test')));
        self::assertSame(1800, $job->getTtr());

        $rowId = $this->pushOwnedJob($job, 300);
        $row = (new Query())->from('{{%queue}}')->where(['id' => $rowId])->one();
        self::assertIsArray($row);
        $serialized = $this->unserializeJob($row);
        self::assertInstanceOf(CreateBackupJob::class, $serialized);
        self::assertSame('scheduled', $serialized->reason);
        self::assertTrue($serialized->reschedule);
        self::assertSame(ScheduledBackupScheduler::RECURRING_OWNER, $serialized->recurringOwner);
        self::assertSame('daily', $serialized->schedule);
        self::assertSame(self::START_TIMESTAMP, $serialized->targetTimestamp);
        self::assertSame(1024, (int)$row['priority']);
        self::assertSame(1800, (int)$row['ttr']);
    }

    public function testBootstrapReconcilesHealthyPhpLegacyRowsToCurrentOwnedOccurrence(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $legacyPayload = $this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $this->insertPayload($legacyPayload, delay: 100);
        $this->insertPayload($legacyPayload, delay: 200);
        $this->pushOwnedJob($this->recurringJob('competing'), 300);

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame([], $this->legacyRowIds());
        self::assertSame(1, $this->countOwnerRows());
        $this->assertOwnerScheduleIdentity($this->onlyOwnerRow(), 'daily');
    }

    public function testRepeatedBootstrapCreatesExactlyOneNewOwnerChain(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        $this->scheduledBackups->synchronize($this->settings());
        $firstId = $this->onlyOwnerRow()['id'];
        $before = $this->queueFingerprint();
        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame(1, $this->countOwnerRows());
        self::assertSame((string)$firstId, (string)$this->onlyOwnerRow()['id']);
        self::assertSame($before, $this->queueFingerprint());
        $this->assertOwnerScheduleIdentity($this->onlyOwnerRow(), 'daily');
    }

    #[DataProvider('configCadenceChangeProvider')]
    public function testBootstrapReplacesPendingOccurrenceWhenConfigCadenceChanges(string $newSchedule): void
    {
        $this->installPortableQueue(true);
        $this->pauseAt(time());
        $configuredSchedule = 'daily';
        $config = $this->createMock(Config::class);
        $config->method('getConfigFromFile')->willReturnCallback(
            static function(string $handle) use (&$configuredSchedule): array {
                return $handle === 'redirect-manager'
                    ? ['backupEnabled' => true, 'backupSchedule' => $configuredSchedule]
                    : [];
            },
        );
        Craft::$app->set('config', $config);

        $dailySettings = RedirectManager::getInstance()->getSettings();
        self::assertInstanceOf(\lindemannrock\redirectmanager\models\Settings::class, $dailySettings);
        $this->scheduledBackups->synchronize($dailySettings);
        $dailyRow = $this->onlyOwnerRow();
        $dailyHandoff = $this->unserializeJob($dailyRow);
        self::assertInstanceOf(DeferredQueueJob::class, $dailyHandoff);

        $configuredSchedule = $newSchedule;
        $changedSettings = RedirectManager::getInstance()->getSettings();
        self::assertInstanceOf(\lindemannrock\redirectmanager\models\Settings::class, $changedSettings);
        self::assertSame($newSchedule, $changedSettings->getEffectiveBackupSchedule());
        $expectedTarget = $this->scheduledBackups->getNextRun($changedSettings);
        self::assertNotNull($expectedTarget);

        $this->scheduledBackups->synchronize($changedSettings);

        $changedRow = $this->onlyOwnerRow();
        $changedHandoff = $this->unserializeJob($changedRow);
        self::assertInstanceOf(DeferredQueueJob::class, $changedHandoff);
        $changedTarget = $changedHandoff->targetTimestamp;
        self::assertSame($expectedTarget->getTimestamp(), $changedTarget);
        self::assertNotSame((string)$dailyRow['id'], (string)$changedRow['id']);
        $this->assertOwnerScheduleIdentity($changedRow, $newSchedule, $expectedTarget->getTimestamp());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function configCadenceChangeProvider(): iterable
    {
        yield 'daily to weekly' => ['weekly'];
        yield 'daily to monthly' => ['monthly'];
    }

    public function testBootstrapCoalescesHealthyCurrentAndLegacyDuplicatesToOneCurrentOccurrence(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $this->scheduledBackups->synchronize($this->settings());
        $currentPayload = (string)$this->onlyOwnerRow()['job'];
        $this->insertPayload($currentPayload, delay: 100);
        $this->insertPayload($this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ])), delay: 50);

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame(1, $this->countOwnerRows());
        self::assertSame([], $this->legacyRowIds());
        $this->assertOwnerScheduleIdentity($this->onlyOwnerRow(), 'daily');
    }

    public function testCadenceReconciliationPreservesNonPendingAndForeignQueueRowsExactly(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $this->scheduledBackups->synchronize($this->settings());
        $pendingRow = $this->onlyOwnerRow();
        $ownedPayload = (string)$pendingRow['job'];
        $preservedIds = [
            $this->insertPayload($ownedPayload, reserved: true),
            $this->insertPayload($ownedPayload, fail: true),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'manual', 'reschedule' => false]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'import', 'reschedule' => true]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'console', 'reschedule' => true]), 50),
            $this->pushOwnedJob(new CleanupAnalyticsJob(['reschedule' => true]), 50),
            $this->insertPayload('{"plugin":"another-plugin","class":"CreateBackupJob","reason":"scheduled","reschedule":true}'),
        ];
        $before = $this->queueRowsByIds($preservedIds);

        $this->settings()->backupSchedule = 'weekly';
        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame($before, $this->queueRowsByIds($preservedIds));
        self::assertFalse((new Query())->from('{{%queue}}')->where(['id' => $pendingRow['id']])->exists());
        self::assertSame(3, $this->countOwnerRows());
        $healthy = $this->ownerQuery()->andWhere(['fail' => false, 'timeUpdated' => null])->all();
        self::assertCount(1, $healthy);
        $this->assertOwnerScheduleIdentity($healthy[0], 'weekly');
    }

    public function testBootstrapLifecycleContentionIsNonfatalAndLeavesRowsUnchanged(): void
    {
        $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $this->insertPayload($this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ])), delay: 100);
        $before = $this->queueFingerprint();
        $mutex = new SelectiveFailureMutex([ScheduledBackupScheduler::LIFECYCLE_MUTEX]);
        $originalMutex = Craft::$app->getMutex();
        $messageOffset = count(Craft::getLogger()->messages);
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        try {
            $this->scheduledBackups->synchronize($this->settings());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame($before, $this->queueFingerprint());
        $this->assertBootstrapDebugSince(
            $messageOffset,
            'Scheduled-backup bootstrap reconciliation deferred because the lifecycle lock is busy.',
        );
        self::assertSame([ScheduledBackupScheduler::LIFECYCLE_MUTEX], $mutex->acquisitions);
        self::assertSame([0], $mutex->timeouts);
        self::assertSame([], $mutex->releases);
    }

    public function testBootstrapPortableContentionIsNonfatalAndLeavesRowsUnchanged(): void
    {
        $legacyPayload = $this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $this->insertPayload($legacyPayload, delay: 100);
        $this->insertPayload($legacyPayload, delay: 200);
        $before = $this->queueFingerprint();
        $mutex = new SelectiveFailureMutex([ScheduledBackupScheduler::PORTABLE_MUTEX]);
        $originalMutex = Craft::$app->getMutex();
        $messageOffset = count(Craft::getLogger()->messages);
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        try {
            $this->scheduledBackups->synchronize($this->settings());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame($before, $this->queueFingerprint());
        $this->assertBootstrapDebugSince(
            $messageOffset,
            'Scheduled-backup bootstrap reconciliation deferred because the portable lock is busy.',
        );
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
        self::assertSame([0, 0], $mutex->timeouts);
        self::assertSame([ScheduledBackupScheduler::LIFECYCLE_MUTEX], $mutex->releases);
    }

    public function testLaterBootstrapReconcilesAfterLifecycleContentionClears(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', new SelectiveFailureMutex([ScheduledBackupScheduler::LIFECYCLE_MUTEX]));

        try {
            $this->scheduledBackups->synchronize($this->settings());
            self::assertSame(0, $this->countOwnerRows());

            Craft::$app->set('mutex', new SelectiveFailureMutex([]));
            $this->scheduledBackups->synchronize($this->settings());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame(1, $this->countOwnerRows());
    }

    public function testDisabledBootstrapRetriesCancellationAfterContentionClears(): void
    {
        $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $this->insertPayload($this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ])));
        $before = $this->queueFingerprint();
        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', new SelectiveFailureMutex([ScheduledBackupScheduler::PORTABLE_MUTEX]));

        try {
            $this->scheduledBackups->synchronize($this->settings());
            self::assertSame($before, $this->queueFingerprint());

            Craft::$app->set('mutex', new SelectiveFailureMutex([]));
            $this->scheduledBackups->synchronize($this->settings());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame(0, $this->countOwnerRows());
        self::assertSame([], $this->legacyRowIds());
    }

    public function testSettingsLifecycleLockFailureStillPropagates(): void
    {
        $mutex = new SelectiveFailureMutex([ScheduledBackupScheduler::LIFECYCLE_MUTEX]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        try {
            RedirectManager::getInstance()->handleBackupScheduleChange($this->settings(), [
                'enabled' => false,
                'schedule' => 'disabled',
            ]);
            self::fail('Expected lifecycle mutex failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Unable to acquire the scheduled-backup lifecycle lock.', $exception->getMessage());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame([5], $mutex->timeouts);
    }

    public function testSettingsPortableLockFailureStillPropagatesAndLeavesRowsUnchanged(): void
    {
        $ownerId = $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $legacyId = $this->insertPayload($this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ])));
        $mutex = new SelectiveFailureMutex([ScheduledBackupScheduler::PORTABLE_MUTEX]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';

        try {
            RedirectManager::getInstance()->handleBackupScheduleChange($this->settings(), [
                'enabled' => true,
                'schedule' => 'daily',
            ]);
            self::fail('Expected portable mutex failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Unable to acquire the portable scheduled-backup queue lock.', $exception->getMessage());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame([$legacyId], $this->legacyRowIds());
        self::assertSame((string)$ownerId, (string)$this->onlyOwnerRow()['id']);
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
        self::assertSame([5, 5], $mutex->timeouts);
        self::assertSame([ScheduledBackupScheduler::LIFECYCLE_MUTEX], $mutex->releases);
    }

    public function testDisabledBootstrapCancelsUnderLifecycleThenPortableLocks(): void
    {
        $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $this->insertPayload($this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ])));
        $mutex = new SelectiveFailureMutex([]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';

        try {
            $this->scheduledBackups->synchronize($this->settings());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame(0, $this->countOwnerRows());
        self::assertSame([], $this->legacyRowIds());
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
        self::assertSame([
            ScheduledBackupScheduler::PORTABLE_MUTEX,
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
        ], $mutex->releases);
        self::assertSame([0, 0], $mutex->timeouts);
    }

    public function testSchedulerDeferredHandoffCarriesThePortableMutex(): void
    {
        $queue = $this->installPortableQueue(true);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'monthly';

        $this->scheduledBackups->synchronize($this->settings());

        $row = $this->onlyOwnerRow();
        $handoff = $this->unserializeJob($row);
        self::assertInstanceOf(DeferredQueueJob::class, $handoff);
        self::assertSame(ScheduledBackupScheduler::PORTABLE_MUTEX, $handoff->mutexName);
        self::assertSame($this->identityTokens(), $handoff->identityTokens);
        self::assertSame(1024, $handoff->priority);
        self::assertSame(1800, $handoff->ttr);

        $mutex = new SelectiveFailureMutex([]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->markExecuting($queue, (string)$row['id']);
        $this->pauseAt($handoff->targetTimestamp - 900);

        try {
            $handoff->execute($queue);
        } finally {
            $this->markExecuting($queue, null);
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame([ScheduledBackupScheduler::PORTABLE_MUTEX], $mutex->acquisitions);
        self::assertSame([ScheduledBackupScheduler::PORTABLE_MUTEX], $mutex->releases);
    }

    public function testCanonicalPushAtTargetRemainsExactWhenPortableLockAcquisitionAdvancesTime(): void
    {
        $this->installPortableQueue(true);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'weekly';
        $target = $this->scheduledBackups->getNextRun($this->settings());
        self::assertNotNull($target);
        $this->pauseAt($target->getTimestamp() - 2_000);
        $mutex = new SelectiveFailureMutex([], $target->getTimestamp() - 1_000);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);

        try {
            $this->scheduledBackups->synchronize($this->settings());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        $row = $this->onlyOwnerRow();
        $handoff = $this->unserializeJob($row);
        self::assertInstanceOf(DeferredQueueJob::class, $handoff);
        self::assertSame($target->getTimestamp(), $handoff->targetTimestamp);
        self::assertSame(900, (int)$row['delay']);
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
    }

    public function testNonRecurringAndNonScheduledLegacyShapesDoNotBlockBootstrap(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $preservedIds = [
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'scheduled', 'reschedule' => false]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'manual', 'reschedule' => true]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'restore', 'reschedule' => true]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'maintenance', 'reschedule' => true]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'console', 'reschedule' => true]), 50),
        ];

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame(1, $this->countOwnerRows());
        self::assertSame(
            array_map('intval', $preservedIds),
            array_map('intval', (new Query())->from('{{%queue}}')->where(['id' => $preservedIds])->orderBy(['id' => SORT_ASC])->column()),
        );
    }

    public function testJsonAndDeferredWrapperLegacyRowsAreReconciledToCurrentIdentity(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $jsonId = $this->insertPayload(json_encode([
            'plugin' => 'redirectmanager',
            'class' => 'CreateBackupJob',
            'reason' => 'scheduled',
            'reschedule' => true,
        ], JSON_THROW_ON_ERROR), delay: 100);
        $deferred = new DeferredQueueJob([
            'job' => new CreateBackupJob(['reason' => 'scheduled', 'reschedule' => true]),
            'targetTimestamp' => self::START_TIMESTAMP + 2_000,
            'identityTokens' => ['redirectmanager', 'CreateBackupJob'],
            'mutexName' => ScheduledBackupScheduler::PORTABLE_MUTEX,
            'chainId' => 'legacy-wrapper-chain',
        ]);
        $this->insertPayload($this->serializeJob($deferred), delay: 200);

        $this->scheduledBackups->synchronize($this->settings());

        self::assertNotContains($jsonId, $this->legacyRowIds());
        self::assertSame([], $this->legacyRowIds());
        self::assertSame(1, $this->countOwnerRows());
        $this->assertOwnerScheduleIdentity($this->onlyOwnerRow(), 'daily');
    }

    public function testFailedLegacyRowDoesNotBlockNewOwnerRecovery(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $legacyPayload = $this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $failedId = $this->insertPayload($legacyPayload, fail: true);

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame([$failedId], $this->legacyRowIds());
        self::assertSame(1, $this->countOwnerRows());
        $this->assertOwnerScheduleIdentity($this->onlyOwnerRow(), 'daily');
    }

    public function testReplacementCancelsEveryOwnedStateAndPreservesOtherQueueFamilies(): void
    {
        $newPayload = $this->serializeJob($this->recurringJob('new'));
        $legacyPayload = $this->serializeJob(new CreateBackupJob(['reason' => 'scheduled', 'reschedule' => true]));
        foreach ([
            ['payload' => $newPayload, 'state' => 'pending'],
            ['payload' => $newPayload, 'state' => 'reserved'],
            ['payload' => $newPayload, 'state' => 'failed'],
            ['payload' => $legacyPayload, 'state' => 'pending'],
            ['payload' => $legacyPayload, 'state' => 'reserved'],
            ['payload' => $legacyPayload, 'state' => 'failed'],
        ] as $row) {
            $this->insertPayload(
                $row['payload'],
                fail: $row['state'] === 'failed',
                reserved: $row['state'] === 'reserved',
            );
        }

        $deferred = new DeferredQueueJob([
            'job' => $this->recurringJob('deferred'),
            'targetTimestamp' => self::START_TIMESTAMP + 2_000,
            'identityTokens' => $this->identityTokens(),
            'mutexName' => ScheduledBackupScheduler::PORTABLE_MUTEX,
            'chainId' => 'owned-deferred-chain',
        ]);
        foreach (['pending', 'reserved', 'failed'] as $state) {
            $this->insertPayload(
                $this->serializeJob($deferred),
                fail: $state === 'failed',
                reserved: $state === 'reserved',
            );
        }

        $preservedIds = [
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'manual', 'reschedule' => false]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'import', 'reschedule' => true]), 50),
            $this->pushOwnedJob(new CleanupAnalyticsJob(['reschedule' => true]), 50),
            $this->insertPayload('{"plugin":"another-plugin","class":"CreateBackupJob","reason":"scheduled","reschedule":true}'),
        ];

        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'daily';
        $this->scheduledBackups->replace($this->settings());

        self::assertSame(0, $this->countOwnerRows());
        self::assertSame([], $this->legacyRowIds());
        self::assertSame(
            array_map('intval', $preservedIds),
            array_map('intval', (new Query())->from('{{%queue}}')->where(['id' => $preservedIds])->orderBy(['id' => SORT_ASC])->column()),
        );
    }

    public function testCancelledDeferredHandoffCannotResurrectTheSchedule(): void
    {
        $queue = $this->installPortableQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);
        $jobId = PortableQueueScheduler::push(
            job: $this->recurringJob('cancelled'),
            delay: 901,
            identityTokens: $this->identityTokens(),
            mutexName: ScheduledBackupScheduler::PORTABLE_MUTEX,
            priority: 1024,
            ttr: 1800,
            queue: $queue,
        );
        self::assertNotNull($jobId);
        $handoff = $this->unserializeJob($this->onlyOwnerRow());
        self::assertInstanceOf(DeferredQueueJob::class, $handoff);
        $this->markExecuting($queue, $jobId);

        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';
        $this->scheduledBackups->replace($this->settings());
        $handoff->execute($queue);

        self::assertSame(0, $this->countOwnerRows());
        self::assertSame([900], $this->proxyDelays());
        $this->markExecuting($queue, null);
    }

    public function testEffectiveSettingsChangesReplaceDisableAndReenableWithoutUnchangedChurn(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $this->scheduledBackups->replace($this->settings());
        $firstId = $this->onlyOwnerRow()['id'];
        $dailyState = $this->scheduledBackups->getEffectiveState($this->settings());

        self::assertFalse($this->scheduledBackups->replaceIfChanged($this->settings(), $dailyState));
        self::assertSame((string)$firstId, (string)$this->onlyOwnerRow()['id']);

        $this->settings()->backupSchedule = 'weekly';
        self::assertTrue($this->scheduledBackups->replaceIfChanged($this->settings(), $dailyState));
        $weeklyId = $this->onlyOwnerRow()['id'];
        self::assertNotSame((string)$firstId, (string)$weeklyId);
        $this->assertOwnerScheduleIdentity($this->onlyOwnerRow(), 'weekly');

        $weeklyState = $this->scheduledBackups->getEffectiveState($this->settings());
        $this->settings()->backupEnabled = false;
        self::assertTrue($this->scheduledBackups->replaceIfChanged($this->settings(), $weeklyState));
        self::assertSame(0, $this->countOwnerRows());

        $disabledState = $this->scheduledBackups->getEffectiveState($this->settings());
        $this->settings()->backupSchedule = 'monthly';
        self::assertFalse($this->scheduledBackups->replaceIfChanged($this->settings(), $disabledState));
        self::assertSame(0, $this->countOwnerRows());

        $this->settings()->backupEnabled = true;
        self::assertTrue($this->scheduledBackups->replaceIfChanged($this->settings(), $disabledState));
        self::assertSame(1, $this->countOwnerRows());
        $this->assertOwnerScheduleIdentity($this->onlyOwnerRow(), 'monthly');
    }

    public function testSettingsReplacementUsesConfigOverriddenEffectiveValues(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $this->scheduledBackups->replace($this->settings());
        self::assertSame(1, $this->countOwnerRows());

        $config = $this->createMock(Config::class);
        $config->method('getConfigFromFile')->willReturnCallback(
            static fn(string $handle): array => $handle === 'redirect-manager'
                ? ['backupEnabled' => false, 'backupSchedule' => 'weekly']
                : [],
        );
        Craft::$app->set('config', $config);

        $savedSettings = clone $this->settings();
        RedirectManager::getInstance()->handleBackupScheduleChange($savedSettings, [
            'enabled' => true,
            'schedule' => 'daily',
        ]);

        self::assertFalse($savedSettings->backupEnabled);
        self::assertSame('weekly', $savedSettings->backupSchedule);
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testDisabledReservedRecurringJobDoesNotCreateBackupOrSuccessor(): void
    {
        $backup = new RecordingBackupService();
        $this->replacePluginComponent('backup', $backup);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'disabled';

        $this->recurringJob('disabled')->execute(Craft::$app->getQueue());

        self::assertSame(0, $backup->createCalls);
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testSuccessfulRecurringBackupCleansRetentionBeforeQueuingOneSuccessor(): void
    {
        $backup = new RecordingBackupService();
        $mutex = new SelectiveFailureMutex([]);
        $backup->onCreate = static function() use ($mutex): void {
            self::assertContains(ScheduledBackupScheduler::LIFECYCLE_MUTEX, $mutex->heldLocks);
        };
        $backup->onCleanup = static function() use ($mutex): void {
            self::assertContains(ScheduledBackupScheduler::LIFECYCLE_MUTEX, $mutex->heldLocks);
        };
        $this->replacePluginComponent('backup', $backup);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $this->settings()->backupRetentionDays = 30;
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);

        try {
            $this->recurringJob('success')->execute(Craft::$app->getQueue());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame(1, $backup->createCalls);
        self::assertSame(['scheduled'], $backup->reasons);
        self::assertSame(1, $backup->cleanupCalls);
        self::assertSame(1, $this->countOwnerRows());
        $this->assertOwnerScheduleIdentity($this->onlyOwnerRow(), 'daily');
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
        self::assertSame([5, 5], $mutex->timeouts);
        self::assertSame([
            ScheduledBackupScheduler::PORTABLE_MUTEX,
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
        ], $mutex->releases);
        self::assertSame([], $mutex->heldLocks);
    }

    public function testBackupFailurePropagatesAfterQueuingOneRecoverySuccessor(): void
    {
        $backup = new RecordingBackupService();
        $backup->failure = new \RuntimeException('Scheduled backup storage failure.');
        $this->replacePluginComponent('backup', $backup);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        try {
            $this->recurringJob('failure')->execute(Craft::$app->getQueue());
            self::fail('Expected backup creation failure to propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Scheduled backup storage failure.', $exception->getMessage());
        }

        self::assertSame(1, $backup->createCalls);
        self::assertSame(0, $backup->cleanupCalls);
        self::assertSame(1, $this->countOwnerRows());
        $this->assertOwnerScheduleIdentity($this->onlyOwnerRow(), 'daily');
    }

    public function testEmptyRecurringBackupQueuesOneSuccessorWithoutRetentionOrRetry(): void
    {
        $backup = new RecordingBackupService();
        $backup->empty = true;
        $this->replacePluginComponent('backup', $backup);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $this->settings()->backupRetentionDays = 30;

        $this->recurringJob('empty')->execute(Craft::$app->getQueue());

        self::assertSame(1, $backup->createCalls);
        self::assertSame(['scheduled'], $backup->reasons);
        self::assertSame(0, $backup->cleanupCalls);
        self::assertSame(1, $this->countOwnerRows());
    }

    public function testSettingsCancellationFailureStillPropagates(): void
    {
        $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $db = Craft::$app->getDb();
        $originalCommandClass = $db->commandClass;
        $db->commandClass = FailingBackupQueueDeleteCommand::class;
        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';

        try {
            $this->scheduledBackups->replace($this->settings());
            self::fail('Expected queue cancellation failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Scheduled backup cancellation failure.', $exception->getMessage());
        } finally {
            $db->commandClass = $originalCommandClass;
        }

        self::assertSame(1, $this->countOwnerRows());
    }

    public function testBootstrapCancellationFailureAfterLockAcquisitionPropagates(): void
    {
        $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $db = Craft::$app->getDb();
        $originalCommandClass = $db->commandClass;
        $db->commandClass = FailingBackupQueueDeleteCommand::class;
        $mutex = new SelectiveFailureMutex([]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';

        try {
            $this->scheduledBackups->synchronize($this->settings());
            self::fail('Expected queue cancellation failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Scheduled backup cancellation failure.', $exception->getMessage());
        } finally {
            $db->commandClass = $originalCommandClass;
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame(1, $this->countOwnerRows());
        self::assertSame([
            ScheduledBackupScheduler::PORTABLE_MUTEX,
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
        ], $mutex->releases);
    }

    public function testBootstrapPushFailureAfterLockAcquisitionPropagates(): void
    {
        $this->installPortableQueue(true);
        self::assertNotNull($this->proxyQueue);
        $this->proxyQueue->failPushes = true;
        $mutex = new SelectiveFailureMutex([]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        try {
            $this->scheduledBackups->synchronize($this->settings());
            self::fail('Expected proxy push failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Scheduled backup proxy failure.', $exception->getMessage());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame([
            ScheduledBackupScheduler::PORTABLE_MUTEX,
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
        ], $mutex->releases);
    }

    public function testSettingsPushFailurePropagatesAndLeavesInspectableOwnedRow(): void
    {
        $this->installPortableQueue(true);
        self::assertNotNull($this->proxyQueue);
        $this->proxyQueue->failPushes = true;
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Scheduled backup proxy failure.');

        try {
            $this->scheduledBackups->replace($this->settings());
        } finally {
            self::assertSame(1, $this->countOwnerRows());
        }
    }

    public function testRuntimeHasNoCloudDependencyOrPrivateInfrastructureInspection(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root . '/src/services/ScheduledBackupScheduler.php')
            . file_get_contents($root . '/src/jobs/CreateBackupJob.php');
        $composer = file_get_contents($root . '/composer.json');

        self::assertIsString($runtime);
        self::assertIsString($composer);
        self::assertStringNotContainsString('craft\\cloud', $runtime);
        self::assertStringNotContainsString('craftcms/cloud', $composer);
        self::assertStringNotContainsString('AWS_', $runtime);
        self::assertStringNotContainsString('CLOUD_', $runtime);
    }

    private function installPortableQueue(bool $bounded): Queue
    {
        $current = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $current);
        $this->proxyQueue = $bounded ? new RecordingBackupSqsQueue() : null;
        $queue = new Queue([
            'db' => Craft::$app->getDb(),
            'mutex' => Craft::$app->getMutex(),
            'tableName' => $current->tableName,
            'channel' => $current->channel,
            'mutexTimeout' => $current->mutexTimeout,
            'proxyQueue' => $this->proxyQueue,
        ]);
        Craft::$app->set('queue', $queue);

        $installedQueue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $installedQueue);

        return $installedQueue;
    }

    private function pauseAt(int $timestamp): void
    {
        if ($this->timePaused) {
            DateTimeHelper::resume();
        }

        DateTimeHelper::pause(new \DateTime("@$timestamp"));
        $this->timePaused = true;
    }

    private function recurringJob(string $nextRunTime): CreateBackupJob
    {
        return new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
            'recurringOwner' => ScheduledBackupScheduler::RECURRING_OWNER,
            'nextRunTime' => $nextRunTime,
        ]);
    }

    /** @return non-empty-list<string> */
    private function identityTokens(): array
    {
        return [
            ScheduledBackupScheduler::PLUGIN_TOKEN,
            'CreateBackupJob',
            ScheduledBackupScheduler::RECURRING_OWNER,
        ];
    }

    /** @return array<string, mixed> */
    private function onlyOwnerRow(): array
    {
        $rows = $this->ownerQuery()->orderBy(['id' => SORT_ASC])->all();
        self::assertCount(1, $rows);

        return $rows[0];
    }

    private function countOwnerRows(): int
    {
        return (int)$this->ownerQuery()->count();
    }

    private function ownerQuery(): Query
    {
        return (new Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', ScheduledBackupScheduler::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->andWhere(['like', 'job', ScheduledBackupScheduler::RECURRING_OWNER]);
    }

    /** @return list<array<string, mixed>> */
    private function queueFingerprint(): array
    {
        $rows = (new Query())
            ->from('{{%queue}}')
            ->orderBy(['id' => SORT_ASC])
            ->all();

        return array_map(static function(array $row): array {
            $row['job'] = hash('sha256', (string)$row['job']);

            return $row;
        }, $rows);
    }

    /**
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    private function queueRowsByIds(array $ids): array
    {
        return (new Query())
            ->from('{{%queue}}')
            ->where(['id' => $ids])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    /** @return list<int> */
    private function legacyRowIds(): array
    {
        $ids = [];
        $rows = (new Query())
            ->from('{{%queue}}')
            ->select(['id', 'job'])
            ->where(['like', 'job', ScheduledBackupScheduler::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->andWhere(['not like', 'job', ScheduledBackupScheduler::RECURRING_OWNER])
            ->orderBy(['id' => SORT_ASC])
            ->all();
        foreach ($rows as $row) {
            $payload = (string)$row['job'];
            if ((str_contains($payload, 's:6:"reason";s:9:"scheduled";')
                    && str_contains($payload, 's:10:"reschedule";b:1;'))
                || (str_contains($payload, '"reason":"scheduled"')
                    && str_contains($payload, '"reschedule":true'))
            ) {
                $ids[] = (int)$row['id'];
            }
        }

        return $ids;
    }

    private function serializeJob(object $job): string
    {
        $queue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $queue);

        return $queue->serializer->serialize($job);
    }

    /** @param array<string, mixed> $row */
    private function unserializeJob(array $row): object
    {
        $queue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $queue);
        $job = $queue->serializer->unserialize((string)$row['job']);
        self::assertIsObject($job);

        return $job;
    }

    /** @param array<string, mixed> $row */
    private function assertOwnerScheduleIdentity(
        array $row,
        string $schedule,
        ?int $targetTimestamp = null,
    ): void {
        $job = $this->unserializeJob($row);
        if ($job instanceof DeferredQueueJob) {
            if ($targetTimestamp !== null) {
                self::assertSame($targetTimestamp, $job->targetTimestamp);
            }
            $job = $job->job;
        }

        self::assertInstanceOf(CreateBackupJob::class, $job);
        self::assertSame(ScheduledBackupScheduler::RECURRING_OWNER, $job->recurringOwner);
        self::assertSame($schedule, $job->schedule);
        self::assertGreaterThan(0, $job->targetTimestamp);
        if ($targetTimestamp !== null) {
            self::assertSame($targetTimestamp, $job->targetTimestamp);
        }
    }

    private function insertPayload(
        string $payload,
        int $delay = 300,
        bool $fail = false,
        bool $reserved = false,
    ): int {
        Craft::$app->getDb()->createCommand()->insert('{{%queue}}', [
            'channel' => 'queue',
            'job' => $payload,
            'description' => 'Redirect Manager queue test row',
            'timePushed' => DateTimeHelper::currentTimeStamp(),
            'ttr' => 1800,
            'delay' => $delay,
            'priority' => 1024,
            'timeUpdated' => $reserved ? DateTimeHelper::currentTimeStamp() : null,
            'fail' => $fail,
        ])->execute();

        return (int)Craft::$app->getDb()->getLastInsertID();
    }

    /** @return list<int> */
    private function proxyDelays(): array
    {
        return $this->proxyQueue === null ? [] : array_column($this->proxyQueue->pushes, 'delay');
    }

    private function markExecuting(Queue $queue, ?string $jobId): void
    {
        if ($jobId !== null) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%queue}}', ['timeUpdated' => DateTimeHelper::currentTimeStamp()], ['id' => $jobId])
                ->execute();
        }

        $property = new ReflectionProperty(Queue::class, '_executingJobId');
        $property->setValue($queue, $jobId);
    }

    private function assertBootstrapDebugSince(int $offset, string $expectedMessage): void
    {
        $matching = array_filter(
            array_slice(Craft::getLogger()->messages, $offset),
            static fn(array $message): bool => $message[0] === $expectedMessage
                && $message[2] === 'redirect-manager',
        );

        self::assertSame([Logger::LEVEL_TRACE], array_values(array_column($matching, 1)));
        self::assertNotContains(Logger::LEVEL_WARNING, array_column($matching, 1));
    }
}

/** Records bounded proxy pushes without contacting a provider. */
final class RecordingBackupSqsQueue extends SqsQueue
{
    /** @var list<array{delay: int, priority: mixed, ttr: int}> */
    public array $pushes = [];

    public bool $failPushes = false;

    protected function pushMessage($message, $ttr, $delay, $priority): string
    {
        if ($this->failPushes) {
            throw new \RuntimeException('Scheduled backup proxy failure.');
        }

        $this->pushes[] = [
            'delay' => (int)$delay,
            'priority' => $priority,
            'ttr' => (int)$ttr,
        ];

        return 'scheduled-backup-proxy-' . count($this->pushes);
    }
}

/** Backup seam that never writes files or volume objects. */
final class RecordingBackupService extends BackupService
{
    public int $createCalls = 0;
    public int $cleanupCalls = 0;
    public bool $empty = false;
    public ?\Throwable $failure = null;
    public ?\Closure $onCreate = null;
    public ?\Closure $onCleanup = null;
    /** @var list<string> */
    public array $reasons = [];

    public function createBackup(string $reason = 'import'): ?string
    {
        $this->createCalls++;
        $this->reasons[] = $reason;
        ($this->onCreate ?? static fn() => null)();

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->empty ? null : '/tmp/redirect-manager-owned-test-backup';
    }

    public function cleanupOldBackups(): int
    {
        $this->cleanupCalls++;
        ($this->onCleanup ?? static fn() => null)();

        return 0;
    }
}

/** Mutex seam that fails only explicitly named locks. */
final class SelectiveFailureMutex extends Mutex
{
    /** @var list<string> */
    public array $acquisitions = [];
    /** @var list<int> */
    public array $timeouts = [];
    /** @var list<string> */
    public array $releases = [];
    /** @var list<string> */
    public array $heldLocks = [];

    /** @param list<string> $failedNames */
    public function __construct(
        private readonly array $failedNames,
        private readonly ?int $portableTimestamp = null,
        array $config = [],
    ) {
        parent::__construct($config);
    }

    protected function acquireLock($name, $timeout = 0): bool
    {
        $this->acquisitions[] = (string)$name;
        $this->timeouts[] = (int)$timeout;
        if ($name === ScheduledBackupScheduler::PORTABLE_MUTEX && $this->portableTimestamp !== null) {
            DateTimeHelper::resume();
            DateTimeHelper::pause(new \DateTime('@' . $this->portableTimestamp));
        }

        if (in_array($name, $this->failedNames, true)) {
            return false;
        }

        $this->heldLocks[] = (string)$name;

        return true;
    }

    protected function releaseLock($name): bool
    {
        $this->releases[] = (string)$name;
        $this->heldLocks = array_values(array_filter(
            $this->heldLocks,
            static fn(string $heldName): bool => $heldName !== (string)$name,
        ));

        return true;
    }
}

/** Command seam that makes exact queue deletion fail before any row changes. */
final class FailingBackupQueueDeleteCommand extends \craft\db\Command
{
    public function delete($table, $condition = '', $params = [])
    {
        if ($table === '{{%queue}}') {
            throw new \RuntimeException('Scheduled backup cancellation failure.');
        }

        return parent::delete($table, $condition, $params);
    }
}
