<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use DateTime;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\base\queue\PortableQueueScheduler;
use lindemannrock\redirectmanager\jobs\CreateBackupJob;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\RedirectManager;
use yii\db\Expression;

/**
 * Owns scheduled-backup eligibility, identity, and recurring queue lifecycle.
 *
 * @since 5.41.0
 */
final class ScheduledBackupScheduler extends Component
{
    public const PLUGIN_TOKEN = 'redirectmanager';
    public const RECURRING_OWNER = 'redirect-manager:backup:scheduled';
    public const LIFECYCLE_MUTEX = 'redirect-manager:backup:schedule';
    public const PORTABLE_MUTEX = 'redirect-manager:backup:portable';

    private const MUTEX_TIMEOUT = 5;
    private const BOOTSTRAP_MUTEX_TIMEOUT = 0;

    /**
     * Synchronize the recurring family during plugin bootstrap.
     */
    public function synchronize(?Settings $settings = null): void
    {
        $settings ??= RedirectManager::$plugin->getSettings();
        $nextRun = $this->getNextRun($settings);

        $this->withBootstrapQueueMutationLocks(
            fn() => $nextRun === null
                ? $this->cancelLocked()
                : $this->queueAtLocked($settings, $nextRun),
        );
    }

    /**
     * Replace the complete recurring family after an effective settings change.
     */
    public function replace(Settings $settings): void
    {
        $nextRun = $this->getNextRun($settings);

        $this->withQueueMutationLocks(function() use ($settings, $nextRun): void {
            $this->cancelLocked();

            if ($nextRun !== null) {
                $this->pushAtLocked($settings, $nextRun);
            }
        });
    }

    /**
     * Replace the recurring family only when its effective policy changed.
     *
     * @param array{enabled: bool, schedule: string} $previousState
     */
    public function replaceIfChanged(Settings $settings, array $previousState): bool
    {
        if ($previousState === $this->getEffectiveState($settings)) {
            return false;
        }

        $this->replace($settings);

        return true;
    }

    /**
     * Run one eligible recurring occurrence and always queue its successor.
     *
     * A failed storage attempt still propagates to Craft's queue while the next
     * occurrence remains scheduled, so unchanged configuration can recover
     * automatically when its storage becomes available again.
     *
     * @param callable(): void $backup
     * @return bool Whether the recurring backup callback ran
     */
    public function runOccurrence(callable $backup): bool
    {
        return $this->withLifecycleLock(function() use ($backup): bool {
            $settings = RedirectManager::$plugin->getSettings();
            if (!$this->isEligible($settings)) {
                return false;
            }

            try {
                $backup();
            } finally {
                $nextRun = $this->getNextRun($settings);
                if ($nextRun !== null) {
                    $this->withPortableLock(fn() => $this->queueAtLocked($settings, $nextRun));
                }
            }

            return true;
        });
    }

    /**
     * Resolve the effective scheduling state used for settings comparisons.
     *
     * @return array{enabled: bool, schedule: string}
     */
    public function getEffectiveState(Settings $settings): array
    {
        $schedule = $settings->getEffectiveBackupSchedule();
        $enabled = $settings->backupEnabled && $schedule !== 'disabled';

        return [
            'enabled' => $enabled,
            'schedule' => $enabled ? $schedule : 'disabled',
        ];
    }

    /**
     * Resolve the next scheduled wall-clock occurrence.
     */
    public function getNextRun(?Settings $settings = null, ?DateTime $from = null): ?DateTime
    {
        $settings ??= RedirectManager::$plugin->getSettings();
        if (!$this->isEligible($settings)) {
            return null;
        }

        return ScheduleHelper::calculateNext($settings->getEffectiveBackupSchedule(), $from);
    }

    /**
     * Format the next scheduled occurrence for Craft's queue UI.
     */
    public function getNextRunTime(?Settings $settings = null, ?DateTime $from = null): ?string
    {
        $settings ??= RedirectManager::$plugin->getSettings();
        $nextRun = $this->getNextRun($settings, $from);
        if ($nextRun === null) {
            return null;
        }

        return DateFormatHelper::formatCompactDatetimeFromSettings(
            $nextRun,
            $settings,
            null,
            false,
            pluginHandle: 'redirect-manager',
        );
    }

    /**
     * Check whether a healthy recurring owner or exact legacy row is pending.
     */
    public function hasPending(): bool
    {
        return $this->healthyOwnedRows() !== [] || $this->healthyLegacyRows() !== [];
    }

