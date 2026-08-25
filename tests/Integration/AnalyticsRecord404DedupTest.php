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
use lindemannrock\redirectmanager\services\analytics\AnalyticsTrackingService;
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

    public function testHistoricalDimensionsRemainTruthfulWhenLatestSummaryChanges(): void
    {
        $url = '/' . self::MARKER . 'historical_' . substr(uniqid('', true), -8);
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;

        Craft::$app->set('request', new StubConsoleRequest(
            userAgent: 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0',
            referrer: 'https://first.example.test/',
        ));
        $this->analytics->record404($url, false, ['siteId' => $siteId]);

        Craft::$app->set('request', new StubConsoleRequest(
            userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Version/17.0 Mobile/15E148 Safari/604.1',
            referrer: 'https://second.example.test/',
        ));
        $redirect = $this->seedRedirect(['sourceUrl' => $url, 'sourceUrlParsed' => $url, 'siteId' => $siteId]);
        $this->analytics->record404($url, true, [
            'siteId' => $siteId,
            'redirectId' => (int)$redirect->id,
        ]);

        $summary = $this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => $url, 'siteId' => $siteId]);
        self::assertNotNull($summary);
        $rows = $this->analytics->getExportData($siteId, [(int)$summary['id']]);

        self::assertCount(2, $rows, 'Both supported request histories must remain reportable.');
        self::assertSame(2, array_sum(array_column($rows, 'count')));
        self::assertSame(['No', 'Yes'], array_values(array_unique(array_column($rows, 'handled'))));
        self::assertSame(
            ['https://first.example.test/', 'https://second.example.test/'],
            array_values(array_unique(array_column($rows, 'referrer'))),
        );
    }

    public function testDailyAuthorityPreservesTransitionsAcrossLocalDays(): void
    {
        $url = '/' . self::MARKER . 'days_' . substr(uniqid('', true), -8);
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $redirect = $this->seedRedirect(['sourceUrl' => $url, 'sourceUrlParsed' => $url, 'siteId' => $siteId]);
        $tracking = new class() extends AnalyticsTrackingService {
            /** @var list<\DateTime> */
            public array $times = [];

            protected function currentTimeUtc(): \DateTime
            {
                $time = array_shift($this->times);
                if (!$time instanceof \DateTime) {
                    throw new \RuntimeException('A controlled analytics time is required.');
                }
                return clone $time;
            }
        };
        $tracking->times = [
            new \DateTime('2026-08-20 12:00:00', new \DateTimeZone('UTC')),
            new \DateTime('2026-08-20 13:00:00', new \DateTimeZone('UTC')),
            new \DateTime('2026-08-21 12:00:00', new \DateTimeZone('UTC')),
        ];
        $originalTracking = $this->analytics->tracking;
        $this->analytics->tracking = $tracking;
        $settings = $this->settings();
        $settings->enableGeoDetection = true;
        $settings->defaultCountry = 'AE';
        $settings->defaultCity = 'Dubai';

        Craft::$app->set('request', new StubConsoleRequest(
            userIp: '127.0.0.1',
            userAgent: 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0',
            referrer: 'https://first.example.test/',
        ));
        $this->analytics->record404($url, false, ['siteId' => $siteId, 'source' => 'frontend']);

        $settings->defaultCountry = 'US';
        $settings->defaultCity = 'New York';
        Craft::$app->set('request', new StubConsoleRequest(
            userIp: '127.0.0.1',
            userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Version/17.0 Mobile Safari/604.1',
            referrer: 'https://second.example.test/',
        ));
        $context = ['siteId' => $siteId, 'source' => 'frontend', 'redirectId' => (int)$redirect->id];
        $this->analytics->record404($url, true, $context);
        $this->analytics->record404($url, true, $context);

        $rows = (new \craft\db\Query())
            ->from('{{%redirectmanager_analytics_daily}}')
            ->where(['urlParsed' => $url, 'siteId' => $siteId])
            ->orderBy(['hitDate' => SORT_ASC, 'handled' => SORT_ASC])
            ->all();
        self::assertCount(3, $rows);
        self::assertSame(3, array_sum(array_map('intval', array_column($rows, 'count'))));
        self::assertSame(['AE', 'US'], array_values(array_unique(array_column($rows, 'country'))));
        self::assertSame(['Dubai', 'New York'], array_values(array_unique(array_column($rows, 'city'))));

        $chart = $this->analytics->getChartData(
            $siteId,
            30,
            new \DateTime('2026-08-20 00:00:00', new \DateTimeZone('UTC')),
            new \DateTime('2026-08-22 00:00:00', new \DateTimeZone('UTC')),
        );
        self::assertSame([2, 1], array_map('intval', array_column($chart, 'total')));
        self::assertSame([1, 1], array_map('intval', array_column($chart, 'handled')));
        self::assertSame([1, 0], array_map('intval', array_column($chart, 'unhandled')));
        $this->analytics->tracking = $originalTracking;
    }

    public function testAggregateFailureRollsBackTheSummaryWrite(): void
    {
        $url = '/' . self::MARKER . 'rollback_' . substr(uniqid('', true), -8);
        $originalTracking = $this->analytics->tracking;
        $this->analytics->tracking = new class() extends AnalyticsTrackingService {
            protected function writeDailyAggregate(array $dailyData, string $now): void
            {
                throw new \RuntimeException('Synthetic dimensional write failure.');
            }
        };

        try {
            $this->analytics->record404($url, false);
            self::fail('The injected dimensional failure must escape the transaction.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Synthetic dimensional write failure.', $exception->getMessage());
        }

        self::assertNull($this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => $url]));
        self::assertNull($this->fetchRow('{{%redirectmanager_analytics_daily}}', ['urlParsed' => $url]));
        $this->analytics->tracking = $originalTracking;
    }
}
