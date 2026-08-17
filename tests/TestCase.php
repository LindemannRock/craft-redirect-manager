<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests;

use Craft;
use craft\cache\FileCache;
use craft\db\Query;
use craft\queue\BaseJob;
use craft\queue\Queue;
use lindemannrock\base\testing\IntegrationTestCase;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\AnalyticsService;
use lindemannrock\redirectmanager\services\MatchingService;
use lindemannrock\redirectmanager\services\RedirectsService;
use lindemannrock\redirectmanager\services\ScheduledBackupScheduler;
use Throwable;
use yii\db\Transaction;

/**
 * Redirect Manager integration boundary with exact per-test ownership.
 *
 * Every test receives a database transaction, a connection-local temporary
 * queue table, and owned runtime/cache directories. Cleanup is idempotent and
 * is also invoked by PHPUnit's finished-test subscriber if child teardown
 * fails before reaching its parent.
 *
 * @since 5.30.0
 */
abstract class TestCase extends IntegrationTestCase
{
    /**
     * Prefix used to identify Redirect Manager test data.
     *
     * @since 5.41.0
     */
    public const MARKER = '__rdr_test_';

    private static ?self $activeTest = null;

    protected MatchingService $matching;
    protected RedirectsService $redirects;
    protected AnalyticsService $analytics;
    protected ScheduledBackupScheduler $scheduledBackups;

    private int $seedCounter = 0;
    /** @var array<string, mixed>|null */
    private ?array $settingsSnapshot = null;
    /** @var array<string, object> */
    private array $appComponentSnapshots = [];
    /** @var array<string, object> */
    private array $pluginComponentSnapshots = [];
    private ?Transaction $transaction = null;
    private ?object $originalQueue = null;
    private ?object $originalCache = null;
    private ?string $originalRuntimePath = null;
    private ?string $queueRawTable = null;
    private ?string $queueShadowTable = null;
    /** @var list<int> */
    private array $ownedRedirectIds = [];
    /** @var list<int> */
    private array $ownedAnalyticsIds = [];
    /** @var list<int> */
    private array $ownedQueueIds = [];
    private bool $isolationFinished = false;
    private bool $baseStateInitialised = false;