    private function isEligible(Settings $settings): bool
    {
        $state = $this->getEffectiveState($settings);

        return $state['enabled'];
    }

    private function queueAtLocked(Settings $settings, DateTime $nextRun): void
    {
        $ownerRows = $this->healthyOwnedRows();
        $matchingRows = array_values(array_filter(
            $ownerRows,
            fn(array $row): bool => $this->hasCurrentScheduleIdentity(
                (string)$row['job'],
                $settings->getEffectiveBackupSchedule(),
            ),
        ));

        if ($matchingRows !== []) {
            $retainedId = (string)$matchingRows[0]['id'];
            $this->deleteRows(array_values(array_filter(
                [...$ownerRows, ...$this->healthyLegacyRows()],
                static fn(array $row): bool => (string)$row['id'] !== $retainedId,
            )));
            return;
        }

        $this->deleteRows([...$ownerRows, ...$this->healthyLegacyRows()]);
        $this->pushAtLocked($settings, $nextRun);
    }

    private function pushAtLocked(Settings $settings, DateTime $nextRun): void
    {
        $nextRunTime = DateFormatHelper::formatCompactDatetimeFromSettings(
            $nextRun,
            $settings,
            null,
            false,
            pluginHandle: 'redirect-manager',
        );

        $jobId = PortableQueueScheduler::pushAt(
            job: new CreateBackupJob([
                'reason' => 'scheduled',
                'reschedule' => true,
                'schedule' => $settings->getEffectiveBackupSchedule(),
                'targetTimestamp' => $nextRun->getTimestamp(),
                'nextRunTime' => $nextRunTime,
                'recurringOwner' => self::RECURRING_OWNER,
            ]),
            targetTimestamp: $nextRun->getTimestamp(),
            identityTokens: [self::PLUGIN_TOKEN, 'CreateBackupJob', self::RECURRING_OWNER],
            mutexName: self::PORTABLE_MUTEX,
            mutexTimeout: self::MUTEX_TIMEOUT,
            priority: 1024,
            ttr: 1800,
        );

        if ($jobId === null) {
            throw new \RuntimeException('Scheduled-backup queue push did not return a job ID.');
        }
    }

    private function hasCurrentScheduleIdentity(string $payload, string $schedule): bool
    {
        if ($payload === '' || !str_contains($payload, self::RECURRING_OWNER)) {
            return false;
        }

        $phpSchedule = sprintf('s:8:"schedule";s:%d:"%s";', strlen($schedule), $schedule);
        $phpTarget = preg_match('/s:15:"targetTimestamp";i:[1-9][0-9]*;/', $payload) === 1;
        $jsonSchedule = preg_match(
            '/"schedule"\s*:\s*"' . preg_quote($schedule, '/') . '"/',
            $payload,
        ) === 1;
        $jsonTarget = preg_match('/"targetTimestamp"\s*:\s*[1-9][0-9]*/', $payload) === 1;

        return (str_contains($payload, $phpSchedule) && $phpTarget) || ($jsonSchedule && $jsonTarget);
    }

    private function cancelLocked(): int
    {
        return $this->cancelNewOwnerLocked() + $this->deleteRows($this->legacyRows());
    }

    private function cancelNewOwnerLocked(): int
    {
        return $this->deleteRows($this->ownedRows());
    }

