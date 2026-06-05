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
        self::assertSame([], $stats['topBots']);
    }

    private function seedBotAnalyticsRecord(int $siteId, string $botName): AnalyticsRecord
    {
        $url = '/' . self::MARKER . 'bot_stats_' . substr(uniqid('', true), -8);

        $record = new AnalyticsRecord();
        $record->siteId = $siteId;
        $record->url = $url;
        $record->urlParsed = $url;
        $record->handled = false;
        $record->sourcePlugin = 'redirect-manager';
        $record->count = 1;
        $record->requestType = 'bot';
        $record->isRobot = true;
        $record->botName = $botName;
        $record->lastHit = date('Y-m-d H:i:s');

        self::assertTrue($record->save(false));

        return $record;
    }
}
