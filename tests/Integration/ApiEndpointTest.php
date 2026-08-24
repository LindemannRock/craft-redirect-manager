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
use Fiber;
use lindemannrock\redirectmanager\controllers\ApiController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\RedirectsService;
use lindemannrock\redirectmanager\tests\Support\InMemoryRedisConnection;
use lindemannrock\redirectmanager\tests\Support\RateLimitTestCache;
use lindemannrock\redirectmanager\tests\Support\RateLimitTestMutex;
use lindemannrock\redirectmanager\tests\TestCase;
use yii\base\Action;
use yii\redis\Cache as RedisCache;
use yii\web\BadRequestHttpException;
use yii\web\HeaderCollection;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\TooManyRequestsHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Covers the read-only JSON redirects API endpoint.
 *
 * @since 5.33.0
 */
final class ApiEndpointTest extends TestCase
{
    private bool $savedApiEndpointEnabled = false;

    private ?string $savedApiEndpointToken = null;

    private int $savedApiEndpointRateLimit = 60;

    private ?object $savedRequest = null;

    private ?object $savedResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = $this->settings();
        $this->savedApiEndpointEnabled = $settings->apiEndpointEnabled;
        $this->savedApiEndpointToken = $settings->apiEndpointToken;
        $this->savedApiEndpointRateLimit = $settings->apiEndpointRateLimit;
        $this->savedRequest = Craft::$app->getRequest();
        $this->savedResponse = Craft::$app->getResponse();

        $settings->apiEndpointEnabled = false;
        $settings->apiEndpointToken = null;
        $settings->apiEndpointRateLimit = 60;

