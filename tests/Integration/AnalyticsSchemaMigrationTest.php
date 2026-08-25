<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use Craft;
use craft\db\Connection;
use craft\db\Query;
use lindemannrock\base\testing\IntegrationTestCase;
use lindemannrock\redirectmanager\migrations\Install;
use lindemannrock\redirectmanager\migrations\m260825_000000_create_analytics_daily;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

/**
 * Verifies clean-install and populated-upgrade analytics schema parity.
 *
 * Each test uses a unique table prefix inside the disposable suite database.
 * The suite runner owns and removes that database and its grant on success,
 * failure, and interruption; this class additionally removes every exact
 * prefixed table it creates.
 *
 * @since 5.41.0
 */
#[CoversClass(Install::class)]
#[CoversClass(m260825_000000_create_analytics_daily::class)]
final class AnalyticsSchemaMigrationTest extends IntegrationTestCase
{
    /** @var list<array{connection: Connection, prefix: string}> */
    private array $ownedSchemas = [];
    private int $outputBufferLevel = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputBufferLevel = ob_get_level();
        ob_start();
    }

    public function testFreshInstallCreatesTheDailyAuthorityWithExpectedIndexes(): void
    {
        [$db, $prefix] = $this->newOwnedConnection();
        $this->createCoreReferences($db);

        $install = new Install(['db' => $db]);
        self::assertTrue($install->safeUp());

        $table = $db->getSchema()->getTableSchema('{{%redirectmanager_analytics_daily}}', true);
        self::assertNotNull($table);
        self::assertSame($prefix . 'redirectmanager_analytics_daily', $table->fullName);
        self::assertSame($this->expectedDailyColumns(), array_keys($table->columns));
        self::assertSame(0, (int)(new Query())->from('{{%redirectmanager_analytics_daily}}')->count('*', $db));
        self::assertContains(
            ['bucketKey'],
            array_values($db->getSchema()->findUniqueIndexes($table)),
        );
        self::assertTrue($install->safeDown());
        self::assertFalse($db->tableExists('{{%redirectmanager_analytics_daily}}'));
    }

    public function testPopulatedUpgradeHasAnEmptyCutoverAndPreservesLegacySummaryBytes(): void
    {
        [$db, $prefix] = $this->newOwnedConnection();
        $this->createCoreReferences($db);

        $install = new Install(['db' => $db]);
        self::assertTrue($install->safeUp());
        $db->createCommand()->dropTable('{{%redirectmanager_analytics_daily}}')->execute();
        $legacy = [
            'siteId' => 1,
            'url' => '/historical',
            'urlParsed' => '/historical',
            'handled' => true,
            'redirectId' => 42,
            'sourcePlugin' => 'redirect-manager',
            'count' => 17,
            'referrer' => 'https://latest.example.test',
            'deviceType' => 'desktop',
            'browser' => 'Latest Browser',
            'osName' => 'Latest OS',
            'country' => 'AE',
            'city' => 'Dubai',
            'trafficType' => 'human',
            'requestType' => 'normal',
            'lastHit' => '2026-08-24 12:34:56',
            'dateCreated' => '2026-08-01 00:00:00',
            'dateUpdated' => '2026-08-24 12:34:56',
            'uid' => '00000000-0000-4000-8000-000000000012',
        ];
        $db->createCommand()->insert('{{%redirectmanager_analytics}}', $legacy)->execute();
        $before = (new Query())->from('{{%redirectmanager_analytics}}')->one($db);

        self::assertFalse($db->tableExists('{{%redirectmanager_analytics_daily}}'));
        self::assertCount(4, $this->redirectManagerTables($db, $prefix));
        self::assertSame(1, (int)(new Query())->from('{{%redirectmanager_analytics}}')->count('*', $db));

        $migration = new m260825_000000_create_analytics_daily(['db' => $db]);
        self::assertTrue($migration->safeUp());
        self::assertSame($before, (new Query())->from('{{%redirectmanager_analytics}}')->one($db));
        self::assertSame(0, (int)(new Query())->from('{{%redirectmanager_analytics_daily}}')->count('*', $db));
        self::assertSame($this->expectedDailyColumns(), array_keys($db->getSchema()->getTableSchema('{{%redirectmanager_analytics_daily}}', true)?->columns ?? []));

        self::assertTrue($migration->safeDown());
        self::assertFalse($db->tableExists('{{%redirectmanager_analytics_daily}}'));
        self::assertSame($before, (new Query())->from('{{%redirectmanager_analytics}}')->one($db));
        self::assertTrue($install->safeDown());
    }

    public function testFailedDailyTableCreationRemovesOnlyItsOwnedPartialSchema(): void
    {
        [$db, $prefix] = $this->newOwnedConnection();
        $migration = new m260825_000000_create_analytics_daily(['db' => $db]);

        try {
            $migration->safeUp();
            self::fail('The foreign key must reject a schema without the Craft sites table.');
        } catch (Throwable $exception) {
            self::assertStringContainsString('sites', $exception->getMessage());
        }

        self::assertFalse($db->tableExists('{{%redirectmanager_analytics_daily}}'));
        self::assertSame([], $this->redirectManagerTables($db, $prefix));
    }

    public function testConcurrentMysqlBucketUpsertsConvergeWithoutLostCounts(): void
    {
        [$db] = $this->newOwnedConnection();
        $this->createCoreReferences($db);
        self::assertTrue((new m260825_000000_create_analytics_daily(['db' => $db]))->safeUp());
        $rawTable = $db->getSchema()->getRawTableName('{{%redirectmanager_analytics_daily}}');
        $sql = sprintf(
            'INSERT INTO %s (`bucketKey`,`hitDate`,`url`,`urlParsed`,`handled`,`sourcePlugin`,`count`,`trafficType`,`requestType`,`lastHit`,`uid`,`dateCreated`,`dateUpdated`) '
            . 'VALUES (:bucketKey,:hitDate,:url,:urlParsed,0,:sourcePlugin,1,:trafficType,:requestType,:lastHit,:uid,:dateCreated,:dateUpdated) '
            . 'ON DUPLICATE KEY UPDATE `count` = `count` + 1, `lastHit` = VALUES(`lastHit`), `dateUpdated` = VALUES(`dateUpdated`)',
            $db->quoteTableName($rawTable),
        );
        $childCode = <<<'PHP'
$pdo = new PDO($argv[1], $argv[2], $argv[3], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$statement = $pdo->prepare(base64_decode($argv[4], true));
$statement->execute([
    ':bucketKey' => str_repeat('a', 64),
    ':hitDate' => '2026-08-25',
    ':url' => '/concurrent',
    ':urlParsed' => '/concurrent',
    ':sourcePlugin' => 'redirect-manager',
    ':trafficType' => 'human',
    ':requestType' => 'normal',
    ':lastHit' => '2026-08-25 12:00:00',
    ':uid' => $argv[5],
    ':dateCreated' => '2026-08-25 12:00:00',
    ':dateUpdated' => '2026-08-25 12:00:00',
]);
PHP;
        $processes = [];
        try {
            foreach ([
                '00000000-0000-4000-8000-000000000021',
                '00000000-0000-4000-8000-000000000022',
            ] as $uid) {
                $process = proc_open([
                    PHP_BINARY, '-r', $childCode, $db->dsn, (string)$db->username, (string)$db->password,
                    base64_encode($sql), $uid,
                ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes];
            }

            foreach ($processes as [$process, $pipes]) {
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), (string)$stdout . (string)$stderr);
            }
            $processes = [];
        } finally {
            foreach ($processes as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                if (is_resource($process)) {
                    proc_terminate($process, 15);
                    proc_close($process);
                }
            }
        }

        self::assertSame(2, (int)(new Query())
            ->select(['count'])
            ->from('{{%redirectmanager_analytics_daily}}')
            ->where(['bucketKey' => str_repeat('a', 64)])
            ->scalar($db));
        self::assertSame(1, (int)(new Query())->from('{{%redirectmanager_analytics_daily}}')->count('*', $db));
    }

    protected function tearDown(): void
    {
        $errors = [];
        foreach (array_reverse($this->ownedSchemas) as ['connection' => $db, 'prefix' => $prefix]) {
            try {
                $this->dropOwnedTables($db, $prefix);
                if ($this->ownedTables($db, $prefix) !== []) {
                    $errors[] = "Exact migration-test tables remain for {$prefix}.";
                }
                $db->close();
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        $this->ownedSchemas = [];
        while (ob_get_level() > $this->outputBufferLevel) {
            ob_end_clean();
        }
        parent::tearDown();

        if ($errors !== []) {
            throw new \RuntimeException('Analytics migration cleanup failed: ' . implode('; ', $errors));
        }
    }

    /** @return array{Connection, string} */
    private function newOwnedConnection(): array
    {
        $source = Craft::$app->getDb();
        if ($source->getDriverName() !== 'mysql') {
            self::markTestSkipped('The canonical disposable migration suite uses MySQL.');
        }
        $prefix = 'rm_schema_' . bin2hex(random_bytes(6)) . '_';
        $db = new Connection([
            'driverName' => $source->driverName,
            'dsn' => $source->dsn,
            'username' => $source->username,
            'password' => $source->password,
            'charset' => $source->charset,
            'tablePrefix' => $prefix,
            'schemaMap' => $source->schemaMap,
        ]);
        $db->open();
        self::assertSame([], $this->ownedTables($db, $prefix), 'Migration-test prefix must begin empty.');
        $this->ownedSchemas[] = ['connection' => $db, 'prefix' => $prefix];

        return [$db, $prefix];
    }

    private function createCoreReferences(Connection $db): void
    {
        $migration = new m260825_000000_create_analytics_daily(['db' => $db]);
        $migration->createTable('{{%sites}}', ['id' => $migration->primaryKey()]);
        $migration->createTable('{{%users}}', ['id' => $migration->primaryKey()]);
        $db->createCommand()->insert('{{%sites}}', ['id' => 1])->execute();
    }

    /** @return list<string> */
    private function expectedDailyColumns(): array
    {
        return [
            'id', 'bucketKey', 'hitDate', 'siteId', 'url', 'urlParsed', 'handled', 'redirectId',
            'sourcePlugin', 'count', 'referrer', 'ip', 'userAgent', 'language', 'deviceType',
            'deviceBrand', 'deviceModel', 'browser', 'browserVersion', 'browserEngine', 'osName',
            'osVersion', 'clientType', 'isRobot', 'isMobileApp', 'botName', 'botCategory', 'botUrl',
            'botProducerName', 'botProducerUrl', 'isSystemAgent', 'trafficType', 'requestType',
            'country', 'city', 'region', 'latitude', 'longitude', 'lastHit', 'uid', 'dateCreated',
            'dateUpdated',
        ];
    }

    /** @return list<string> */
    private function redirectManagerTables(Connection $db, string $prefix): array
    {
        return array_values(array_filter(
            array_map(static fn($table): string => $table->fullName, $db->getSchema()->getTableSchemas('', true)),
            static fn(string $table): bool => str_starts_with($table, $prefix . 'redirectmanager_'),
        ));
    }

    /** @return list<string> */
    private function ownedTables(Connection $db, string $prefix): array
    {
        return array_values(array_filter(
            array_map(static fn($table): string => $table->fullName, $db->getSchema()->getTableSchemas('', true)),
            static fn(string $table): bool => str_starts_with($table, $prefix),
        ));
    }

    private function dropOwnedTables(Connection $db, string $prefix): void
    {
        $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
        try {
            foreach ($this->ownedTables($db, $prefix) as $table) {
                $db->createCommand()->dropTable($db->quoteTableName($table))->execute();
            }
            $db->getSchema()->refresh();
        } finally {
            $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
        }
    }
}
