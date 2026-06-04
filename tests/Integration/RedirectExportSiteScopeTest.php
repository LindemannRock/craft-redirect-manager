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
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\controllers\ImportExportController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionMethod;

/**
 * Pins the redirect export site-scope guard.
 *
 * Export uses the same visibility contract as the dashboard list: global
 * redirects (`siteId` null) are visible, and site-specific redirects are
 * visible only when the user can edit that site. Selected redirect IDs must
 * not bypass that scope.
 *
 * @since 5.32.0
 */
final class RedirectExportSiteScopeTest extends TestCase
{
    public function testExportQueryIncludesGlobalAndEditableSiteRedirects(): void
    {
        $editableSiteId = Craft::$app->getSites()->getPrimarySite()->id;

        $global = $this->seedGlobalRedirect();
        $editable = $this->seedRedirect(['siteId' => $editableSiteId]);

        $ids = $this->exportedRedirectIds(null, [$editableSiteId]);

        self::assertContains((int)$global->id, $ids);
        self::assertContains((int)$editable->id, $ids);
    }

    public function testExportQueryExcludesNonEditableSiteRedirects(): void
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;

        $global = $this->seedGlobalRedirect();
        $siteSpecific = $this->seedRedirect(['siteId' => $siteId]);

        $ids = $this->exportedRedirectIds(null, []);

        self::assertContains((int)$global->id, $ids);
        self::assertNotContains((int)$siteSpecific->id, $ids);
    }

    public function testSelectedRedirectIdsDoNotBypassSiteScope(): void
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;

        $global = $this->seedGlobalRedirect();
        $siteSpecific = $this->seedRedirect(['siteId' => $siteId]);

        $ids = $this->exportedRedirectIds([(int)$global->id, (int)$siteSpecific->id], []);

        self::assertSame([(int)$global->id], $ids);
    }

    /**
     * @param array<int>|null $redirectIds
     * @param array<int> $editableSiteIds
     * @return array<int>
     */
    private function exportedRedirectIds(?array $redirectIds, array $editableSiteIds): array
    {
        $controller = new ImportExportController('import-export', RedirectManager::getInstance());
        $method = new ReflectionMethod($controller, 'buildRedirectExportQuery');
        $method->setAccessible(true);

        $query = $method->invoke($controller, $redirectIds, $editableSiteIds);

        return array_map('intval', $query->select(['id'])->column());
    }

    private function seedGlobalRedirect(): RedirectRecord
    {
        $record = $this->seedRedirect();
        $record->siteId = null;
        self::assertTrue($record->save(false));

        return $record;
    }
}
