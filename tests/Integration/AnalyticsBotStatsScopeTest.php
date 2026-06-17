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
use lindemannrock\redirectmanager\records\AnalyticsRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;

/**
 * Pins bot breakdown site filtering.
 *
 * @since 5.32.0
 */
final class AnalyticsBotStatsScopeTest extends TestCase
{
    public function testTopBotsRespectSiteFilter(): void
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $this->seedBotAnalyticsRecord($siteId, 'ScopedBot');

        $stats = RedirectManager::$plugin->analytics->getBotStats([], 30);

        self::assertSame(0, $stats['total']);
        self::assertSame(0, $stats['botCount']);
        self::assertSame([], $stats['topAgents']);
        self::assertSame([], $stats['topBots']);
    }

    public function testTrafficBreakdownsUseHitWeightedRequestTypes(): void
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $start = new \DateTime('+10 years');
        $end = (clone $start)->modify('+1 day');
        $lastHit = $start->format('Y-m-d H:i:s');

        $this->seedBotAnalyticsRecord($siteId, '', 'normal', false, 7, null, null, null, 'desktop', 'Chrome', 'macOS', $lastHit);
        $this->seedBotAnalyticsRecord($siteId, 'Cache Manager', 'system', true, 5, 'Service Agent', 'LindemannRock', null, 'system', null, null, $lastHit);
        $this->seedBotAnalyticsRecord($siteId, 'Googlebot', 'bot', true, 3, 'Crawler', 'Google', null, 'desktop', 'Chrome', 'Linux', $lastHit);
        $this->seedBotAnalyticsRecord($siteId, 'ProbeBot', 'probe', true, 2, 'Security Scanner', 'Example', null, 'desktop', 'Firefox', 'Linux', $lastHit);

        $stats = RedirectManager::$plugin->analytics->getBotStats($siteId, 30, $start, $end);

        self::assertSame(17, $stats['total']);
        self::assertSame(7, $stats['humanCount']);
        self::assertSame(5, $stats['systemCount']);
        self::assertSame(3, $stats['botCount']);
        self::assertSame(2, $stats['probeCount']);
        self::assertSame(10, $stats['nonHumanCount']);
        self::assertSame(['normal', 'system', 'bot', 'probe'], $stats['chart']['types']);
        self::assertSame([7, 5, 3, 2], $stats['chart']['values']);
        self::assertSame('Cache Manager', $stats['topAgents'][0]['botName']);
        self::assertSame('system', $stats['topAgents'][0]['requestType']);
        self::assertSame(5, (int)$stats['topAgents'][0]['count']);

        $devices = RedirectManager::$plugin->analytics->getDeviceBreakdown($siteId, 30, $start, $end);
        self::assertSame(['Desktop', 'System'], $devices['labels']);
        self::assertSame([12, 5], $devices['values']);

        $browsers = RedirectManager::$plugin->analytics->getBrowserBreakdown($siteId, 30, $start, $end);
        self::assertSame(['Chrome', 'Firefox'], $browsers['labels']);
        self::assertSame([10, 2], $browsers['values']);

        $os = RedirectManager::$plugin->analytics->getOsBreakdown($siteId, 30, $start, $end);
        self::assertSame(['macOS', 'Linux'], $os['labels']);
        self::assertSame([7, 5], $os['values']);
    }

    private function seedBotAnalyticsRecord(
        int $siteId,
        string $botName,
        string $requestType = 'bot',
        bool $isRobot = true,
        int $count = 1,
        ?string $botCategory = null,
        ?string $botProducerName = null,
        ?string $trafficType = null,
        ?string $deviceType = null,
        ?string $browser = null,
        ?string $osName = null,
        ?string $lastHit = null,
    ): AnalyticsRecord
    {
        $url = '/' . self::MARKER . 'bot_stats_' . substr(uniqid('', true), -8);

        $record = new AnalyticsRecord();
        $record->siteId = $siteId;
        $record->url = $url;
        $record->urlParsed = $url;
        $record->handled = false;
        $record->sourcePlugin = 'redirect-manager';
        $record->count = $count;
        $record->requestType = $requestType;
        $record->isRobot = $isRobot;
        $record->botName = $botName;
        $record->botCategory = $botCategory;
        $record->botProducerName = $botProducerName;
        $record->trafficType = $trafficType ?? ($requestType === 'normal' ? 'human' : $requestType);
        $record->deviceType = $deviceType;
        $record->browser = $browser;
        $record->osName = $osName;
        $record->lastHit = $lastHit ?? date('Y-m-d H:i:s');

        self::assertTrue($record->save(false));

        return $record;
    }
}
