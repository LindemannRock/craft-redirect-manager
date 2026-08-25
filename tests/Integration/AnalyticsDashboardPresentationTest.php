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
use lindemannrock\redirectmanager\controllers\AnalyticsController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionMethod;

/**
 * Pins site-scoped dashboard links and already-local chart dates.
 *
 * @since 5.41.0
 */
final class AnalyticsDashboardPresentationTest extends TestCase
{
    public function testDashboardPreservesVisibleWinnerAndUsesSiteScopedFallback(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        self::assertGreaterThanOrEqual(2, count($sites));
        $siteId = (int)$sites[0]->id;
        $otherSiteId = (int)$sites[1]->id;
        $url = '/' . self::MARKER . 'dashboard_' . bin2hex(random_bytes(4));

        $global = $this->seedRedirect(['sourceUrl' => $url, 'sourceUrlParsed' => $url, 'siteId' => $siteId]);
        $global->siteId = null;
        $global->siteIdKey = 0;
        self::assertTrue($global->save(false));
        $siteWinner = $this->seedRedirect(['sourceUrl' => $url, 'sourceUrlParsed' => $url, 'siteId' => $siteId]);
        $otherSite = $this->seedRedirect(['sourceUrl' => $url, 'sourceUrlParsed' => $url, 'siteId' => $otherSiteId]);

        $rows = $this->prepareRows([
            ['handled' => true, 'urlParsed' => $url, 'siteId' => $siteId, 'redirectId' => (int)$global->id],
            ['handled' => true, 'urlParsed' => $url, 'siteId' => $siteId, 'redirectId' => null],
            ['handled' => true, 'urlParsed' => $url, 'siteId' => $siteId, 'redirectId' => (int)$otherSite->id],
        ]);

        self::assertSame((int)$global->id, $rows[0]['redirectId'], 'The exact visible stored winner must be preserved.');
        self::assertSame((int)$siteWinner->id, $rows[1]['redirectId'], 'Fallback must prefer the row site over global scope.');
        self::assertSame((int)$siteWinner->id, $rows[2]['redirectId'], 'An inaccessible cross-site ID must not leak into the link.');
    }

    public function testAlreadyLocalNegativeOffsetChartDateIsNotConvertedAgain(): void
    {
        Craft::$app->setTimeZone('America/Los_Angeles');
        $controller = new AnalyticsController('analytics', RedirectManager::getInstance());
        $method = new ReflectionMethod($controller, '_normalizeChartData');

        $rows = $method->invoke($controller, [
            ['date' => '2026-01-01', 'total' => 2, 'handled' => 1, 'unhandled' => 1],
        ], null, null);

        self::assertSame('2026-01-01', $rows[0]['date']);
        self::assertSame(2, $rows[0]['total']);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function prepareRows(array $rows): array
    {
        $controller = new AnalyticsController('analytics', RedirectManager::getInstance());
        $method = new ReflectionMethod($controller, '_prepareDashboardAnalyticsRows');

        return $method->invoke($controller, $rows);
    }
}
