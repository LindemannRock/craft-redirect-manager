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
use craft\console\Request as ConsoleRequest;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\MatchingService;
use lindemannrock\redirectmanager\services\RedirectsService;
use lindemannrock\redirectmanager\tests\Support\InMemoryRedisConnection;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionClass;
use yii\redis\Cache as RedisCache;
use yii\web\NotFoundHttpException;

/**
 * Pins redirect lookup result caching and ordered multi-path resolution.
 *
 * @since 5.41.0
 */
final class RedirectLookupCacheTest extends TestCase
{
    private MatchingService $countingMatching;

    private RedirectsService $countingRedirects;

    private int $matcherCalls = 0;

    private int $candidateLoads = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcherCalls = 0;
        $this->candidateLoads = 0;
        $this->countingMatching = new class(fn() => $this->matcherCalls++) extends MatchingService {
            public function __construct(private readonly \Closure $recordCall)
            {
            }

            public function matchWithCaptures(string $matchType, string $pattern, string $url): array
            {
                ($this->recordCall)();

                return parent::matchWithCaptures($matchType, $pattern, $url);
            }
        };
        $this->countingRedirects = new class(fn() => $this->candidateLoads++) extends RedirectsService {
            public function __construct(private readonly \Closure $recordLoad)
            {
            }

            public function getEnabledRedirects(int|array|null $siteId = null): array
            {
                ($this->recordLoad)();

                return parent::getEnabledRedirects($siteId);
            }
        };
        $this->replacePluginComponent('matching', $this->countingMatching);
        $this->replacePluginComponent('redirects', $this->countingRedirects);
        $this->matching = $this->countingMatching;
        $this->redirects = $this->countingRedirects;

