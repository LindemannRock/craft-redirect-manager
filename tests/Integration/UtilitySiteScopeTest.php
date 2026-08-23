<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use Craft;
use craft\web\View;
use lindemannrock\redirectmanager\records\AnalyticsRecord;
use lindemannrock\redirectmanager\tests\Support\StubCpUser;
use lindemannrock\redirectmanager\tests\Support\StubEditableSites;
use lindemannrock\redirectmanager\tests\TestCase;
use lindemannrock\redirectmanager\utilities\RedirectManagerUtility;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins utility aggregates and destructive counts to editable-site scope.
 */
final class UtilitySiteScopeTest extends TestCase
{
    #[DataProvider('editableScopeProvider')]
    public function testUtilityAggregatesUseOneEditableSiteScope(
        int $editableCount,
        int $expectedRedirects,
        int $expectedActive,
        int $expectedAnalyticsRows,
        int $expectedHandled,
        int $expectedUnhandled,
    ): void {
        [$siteIds, $redirects, $analytics] = $this->seedScopedUtilityData();
        $editable = array_slice($siteIds, 0, $editableCount);
        $variables = $this->captureUtilityVariables($editable, [
            'utility:redirect-manager',
            'redirectManager:manageRedirects',
            'redirectManager:viewAnalytics',
            'redirectManager:clearAnalytics',
        ]);

        self::assertSame($expectedRedirects, (int)$variables['totalRedirects']);
        self::assertSame($expectedActive, (int)$variables['activeRedirects']);
        self::assertSame($expectedAnalyticsRows, (int)$variables['analyticsCount']);
        self::assertSame($expectedHandled, (int)$variables['handled']);
        self::assertSame($expectedUnhandled, (int)$variables['unhandled']);
        self::assertSame($expectedHandled + $expectedUnhandled, (int)$variables['total404s']);
        self::assertCount(3, $redirects);
        self::assertCount(2, $analytics);
    }

    /** @return iterable<string, array{int, int, int, int, int, int}> */
    public static function editableScopeProvider(): iterable
    {
        yield 'one editable site' => [1, 2, 2, 1, 1, 0];
        yield 'multiple editable sites' => [2, 3, 2, 2, 1, 1];
        yield 'no editable sites' => [0, 1, 1, 0, 0, 0];
    }

    #[DataProvider('permissionProvider')]
    public function testUtilityAggregatesRespectIndependentPluginPermissions(
        array $permissions,
        bool $redirectsVisible,
        bool $analyticsVisible,
        bool $clearCountVisible,
    ): void {
        [$siteIds] = $this->seedScopedUtilityData();
        $variables = $this->captureUtilityVariables([$siteIds[0]], array_merge(
            ['utility:redirect-manager'],
            $permissions,
        ));

        self::assertSame($redirectsVisible ? 2 : 0, (int)$variables['totalRedirects']);
        self::assertSame($analyticsVisible ? 1 : 0, (int)$variables['total404s']);
        self::assertSame($clearCountVisible ? 1 : 0, (int)$variables['analyticsCount']);
    }

    /** @return iterable<string, array{list<string>, bool, bool, bool}> */
    public static function permissionProvider(): iterable
    {
        yield 'redirects only' => [['redirectManager:manageRedirects'], true, false, false];
        yield 'analytics only' => [['redirectManager:viewAnalytics'], false, true, false];
        yield 'clear analytics only' => [['redirectManager:clearAnalytics'], false, false, true];
        yield 'no plugin permissions' => [[], false, false, false];
    }

    public function testAnalyticsDisabledAndUtilityPermissionDenialArePreserved(): void
    {
        [$siteIds] = $this->seedScopedUtilityData();
        $this->settings()->enableAnalytics = false;
        $variables = $this->captureUtilityVariables([$siteIds[0]], [
            'utility:redirect-manager',
            'redirectManager:viewAnalytics',
            'redirectManager:clearAnalytics',
        ]);
        self::assertSame(0, (int)$variables['total404s']);
        self::assertSame(0, (int)$variables['analyticsCount']);

        Craft::$app->set('user', new StubCpUser([]));
        self::assertFalse(Craft::$app->getUtilities()->checkAuthorization(RedirectManagerUtility::class));
        Craft::$app->set('user', new StubCpUser(['utility:redirect-manager']));
        self::assertTrue(Craft::$app->getUtilities()->checkAuthorization(RedirectManagerUtility::class));
    }

    /**
     * @return array{list<int>, list<\lindemannrock\redirectmanager\records\RedirectRecord>, list<AnalyticsRecord>}
     */
    private function seedScopedUtilityData(): array
    {
        $siteIds = array_map(
            static fn($site): int => (int)$site->id,
            Craft::$app->getSites()->getAllSites(),
        );
        self::assertGreaterThanOrEqual(2, count($siteIds));
        $siteIds = array_slice($siteIds, 0, 2);

        $siteA = $this->seedRedirect(['siteId' => $siteIds[0], 'enabled' => true]);
        $siteB = $this->seedRedirect(['siteId' => $siteIds[1], 'enabled' => false]);
        $global = $this->seedRedirect(['enabled' => true]);
        $global->siteId = null;
        self::assertTrue($global->save(false));

        $analytics = [
            $this->seedAnalytics($siteIds[0], true, 2),
            $this->seedAnalytics($siteIds[1], false, 3),
        ];

        return [$siteIds, [$siteA, $siteB, $global], $analytics];
    }

    private function seedAnalytics(int $siteId, bool $handled, int $count): AnalyticsRecord
    {
        $url = '/' . self::MARKER . 'utility_' . bin2hex(random_bytes(4));
        $record = new AnalyticsRecord();
        $record->siteId = $siteId;
        $record->url = $url;
        $record->urlParsed = $url;
        $record->handled = $handled;
        $record->sourcePlugin = 'redirect-manager';
        $record->count = $count;
        $record->requestType = 'normal';
        $record->isRobot = false;
        $record->lastHit = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        self::assertTrue($record->save(false));
        return $record;
    }

    /** @param list<int> $editableSiteIds @param list<string> $permissions */
    private function captureUtilityVariables(array $editableSiteIds, array $permissions): array
    {
        $realSites = Craft::$app->getSites();
        Craft::$app->set('sites', new StubEditableSites($realSites, $editableSiteIds));
        Craft::$app->set('user', new StubCpUser($permissions));
        $savedView = Craft::$app->getView();
        $view = new class() extends View {
            /** @var array<string, mixed> */
            public array $captured = [];

            public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): string
            {
                $this->captured = $variables;
                return 'captured';
            }
        };

        try {
            Craft::$app->set('view', $view);
            RedirectManagerUtility::contentHtml();
            return $view->captured;
        } finally {
            Craft::$app->set('view', $savedView);
        }
    }
}
