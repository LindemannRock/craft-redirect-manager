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
use craft\helpers\Db;
use craft\helpers\StringHelper;
use lindemannrock\redirectmanager\tests\TestCase;

/**
 * Pins geographic breakdowns to analytics hit counts, not row counts.
 *
 * @since 5.34.0
 */
final class AnalyticsGeoBreakdownCountTest extends TestCase
{
    public function testGeoBreakdownsUseSummedHitCounts(): void
    {
        $lastHit = new \DateTime('2099-01-01 12:00:00');
        $startDate = new \DateTime('2099-01-01 00:00:00');
        $endDate = new \DateTime('2099-01-02 00:00:00');

        $this->insertAnalytics('/' . self::MARKER . 'geo-ae', 20, 'AE', 'Dubai', $lastHit);
        $this->insertAnalytics('/' . self::MARKER . 'geo-us-1', 1, 'US', 'New York', $lastHit);
        $this->insertAnalytics('/' . self::MARKER . 'geo-us-2', 1, 'US', 'New York', $lastHit);

        $countries = $this->analytics->getTopCountries(null, 30, 10, $startDate, $endDate);
        $cities = $this->analytics->getTopCities(null, 30, 10, $startDate, $endDate);

        self::assertSame('AE', $countries[0]['country']);
        self::assertSame(20, $countries[0]['count']);
        self::assertSame(90.9, $countries[0]['percentage']);

        self::assertSame('Dubai', $cities[0]['city']);
        self::assertSame('AE', $cities[0]['country']);
        self::assertSame(20, $cities[0]['count']);
        self::assertSame(90.9, $cities[0]['percentage']);

        $emptyStart = new \DateTime('2099-01-03 00:00:00');
        $emptyEnd = new \DateTime('2099-01-04 00:00:00');
        self::assertSame([], $this->analytics->getTopCountries(null, 30, 10, $emptyStart, $emptyEnd));
        self::assertSame([], $this->analytics->getTopCities(null, 30, 10, $emptyStart, $emptyEnd));
    }

    public function testLimitedGeoPercentagesUseTheCompleteFilteredPopulation(): void
    {
        $lastHit = new \DateTime('2099-01-01 12:00:00');
        $startDate = new \DateTime('2099-01-01 00:00:00');
        $endDate = new \DateTime('2099-01-02 00:00:00');
        $sites = Craft::$app->getSites()->getAllSites();
        $siteId = (int)$sites[0]->id;
        $otherSiteId = (int)$sites[1]->id;

        for ($index = 0; $index < 17; $index++) {
            $country = chr(65 + intdiv($index, 26)) . chr(65 + ($index % 26));
            $this->insertAnalytics(
                '/' . self::MARKER . 'geo-tail-' . $index,
                17 - $index,
                $country,
                'City ' . $index,
                $lastHit,
                $siteId,
            );
        }
        $this->insertAnalytics('/' . self::MARKER . 'other-site', 1000, 'ZZ', 'Excluded', $lastHit, $otherSiteId);

        $countries = $this->analytics->getTopCountries($siteId, 30, 15, $startDate, $endDate);
        $cities = $this->analytics->getTopCities($siteId, 30, 15, $startDate, $endDate);

        self::assertCount(15, $countries);
        self::assertCount(15, $cities);
        self::assertSame(11.1, $countries[0]['percentage']);
        self::assertSame(11.1, $cities[0]['percentage']);
        self::assertSame(3, 153 - array_sum(array_column($countries, 'count')), 'The long tail remains outside top 15.');
    }

    private function insertAnalytics(
        string $url,
        int $count,
        string $country,
        string $city,
        \DateTime $lastHit,
        ?int $siteId = null,
    ): void {
        $now = Db::prepareDateForDb($lastHit);

        Craft::$app->getDb()->createCommand()->insert('{{%redirectmanager_analytics_daily}}', [
            'bucketKey' => hash('sha256', $url),
            'hitDate' => $lastHit->format('Y-m-d'),
            'siteId' => $siteId,
            'url' => $url,
            'urlParsed' => $url,
            'handled' => false,
            'count' => $count,
            'country' => $country,
            'city' => $city,
            'lastHit' => $now,
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => StringHelper::UUID(),
        ])->execute();
    }
}
