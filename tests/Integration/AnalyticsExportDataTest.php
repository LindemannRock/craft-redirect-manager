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
 * Pins analytics export scoping and the enriched bot/system metadata fields.
 *
 * @since 5.32.0
 */
final class AnalyticsExportDataTest extends TestCase
{
    public function testAnalyticsExportIncludesEnrichedRequestAndAgentFields(): void
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $record = $this->seedAnalyticsRecord($siteId);

        $rows = RedirectManager::$plugin->analytics->getExportData($siteId, [(int)$record->id]);

        self::assertCount(1, $rows);
        self::assertSame('system', $rows[0]['requestType']);
        self::assertSame('system', $rows[0]['trafficType']);
        self::assertSame('Yes', $rows[0]['isSystemAgent']);
        self::assertSame('Yes', $rows[0]['isRobot']);
        self::assertSame('Cache Manager', $rows[0]['botName']);
        self::assertSame('Service Agent', $rows[0]['botCategory']);
        self::assertSame('LindemannRock', $rows[0]['botProducerName']);
    }

    public function testSelectedAnalyticsIdsDoNotBypassSiteScope(): void
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $record = $this->seedAnalyticsRecord($siteId);

        $rows = RedirectManager::$plugin->analytics->getExportData([], [(int)$record->id]);

        self::assertSame([], $rows);
    }

    private function seedAnalyticsRecord(int $siteId): AnalyticsRecord
    {
        $url = '/' . self::MARKER . 'analytics_export_' . substr(uniqid('', true), -8);

        $record = new AnalyticsRecord();
        $record->siteId = $siteId;
        $record->url = $url;
        $record->urlParsed = $url;
        $record->handled = false;
        $record->sourcePlugin = 'redirect-manager';
        $record->count = 4;
        $record->requestType = 'system';
        $record->trafficType = 'system';
        $record->isRobot = true;
        $record->isSystemAgent = true;
        $record->botName = 'Cache Manager';
        $record->botCategory = 'Service Agent';
        $record->botProducerName = 'LindemannRock';
        $record->deviceType = 'system';
        $record->userAgent = 'CacheManager/1.0';
        $record->lastHit = date('Y-m-d H:i:s');

        self::assertTrue($record->save(false));

        return $record;
    }
}