    /**
     * @return list<array{id: int|string, job: string, timePushed: int|string, delay: int|string, priority: int|string}>
     */
    private function healthyOwnedRows(): array
    {
        /** @var list<array{id: int|string, job: string, timePushed: int|string, delay: int|string, priority: int|string}> */
        return $this->ownedQuery()
            ->andWhere(['fail' => false, 'timeUpdated' => null])
            ->orderBy(new Expression('[[timePushed]] + [[delay]] ASC'))
            ->addOrderBy(['priority' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * @return list<array{id: int|string, job: string, timePushed: int|string, delay: int|string, priority: int|string}>
     */
    private function ownedRows(): array
    {
        /** @var list<array{id: int|string, job: string, timePushed: int|string, delay: int|string, priority: int|string}> */
        return $this->ownedQuery()->all();
    }

    private function ownedQuery(): Query
    {
        return (new Query())
            ->from('{{%queue}}')
            ->select(['id', 'job', 'timePushed', 'delay', 'priority'])
            ->where(['like', 'job', self::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->andWhere(['like', 'job', self::RECURRING_OWNER]);
    }

    /**
     * @return list<array{id: int|string, job: string, timePushed: int|string, delay: int|string, priority: int|string}>
     */
    private function healthyLegacyRows(): array
    {
        $rows = $this->legacyQuery()
            ->andWhere(['fail' => false, 'timeUpdated' => null])
            ->orderBy(new Expression('[[timePushed]] + [[delay]] ASC'))
            ->addOrderBy(['priority' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $this->filterLegacyRows($rows);
    }

    /**
     * @return list<array{id: int|string, job: string, timePushed: int|string, delay: int|string, priority: int|string}>
     */
    private function legacyRows(): array
    {
        return $this->filterLegacyRows($this->legacyQuery()->all());
    }

    private function legacyQuery(): Query
    {
        return (new Query())
            ->from('{{%queue}}')
            ->select(['id', 'job', 'timePushed', 'delay', 'priority'])
            ->where(['like', 'job', self::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->andWhere(['not like', 'job', self::RECURRING_OWNER]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<array{id: int|string, job: string, timePushed: int|string, delay: int|string, priority: int|string}>
     */
    private function filterLegacyRows(array $rows): array
    {
        $legacyRows = [];
        foreach ($rows as $row) {
            $payload = (string)($row['job'] ?? '');
            if ($this->isLegacyPayload($payload)) {
                /** @var array{id: int|string, job: string, timePushed: int|string, delay: int|string, priority: int|string} $row */
                $legacyRows[] = $row;
            }
        }

        return $legacyRows;
    }

    private function isLegacyPayload(string $payload): bool
    {
        if ($payload === '' || str_contains($payload, self::RECURRING_OWNER)) {
            return false;
        }

        if (!str_contains($payload, self::PLUGIN_TOKEN) || !str_contains($payload, 'CreateBackupJob')) {
            return false;
        }

        $phpReason = str_contains($payload, 's:6:"reason";s:9:"scheduled";');
        $phpReschedule = str_contains($payload, 's:10:"reschedule";b:1;');
        $jsonReason = preg_match('/"reason"\s*:\s*"scheduled"/', $payload) === 1;
        $jsonReschedule = preg_match('/"reschedule"\s*:\s*true/', $payload) === 1;

        return ($phpReason && $phpReschedule) || ($jsonReason && $jsonReschedule);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function deleteRows(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $ids = array_map(static fn(array $row): string => (string)$row['id'], $rows);
        $deleted = Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', ['id' => $ids])
            ->execute();

        if ($deleted !== count($ids)) {
            throw new \RuntimeException('Scheduled-backup queue cancellation was incomplete.');
        }

        return $deleted;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withLifecycleLock(callable $callback): mixed
    {
        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire(self::LIFECYCLE_MUTEX, self::MUTEX_TIMEOUT)) {
            throw new \RuntimeException('Unable to acquire the scheduled-backup lifecycle lock.');
        }

        try {
            return $callback();
        } finally {
            $mutex->release(self::LIFECYCLE_MUTEX);
        }
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withPortableLock(callable $callback): mixed
    {
        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire(self::PORTABLE_MUTEX, self::MUTEX_TIMEOUT)) {
            throw new \RuntimeException('Unable to acquire the portable scheduled-backup queue lock.');
        }

        try {
            return $callback();
        } finally {
            $mutex->release(self::PORTABLE_MUTEX);
        }
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withQueueMutationLocks(callable $callback): mixed
    {
        return $this->withLifecycleLock(fn() => $this->withPortableLock($callback));
    }

    /** Attempt bootstrap reconciliation without blocking normal request processing. */
    private function withBootstrapQueueMutationLocks(callable $callback): bool
    {
        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire(self::LIFECYCLE_MUTEX, self::BOOTSTRAP_MUTEX_TIMEOUT)) {
            Craft::warning(
                'Scheduled-backup bootstrap reconciliation deferred because the lifecycle lock is busy.',
                'redirect-manager',
            );

            return false;
        }

        try {
            if (!$mutex->acquire(self::PORTABLE_MUTEX, self::BOOTSTRAP_MUTEX_TIMEOUT)) {
                Craft::warning(
                    'Scheduled-backup bootstrap reconciliation deferred because the portable lock is busy.',
                    'redirect-manager',
                );

                return false;
            }

            try {
                $callback();

                return true;
            } finally {
                $mutex->release(self::PORTABLE_MUTEX);
            }
        } finally {
            $mutex->release(self::LIFECYCLE_MUTEX);
        }
    }
}