        $this->settings()->enableRedirectCache = true;
        $this->settings()->cacheStorageMethod = 'file';
        $this->settings()->redirectCacheDuration = 3600;
    }

    public function testRepeatedEquivalentMissReusesNegativeResult(): void
    {
        $path = '/' . self::MARKER . 'negative_' . bin2hex(random_bytes(4));
        $fullUrl = 'https://example.test' . $path;
        $this->seedRedirect([
            'sourceUrl' => $path . '/different',
            'sourceUrlParsed' => $path . '/different',
        ]);

        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(1, $this->candidateLoads);
        self::assertSame(1, $this->matcherCalls);

        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(1, $this->candidateLoads);
        self::assertSame(1, $this->matcherCalls);
        self::assertSame(1, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testOrderedPathCandidatesShareOneLoadAndOneFullUrlEvaluation(): void
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $token = bin2hex(random_bytes(4));
        $fullUrl = 'https://example.test/en/' . self::MARKER . $token . '/captured';
        $strippedPath = '/' . self::MARKER . $token . '/captured';
        $originalPath = '/en/' . self::MARKER . $token . '/captured';
        $this->seedRedirect([
            'sourceUrl' => 'https://different.example/' . self::MARKER . $token,
            'sourceUrlParsed' => 'https://different.example/' . self::MARKER . $token,
            'destinationUrl' => '/never',
            'redirectSrcMatch' => 'fullurl',
            'priority' => 0,
        ]);
        $winner = $this->seedRedirect([
            'sourceUrl' => '/en/' . self::MARKER . $token . '/*',
            'sourceUrlParsed' => '/en/' . self::MARKER . $token . '/*',
            'destinationUrl' => '/winner/$1',
            'matchType' => 'wildcard',
            'priority' => 1,
        ]);

        $result = $this->redirects->findRedirectForSiteCandidates(
            $fullUrl,
            [$strippedPath, $originalPath],
            $siteId,
        );

        self::assertNotNull($result);
        self::assertSame($winner->id, (int)$result['id']);
        self::assertSame('/winner/captured', $result['destinationUrl']);
        self::assertSame(
            ['candidateLoads' => 1, 'matcherCalls' => 3],
            [
                'candidateLoads' => $this->candidateLoads,
                'matcherCalls' => $this->matcherCalls,
            ],
        );
    }

    public function testExpiredNegativeResultIsRecomputed(): void
    {
        [$fullUrl, $path] = $this->missingLookup('expired');

        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        $cacheFile = $this->onlyCacheFile();
        $stored = json_decode((string)file_get_contents($cacheFile), true, flags: JSON_THROW_ON_ERROR);
        $stored['expires'] = time() - 1;
        file_put_contents($cacheFile, json_encode($stored, JSON_THROW_ON_ERROR));
        $this->resetCounters();

        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(1, $this->candidateLoads);
        self::assertSame(1, $this->matcherCalls);
        self::assertSame(1, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testInvalidatedNegativeResultIsRecomputed(): void
    {
        [$fullUrl, $path] = $this->missingLookup('invalidated');

        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        $this->redirects->invalidateCaches();
        $this->resetCounters();

        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(1, $this->candidateLoads);
        self::assertSame(1, $this->matcherCalls);
        self::assertSame(1, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testCacheDisabledRepeatedMissAlwaysResolvesUncached(): void
    {
        [$fullUrl, $path] = $this->missingLookup('disabled');
        $this->settings()->enableRedirectCache = false;

        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));

        self::assertSame(2, $this->candidateLoads);
        self::assertSame(2, $this->matcherCalls);
        self::assertSame(0, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testNoEnabledRowsStillCachesAReusableNegativeResult(): void
    {
        $path = '/' . self::MARKER . 'empty_' . bin2hex(random_bytes(4));
        $fullUrl = 'https://example.test' . $path;

        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));

        self::assertSame(1, $this->candidateLoads);
        self::assertSame(0, $this->matcherCalls);
        self::assertSame(1, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testLookupIdentitySeparatesSiteFullUrlAndOrderedPathCandidates(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        self::assertGreaterThanOrEqual(2, count($sites));
        $siteA = (int)$sites[0]->id;
        $siteB = (int)$sites[1]->id;
        $pathA = '/' . self::MARKER . 'identity_a_' . bin2hex(random_bytes(4));
        $pathB = $pathA . '/variant';
        $fullA = 'https://one.example.test' . $pathA;
        $fullB = 'https://two.example.test' . $pathA;

        self::assertNull($this->redirects->findRedirectForSite($fullA, $pathA, $siteA));
        self::assertNull($this->redirects->findRedirectForSite($fullA, $pathA, $siteA));
        self::assertNull($this->redirects->findRedirectForSite($fullB, $pathA, $siteA));
        self::assertNull($this->redirects->findRedirectForSite($fullA, $pathB, $siteA));
        self::assertNull($this->redirects->findRedirectForSite($fullA, $pathA, $siteB));
        self::assertNull($this->redirects->findRedirectForSiteCandidates($fullA, [$pathA, $pathB], $siteA));
        self::assertNull($this->redirects->findRedirectForSite($fullA . '?keep=1', $pathA . '?keep=1', $siteA));

        self::assertSame(6, $this->candidateLoads);
        self::assertSame(6, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testMalformedAndLegacyResultsAreRejectedAndReplaced(): void
    {
        [$fullUrl, $path] = $this->missingLookup('malformed');
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        $cacheFile = $this->onlyCacheFile();

        file_put_contents($cacheFile, '{broken');
        $this->resetCounters();
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(['loads' => 1, 'matches' => 1], [
            'loads' => $this->candidateLoads,
            'matches' => $this->matcherCalls,
        ]);

        file_put_contents($cacheFile, json_encode([
            'data' => ['id' => 999, 'destinationUrl' => '/legacy'],
            'expires' => time() + 3600,
        ], JSON_THROW_ON_ERROR));
        $this->resetCounters();
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(['loads' => 1, 'matches' => 1], [
            'loads' => $this->candidateLoads,
            'matches' => $this->matcherCalls,
        ]);
    }

    public function testCreateInvalidatesAFormerlyMissingUrl(): void
    {
        $path = '/' . self::MARKER . 'create_' . bin2hex(random_bytes(4));
        $fullUrl = 'https://example.test' . $path;
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));

        $id = $this->redirects->createRedirect([
            'sourceUrl' => $path,
            'destinationUrl' => '/created-winner',
            'matchType' => 'exact',
            'redirectSrcMatch' => 'pathonly',
            'statusCode' => 301,
            'priority' => 0,
            'enabled' => true,
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
        ]);

        self::assertIsInt($id);
        $result = $this->redirects->findRedirect($fullUrl, $path);
        self::assertNotNull($result);
        self::assertSame($id, (int)$result['id']);
        self::assertSame('/created-winner', $result['destinationUrl']);
    }

    public function testUpdateInvalidatesAFormerWinnerForTheNextSafeRule(): void
    {
        $this->useMemoryApplicationCache();
        $token = bin2hex(random_bytes(4));
        $path = '/' . self::MARKER . 'update_' . $token . '/page';
        $first = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'update_' . $token . '/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'update_' . $token . '/*',
            'destinationUrl' => '/first/$1',
            'matchType' => 'wildcard',
            'priority' => 0,
        ]);
        $second = $this->seedRedirect([
            'sourceUrl' => '^/' . self::MARKER . 'update_' . $token . '/(.*)$',
            'sourceUrlParsed' => '^/' . self::MARKER . 'update_' . $token . '/(.*)$',
            'destinationUrl' => '/second/$1',
            'matchType' => 'regex',
            'priority' => 1,
        ]);
        $fullUrl = 'https://example.test' . $path;

        self::assertSame($first->id, (int)$this->redirects->findRedirect($fullUrl, $path)['id']);
        self::assertTrue($this->redirects->updateRedirect((int)$first->id, ['enabled' => false], $first));
        $result = $this->redirects->findRedirect($fullUrl, $path);

        self::assertNotNull($result);
        self::assertSame($second->id, (int)$result['id']);
        self::assertSame('/second/page', $result['destinationUrl']);
    }

    public function testDeleteInvalidatesAFormerWinnerIntoAMiss(): void
    {
        $this->useMemoryApplicationCache();
        $redirect = $this->seedRedirect();
        $path = (string)$redirect->sourceUrlParsed;
        $fullUrl = 'https://example.test' . $path;

        self::assertSame($redirect->id, (int)$this->redirects->findRedirect($fullUrl, $path)['id']);
        self::assertTrue($this->redirects->deleteRedirect((int)$redirect->id, $redirect));
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(0, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testFileCacheRemainsBoundedAndExpiredMissesConverge(): void
    {
        $constant = (new ReflectionClass(RedirectsService::class))->getReflectionConstant('CACHE_MAX_ENTRIES');
        self::assertNotFalse($constant);
        $bound = (int)$constant->getValue();
        self::assertSame(1000, $bound);

        $allResultsWereMisses = true;
        for ($index = 0; $index < $bound + 25; $index++) {
            $path = '/' . self::MARKER . 'cardinality_' . $index;
            if ($this->redirects->findRedirect('https://example.test' . $path, $path) !== null) {
                $allResultsWereMisses = false;
            }
        }
        self::assertTrue($allResultsWereMisses);
        self::assertSame($bound, RedirectManager::$plugin->localCache->countRedirectCacheFiles());

        foreach ($this->cacheFiles() as $cacheFile) {
            $stored = json_decode((string)file_get_contents($cacheFile), true, flags: JSON_THROW_ON_ERROR);
            $stored['expires'] = time() - 1;
            file_put_contents($cacheFile, json_encode($stored, JSON_THROW_ON_ERROR));
        }
        $path = '/' . self::MARKER . 'cardinality_replacement';
        self::assertNull($this->redirects->findRedirect('https://example.test' . $path, $path));
        self::assertSame(1, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testFileCapacityEvictsOldestEntryAcrossPositiveAndNegativeResults(): void
    {
        $cachePath = PluginHelper::getCachePath(RedirectManager::$plugin, 'redirects');
        \craft\helpers\FileHelper::createDirectory($cachePath);
        $oldest = $cachePath . '0000.cache';
        for ($index = 0; $index < 1000; $index++) {
            $state = $index % 2 === 0
                ? ['state' => 'negative']
                : ['state' => 'positive', 'redirect' => ['id' => $index]];
            $path = $cachePath . sprintf('%04d.cache', $index);
            file_put_contents($path, json_encode([
                'result' => ['version' => 2] + $state,
                'expires' => time() + 3600,
            ], JSON_THROW_ON_ERROR));
            touch($path, time() - ($index === 0 ? 200 : 100));
        }

        [$fullUrl, $path] = $this->missingLookup('oldest_first');
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));

        self::assertFileDoesNotExist($oldest);
        self::assertSame(1000, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testFileBackendFailureFallsBackToCorrectResolution(): void
    {
        $redirect = $this->seedRedirect();
        $path = (string)$redirect->sourceUrlParsed;
        $blockedRoot = $this->createTrackedTempDirectory('redirect-cache-blocked-');
        $blockedRuntime = $blockedRoot . '/runtime-file';
        file_put_contents($blockedRuntime, 'not-a-directory');
        Craft::$app->setRuntimePath($blockedRuntime);

        $result = $this->redirects->findRedirect('https://example.test' . $path, $path);

        self::assertNotNull($result);
        self::assertSame($redirect->id, (int)$result['id']);
        self::assertSame(1, $this->candidateLoads);
        self::assertSame(1, $this->matcherCalls);
    }

    public function testFrontendCachedMissStillRecordsEveryRequest(): void
    {
        $path = '/' . self::MARKER . 'frontend_' . bin2hex(random_bytes(4));
        $fullUrl = 'https://example.test' . $path;
        Craft::$app->set('request', new class($fullUrl, $path) extends ConsoleRequest {
            public function __construct(private readonly string $fullUrl, private readonly string $path)
            {
                parent::__construct();
            }

            public function getAbsoluteUrl(): string
            {
                return $this->fullUrl;
            }

            public function getUrl(): string
            {
                return $this->path;
            }

            public function getQueryString(): string
            {
                return '';
            }

            public function getUserAgent(): ?string
            {
                return 'Redirect lookup test';
            }

            public function getUserIP(): ?string
            {
                return '203.0.113.42';
            }

            public function getReferrer(): ?string
            {
                return null;
            }
        });
        $this->settings()->enableAnalytics = true;
        $this->settings()->autoTrimAnalytics = false;
        $this->settings()->enableGeoDetection = false;
        $this->settings()->anonymizeIpAddress = false;
        $this->settings()->ipHashSalt = '0123456789abcdef0123456789abcdef';

        $this->redirects->handle404(new NotFoundHttpException());
        $this->redirects->handle404(new NotFoundHttpException());

        self::assertSame(1, $this->candidateLoads);
        $analytics = $this->fetchRow('{{%redirectmanager_analytics}}', [
            'urlParsed' => strtolower($path),
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
        ]);
        self::assertNotNull($analytics);
        self::assertSame(0, (int)$analytics['handled']);
        self::assertSame(2, (int)$analytics['count']);
    }


    public function testApplicationCacheReusesPositiveAndNegativeResults(): void
    {
        $connection = $this->useMemoryApplicationCache();
        $redirect = $this->seedRedirect();
        $path = (string)$redirect->sourceUrlParsed;
        $fullUrl = 'https://example.test' . $path;

        self::assertSame($redirect->id, (int)$this->redirects->findRedirect($fullUrl, $path)['id']);
        self::assertSame($redirect->id, (int)$this->redirects->findRedirect($fullUrl, $path)['id']);

        $missingPath = '/' . self::MARKER . 'application_negative_' . bin2hex(random_bytes(4));
        $missingFullUrl = 'https://example.test' . $missingPath;
        self::assertNull($this->redirects->findRedirect($missingFullUrl, $missingPath));
        self::assertNull($this->redirects->findRedirect($missingFullUrl, $missingPath));

        self::assertSame(2, $this->candidateLoads);
        self::assertSame(2, $this->matcherCalls);
        self::assertSame(2, $this->fetchHitCountFromDb((int)$redirect->id));
        self::assertSame(0, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
        self::assertNotEmpty(array_filter(
            $connection->commandLog(),
            static fn(array $command): bool => $command['name'] === 'SET'
                && ($command['params'][2] ?? null) === 'PX'
                && ($command['params'][3] ?? null) === 3_600_000,
        ));
    }

    public function testSiteMutationInvalidatesOnlyAffectedApplicationCacheScope(): void
    {
        $this->useMemoryApplicationCache();
        $sites = Craft::$app->getSites()->getAllSites();
        self::assertGreaterThanOrEqual(2, count($sites));
        $siteA = (int)$sites[0]->id;
        $siteB = (int)$sites[1]->id;
        $pathA = '/' . self::MARKER . 'site_a_' . bin2hex(random_bytes(4));
        $pathB = '/' . self::MARKER . 'site_b_' . bin2hex(random_bytes(4));

        self::assertNull($this->redirects->findRedirectForSite('https://one.example.test' . $pathA, $pathA, $siteA));
        self::assertNull($this->redirects->findRedirectForSite('https://two.example.test' . $pathB, $pathB, $siteB));

        $id = $this->redirects->createRedirect([
            'sourceUrl' => $pathA,
            'destinationUrl' => '/site-a-winner',
            'matchType' => 'exact',
            'redirectSrcMatch' => 'pathonly',
            'statusCode' => 301,
            'priority' => 0,
            'enabled' => true,
            'siteId' => $siteA,
        ]);
        self::assertIsInt($id);
        $this->resetCounters();

        self::assertNull($this->redirects->findRedirectForSite('https://two.example.test' . $pathB, $pathB, $siteB));
        $result = $this->redirects->findRedirectForSite('https://one.example.test' . $pathA, $pathA, $siteA);

        self::assertNotNull($result);
        self::assertSame($id, (int)$result['id']);
        self::assertSame(1, $this->candidateLoads);
    }

    public function testGlobalMutationInvalidatesEveryApplicationCacheScope(): void
    {
        $this->useMemoryApplicationCache();
        $sites = Craft::$app->getSites()->getAllSites();
        self::assertGreaterThanOrEqual(2, count($sites));
        $siteA = (int)$sites[0]->id;
        $siteB = (int)$sites[1]->id;
        $pathA = '/' . self::MARKER . 'global_a_' . bin2hex(random_bytes(4));
        $pathB = '/' . self::MARKER . 'global_b_' . bin2hex(random_bytes(4));

        self::assertNull($this->redirects->findRedirectForSite('https://one.example.test' . $pathA, $pathA, $siteA));
        self::assertNull($this->redirects->findRedirectForSite('https://two.example.test' . $pathB, $pathB, $siteB));

        $id = $this->redirects->createRedirect([
            'sourceUrl' => $pathA,
            'destinationUrl' => '/global-winner',
            'matchType' => 'exact',
            'redirectSrcMatch' => 'pathonly',
            'statusCode' => 301,
            'priority' => 0,
            'enabled' => true,
            'siteId' => null,
        ]);
        self::assertIsInt($id);
        $this->resetCounters();

        self::assertNull($this->redirects->findRedirectForSite('https://two.example.test' . $pathB, $pathB, $siteB));
        self::assertNotNull($this->redirects->findRedirectForSite('https://one.example.test' . $pathA, $pathA, $siteA));
        self::assertSame(2, $this->candidateLoads);
    }

    public function testApplicationCacheFailureFallsBackToCorrectResolution(): void
    {
        $connection = $this->useMemoryApplicationCache();
        $redirect = $this->seedRedirect();
        $path = (string)$redirect->sourceUrlParsed;
        $connection->setFailCommands(true);

        $result = $this->redirects->findRedirect('https://example.test' . $path, $path);

        self::assertNotNull($result);
        self::assertSame($redirect->id, (int)$result['id']);
        self::assertSame(1, $this->candidateLoads);
        self::assertSame(1, $this->matcherCalls);
    }

    public function testManualApplicationCacheClearPreservesUnrelatedEntries(): void
    {
        $this->useMemoryApplicationCache();
        $cache = Craft::$app->getCache();
        self::assertInstanceOf(RedisCache::class, $cache);
        self::assertTrue($cache->set('unrelated-sentinel', 'keep', 3600));

        [$fullUrl, $path] = $this->missingLookup('application_clear');
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(0, RedirectManager::$plugin->localCache->clearRedirectCache());

        self::assertSame('keep', $cache->get('unrelated-sentinel'));
        $this->resetCounters();
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(1, $this->candidateLoads);
    }

    public function testCommittedMutationSurvivesApplicationCacheInvalidationFailure(): void
    {
        $connection = $this->useMemoryApplicationCache();
        $connection->setFailCommands(true);
        $path = '/' . self::MARKER . 'invalidation_failure_' . bin2hex(random_bytes(4));

        $id = $this->redirects->createRedirect([
            'sourceUrl' => $path,
            'destinationUrl' => '/committed',
            'matchType' => 'exact',
            'redirectSrcMatch' => 'pathonly',
            'statusCode' => 301,
            'priority' => 0,
            'enabled' => true,
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
        ]);

        self::assertIsInt($id);
        self::assertNotNull($this->fetchRow('{{%redirectmanager_redirects}}', ['id' => $id]));
    }

    public function testApplicationCacheCapacityRemainsBackendOwned(): void
    {
        $this->useMemoryApplicationCache();
        $storage = RedirectManager::$plugin->localCache;
        $decision = $storage->getStorageDecision();
        $cache = $storage->getScopedCache($decision, 'redirect-lookups');
        self::assertNotNull($cache);

        for ($index = 0; $index < 1025; $index++) {
            self::assertTrue($cache->set(
                ['lookup' => $index],
                ['version' => 2, 'state' => 'negative'],
                3600,
                ['siteId' => 1],
            ));
        }

        self::assertTrue($cache->get(['lookup' => 0], ['siteId' => 1])->isHit());
        self::assertTrue($cache->get(['lookup' => 1024], ['siteId' => 1])->isHit());
    }

    private function missingLookup(string $label): array
    {
        $path = '/' . self::MARKER . $label . '_' . bin2hex(random_bytes(4));
        $this->seedRedirect([
            'sourceUrl' => $path . '/different',
            'sourceUrlParsed' => $path . '/different',
        ]);

        return ['https://example.test' . $path, $path];
    }

    private function resetCounters(): void
    {
        $this->candidateLoads = 0;
        $this->matcherCalls = 0;
    }

    /** @return array<int, string> */
    private function cacheFiles(): array
    {
        $cachePath = PluginHelper::getCachePath(RedirectManager::$plugin, 'redirects');
        if (!is_dir($cachePath)) {
            return [];
        }

        $files = [];
        foreach (new \DirectoryIterator($cachePath) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.cache')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function onlyCacheFile(): string
    {
        $files = $this->cacheFiles();
        self::assertCount(1, $files);

        return $files[0];
    }

    private function useMemoryApplicationCache(): InMemoryRedisConnection
    {
        $connection = new InMemoryRedisConnection();
        Craft::$app->set('cache', new RedisCache([
            'redis' => $connection,
            'keyPrefix' => 'redirect-manager-memory-test:',
            'forceClusterMode' => false,
        ]));
        $this->settings()->cacheStorageMethod = 'redis';

        return $connection;
    }
}
