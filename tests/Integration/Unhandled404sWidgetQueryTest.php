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
 * Pins the unhandled-404 widget data source.
 *
 * @since 5.34.0
 */
final class Unhandled404sWidgetQueryTest extends TestCase
{
    public function testUnhandledQueryAppliesHandledFilterBeforeLimit(): void
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $handledBase = new \DateTimeImmutable('+10 years');
        $unhandledBase = new \DateTimeImmutable('+9 years');

        for ($i = 0; $i < 3; $i++) {
            $this->seedAnalyticsRecord($siteId, true, $handledBase->modify('+' . $i . ' minutes'));
        }

        $firstUnhandled = $this->seedAnalyticsRecord($siteId, false, $unhandledBase->modify('+1 minute'), 1000);
        $secondUnhandled = $this->seedAnalyticsRecord($siteId, false, $unhandledBase, 999);

        $rows = RedirectManager::$plugin->analytics->getUnhandled404s($siteId, 2);

        self::assertSame(
            [$firstUnhandled->urlParsed, $secondUnhandled->urlParsed],
            array_column($rows, 'urlParsed'),
            'Unhandled widget data must filter handled=false before applying the display limit.',
        );
    }

    private function seedAnalyticsRecord(int $siteId, bool $handled, \DateTimeImmutable $lastHit, int $count = 1): AnalyticsRecord
    {
        $url = '/' . self::MARKER . 'widget_unhandled_' . substr(uniqid('', true), -8);

        $record = new AnalyticsRecord();
        $record->siteId = $siteId;
        $record->url = $url;
        $record->urlParsed = $url;
        $record->handled = $handled;
        $record->sourcePlugin = 'redirect-manager';
        $record->count = $count;
        $record->requestType = 'normal';
        $record->isRobot = false;
        $record->lastHit = $lastHit->format('Y-m-d H:i:s');

        self::assertTrue($record->save(false));

        return $record;
    }
}
