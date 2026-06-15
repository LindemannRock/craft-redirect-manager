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
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\redirectmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\redirectmanager\jobs\CreateBackupJob;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionMethod;

/**
 * Pins Redirect Manager's scheduler-pattern integration with base helpers.
 *
 * @since 5.32.0
 */
final class SchedulerPatternTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->deleteRedirectManagerQueueRows();
    }

    protected function tearDown(): void
    {
        $this->deleteRedirectManagerQueueRows();
        parent::tearDown();
    }

    public function testAnalyticsCleanupReschedulesWhenExistingCleanupRowExists(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 30;

        Craft::$app->getQueue()->delay(300)->push(new CleanupAnalyticsJob([
            'reschedule' => true,
        ]));
        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));

        $job = new CleanupAnalyticsJob([
            'reschedule' => true,
        ]);
        $this->invokePrivate($job, 'scheduleNextCleanup');

        $this->assertSame(2, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testAnalyticsCleanupBootstrapUsesCanonicalDailyRun(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 30;

        $this->invokePrivate(RedirectManager::getInstance(), 'scheduleAnalyticsCleanup');

        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));

        $row = $this->latestQueueRow('CleanupAnalyticsJob');
        self::assertIsArray($row);
        self::assertStringContainsString($this->expectedDailyRunTime(), (string) $row['description']);
    }

    public function testAnalyticsCleanupBootstrapCollapsesDuplicatePendingRows(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 30;

        Craft::$app->getQueue()->delay(300)->push(new CleanupAnalyticsJob([
            'reschedule' => true,
        ]));
        Craft::$app->getQueue()->delay(300)->push(new CleanupAnalyticsJob([
            'reschedule' => true,
        ]));
        $this->assertSame(2, $this->countQueueRows('CleanupAnalyticsJob'));

        $this->invokePrivate(RedirectManager::getInstance(), 'scheduleAnalyticsCleanup');

        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testBackupReschedulesWhenExistingBackupRowExists(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $this->assertSame(1, $this->countQueueRows('CreateBackupJob'));

        $job = new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]);
        $this->invokePrivate($job, 'scheduleNextBackup');

        $this->assertSame(2, $this->countQueueRows('CreateBackupJob'));
    }

    public function testBackupBootstrapDoesNotDuplicateExistingDelayedBackupRow(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $this->assertSame(1, $this->countQueueRows('CreateBackupJob'));

        $this->invokePrivate(RedirectManager::getInstance(), 'scheduleBackupJob');

        $this->assertSame(1, $this->countQueueRows('CreateBackupJob'));
    }

    public function testBackupBootstrapCollapsesDuplicatePendingBackupRows(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $this->assertSame(2, $this->countQueueRows('CreateBackupJob'));

        $this->invokePrivate(RedirectManager::getInstance(), 'scheduleBackupJob');

        $this->assertSame(1, $this->countQueueRows('CreateBackupJob'));
    }

    public function testBackupScheduleChangeReplacesExistingBackupRows(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $this->assertSame(2, $this->countQueueRows('CreateBackupJob'));

        RedirectManager::getInstance()->handleBackupScheduleChange($this->settings());

        $this->assertSame(1, $this->countQueueRows('CreateBackupJob'));
    }

    public function testBackupScheduleChangeCancelsExistingBackupRowsWhenBackupsDisabled(): void
    {
        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'daily';

        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $this->assertSame(2, $this->countQueueRows('CreateBackupJob'));

        RedirectManager::getInstance()->handleBackupScheduleChange($this->settings());

        $this->assertSame(0, $this->countQueueRows('CreateBackupJob'));
    }

    public function testBackupScheduleOptionsUseDisabledInsteadOfManual(): void
    {
        $this->assertSame([
            'disabled',
            'daily',
            'weekly',
            'monthly',
        ], array_column($this->settings()->getBackupScheduleOptions(), 'value'));

        $this->settings()->backupSchedule = 'manual';
        $this->assertSame('disabled', $this->settings()->getEffectiveBackupSchedule());
    }

    private function invokePrivate(object $object, string $method): void
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->invoke($object);
    }

    private function countQueueRows(string $jobClass): int
    {
        return (int) (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'redirectmanager'])
            ->andWhere(['like', 'job', $jobClass])
            ->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestQueueRow(string $jobClass): ?array
    {
        $row = (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'redirectmanager'])
            ->andWhere(['like', 'job', $jobClass])
            ->select(['id', 'description'])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $row !== false ? $row : null;
    }

    private function expectedDailyRunTime(): string
    {
        $nextRun = ScheduleHelper::calculateNext('daily');
        self::assertNotNull($nextRun);

        return DateFormatHelper::formatCompactDatetimeFromSettings(
            $nextRun,
            $this->settings(),
            false,
            false,
        );
    }

    private function deleteRedirectManagerQueueRows(): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', [
                'and',
                ['like', 'job', 'redirectmanager'],
                [
                    'or',
                    ['like', 'job', 'CleanupAnalyticsJob'],
                    ['like', 'job', 'CreateBackupJob'],
                ],
            ])
            ->execute();
    }
}