        Craft::$app->set('response', new \craft\web\Response());
    }

    protected function tearDown(): void
    {
        $settings = $this->settings();
        $settings->apiEndpointEnabled = $this->savedApiEndpointEnabled;
        $settings->apiEndpointToken = $this->savedApiEndpointToken;
        $settings->apiEndpointRateLimit = $this->savedApiEndpointRateLimit;

        if ($this->savedRequest !== null) {
            Craft::$app->set('request', $this->savedRequest);
        }
        if ($this->savedResponse !== null) {
            Craft::$app->set('response', $this->savedResponse);
        }

        parent::tearDown();
    }

    public function testDisabledEndpointThrows404(): void
    {
        $this->installRequest();

        $this->expectException(NotFoundHttpException::class);
        $this->runApiBeforeAction();
    }

    public function testEnabledEndpointRejectsMissingConfiguredToken(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->installRequest();

        $this->expectException(UnauthorizedHttpException::class);
        $this->runApiBeforeAction();
    }

    public function testTokenProtectedEndpointRejectsMissingToken(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest();

        $this->expectException(UnauthorizedHttpException::class);
        $this->runApiBeforeAction();
    }

    public function testTokenProtectedEndpointAcceptsHeaderToken(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => 'test-token']);

        self::assertTrue($this->runApiBeforeAction());
    }

    public function testTokenProtectedEndpointAcceptsBearerToken(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(headers: ['Authorization' => 'Bearer test-token']);

        self::assertTrue($this->runApiBeforeAction());
    }

    public function testListEndpointRequiresJsonAcceptHeader(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token-accept';
        $this->installRequest(
            headers: [ApiController::TOKEN_HEADER => 'test-token-accept'],
            acceptJson: false,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->runApiBeforeAction();
    }

    public function testRateLimitRejectsAfterConfiguredLimit(): void
    {
        $token = 'test-token-limit-' . uniqid('', true);
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = $token;
        $this->settings()->apiEndpointRateLimit = 1;
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);

        self::assertTrue($this->runApiBeforeAction());

        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);

        $this->expectException(TooManyRequestsHttpException::class);
        $this->runApiBeforeAction();
    }

    public function testRateLimitCanBeDisabled(): void
    {
        $token = 'test-token-unlimited-' . uniqid('', true);
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = $token;
        $this->settings()->apiEndpointRateLimit = 0;

        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);
        self::assertTrue($this->runApiBeforeAction());

        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);
        self::assertTrue($this->runApiBeforeAction());
    }

    public function testConcurrentSameTokenRequestsAcceptOnlyTheConfiguredCount(): void
    {
        $token = 'test-token-concurrent-' . uniqid('', true);
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = $token;
        $this->settings()->apiEndpointRateLimit = 1;
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);

        $cache = new RateLimitTestCache(2);
        $mutex = new RateLimitTestMutex();
        $mutex->waitForHeldLock = true;
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);

        $accepted = 0;
        $statuses = [];
        $requests = [];
        for ($request = 0; $request < 2; $request++) {
            $requests[] = new Fiber(function() use (&$accepted, &$statuses): void {
                try {
                    $this->runApiBeforeAction();
                    $accepted++;
                } catch (HttpException $exception) {
                    $statuses[] = $exception->statusCode;
                }
            });
        }

        foreach ($requests as $request) {
            $request->start();
        }
        do {
            $suspended = false;
            foreach ($requests as $request) {
                if ($request->isSuspended()) {
                    $suspended = true;
                    $request->resume();
                }
            }
        } while ($suspended);

        self::assertSame(1, $accepted);
        self::assertSame([429], $statuses);
        self::assertCount(2, $cache->readKeys());
        self::assertCount(1, $cache->writeKeys());
        self::assertSame($mutex->acquiredNames(), $mutex->releasedNames());
        self::assertFalse($mutex->isHeld());
    }

    public function testRateLimitHeadersDescribeBoundaryStates(): void
    {
        $token = 'test-token-headers-' . uniqid('', true);
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = $token;
        $this->settings()->apiEndpointRateLimit = 1;
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);

        $before = time();
        self::assertTrue($this->runApiBeforeAction());
        $reset = (int)$this->responseHeader('X-RateLimit-Reset');
        self::assertSame('1', $this->responseHeader('X-RateLimit-Limit'));
        self::assertSame('0', $this->responseHeader('X-RateLimit-Remaining'));
        self::assertGreaterThan($before, $reset);
        self::assertLessThanOrEqual($before + 60, $reset);
        self::assertSame(0, $reset % 60);
        self::assertNull($this->responseHeader('Retry-After'));

        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);
        try {
            $this->runApiBeforeAction();
            $this->fail('The exhausted rate-limit window must reject another request.');
        } catch (TooManyRequestsHttpException $exception) {
            self::assertSame(429, $exception->statusCode);
        }

        $retryAfter = (int)$this->responseHeader('Retry-After');
        self::assertSame('1', $this->responseHeader('X-RateLimit-Limit'));
        self::assertSame('0', $this->responseHeader('X-RateLimit-Remaining'));
        self::assertSame((string)$reset, $this->responseHeader('X-RateLimit-Reset'));
        self::assertGreaterThan(0, $retryAfter);
        self::assertLessThanOrEqual(60, $retryAfter);
        self::assertContains($reset - time(), [$retryAfter - 1, $retryAfter]);
    }

    public function testIndependentTokensUseIndependentCountersAndLocks(): void
    {
        $cache = new RateLimitTestCache();
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointRateLimit = 1;

        $tokens = [
            'test-token-independent-a-' . uniqid('', true),
            'test-token-independent-b-' . uniqid('', true),
        ];
        foreach ($tokens as $token) {
            $this->settings()->apiEndpointToken = $token;
            $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);
            self::assertTrue($this->runApiBeforeAction());
            self::assertSame('0', $this->responseHeader('X-RateLimit-Remaining'));
        }

        self::assertCount(2, array_unique($cache->writeKeys()));
        self::assertCount(2, array_unique($mutex->acquiredNames()));
        self::assertSame($mutex->acquiredNames(), $mutex->releasedNames());
        foreach ($mutex->acquiredNames() as $lockName) {
            self::assertStringContainsString('redirectmanager:api-rate-limit:', $lockName);
            self::assertStringEndsWith(':lock', $lockName);
            foreach ($tokens as $token) {
                self::assertStringNotContainsString($token, $lockName);
            }
        }
    }

    public function testWindowRolloverStartsANewCounter(): void
    {
        $token = 'test-token-rollover-' . uniqid('', true);
        $cache = new RateLimitTestCache();
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = $token;
        $this->settings()->apiEndpointRateLimit = 1;

        $previousKey = $this->rateLimitCacheKey($token, time() - 60);
        self::assertTrue($cache->set($previousKey, 1, 120));
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);
        self::assertTrue($this->runApiBeforeAction());

        $currentKey = $this->rateLimitCacheKey($token, (int)$this->responseHeader('X-RateLimit-Reset') - 1);
        self::assertNotSame($previousKey, $currentKey);
        self::assertSame(1, $cache->get($currentKey));
        self::assertSame('0', $this->responseHeader('X-RateLimit-Remaining'));
        self::assertTrue($cache->delete($previousKey));
        self::assertTrue($cache->delete($currentKey));
    }

    public function testRequestWaitingAcrossWindowUsesCurrentWindowDecision(): void
    {
        $token = 'test-token-wait-rollover-' . uniqid('', true);
        $oldTimestamp = 1_800_000_059;
        $newTimestamp = $oldTimestamp + 2;
        $limit = 2;
        $cache = new RateLimitTestCache();
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint($token, $limit);

        $oldKey = $this->rateLimitCacheKey($token, $oldTimestamp);
        $newKey = $this->rateLimitCacheKey($token, $newTimestamp);
        self::assertNotSame($oldKey, $newKey);
        self::assertTrue($cache->set($oldKey, $limit, 120));

        $controller = new RateLimitClockApiController('api', RedirectManager::$plugin);
        $controller->timestamp = $oldTimestamp;
        $mutex->beforeAcquireCompletes = static function() use ($controller, $newTimestamp): void {
            $controller->timestamp = $newTimestamp;
        };

        try {
            self::assertTrue($this->runApiBeforeAction($controller));
            $oldStorageKey = $cache->buildKey($oldKey);
            $newStorageKey = $cache->buildKey($newKey);
            $decisionReadKeys = $cache->readKeys();
            self::assertSame($limit, $cache->get($oldKey));
            self::assertSame(1, $cache->get($newKey));
            self::assertSame([$newStorageKey], $decisionReadKeys);
            self::assertSame([$oldStorageKey, $newStorageKey], $cache->writeKeys());
            self::assertSame('1', $this->responseHeader('X-RateLimit-Remaining'));
            self::assertSame('1800000120', $this->responseHeader('X-RateLimit-Reset'));
            self::assertGreaterThan($newTimestamp, (int)$this->responseHeader('X-RateLimit-Reset'));
            self::assertNull($this->responseHeader('Retry-After'));

            $stableLock = 'redirectmanager:api-rate-limit:' . hash('sha256', $token) . ':lock';
            self::assertSame([$stableLock], $mutex->acquiredNames());
            self::assertSame([$stableLock], $mutex->releasedNames());
            self::assertFalse($mutex->isHeld());
            foreach ([...$decisionReadKeys, ...$cache->writeKeys(), ...$mutex->acquiredNames()] as $identity) {
                self::assertStringNotContainsString($token, $identity);
            }
        } finally {
            $cache->delete($oldKey);
            $cache->delete($newKey);
        }
    }

    public function testDisabledRateLimitBypassesCacheAndMutexWork(): void
    {
        $token = 'test-token-bypass-' . uniqid('', true);
        $cache = new RateLimitTestCache();
        $cache->throwOnRead = true;
        $cache->throwOnWrite = true;
        $mutex = new RateLimitTestMutex();
        $mutex->throwOnAcquire = true;
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = $token;
        $this->settings()->apiEndpointRateLimit = 0;
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);

        self::assertTrue($this->runApiBeforeAction());
        self::assertSame([], $cache->readKeys());
        self::assertSame([], $cache->writeKeys());
        self::assertSame([], $mutex->acquiredNames());
        self::assertNull($this->responseHeader('X-RateLimit-Limit'));
        self::assertNull($this->responseHeader('X-RateLimit-Remaining'));
        self::assertNull($this->responseHeader('X-RateLimit-Reset'));
    }

    public function testCacheMissBeginsAtZeroAndReleasesTheExactLock(): void
    {
        $token = 'test-token-miss-' . uniqid('', true);
        $cache = new RateLimitTestCache();
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint($token, 2);

        self::assertTrue($this->runApiBeforeAction());
        self::assertSame('1', $this->responseHeader('X-RateLimit-Remaining'));
        self::assertCount(1, $cache->readKeys());
        self::assertCount(1, $cache->writeKeys());
        self::assertSame($mutex->acquiredNames(), $mutex->releasedNames());
        self::assertFalse($mutex->isHeld());
    }

    public function testCacheReadExceptionFailsClosedAndReleasesTheLock(): void
    {
        $cache = new RateLimitTestCache();
        $cache->throwOnRead = true;
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint('test-token-read-failure-' . uniqid('', true));

        $this->assertServiceUnavailable(fn(): bool => $this->runApiBeforeAction());
        self::assertSame([], $cache->writeKeys());
        self::assertSame($mutex->acquiredNames(), $mutex->releasedNames());
        self::assertFalse($mutex->isHeld());
    }

    public function testCacheWriteFalseFailsClosedAndReleasesTheLock(): void
    {
        $cache = new RateLimitTestCache();
        $cache->returnFalseOnWrite = true;
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint('test-token-write-false-' . uniqid('', true));

        $this->assertServiceUnavailable(fn(): bool => $this->runApiBeforeAction());
        self::assertCount(1, $cache->writeKeys());
        self::assertSame($mutex->acquiredNames(), $mutex->releasedNames());
        self::assertFalse($mutex->isHeld());
    }

    public function testCacheWriteExceptionFailsClosedAndReleasesTheLock(): void
    {
        $cache = new RateLimitTestCache();
        $cache->throwOnWrite = true;
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint('test-token-write-failure-' . uniqid('', true));

        $this->assertServiceUnavailable(fn(): bool => $this->runApiBeforeAction());
        self::assertCount(1, $cache->writeKeys());
        self::assertSame($mutex->acquiredNames(), $mutex->releasedNames());
        self::assertFalse($mutex->isHeld());
    }

    public function testMutexAcquisitionFailureFailsClosedWithoutRelease(): void
    {
        $cache = new RateLimitTestCache();
        $mutex = new RateLimitTestMutex();
        $mutex->returnFalseOnAcquire = true;
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint('test-token-lock-miss-' . uniqid('', true));

        $this->assertServiceUnavailable(fn(): bool => $this->runApiBeforeAction());
        self::assertCount(1, $mutex->acquiredNames());
        self::assertSame([], $mutex->releasedNames());
        self::assertSame([], $cache->readKeys());
        self::assertSame([], $cache->writeKeys());
    }

    public function testMutexAcquisitionExceptionFailsClosedWithoutRelease(): void
    {
        $cache = new RateLimitTestCache();
        $mutex = new RateLimitTestMutex();
        $mutex->throwOnAcquire = true;
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint('test-token-lock-error-' . uniqid('', true));

        $this->assertServiceUnavailable(fn(): bool => $this->runApiBeforeAction());
        self::assertCount(1, $mutex->acquiredNames());
        self::assertSame([], $mutex->releasedNames());
        self::assertSame([], $cache->readKeys());
    }

    public function testExhaustedWindowReleasesTheLock(): void
    {
        $cache = new RateLimitTestCache();
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $token = 'test-token-exhausted-' . uniqid('', true);
        $this->configureLimitedEndpoint($token, 1);

        self::assertTrue($this->runApiBeforeAction());
        $this->assertHttpStatus(429, fn(): bool => $this->runApiBeforeAction());
        self::assertCount(2, $mutex->releasedNames());
        self::assertSame($mutex->acquiredNames(), $mutex->releasedNames());
        self::assertFalse($mutex->isHeld());
    }

    public function testMutexReleaseFailureFailsClosed(): void
    {
        $cache = new RateLimitTestCache();
        $mutex = new RateLimitTestMutex();
        $mutex->returnFalseOnRelease = true;
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint('test-token-release-failure-' . uniqid('', true));

        $this->assertServiceUnavailable(fn(): bool => $this->runApiBeforeAction());
        self::assertCount(1, $cache->writeKeys());
        self::assertCount(1, $mutex->releasedNames());
        self::assertTrue($mutex->isHeld());

        $lockName = $mutex->releasedNames()[0];
        $mutex->returnFalseOnRelease = false;
        $mutex->release($lockName);
    }

    public function testFailedRateDecisionDoesNotLoadRedirects(): void
    {
        $redirects = new EndpointFailureRedirectsService();
        $this->replacePluginComponent('redirects', $redirects);
        $cache = new RateLimitTestCache();
        $cache->throwOnRead = true;
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint('test-token-no-load-' . uniqid('', true));

        $this->assertServiceUnavailable(fn() => $this->executeApiRequest());
        self::assertSame(0, $redirects->getEnabledRedirectsCalls);
        self::assertSame($mutex->acquiredNames(), $mutex->releasedNames());
    }

    public function testEndpointFailureOccursAfterTheRateLockIsReleased(): void
    {
        $redirects = new EndpointFailureRedirectsService();
        $redirects->failure = new \RuntimeException('Synthetic endpoint failure.');
        $this->replacePluginComponent('redirects', $redirects);
        $cache = new RateLimitTestCache();
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint('test-token-endpoint-failure-' . uniqid('', true));

        try {
            $this->executeApiRequest();
            $this->fail('The endpoint failure must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Synthetic endpoint failure.', $exception->getMessage());
        }
        self::assertSame(1, $redirects->getEnabledRedirectsCalls);
        self::assertSame($mutex->acquiredNames(), $mutex->releasedNames());
        self::assertFalse($mutex->isHeld());
    }

    public function testCraftFileCachePersistsTheCounter(): void
    {
        $token = 'test-token-file-cache-' . uniqid('', true);
        $this->configureLimitedEndpoint($token, 2);
        $cache = Craft::$app->getCache();

        self::assertTrue($this->runApiBeforeAction());
        self::assertTrue($this->runApiBeforeAction());
        $cacheKey = $this->rateLimitCacheKey($token, (int)$this->responseHeader('X-RateLimit-Reset') - 1);
        self::assertSame(2, $cache->get($cacheKey));
        self::assertTrue($cache->delete($cacheKey));
    }

    public function testRedisBackedCachePersistsTheCounter(): void
    {
        $token = 'test-token-redis-cache-' . uniqid('', true);
        $connection = new InMemoryRedisConnection();
        $cache = new RedisCache([
            'redis' => $connection,
            'keyPrefix' => 'redirect-manager-rate-limit-test:',
            'forceClusterMode' => false,
        ]);
        $mutex = new RateLimitTestMutex();
        Craft::$app->set('cache', $cache);
        Craft::$app->set('mutex', $mutex);
        $this->configureLimitedEndpoint($token, 2);

        self::assertTrue($this->runApiBeforeAction());
        self::assertTrue($this->runApiBeforeAction());
        $cacheKey = $this->rateLimitCacheKey($token, (int)$this->responseHeader('X-RateLimit-Reset') - 1);
        self::assertSame(2, $cache->get($cacheKey));
        self::assertSame(1, $connection->actualValueCount());
        $actualKey = $cache->buildKey($cacheKey);
        self::assertSame([$actualKey], $connection->actualValueKeys());
        self::assertTrue($cache->delete($cacheKey));
        self::assertSame(0, $connection->actualValueCount());
    }

    public function testGetRedirectsReturnsEnabledRedirectsOnly(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => 'test-token']);

        $enabled = $this->seedRedirect();
        $disabled = $this->seedRedirect(['enabled' => false]);

        $this->runApiBeforeAction();
        $ids = $this->responseRedirectIds();

        self::assertContains($enabled->id, $ids);
        self::assertNotContains($disabled->id, $ids);
    }

    public function testSiteIdFilterIncludesGlobalRedirects(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(['siteId' => (string)$site->id], [ApiController::TOKEN_HEADER => 'test-token']);

        $siteRedirect = $this->seedRedirect(['siteId' => $site->id]);
        $globalRedirect = $this->seedRedirect(['siteId' => null]);

        $this->runApiBeforeAction();
        $ids = $this->responseRedirectIds();

        self::assertContains($siteRedirect->id, $ids);
        self::assertContains($globalRedirect->id, $ids);
    }

    public function testInvalidExplicitSiteReturnsEmptyList(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(['site' => '__missing_site__'], [ApiController::TOKEN_HEADER => 'test-token']);
        $this->seedRedirect();

        $this->runApiBeforeAction();

        self::assertSame([], $this->apiResponseData());
    }

    public function testListEndpointDoesNotRecordHitsOrAnalytics(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(['siteId' => (string)$site->id], [ApiController::TOKEN_HEADER => 'test-token']);

        $redirect = $this->seedRedirect(['siteId' => $site->id]);

        $this->runApiBeforeAction();
        $this->apiResponseData();

        self::assertSame(0, $this->fetchHitCountFromDb($redirect->id));
        self::assertNull($this->fetchRow('{{%redirectmanager_analytics}}', [
            'urlParsed' => $redirect->sourceUrlParsed,
            'siteId' => $site->id,
        ]));
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $headers
     */
    private function installRequest(array $params = [], array $headers = [], bool $acceptJson = true): void
    {
        if ($acceptJson && !isset($headers['Accept'])) {
            $headers['Accept'] = 'application/json';
        }

        Craft::$app->set('request', new class($params, $headers, $acceptJson) extends \craft\console\Request {
            private HeaderCollection $headers;

            /**
             * @param array<string, string> $params
             * @param array<string, string> $headers
             */
            public function __construct(private readonly array $params, array $headers, private readonly bool $acceptJson)
            {
                parent::__construct();
                $this->headers = new HeaderCollection();
                foreach ($headers as $name => $value) {
                    $this->headers->set($name, $value);
                }
            }

            public function getHeaders(): HeaderCollection
            {
                return $this->headers;
            }

            public function getAcceptsJson(): bool
            {
                return $this->acceptJson;
            }

            public function getIsOptions(): bool
            {
                return false;
            }

            public function getParam($name, $defaultValue = null): mixed
            {
                return $this->params[$name] ?? $defaultValue;
            }

            public function validateCsrfToken($clientSuppliedToken = null): bool
            {
                return true;
            }

            public function hasValidSiteToken(): bool
            {
                return false;
            }
        });
    }

    private function runApiBeforeAction(?ApiController $controller = null): bool
    {
        $controller ??= $this->apiController();

        return $controller->beforeAction(new Action('get-redirects', $controller));
    }

    private function executeApiRequest(): \yii\web\Response
    {
        $controller = $this->apiController();
        $action = new Action('get-redirects', $controller);
        $controller->beforeAction($action);

        return $controller->actionGetRedirects();
    }

    private function configureLimitedEndpoint(string $token, int $limit = 1): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = $token;
        $this->settings()->apiEndpointRateLimit = $limit;
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);
    }

    private function assertServiceUnavailable(callable $request): void
    {
        $this->assertHttpStatus(503, $request);
    }

    private function assertHttpStatus(int $expectedStatus, callable $request): void
    {
        try {
            $request();
            $this->fail("Expected HTTP status {$expectedStatus}.");
        } catch (HttpException $exception) {
            self::assertSame($expectedStatus, $exception->statusCode);
        }
    }

    private function responseHeader(string $name): ?string
    {
        $value = Craft::$app->getResponse()->getHeaders()->get($name);

        return is_string($value) ? $value : null;
    }

    private function rateLimitCacheKey(string $token, int $timestamp): string
    {
        return 'redirectmanager:api-rate-limit:' . hash('sha256', $token) . ':' . intdiv($timestamp, 60);
    }

    /**
     * @return list<int>
     */
    private function responseRedirectIds(): array
    {
        return array_map(
            static fn(array $row): int => (int)$row['id'],
            $this->apiResponseData(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function apiResponseData(): array
    {
        $response = $this->apiController()->actionGetRedirects();

        self::assertIsArray($response->data);

        return $response->data;
    }

    private function apiController(): ApiController
    {
        return new ApiController('api', RedirectManager::$plugin);
    }
}

/** Supplies a deterministic timestamp for rate-limit window behavior. */
final class RateLimitClockApiController extends ApiController
{
    public int $timestamp;

    protected function currentTimestamp(): int
    {
        return $this->timestamp;
    }
}

/** Records endpoint loading and can expose an endpoint failure after the rate decision. */
final class EndpointFailureRedirectsService extends RedirectsService
{
    public int $getEnabledRedirectsCalls = 0;

    public ?\Throwable $failure = null;

    public function getEnabledRedirects(int|array|null $siteId = null): array
    {
        $this->getEnabledRedirectsCalls++;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return [];
    }
}
