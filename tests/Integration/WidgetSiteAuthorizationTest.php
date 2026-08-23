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
use lindemannrock\redirectmanager\services\AnalyticsService;
use lindemannrock\redirectmanager\tests\Support\StubCpUser;
use lindemannrock\redirectmanager\tests\Support\StubEditableSites;
use lindemannrock\redirectmanager\tests\TestCase;
use lindemannrock\redirectmanager\widgets\AnalyticsSummaryWidget;
use lindemannrock\redirectmanager\widgets\Unhandled404sWidget;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins saved widget selections to current editable-site authority.
 */
final class WidgetSiteAuthorizationTest extends TestCase
{
    #[DataProvider('widgetProvider')]
    public function testSavedSiteAccessIsRevalidatedWhenGrantedRevokedAndRestored(string $widgetClass): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $realSites = Craft::$app->getSites();
        $analytics = new RecordingWidgetAnalyticsService();
        $this->replacePluginComponent('analytics', $analytics);
        Craft::$app->set('user', new StubCpUser(['redirectManager:viewAnalytics']));
        $this->settings()->enableAnalytics = true;

        $widget = new $widgetClass();
        $widget->siteId = (string)$siteId;

        Craft::$app->set('sites', new StubEditableSites($realSites, [$siteId]));
        $this->renderWidget($widget);
        self::assertSame([$siteId], $analytics->siteScopes);

        Craft::$app->set('sites', new StubEditableSites($realSites, []));
        $revokedHtml = $this->renderWidget($widget);
        self::assertStringContainsString('permission', strtolower((string)$revokedHtml));
        self::assertSame([$siteId], $analytics->siteScopes, 'Revoked site access must not reach analytics queries.');

        Craft::$app->set('sites', new StubEditableSites($realSites, [$siteId]));
        $this->renderWidget($widget);
        self::assertSame([$siteId, $siteId], $analytics->siteScopes);
    }

    #[DataProvider('widgetProvider')]
    public function testAllNoEditableAndInvalidExplicitSelectionsFailClosed(string $widgetClass): void
    {
        $siteIds = array_map(
            static fn($site): int => (int)$site->id,
            Craft::$app->getSites()->getAllSites(),
        );
        self::assertGreaterThanOrEqual(2, count($siteIds));
        $editable = array_slice($siteIds, 0, 2);
        $realSites = Craft::$app->getSites();
        $analytics = new RecordingWidgetAnalyticsService();
        $this->replacePluginComponent('analytics', $analytics);
        Craft::$app->set('user', new StubCpUser(['redirectManager:viewAnalytics']));
        $this->settings()->enableAnalytics = true;

        $widget = new $widgetClass();
        $widget->siteId = 'all';
        Craft::$app->set('sites', new StubEditableSites($realSites, $editable));
        $this->renderWidget($widget);
        self::assertSame([$editable], $analytics->siteScopes);

        Craft::$app->set('sites', new StubEditableSites($realSites, []));
        $this->renderWidget($widget);
        self::assertSame([$editable, []], $analytics->siteScopes);

        $widget->siteId = (string)$editable[0];
        $revokedHtml = $this->renderWidget($widget);
        self::assertStringContainsString('permission', strtolower((string)$revokedHtml));
        self::assertSame([$editable, []], $analytics->siteScopes);
    }

    #[DataProvider('widgetProvider')]
    public function testAdministratorAndAnalyticsDisabledBehaviorIsPreserved(string $widgetClass): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $realSites = Craft::$app->getSites();
        $analytics = new RecordingWidgetAnalyticsService();
        $this->replacePluginComponent('analytics', $analytics);
        Craft::$app->set('user', new StubCpUser([], true));
        Craft::$app->set('sites', new StubEditableSites($realSites, [$siteId]));

        $widget = new $widgetClass();
        $widget->siteId = (string)$siteId;
        $this->settings()->enableAnalytics = true;
        $this->renderWidget($widget);
        self::assertSame([$siteId], $analytics->siteScopes);

        $this->settings()->enableAnalytics = false;
        $disabledHtml = $this->renderWidget($widget);
        self::assertStringContainsString('disabled', strtolower((string)$disabledHtml));
        self::assertSame([$siteId], $analytics->siteScopes);
    }

    /** @return iterable<string, array{class-string}> */
    public static function widgetProvider(): iterable
    {
        yield 'analytics summary' => [AnalyticsSummaryWidget::class];
        yield 'unhandled 404s' => [Unhandled404sWidget::class];
    }

    private function renderWidget(AnalyticsSummaryWidget|Unhandled404sWidget $widget): string
    {
        $savedView = Craft::$app->getView();
        Craft::$app->set('view', new class() extends View {
            public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): string
            {
                return 'rendered';
            }
        });

        try {
            return (string) $widget->getBodyHtml();
        } finally {
            Craft::$app->set('view', $savedView);
        }
    }
}

final class RecordingWidgetAnalyticsService extends AnalyticsService
{
    /** @var list<int|array<int>> */
    public array $siteScopes = [];

    public function getUnhandled404s(int|array|null $siteId = null, ?int $limit = null): array
    {
        if ($siteId !== null) {
            $this->siteScopes[] = $siteId;
        }
        return [];
    }

    public function getChartData(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        if ($siteId !== null) {
            $this->siteScopes[] = $siteId;
        }
        return [];
    }
}
