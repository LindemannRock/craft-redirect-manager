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
use lindemannrock\base\testing\StubConsoleRequest;
use lindemannrock\redirectmanager\tests\TestCase;
use Yii;
use yii\base\Request as YiiRequest;
use yii\log\Logger;

/**
 * Verifies that analytics recording remains synchronous without running
 * limit maintenance on the request path.
 *
 * @since 5.41.0
 */
final class AnalyticsRequestMaintenanceTest extends TestCase
{
    private const TEST_SALT = 'fedcba9876543210fedcba9876543210';

    private ?YiiRequest $savedRequest = null;

    private bool $savedDbLogging = false;

    private bool $savedDbProfiling = false;

    private int $savedFlushInterval = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedRequest = Craft::$app->getRequest();
        Craft::$app->set('request', new StubConsoleRequest(userIp: '203.0.113.84'));

        $settings = $this->settings();
        $settings->enableAnalytics = true;
        $settings->enableGeoDetection = false;
        $settings->anonymizeIpAddress = false;
        $settings->ipHashSalt = self::TEST_SALT;
        $settings->autoTrimAnalytics = true;
        $settings->analyticsLimit = 1000;

        $this->savedDbLogging = Craft::$app->getDb()->enableLogging;
        Craft::$app->getDb()->enableLogging = true;
        $this->savedDbProfiling = Craft::$app->getDb()->enableProfiling;
        Craft::$app->getDb()->enableProfiling = true;
        $this->savedFlushInterval = Yii::getLogger()->flushInterval;
        Yii::getLogger()->flushInterval = 1000000;
    }

    protected function tearDown(): void
    {
        Craft::$app->getDb()->enableLogging = $this->savedDbLogging;
        Craft::$app->getDb()->enableProfiling = $this->savedDbProfiling;
        Yii::getLogger()->flushInterval = $this->savedFlushInterval;
        if ($this->savedRequest !== null) {
            Craft::$app->set('request', $this->savedRequest);
        }

        parent::tearDown();
    }

    public function testHandledEventWritesOnceWithoutRunningLimitMaintenance(): void
    {
        $redirect = $this->seedRedirect();
        $url = '/' . self::MARKER . 'handled-maintenance-' . bin2hex(random_bytes(4));

        $queries = $this->captureAnalyticsQueries(fn() => $this->analytics->record404($url, true, [
            'redirectId' => $redirect->id,
            'source' => 'frontend',
        ]));

        $this->assertAnalyticsWriteWithoutCount($queries);
        $row = $this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => $url]);
        self::assertNotNull($row);
        self::assertSame(1, (int)$row['count']);
        self::assertSame(1, (int)$row['handled']);
        self::assertSame((int)$redirect->id, (int)$row['redirectId']);
        self::assertSame('frontend', $row['sourcePlugin']);
    }

    public function testUnhandledEventWritesOnceWithoutRunningLimitMaintenance(): void
    {
        $url = '/' . self::MARKER . 'unhandled-maintenance-' . bin2hex(random_bytes(4));

        $queries = $this->captureAnalyticsQueries(
            fn() => $this->analytics->record404($url, false, ['source' => 'frontend']),
        );

        $this->assertAnalyticsWriteWithoutCount($queries);
        $row = $this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => $url]);
        self::assertNotNull($row);
        self::assertSame(1, (int)$row['count']);
        self::assertSame(0, (int)$row['handled']);
        self::assertNull($row['redirectId']);
        self::assertSame('frontend', $row['sourcePlugin']);
    }

    public function testDisabledAnalyticsDoesNotReadOrWriteAnalyticsRows(): void
    {
        $this->settings()->enableAnalytics = false;
        $url = '/' . self::MARKER . 'disabled-maintenance-' . bin2hex(random_bytes(4));

        $queries = $this->captureAnalyticsQueries(
            fn() => $this->analytics->record404($url, false),
        );

        self::assertSame([], $queries);
        self::assertNull($this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => $url]));
    }

    /**
     * @return list<string>
     */
    private function captureAnalyticsQueries(callable $callback): array
    {
        $logger = Yii::getLogger();
        $offset = count($logger->messages);

        $callback();

        $queries = [];
        foreach (array_slice($logger->messages, $offset) as $message) {
            $sql = $message[0] ?? null;
            $level = $message[1] ?? null;
            $category = $message[2] ?? null;
            if (!is_string($sql) || !is_string($category)
                || $level !== Logger::LEVEL_PROFILE_BEGIN
                || !str_starts_with($category, 'yii\\db\\Command::')
                || !str_contains($sql, 'redirectmanager_analytics')
            ) {
                continue;
            }
            $queries[] = $sql;
        }

        return $queries;
    }

    /** @param list<string> $queries */
    private function assertAnalyticsWriteWithoutCount(array $queries): void
    {
        $writes = array_filter(
            $queries,
            static fn(string $sql): bool => preg_match('/^(INSERT|UPDATE)\b/i', ltrim($sql)) === 1,
        );
        $counts = array_filter(
            $queries,
            static fn(string $sql): bool => preg_match('/SELECT\s+COUNT\s*\(\s*\*\s*\)/i', $sql) === 1,
        );

        self::assertCount(1, $writes, "Expected one atomic analytics upsert.\n" . implode("\n", $queries));
        self::assertCount(0, $counts, "Request-time analytics must not run full-table count maintenance.\n" . implode("\n", $queries));
    }
}
