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
use craft\errors\MissingComponentException;
use lindemannrock\redirectmanager\controllers\RedirectsController;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\LocalCacheService;
use lindemannrock\redirectmanager\tests\Support\StubConsoleRequest;
use lindemannrock\redirectmanager\tests\Support\StubCpUser;
use lindemannrock\redirectmanager\tests\Support\StubEditableSites;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;
use yii\web\ForbiddenHttpException;

/**
 * Pins current-site and target-site authorization for redirect updates.
 */
final class RedirectMutationAuthorizationTest extends TestCase
{
    #[DataProvider('forgedTargetProvider')]
    public function testInaccessibleCurrentRedirectCannotBeUpdatedThroughAuthorizedTarget(string $target): void
    {
        [$inaccessibleSiteId, $editableSiteId] = $this->siteIds();
        $record = $this->seedRedirect([
            'siteId' => $inaccessibleSiteId,
            'destinationUrl' => '/original-destination',
        ]);
        $cache = new class() extends LocalCacheService {
            public int $invalidations = 0;

            public function invalidateRedirectMutation(?int $oldSiteId, ?int $newSiteId): bool
            {
                $this->invalidations++;
                return true;
            }
        };
        $this->replacePluginComponent('localCache', $cache);
        $analyticsBefore = $this->countRows('{{%redirectmanager_analytics}}');

        $realSites = Craft::$app->getSites();
        Craft::$app->set('sites', new StubEditableSites($realSites, [$editableSiteId]));
        Craft::$app->set('user', new StubCpUser(['redirectManager:editRedirects']));
        Craft::$app->set('request', new StubConsoleRequest([
            'redirectId' => (int)$record->id,
            'sourceUrl' => $record->sourceUrl,
            'destinationUrl' => '/forged-destination',
            'redirectSrcMatch' => 'pathonly',
            'matchType' => 'exact',
            'statusCode' => 302,
            'enabled' => false,
            'priority' => 9,
            'siteId' => $target === 'global' ? '' : $editableSiteId,
        ]));

        $thrown = null;
        try {
            (new RedirectsController('redirects', RedirectManager::$plugin))->actionSave();
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        self::assertInstanceOf(ForbiddenHttpException::class, $thrown);
        $persisted = RedirectRecord::findOne((int)$record->id);
        self::assertNotNull($persisted);
        self::assertSame($inaccessibleSiteId, (int)$persisted->siteId);
        self::assertSame('/original-destination', $persisted->destinationUrl);
        self::assertSame(301, (int)$persisted->statusCode);
        self::assertTrue((bool)$persisted->enabled);
        self::assertSame(0, $cache->invalidations);
        self::assertSame($analyticsBefore, $this->countRows('{{%redirectmanager_analytics}}'));
    }

    /** @return iterable<string, array{string}> */
    public static function forgedTargetProvider(): iterable
    {
        yield 'editable target' => ['editable'];
        yield 'global target' => ['global'];
    }

    public function testAccessibleRedirectCanMoveToAnotherEditableSite(): void
    {
        [$currentSiteId, $targetSiteId] = $this->siteIds();
        $record = $this->seedRedirect([
            'siteId' => $currentSiteId,
            'destinationUrl' => '/original-destination',
        ]);
        $cache = new class() extends LocalCacheService {
            public int $invalidations = 0;

            public function invalidateRedirectMutation(?int $oldSiteId, ?int $newSiteId): bool
            {
                $this->invalidations++;
                return true;
            }
        };
        $this->replacePluginComponent('localCache', $cache);

        $realSites = Craft::$app->getSites();
        Craft::$app->set('sites', new StubEditableSites($realSites, [$currentSiteId, $targetSiteId]));
        Craft::$app->set('user', new StubCpUser(['redirectManager:editRedirects']));
        Craft::$app->set('request', new StubConsoleRequest([
            'redirectId' => (int)$record->id,
            'sourceUrl' => $record->sourceUrl,
            'destinationUrl' => '/authorized-destination',
            'redirectSrcMatch' => 'pathonly',
            'matchType' => 'exact',
            'statusCode' => 302,
            'enabled' => true,
            'priority' => 1,
            'siteId' => $targetSiteId,
        ]));

        try {
            (new RedirectsController('redirects', RedirectManager::$plugin))->actionSave();
        } catch (MissingComponentException) {
            // The console fixture has no session; the authorized mutation has
            // already completed before production-only notice/redirect output.
        }

        $persisted = RedirectRecord::findOne((int)$record->id);
        self::assertNotNull($persisted);
        self::assertSame($targetSiteId, (int)$persisted->siteId);
        self::assertSame('/authorized-destination', $persisted->destinationUrl);
        self::assertSame(302, (int)$persisted->statusCode);
        self::assertSame(1, $cache->invalidations);
    }

    /** @return list<int> */
    private function siteIds(): array
    {
        $siteIds = array_map(
            static fn($site): int => (int)$site->id,
            Craft::$app->getSites()->getAllSites(),
        );
        self::assertGreaterThanOrEqual(2, count($siteIds));
        return array_slice($siteIds, 0, 2);
    }
}
