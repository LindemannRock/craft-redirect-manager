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
use yii\base\Request as YiiRequest;

/**
 * Pins the atomic dedup-by-URL increment in
 * {@see \lindemannrock\redirectmanager\services\analytics\AnalyticsTrackingService::record404()}.
 *
 * The analytics table dedups by `(urlParsed, siteId)`: a repeat 404 to the
 * same URL increments the row's `count` column rather than inserting a new
 * row. The increment uses an SQL expression — `[[count]] + 1` — so two
 * concurrent 404 handlers can each add `+1` without losing a count. The
 * naïve `'count' => $existing['count'] + 1` would read the stale snapshot
 * from the lookup query and clobber a concurrent write.
 *
 * `record404()` reaches for `Craft::$app->request->getUserIP()` /
 * `->getUserAgent()` / `->getReferrer()` — three accessors that live on
 * `yii\web\Request` but not on `yii\console\Request`. The integration
 * bootstrap loads Craft as a console application, so a {@see StubConsoleRequest}
 * is swapped in for the duration of each test. The actual IP / UA values
 * don't matter for these assertions; only the column-update shape does.
 *
 * @since 5.30.0
 */
final class AnalyticsRecord404DedupTest extends TestCase
{
    private const TEST_SALT = '0123456789abcdef0123456789abcdef';

    private ?YiiRequest $savedRequest = null;

    private bool $savedEnableAnalytics = true;

    private bool $savedEnableGeo = false;

    private bool $savedAnonymize = false;

    private ?string $savedSalt = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedRequest = Craft::$app->getRequest();
        Craft::$app->set('request', new StubConsoleRequest(userIp: '203.0.113.42'));

        $settings = $this->settings();
        $this->savedEnableAnalytics = $settings->enableAnalytics;
        $this->savedEnableGeo = $settings->enableGeoDetection;
        $this->savedAnonymize = $settings->anonymizeIpAddress;
        $this->savedSalt = $settings->ipHashSalt;

        // Stable settings for the increment path:
        //  - analytics master switch ON, otherwise record404() short-circuits
        //  - geo lookup OFF, to avoid the integration touching MaxMind
        //  - IP salt set so AnalyticsIpHelper produces a deterministic hash
        //    rather than the "missing salt" sentinel branch.
        $settings->enableAnalytics = true;
        $settings->enableGeoDetection = false;
        $settings->anonymizeIpAddress = false;
        $settings->ipHashSalt = self::TEST_SALT;
    }

    protected function tearDown(): void
    {
        if ($this->savedRequest !== null) {
            Craft::$app->set('request', $this->savedRequest);
        }

        $settings = $this->settings();
        $settings->enableAnalytics = $this->savedEnableAnalytics;
        $settings->enableGeoDetection = $this->savedEnableGeo;
        $settings->anonymizeIpAddress = $this->savedAnonymize;
        $settings->ipHashSalt = $this->savedSalt;

        parent::tearDown();
    }

    public function testRepeat404IncrementsCountAtomicallyOnSameUrl(): void
    {
        $url = '/' . self::MARKER . 'analytics_' . substr(uniqid('', true), -8);

        $this->analytics->record404($url, false);
        $first = $this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => $url]);
        $this->assertNotNull($first, 'First record404() must insert a row.');
        $this->assertSame(1, (int) $first['count'], 'First hit lands at count=1.');

        $this->analytics->record404($url, false);
        $second = $this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => $url]);
        $this->assertNotNull($second);
        $this->assertSame((int) $first['id'], (int) $second['id'], 'No new row — same URL dedups onto the first one.');
        $this->assertSame(
            2,
            (int) $second['count'],
            'Second hit must increment count via the atomic `[[count]] + 1` expression.',
        );

        $this->assertSame(
            1,
            $this->countRows('{{%redirectmanager_analytics}}', ['urlParsed' => $url]),
            'The dedup contract: one (urlParsed, siteId) → one row.',
        );
    }
}
