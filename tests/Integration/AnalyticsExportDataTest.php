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
        self::assertSame('system', $rows[0]['deviceType']);
        self::assertSame('CacheBot', $rows[0]['browser']);
        self::assertSame('1.0', $rows[0]['browserVersion']);
        self::assertSame('ServiceEngine', $rows[0]['browserEngine']);
        self::assertSame($this->analyticsTableHasColumn('language') ? 'en' : '', $rows[0]['language']);
        self::assertSame('Cache Manager', $rows[0]['botName']);
        self::assertSame('Service Agent', $rows[0]['botCategory']);
        self::assertSame('LindemannRock', $rows[0]['botProducerName']);
        self::assertSame('CacheManager/1.0', $rows[0]['userAgent']);
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
        $record->browser = 'CacheBot';
        $record->browserVersion = '1.0';
        $record->browserEngine = 'ServiceEngine';
        if ($this->analyticsTableHasColumn('language')) {
            $record->language = 'en';
        }
        $record->userAgent = 'CacheManager/1.0';
        $record->lastHit = date('Y-m-d H:i:s');

        self::assertTrue($record->save(false));

        return $record;
    }

    private function analyticsTableHasColumn(string $column): bool
    {
        $columns = Craft::$app->getDb()->getTableSchema(AnalyticsRecord::tableName(), true)?->columnNames ?? [];

        return in_array($column, $columns, true);
    }
}
