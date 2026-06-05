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
use lindemannrock\redirectmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\redirectmanager\jobs\CreateBackupJob;
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

        $this->invokePrivate(\lindemannrock\redirectmanager\RedirectManager::getInstance(), 'scheduleBackupJob');

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

        \lindemannrock\redirectmanager\RedirectManager::getInstance()
            ->handleBackupScheduleChange($this->settings());

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

        \lindemannrock\redirectmanager\RedirectManager::getInstance()
            ->handleBackupScheduleChange($this->settings());

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