    protected function setUp(): void
    {
        self::$activeTest = $this;
        $this->isolationFinished = false;
        try {
            parent::setUp();
            $this->baseStateInitialised = true;
            $this->snapshotAppComponents();
            $this->settingsSnapshot = RedirectManager::$plugin->getSettings()->getAttributes();
            $this->isolateRuntimeAndCache();
            $this->isolateQueue();
            $this->transaction = Craft::$app->getDb()->beginTransaction();

            $this->matching = RedirectManager::$plugin->matching;
            $this->redirects = RedirectManager::$plugin->redirects;
            $this->analytics = RedirectManager::$plugin->analytics;
            $this->scheduledBackups = RedirectManager::$plugin->scheduledBackups;
            $this->seedCounter = 0;
        } catch (Throwable $exception) {
            try {
                $this->finishIsolation();
            } catch (Throwable $cleanupException) {
                fwrite(STDERR, 'Redirect Manager setup cleanup failed: ' . $cleanupException->getMessage() . PHP_EOL);
            }
            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        $this->finishIsolation();
    }

    /**
     * Runner fallback when child teardown exits before parent cleanup.
     *
     * @since 5.41.0
     */
    public static function finishActiveTestIsolation(): void
    {
        self::$activeTest?->finishIsolation();
    }

    /**
     * Seed and track one exact redirect row.
     *
     * @param array<string, mixed> $overrides
     */
    protected function seedRedirect(array $overrides = []): RedirectRecord
    {
        $this->seedCounter++;
        $runId = \craft\helpers\App::env('REDIRECT_MANAGER_TEST_RUN_ID');
        $runMarker = is_string($runId) ? substr($runId, 0, 8) : bin2hex(random_bytes(4));
        $marker = '/' . self::MARKER . $runMarker . '_' . $this->seedCounter . '_' . bin2hex(random_bytes(4));

        $record = new RedirectRecord();
        $record->sourceUrl = $overrides['sourceUrl'] ?? $marker;
        $record->sourceUrlParsed = $overrides['sourceUrlParsed'] ?? $record->sourceUrl;
        $record->destinationUrl = $overrides['destinationUrl'] ?? '/destination';
        $record->matchType = $overrides['matchType'] ?? 'exact';
        $record->redirectSrcMatch = $overrides['redirectSrcMatch'] ?? 'pathonly';
        $record->statusCode = $overrides['statusCode'] ?? 301;
        $record->siteId = $overrides['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id;
        $record->enabled = $overrides['enabled'] ?? true;
        $record->priority = $overrides['priority'] ?? 0;
        $record->creationType = $overrides['creationType'] ?? 'manual';
        $record->sourcePlugin = $overrides['sourcePlugin'] ?? 'redirect-manager';
        $record->elementId = $overrides['elementId'] ?? null;
        $record->hitCount = $overrides['hitCount'] ?? 0;

        $this->assertTrue($record->save(false), 'Seeded redirect must save: ' . json_encode($record->getErrors()));
        $this->ownedRedirectIds[] = (int)$record->id;

        return $record;
    }

    /** Push and track a job in the test's connection-local queue. */
    protected function pushOwnedJob(BaseJob $job, int $delay = 0): int
    {
        $id = (int)Craft::$app->getQueue()->delay($delay)->push($job);
        $this->ownedQueueIds[] = $id;
        return $id;
    }

    protected function fetchHitCountFromDb(int $id): int
    {
        $row = $this->fetchRow(RedirectRecord::tableName(), ['id' => $id]);
        $this->assertNotNull($row, "Redirect row {$id} not found.");
        return (int)$row['hitCount'];
    }

    protected function settings(): Settings
    {
        /** @var Settings $settings */
        $settings = RedirectManager::$plugin->getSettings();
        return $settings;
    }

    /** Replace a plugin component while retaining exact automatic restoration. */
    protected function replacePluginComponent(string $id, object $component): void
    {
        if (!isset($this->pluginComponentSnapshots[$id])) {
            $original = RedirectManager::$plugin->get($id);
            if (!is_object($original)) {
                throw new \RuntimeException("Redirect Manager component {$id} is not an object.");
            }
            $this->pluginComponentSnapshots[$id] = $original;
        }

        RedirectManager::$plugin->set($id, $component);
    }

    protected function cleanupExternalState(): void
    {
        // Runtime and cache are isolated under an exact Base-tracked path.
    }

    private function snapshotAppComponents(): void
    {
        foreach (['request', 'response', 'sites', 'user', 'config', 'mutex', 'elements'] as $id) {
            if (Craft::$app->has($id)) {
                $component = Craft::$app->get($id);
                if (is_object($component)) {
                    $this->appComponentSnapshots[$id] = $component;
                }
            }
        }
    }

    private function isolateRuntimeAndCache(): void
    {
        $this->originalRuntimePath = Craft::$app->getRuntimePath();
        $this->originalCache = Craft::$app->getCache();
        $runtimePath = $this->createTrackedTempDirectory('redirect-manager-runtime-');
        Craft::$app->setRuntimePath($runtimePath);
        Craft::$app->set('cache', new FileCache([
            'cachePath' => $runtimePath . '/cache',
            'keyPrefix' => 'redirect-manager-test-' . bin2hex(random_bytes(8)),
        ]));
    }

    private function isolateQueue(): void
    {
        $queue = Craft::$app->getQueue();
        if (!$queue instanceof Queue) {
            throw new \RuntimeException('Redirect Manager tests require Craft\'s database queue.');
        }
        $this->originalQueue = $queue;
        $db = Craft::$app->getDb();
        if ($db->getDriverName() !== 'mysql') {
            throw new \RuntimeException('The disposable Redirect Manager suite currently requires MySQL.');
        }
        $this->queueRawTable = $db->getSchema()->getRawTableName($queue->tableName);
        $this->queueShadowTable = $this->queueRawTable . '_rm_' . bin2hex(random_bytes(8));
        $db->createCommand(sprintf(
            'CREATE TEMPORARY TABLE %s LIKE %s',
            $db->quoteTableName($this->queueShadowTable),
            $db->quoteTableName($this->queueRawTable),
        ))->execute();
        $db->createCommand(sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $db->quoteTableName($this->queueShadowTable),
            $db->quoteTableName($this->queueRawTable),
        ))->execute();
        $this->queueShadowTable = null;
        Craft::$app->set('queue', new Queue([
            'db' => $db,
            'mutex' => $queue->mutex,
            'tableName' => $queue->tableName,
            'channel' => $queue->channel,
            'mutexTimeout' => $queue->mutexTimeout,
        ]));
    }

    private function captureOwnedIds(): void
    {
        $this->ownedRedirectIds = array_values(array_unique(array_merge(
            $this->ownedRedirectIds,
            array_map('intval', (new Query())->select(['id'])->from(RedirectRecord::tableName())->column()),
        )));
        $this->ownedAnalyticsIds = array_map(
            'intval',
            (new Query())->select(['id'])->from('{{%redirectmanager_analytics}}')->column(),
        );
        $this->ownedQueueIds = array_values(array_unique(array_merge(
            $this->ownedQueueIds,
            array_map('intval', (new Query())->select(['id'])->from('{{%queue}}')->column()),
        )));
    }

    private function finishIsolation(): void
    {
        if ($this->isolationFinished) {
            return;
        }
        $this->isolationFinished = true;
        $errors = [];

        $this->runCleanupStep($errors, fn() => $this->captureOwnedIds());
        $this->runCleanupStep($errors, function(): void {
            if ($this->transaction !== null && $this->transaction->getIsActive()) {
                $this->transaction->rollBack();
            }
            $this->transaction = null;
        });
        $this->runCleanupStep($errors, fn() => $this->verifyOwnedRowsRemoved());
        $this->runCleanupStep($errors, function(): void {
            foreach ($this->pluginComponentSnapshots as $id => $component) {
                RedirectManager::$plugin->set($id, $component);
            }
            $this->pluginComponentSnapshots = [];
        });
        $this->runCleanupStep($errors, function(): void {
            foreach ($this->appComponentSnapshots as $id => $component) {
                Craft::$app->set($id, $component);
            }
            $this->appComponentSnapshots = [];
        });
        $this->runCleanupStep($errors, function(): void {
            if ($this->settingsSnapshot !== null) {
                RedirectManager::$plugin->getSettings()->setAttributes($this->settingsSnapshot, false);
                $this->settingsSnapshot = null;
            }
        });
        $this->runCleanupStep($errors, function(): void {
            if ($this->originalQueue !== null) {
                Craft::$app->set('queue', $this->originalQueue);
                $this->originalQueue = null;
            }
            $db = Craft::$app->getDb();
            foreach ([$this->queueRawTable, $this->queueShadowTable] as $table) {
                if ($table !== null) {
                    $db->createCommand('DROP TEMPORARY TABLE IF EXISTS ' . $db->quoteTableName($table))->execute();
                }
            }
            $this->queueRawTable = null;
            $this->queueShadowTable = null;
        });
        $this->runCleanupStep($errors, function(): void {
            if ($this->originalCache !== null) {
                Craft::$app->set('cache', $this->originalCache);
                $this->originalCache = null;
            }
            if ($this->originalRuntimePath !== null) {
                Craft::$app->setRuntimePath($this->originalRuntimePath);
                $this->originalRuntimePath = null;
            }
        });

        if ($this->baseStateInitialised) {
            $this->runCleanupStep($errors, fn() => parent::tearDown());
            $this->baseStateInitialised = false;
        }
        self::$activeTest = null;

        if ($errors !== []) {
            $messages = array_map(
                static fn(Throwable $error): string => $error::class . ': ' . $error->getMessage(),
                $errors,
            );
            throw new \RuntimeException(
                'Redirect Manager test isolation cleanup failed: ' . implode(' | ', $messages),
                0,
                $errors[0],
            );
        }
    }

    /** @param list<Throwable> $errors */
    private function runCleanupStep(array &$errors, callable $cleanup): void
    {
        try {
            $cleanup();
        } catch (Throwable $exception) {
            $errors[] = $exception;
        }
    }

    private function verifyOwnedRowsRemoved(): void
    {
        foreach ([
            [RedirectRecord::tableName(), $this->ownedRedirectIds],
            ['{{%redirectmanager_analytics}}', $this->ownedAnalyticsIds],
            ['{{%queue}}', $this->ownedQueueIds],
        ] as [$table, $ids]) {
            if ($ids !== [] && (new Query())->from($table)->where(['id' => $ids])->exists()) {
                throw new \RuntimeException("Exact test-owned rows remain in {$table} after rollback.");
            }
        }
        $this->ownedRedirectIds = [];
        $this->ownedAnalyticsIds = [];
        $this->ownedQueueIds = [];
    }
}
