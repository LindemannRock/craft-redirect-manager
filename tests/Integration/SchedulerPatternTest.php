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
    public function testAnalyticsCleanupSynchronizationKeepsOneExistingCleanupRow(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 30;
        $this->settings()->autoTrimAnalytics = true;

        $this->pushOwnedJob(new CleanupAnalyticsJob([
            'reschedule' => true,
        ]), 300);
        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));

        $this->analytics->maintenance->synchronizeRecurringCleanup();

        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));
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

    public function testAnalyticsCleanupSchedulesLimitMaintenanceWithoutRetention(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 0;
        $this->settings()->autoTrimAnalytics = true;

        $this->invokePrivate(RedirectManager::getInstance(), 'scheduleAnalyticsCleanup');

        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testAnalyticsCleanupSchedulesRetentionWithoutLimitMaintenance(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 30;
        $this->settings()->autoTrimAnalytics = false;

        $this->analytics->maintenance->synchronizeRecurringCleanup();

        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testAnalyticsCleanupSchedulesWhenRetentionAndLimitMaintenanceAreEnabled(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 30;
        $this->settings()->autoTrimAnalytics = true;

        $this->analytics->maintenance->synchronizeRecurringCleanup();

        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testAnalyticsCleanupCancelsOwnedRowsWhenAnalyticsIsDisabled(): void
    {
        $this->pushOwnedJob(new CleanupAnalyticsJob(['reschedule' => true]), 300);
        $this->settings()->enableAnalytics = false;
        $this->settings()->analyticsRetention = 30;
        $this->settings()->autoTrimAnalytics = true;

        $this->analytics->maintenance->synchronizeRecurringCleanup();

        $this->assertSame(0, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testAnalyticsCleanupCancelsOwnedRowsWhenNoMaintenanceIsEnabled(): void
    {
        $this->pushOwnedJob(new CleanupAnalyticsJob(['reschedule' => true]), 300);
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 0;
        $this->settings()->autoTrimAnalytics = false;

        $this->analytics->maintenance->synchronizeRecurringCleanup();

        $this->assertSame(0, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testAnalyticsCleanupHonorsSettingsChangedBetweenSynchronizations(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 30;
        $this->settings()->autoTrimAnalytics = true;
        $this->analytics->maintenance->synchronizeRecurringCleanup();
        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));

        $this->settings()->analyticsRetention = 0;
        $this->settings()->autoTrimAnalytics = false;
        $this->analytics->maintenance->synchronizeRecurringCleanup();
        $this->assertSame(0, $this->countQueueRows('CleanupAnalyticsJob'));

        $this->settings()->autoTrimAnalytics = true;
        $this->analytics->maintenance->synchronizeRecurringCleanup();
        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testAnalyticsSettingsChangeUsesTheRecurringSynchronizationPath(): void
    {
        $settings = $this->settings();
        $settings->enableAnalytics = true;
        $settings->analyticsRetention = 0;
        $settings->autoTrimAnalytics = true;

        RedirectManager::getInstance()->handleAnalyticsMaintenanceChange($settings);
        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));

        $settings->autoTrimAnalytics = false;
        RedirectManager::getInstance()->handleAnalyticsMaintenanceChange($settings);
        $this->assertSame(0, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testSuccessfulAnalyticsCleanupReschedulesOneRecurringJob(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 0;
        $this->settings()->autoTrimAnalytics = true;

        (new CleanupAnalyticsJob(['reschedule' => true]))->execute(Craft::$app->getQueue());

        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testIneligibleAnalyticsCleanupDoesNotDeleteOtherOwnedJobFamilies(): void
    {
        $this->pushOwnedJob(new CleanupAnalyticsJob(['reschedule' => true]), 300);
        $this->pushOwnedJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]), 300);
        $this->settings()->enableAnalytics = false;

        $this->analytics->maintenance->synchronizeRecurringCleanup();

        $this->assertSame(0, $this->countQueueRows('CleanupAnalyticsJob'));
        $this->assertSame(1, $this->countQueueRows('CreateBackupJob'));
    }

    public function testIneligibleAnalyticsCleanupPreservesRunningOwnedRows(): void
    {
        $this->pushOwnedJob(new CleanupAnalyticsJob(['reschedule' => true]), 300);
        $row = $this->latestQueueRow('CleanupAnalyticsJob');
        self::assertIsArray($row);
        Craft::$app->getDb()->createCommand()->update(
            '{{%queue}}',
            ['timeUpdated' => time()],
            ['id' => $row['id']],
        )->execute();
        $this->settings()->enableAnalytics = false;

        $this->analytics->maintenance->synchronizeRecurringCleanup();

        $this->assertSame(1, $this->countQueueRows('CleanupAnalyticsJob'));
    }

    public function testAnalyticsCleanupBootstrapCollapsesDuplicatePendingRows(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 30;

        $this->pushOwnedJob(new CleanupAnalyticsJob([
            'reschedule' => true,
        ]), 300);
        $this->pushOwnedJob(new CleanupAnalyticsJob([
            'reschedule' => true,
        ]), 300);
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
            null,
            false,
            pluginHandle: 'redirect-manager',
        );
    }
}
