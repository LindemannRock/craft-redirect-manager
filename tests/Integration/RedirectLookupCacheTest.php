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
use PHPUnit\Framework\Attributes\DataProvider;
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
        $redirect = $this->seedRedirect();
        $path = (string)$redirect->sourceUrlParsed;
        $fullUrl = 'https://example.test' . $path;

        self::assertSame($redirect->id, (int)$this->redirects->findRedirect($fullUrl, $path)['id']);
        self::assertTrue($this->redirects->deleteRedirect((int)$redirect->id, $redirect));
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(1, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
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

    public function testRedisBackendReusesPositiveAndNegativeResults(): void
    {
        $connection = $this->useMemoryRedisBackend();
        $redirect = $this->seedRedirect();
        $path = (string)$redirect->sourceUrlParsed;
        $fullUrl = 'https://example.test' . $path;

        self::assertSame($redirect->id, (int)$this->redirects->findRedirect($fullUrl, $path)['id']);
        self::assertSame($redirect->id, (int)$this->redirects->findRedirect($fullUrl, $path)['id']);
        $missingPath = '/' . self::MARKER . 'redis_negative_' . bin2hex(random_bytes(4));
        $missingFullUrl = 'https://example.test' . $missingPath;
        self::assertNull($this->redirects->findRedirect($missingFullUrl, $missingPath));
        self::assertNull($this->redirects->findRedirect($missingFullUrl, $missingPath));

        self::assertSame(2, $this->candidateLoads);
        self::assertSame(2, $this->matcherCalls);
        self::assertSame(2, $this->fetchHitCountFromDb((int)$redirect->id));
        self::assertCount(2, $connection->setMembers($this->redisTrackingSetKey()));
        self::assertSame(0, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testRedisResultBecomesLiveOnlyAfterTrackingExpiryIsRefreshed(): void
    {
        [$fullUrl, $path] = $this->missingLookup('redis_write_order');
        $connection = $this->useMemoryRedisBackend();
        $connection->resetCommandAccounting();

        self::assertNull($this->redirects->findRedirect($fullUrl, $path));

        $members = $connection->setMembers($this->redisTrackingSetKey());
        self::assertCount(1, $members);
        $cache = Craft::$app->getCache();
        self::assertInstanceOf(RedisCache::class, $cache);
        $commands = $connection->commandLog();
        $membershipIndex = $this->commandIndex($commands, 'SADD', $members[0]);
        $trackingExpiryIndex = $this->commandIndex($commands, 'EXPIRE', $this->redisTrackingSetKey());
        $resultWriteIndex = $this->commandIndex($commands, 'SET', $cache->buildKey($members[0]));

        self::assertLessThan($trackingExpiryIndex, $membershipIndex);
        self::assertLessThan($resultWriteIndex, $trackingExpiryIndex);
        $this->assertNoLiveUntrackedRedisResults($connection);
    }

    public function testRedisResultCapacityUsesConstantWriteMaintenance(): void
    {
        $connection = $this->useMemoryRedisBackend();
        $constant = (new ReflectionClass(RedirectsService::class))->getReflectionConstant('CACHE_MAX_ENTRIES');
        self::assertNotFalse($constant);
        $bound = (int)$constant->getValue();
        $writeCount = $bound + 25;
        $maxCommandsForOneLookup = 0;
        $maxCommandsForOneWrite = 0;
        $maxMembersInspectedForOneLookup = 0;
        $latestPath = '';

        $connection->resetCommandAccounting();
        for ($index = 0; $index < $writeCount; $index++) {
            $latestPath = '/' . self::MARKER . 'redis_capacity_' . $index;
            $commandsBefore = $connection->totalCommandCount();
            $membersBefore = $connection->inspectedMemberCount();
            self::assertNull($this->redirects->findRedirect('https://example.test' . $latestPath, $latestPath));
            $commands = array_slice($connection->commandLog(), $commandsBefore);
            $writeStart = array_search('SISMEMBER', array_column($commands, 'name'), true);
            self::assertIsInt($writeStart);
            $maxCommandsForOneLookup = max(
                $maxCommandsForOneLookup,
                $connection->totalCommandCount() - $commandsBefore,
            );
            $maxCommandsForOneWrite = max($maxCommandsForOneWrite, count($commands) - $writeStart);
            $maxMembersInspectedForOneLookup = max(
                $maxMembersInspectedForOneLookup,
                $connection->inspectedMemberCount() - $membersBefore,
            );
        }

        $metrics = [
            'actualResultKeys' => $connection->actualValueCount(),
            'trackedMembers' => count($connection->setMembers($this->redisTrackingSetKey())),
            'totalCommands' => $connection->totalCommandCount(),
            'existsCommands' => $connection->commandCount('EXISTS'),
            'scanCommands' => $connection->commandCount('SSCAN'),
            'maxCommandsForOneLookup' => $maxCommandsForOneLookup,
            'maxCommandsForOneWrite' => $maxCommandsForOneWrite,
            'maxMembersInspectedForOneLookup' => $maxMembersInspectedForOneLookup,
        ];
        $failureContext = json_encode($metrics, JSON_THROW_ON_ERROR);

        self::assertSame(0, $metrics['scanCommands'], $failureContext);
        self::assertSame(0, $metrics['existsCommands'], $failureContext);
        self::assertLessThanOrEqual($bound, $metrics['actualResultKeys'], $failureContext);
        self::assertLessThanOrEqual($bound, $metrics['trackedMembers'], $failureContext);
        self::assertSame(11, $maxCommandsForOneLookup, $failureContext);
        self::assertSame(8, $maxCommandsForOneWrite, $failureContext);
        self::assertLessThanOrEqual(2, $maxMembersInspectedForOneLookup, $failureContext);
        self::assertSame(8275, $metrics['totalCommands'], $failureContext);
        self::assertSame(1050, $connection->inspectedMemberCount(), $failureContext);

        $loadsBeforeReuse = $this->candidateLoads;
        $matchesBeforeReuse = $this->matcherCalls;
        self::assertNull($this->redirects->findRedirect('https://example.test' . $latestPath, $latestPath));
        self::assertSame($loadsBeforeReuse, $this->candidateLoads);
        self::assertSame($matchesBeforeReuse, $this->matcherCalls);
    }

    public function testRedisCapacityEvictsTheResultBeforeItsMembershipAndAllowsRecomputation(): void
    {
        $connection = $this->useMemoryRedisBackend();
        $redirect = $this->seedRedirect();
        $path = (string)$redirect->sourceUrlParsed;
        $fullUrl = 'https://example.test' . $path;
        self::assertSame($redirect->id, (int)$this->redirects->findRedirect($fullUrl, $path)['id']);
        $positiveMember = $connection->setMembers($this->redisTrackingSetKey())[0];
        $this->seedTrackedRedisResults(999, 'capacity_eviction');
        self::assertSame(1000, $connection->actualValueCount());
        self::assertCount(1000, $connection->setMembers($this->redisTrackingSetKey()));

        $connection->resetCommandAccounting();
        [$missingFullUrl, $missingPath] = $this->missingLookup('capacity_eviction_new');
        self::assertNull($this->redirects->findRedirect($missingFullUrl, $missingPath));

        $commands = $connection->commandLog();
        $deleteIndex = $this->commandIndex($commands, 'DEL', Craft::$app->getCache()->buildKey($positiveMember));
        $untrackIndex = $this->commandIndex($commands, 'SREM', $positiveMember);
        self::assertLessThan($untrackIndex, $deleteIndex);
        self::assertFalse($connection->hasActualKey(Craft::$app->getCache()->buildKey($positiveMember)));
        self::assertNotContains($positiveMember, $connection->setMembers($this->redisTrackingSetKey()));
        $this->assertNoLiveUntrackedRedisResults($connection);

        $loadsBeforeRecompute = $this->candidateLoads;
        self::assertSame($redirect->id, (int)$this->redirects->findRedirect($fullUrl, $path)['id']);
        self::assertSame($loadsBeforeRecompute + 1, $this->candidateLoads);
        self::assertSame(2, $this->fetchHitCountFromDb((int)$redirect->id));

        self::assertTrue($this->redirects->updateRedirect((int)$redirect->id, ['enabled' => false], $redirect));
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
    }

    #[DataProvider('redisCapacityFailureProvider')]
    public function testRedisCapacityFailuresAbortNewWritesWithoutLiveUntrackedResults(
        string $command,
        int $occurrence,
    ): void {
        $connection = $this->useMemoryRedisBackend();
        $this->seedTrackedRedisResults(1000, 'capacity_failure_' . strtolower($command));
        [$fullUrl, $path] = $this->missingLookup('capacity_failure_' . strtolower($command));
        $connection->resetCommandAccounting();
        $connection->failOnFutureCommand($command, $occurrence);

        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        $commands = $connection->commandLog();
        if ($command === 'EXPIRE' || $command === 'SET') {
            $membershipCommand = null;
            foreach ($commands as $recordedCommand) {
                if ($recordedCommand['name'] === 'SADD') {
                    $membershipCommand = $recordedCommand;
                    break;
                }
            }
            self::assertIsArray($membershipCommand);
            $newMember = $membershipCommand['params'][1] ?? null;
            self::assertIsString($newMember);
            $cache = Craft::$app->getCache();
            self::assertInstanceOf(RedisCache::class, $cache);
            $membershipIndex = $this->commandIndex($commands, 'SADD', $newMember);
            $trackingExpiryIndex = $this->commandIndex($commands, 'EXPIRE', $this->redisTrackingSetKey());
            self::assertLessThan($trackingExpiryIndex, $membershipIndex);

            if ($command === 'EXPIRE') {
                self::assertSame(0, $connection->commandCount('SET'));
            } else {
                $resultWriteIndex = $this->commandIndex($commands, 'SET', $cache->buildKey($newMember));
                self::assertLessThan($resultWriteIndex, $trackingExpiryIndex);
            }

            $deleteIndex = $this->commandIndex($commands, 'DEL', $cache->buildKey($newMember));
            $untrackIndex = $this->lastCommandIndex($commands, 'SREM', $newMember);
            self::assertLessThan($untrackIndex, $deleteIndex);
            self::assertFalse($connection->hasActualKey($cache->buildKey($newMember)));
            self::assertNotContains($newMember, $connection->setMembers($this->redisTrackingSetKey()));
        }
        self::assertLessThanOrEqual(1000, $connection->actualValueCount());
        self::assertLessThanOrEqual(1000, count($connection->setMembers($this->redisTrackingSetKey())));
        $this->assertNoLiveUntrackedRedisResults($connection);

        $loadsBeforeRetry = $this->candidateLoads;
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame($loadsBeforeRetry + 1, $this->candidateLoads);
        self::assertLessThanOrEqual(1000, $connection->actualValueCount());
        self::assertLessThanOrEqual(1000, count($connection->setMembers($this->redisTrackingSetKey())));
        $this->assertNoLiveUntrackedRedisResults($connection);
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function redisCapacityFailureProvider(): array
    {
        return [
            'victim deletion' => ['DEL', 1],
            'victim membership removal' => ['SREM', 2],
            'new membership addition' => ['SADD', 1],
            'new result write' => ['SET', 1],
            'tracking expiry refresh' => ['EXPIRE', 1],
        ];
    }

    public function testRedisStaleMembershipRemainsBoundedAndConvergesThroughTurnover(): void
    {
        $connection = $this->useMemoryRedisBackend();
        $cache = Craft::$app->getCache();
        self::assertInstanceOf(RedisCache::class, $cache);
        $members = $this->seedTrackedRedisResults(1000, 'stale_turnover');
        foreach (array_slice($members, 0, 25) as $member) {
            $connection->expireActualKey($cache->buildKey($member));
        }
        self::assertSame(975, $connection->actualValueCount());
        self::assertCount(1000, $connection->setMembers($this->redisTrackingSetKey()));

        $connection->resetCommandAccounting();
        for ($index = 0; $index < 25; $index++) {
            $path = '/' . self::MARKER . 'stale_turnover_new_' . $index;
            self::assertNull($this->redirects->findRedirect('https://example.test' . $path, $path));
        }

        self::assertSame(1000, $connection->actualValueCount());
        self::assertCount(1000, $connection->setMembers($this->redisTrackingSetKey()));
        self::assertSame(0, $connection->commandCount('SSCAN'));
        self::assertSame(50, $connection->inspectedMemberCount());
        $this->assertNoLiveUntrackedRedisResults($connection);

        foreach ($connection->actualValueKeys() as $actualKey) {
            $connection->expireActualKey($actualKey);
        }
        $connection->expireActualKey($this->redisTrackingSetKey());
        self::assertSame(0, $connection->actualValueCount());
        self::assertSame([], $connection->setMembers($this->redisTrackingSetKey()));
    }

    public function testRedisExpiredAndLegacyResultsAreRecomputed(): void
    {
        $connection = $this->useMemoryRedisBackend();
        [$fullUrl, $path] = $this->missingLookup('redis_expiry');
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        $members = $connection->setMembers($this->redisTrackingSetKey());
        self::assertCount(1, $members);
        $cache = Craft::$app->getCache();
        self::assertInstanceOf(RedisCache::class, $cache);

        $connection->expireActualKey($cache->buildKey($members[0]));
        $this->resetCounters();
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(['loads' => 1, 'matches' => 1], [
            'loads' => $this->candidateLoads,
            'matches' => $this->matcherCalls,
        ]);

        self::assertTrue($cache->set($members[0], ['legacy' => true], 3600));
        $this->resetCounters();
        self::assertNull($this->redirects->findRedirect($fullUrl, $path));
        self::assertSame(['loads' => 1, 'matches' => 1], [
            'loads' => $this->candidateLoads,
            'matches' => $this->matcherCalls,
        ]);
    }

    public function testRedisFailureFallsBackToCorrectUncachedResolution(): void
    {
        $connection = $this->useMemoryRedisBackend();
        $redirect = $this->seedRedirect();
        $path = (string)$redirect->sourceUrlParsed;
        $connection->setFailCommands(true);

        $result = $this->redirects->findRedirect('https://example.test' . $path, $path);

        self::assertNotNull($result);
        self::assertSame($redirect->id, (int)$result['id']);
        self::assertSame(1, $this->candidateLoads);
        self::assertSame(1, $this->matcherCalls);
    }

    public function testRedisInvalidationDeletesOnlyTrackedRedirectEntries(): void
    {
        $connection = $this->useMemoryRedisBackend();
        $redirect = $this->seedRedirect();
        $path = (string)$redirect->sourceUrlParsed;
        self::assertNotNull($this->redirects->findRedirect('https://example.test' . $path, $path));
        [$missingFullUrl, $missingPath] = $this->missingLookup('redis_clear');
        self::assertNull($this->redirects->findRedirect($missingFullUrl, $missingPath));
        self::assertCount(2, $connection->setMembers($this->redisTrackingSetKey()));

        $connection->executeCommand('SET', ['shared:owner-key', 'keep']);
        self::assertSame(2, RedirectManager::$plugin->localCache->clearRedirectCache());

        self::assertTrue($connection->hasActualKey('shared:owner-key'));
        self::assertSame([], $connection->setMembers($this->redisTrackingSetKey()));
    }

    public function testAggregateInvalidationClearsExactFileAndRedisNamespaces(): void
    {
        [$fileFullUrl, $filePath] = $this->missingLookup('aggregate_file');
        self::assertNull($this->redirects->findRedirect($fileFullUrl, $filePath));
        self::assertSame(1, RedirectManager::$plugin->localCache->countRedirectCacheFiles());

        $connection = $this->useMemoryRedisBackend();
        [$redisFullUrl, $redisPath] = $this->missingLookup('aggregate_redis');
        self::assertNull($this->redirects->findRedirect($redisFullUrl, $redisPath));
        self::assertCount(1, $connection->setMembers($this->redisTrackingSetKey()));

        $this->settings()->cacheStorageMethod = 'file';
        $this->redirects->invalidateCaches();

        self::assertSame(0, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
        self::assertSame([], $connection->setMembers($this->redisTrackingSetKey()));
    }

    public function testRedisExpiredRequestedResultRemovesItsExactMembership(): void
    {
        $connection = $this->useMemoryRedisBackend();
        $cache = Craft::$app->getCache();
        self::assertInstanceOf(RedisCache::class, $cache);
        $firstPath = '/' . self::MARKER . 'redis_stale_first';
        self::assertNull($this->redirects->findRedirect('https://example.test' . $firstPath, $firstPath));
        $members = $connection->setMembers($this->redisTrackingSetKey());
        self::assertCount(1, $members);
        $staleMember = $members[0];
        $connection->expireActualKey($cache->buildKey($staleMember));
        $connection->resetCommandAccounting();

        self::assertNull($this->redirects->findRedirect('https://example.test' . $firstPath, $firstPath));

        $members = $connection->setMembers($this->redisTrackingSetKey());
        self::assertCount(1, $members);
        self::assertSame($staleMember, $members[0]);
        $commands = $connection->commandLog();
        $exactUntrack = $this->commandIndex($commands, 'SREM', $staleMember);
        $capacityCheck = $this->commandIndex($commands, 'SCARD', $this->redisTrackingSetKey());
        self::assertLessThan($capacityCheck, $exactUntrack);
    }

    /** @return array{0: string, 1: string} */
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

    private function useMemoryRedisBackend(): InMemoryRedisConnection
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

    /** @return array<int, string> */
    private function seedTrackedRedisResults(int $count, string $label): array
    {
        $cache = Craft::$app->getCache();
        self::assertInstanceOf(RedisCache::class, $cache);
        $setKey = $this->redisTrackingSetKey();
        $members = [];
        $allResultsStored = true;
        $allMembersTracked = true;
        for ($index = 0; $index < $count; $index++) {
            $member = PluginHelper::getCacheKeyPrefix(RedirectManager::$plugin->id, 'redirect')
                . hash('sha256', $label . ':' . $index);
            $resultStored = $cache->set($member, ['version' => 2, 'state' => 'negative'], 3600);
            $memberTracked = $cache->redis->executeCommand('SADD', [$setKey, $member]);
            $allResultsStored = $resultStored && $allResultsStored;
            $allMembersTracked = $memberTracked === 1 && $allMembersTracked;
            $members[] = $member;
        }
        self::assertTrue($allResultsStored);
        self::assertTrue($allMembersTracked);
        self::assertSame(1, $cache->redis->executeCommand('EXPIRE', [$setKey, 3600]));

        return $members;
    }

    /**
     * @param array<int, array{name: string, params: array<int, mixed>}> $commands
     */
    private function commandIndex(array $commands, string $name, string $parameter): int
    {
        foreach ($commands as $index => $command) {
            if ($command['name'] === $name && in_array($parameter, $command['params'], true)) {
                return $index;
            }
        }

        self::fail("Redis command {$name} with the expected parameter was not recorded.");
    }

    /**
     * @param array<int, array{name: string, params: array<int, mixed>}> $commands
     */
    private function lastCommandIndex(array $commands, string $name, string $parameter): int
    {
        for ($index = count($commands) - 1; $index >= 0; $index--) {
            if ($commands[$index]['name'] === $name && in_array($parameter, $commands[$index]['params'], true)) {
                return $index;
            }
        }

        self::fail("Redis command {$name} with the expected parameter was not recorded.");
    }

    private function assertNoLiveUntrackedRedisResults(InMemoryRedisConnection $connection): void
    {
        $cache = Craft::$app->getCache();
        self::assertInstanceOf(RedisCache::class, $cache);
        $trackedActualKeys = array_map(
            static fn(string $member): string => $cache->buildKey($member),
            $connection->setMembers($this->redisTrackingSetKey()),
        );

        self::assertSame([], array_values(array_diff($connection->actualValueKeys(), $trackedActualKeys)));
    }

    private function redisTrackingSetKey(): string
    {
        return PluginHelper::getCacheKeySet(RedirectManager::$plugin->id, 'redirect');
    }
}
