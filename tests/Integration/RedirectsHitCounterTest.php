<?php

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use Craft;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\tests\TestCase;

/**
 * Pins the atomic-SQL hit-count increment in
 * {@see \lindemannrock\redirectmanager\services\RedirectsService::findRedirect()}
 * (the underlying private `incrementHitCount()`).
 *
 * The increment uses `[[hitCount]] + 1` (an SQL expression). The naïve form
 * `['hitCount' => $record->hitCount + 1]` would read the stale in-memory
 * value, so two concurrent 404 handlers could both write `n + 1` and lose a
 * count. This is the same audit pattern that bit shortlink-manager (#3.9)
 * and smartlink-manager — this test pins the atomic shape so a regression
 * to the stale form would fail in CI.
 *
 * Caching is disabled so each test iteration walks the DB-match branch
 * deterministically. The cache-hit path also increments, but mixing the two
 * paths within one test makes the accumulation count harder to read.
 *
 * @since 5.30.0
 */
final class RedirectsHitCounterTest extends TestCase
{
    private bool $savedCacheEnabled = false;

    protected function setUp(): void
    {
        parent::setUp();
        $settings = $this->settings();
        $this->savedCacheEnabled = $settings->enableRedirectCache;
        $settings->enableRedirectCache = false;
    }

    protected function tearDown(): void
    {
        $this->settings()->enableRedirectCache = $this->savedCacheEnabled;
        parent::tearDown();
    }

    public function testFindRedirectIncrementsHitCountFromZeroToOne(): void
    {
        $redirect = $this->seedRedirect();
        $this->assertSame(0, $this->fetchHitCountFromDb($redirect->id));

        $match = $this->redirects->findRedirect(
            'https://example.com' . $redirect->sourceUrlParsed,
            $redirect->sourceUrlParsed,
        );

        $this->assertNotNull($match, 'Seeded redirect should match its own source URL.');
        $this->assertSame($redirect->id, $match['id']);
        $this->assertSame(1, $this->fetchHitCountFromDb($redirect->id));
    }

    public function testMultipleFindRedirectCallsAccumulate(): void
    {
        $redirect = $this->seedRedirect();

        for ($i = 0; $i < 5; $i++) {
            $this->redirects->findRedirect(
                'https://example.com' . $redirect->sourceUrlParsed,
                $redirect->sourceUrlParsed,
            );
        }

        $this->assertSame(5, $this->fetchHitCountFromDb($redirect->id));
    }

    public function testIncrementUsesAtomicSqlAndDoesNotClobberConcurrentWrites(): void
    {
        $redirect = $this->seedRedirect();
        $this->assertSame(0, $redirect->hitCount, 'Seeded record starts at 0 hits.');

        // Simulate a concurrent worker advancing the DB column while our
        // in-memory record still believes `hitCount = 0`. The atomic
        // `[[hitCount]] + 1` SQL expression must read the *current* DB value,
        // not the stale in-memory one.
        Craft::$app->getDb()
            ->createCommand()
            ->update(RedirectRecord::tableName(), ['hitCount' => 100], ['id' => $redirect->id])
            ->execute();

        $this->assertSame(0, $redirect->hitCount);
        $this->assertSame(100, $this->fetchHitCountFromDb($redirect->id));

        $this->redirects->findRedirect(
            'https://example.com' . $redirect->sourceUrlParsed,
            $redirect->sourceUrlParsed,
        );

        // If the increment regressed to `$record->hitCount + 1`, the DB
        // would now hold `1` (clobbering the concurrent +100). The atomic
        // expression instead reads from disk and lands on 101.
        $this->assertSame(
            101,
            $this->fetchHitCountFromDb($redirect->id),
            'Atomic SQL expression must read fresh value from disk.',
        );
    }
}
