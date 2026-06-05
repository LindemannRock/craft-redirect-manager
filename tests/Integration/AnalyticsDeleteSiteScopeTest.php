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
use lindemannrock\redirectmanager\records\AnalyticsRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionMethod;
use yii\web\ForbiddenHttpException;

/**
 * Pins the single-record analytics delete site-scope guard.
 *
 * @since 5.32.0
 */
final class AnalyticsDeleteSiteScopeTest extends TestCase
{
    public function testDeleteGuardAllowsEditableSiteAnalyticsRow(): void
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $record = $this->seedAnalyticsRecord($siteId);

        $resolved = $this->resolveEditableAnalyticsRecord((int)$record->id, [$siteId]);

        self::assertSame((int)$record->id, (int)$resolved?->id);
    }

    public function testDeleteGuardRejectsNonEditableSiteAnalyticsRow(): void
    {
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $record = $this->seedAnalyticsRecord($siteId);

        $this->expectException(ForbiddenHttpException::class);

        $this->resolveEditableAnalyticsRecord((int)$record->id, []);
    }

    /**
     * @param array<int> $editableSiteIds
     */
    private function resolveEditableAnalyticsRecord(int $analyticId, array $editableSiteIds): ?AnalyticsRecord
    {
        $controller = new AnalyticsController('analytics', RedirectManager::getInstance());
        $method = new ReflectionMethod($controller, 'requireEditableAnalyticsRecord');
        $method->setAccessible(true);

        return $method->invoke($controller, $analyticId, $editableSiteIds);
    }

    private function seedAnalyticsRecord(int $siteId): AnalyticsRecord
    {
        $url = '/' . self::MARKER . 'analytics_delete_' . substr(uniqid('', true), -8);

        $record = new AnalyticsRecord();
        $record->siteId = $siteId;
        $record->url = $url;
        $record->urlParsed = $url;
        $record->handled = false;
        $record->sourcePlugin = 'redirect-manager';
        $record->count = 1;
        $record->requestType = 'normal';
        $record->lastHit = date('Y-m-d H:i:s');

        self::assertTrue($record->save(false));

        return $record;
    }
}
