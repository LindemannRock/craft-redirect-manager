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
use craft\helpers\FileHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\LocalCacheService;
use lindemannrock\redirectmanager\tests\Support\InMemoryRedisConnection;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use yii\redis\Cache as RedisCache;

/**
 * @since 5.36.0
 */
#[CoversClass(LocalCacheService::class)]
final class LocalCacheServiceTest extends TestCase
{
    private string $originalCacheStorageMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCacheStorageMethod = RedirectManager::$plugin->getSettings()->cacheStorageMethod;
        RedirectManager::$plugin->getSettings()->cacheStorageMethod = 'file';
    }

    protected function tearDown(): void
    {
        RedirectManager::$plugin->getSettings()->cacheStorageMethod = $this->originalCacheStorageMethod;
        parent::tearDown();
    }

    public function testFileClearDeletesOnlyCacheFiles(): void
    {
        $redirectCache = $this->cacheFile('redirects', 'one.cache');
        $redirectText = $this->cacheFile('redirects', 'keep.txt');
        $deviceCache = $this->cacheFile('device', 'two.cache');
        $deviceText = $this->cacheFile('device', 'keep.txt');

        $this->writeCacheFile($redirectCache);
        $this->writeCacheFile($redirectText);
        $this->writeCacheFile($deviceCache);
        $this->writeCacheFile($deviceText);

        self::assertSame(1, RedirectManager::$plugin->localCache->clearRedirectCache());
        self::assertSame(1, RedirectManager::$plugin->localCache->clearDeviceCache());

        self::assertFileDoesNotExist($redirectCache);
        self::assertFileDoesNotExist($deviceCache);
        self::assertFileExists($redirectText);
        self::assertFileExists($deviceText);
    }

    public function testCountMethodsCountOnlyCacheFiles(): void
    {
        $this->writeCacheFile($this->cacheFile('redirects', 'one.cache'));
        $this->writeCacheFile($this->cacheFile('redirects', 'two.cache'));
        $this->writeCacheFile($this->cacheFile('redirects', 'keep.txt'));
        $this->writeCacheFile($this->cacheFile('device', 'one.cache'));
        $this->writeCacheFile($this->cacheFile('device', 'keep.txt'));

        self::assertSame(2, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
        self::assertSame(1, RedirectManager::$plugin->localCache->countDeviceCacheFiles());
    }

    public function testInvalidateCachesClearsRedirectCacheOnly(): void
    {
        $redirectCache = $this->cacheFile('redirects', 'one.cache');
        $deviceCache = $this->cacheFile('device', 'one.cache');
        $this->writeCacheFile($redirectCache);
        $this->writeCacheFile($deviceCache);

        RedirectManager::$plugin->redirects->invalidateCaches();

        self::assertFileDoesNotExist($redirectCache);
        self::assertFileExists($deviceCache);
    }

    public function testDeviceClearInvalidatesOnlyTheRedirectOwnedDeviceFamily(): void
    {
        $this->useMemoryApplicationCache();
        $storage = RedirectManager::$plugin->localCache;
        $decision = $storage->getStorageDecision();
        $redirects = $storage->getScopedCache($decision, LocalCacheService::FAMILY_REDIRECT_LOOKUPS);
        $devices = $storage->getScopedCache($decision, LocalCacheService::FAMILY_DEVICE);
        self::assertNotNull($redirects);
        self::assertNotNull($devices);
        self::assertTrue($redirects->set('lookup', 'redirect', 3600, ['siteId' => 1]));
        self::assertTrue($devices->set('agent', 'device', 3600));

        self::assertSame(0, $storage->clearDeviceCache($decision));

        self::assertTrue($redirects->get('lookup', ['siteId' => 1])->isHit());
        self::assertTrue($devices->get('agent')->isMiss());
    }

    public function testManualApplicationCacheClearReportsInvalidationFailure(): void
    {
        $connection = $this->useMemoryApplicationCache();
        $decision = RedirectManager::$plugin->localCache->getStorageDecision();
        $connection->setFailCommands(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to invalidate the redirect-lookups cache family.');

        RedirectManager::$plugin->localCache->clearRedirectCache($decision);
    }

    public function testClearAllReportsEveryRequiredApplicationInvalidationFailure(): void
    {
        $connection = $this->useMemoryApplicationCache();
        $decision = RedirectManager::$plugin->localCache->getStorageDecision();
        $connection->setFailCommands(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('redirect-lookups: Unable to invalidate the redirect-lookups cache family.');
        $this->expectExceptionMessage('device: Unable to invalidate the device cache family.');

        RedirectManager::$plugin->localCache->clearAllCaches($decision);
    }

    public function testPostMutationInvalidationContainsUnexpectedStorageResolutionFailure(): void
    {
        $storage = new class() extends LocalCacheService {
            public function getStorageDecision(?string $configuredStorage = null): \lindemannrock\base\cache\DisposableCacheStorageDecision
            {
                throw new \RuntimeException('Synthetic storage resolution failure.');
            }
        };

        self::assertFalse($storage->invalidateRedirectMutation(1, 1));
    }

    public function testUnknownStorageTokenDisablesCacheWithoutTouchingRuntimePaths(): void
    {
        $runtimePath = Craft::$app->getRuntimePath();
        $decision = RedirectManager::$plugin->localCache->getStorageDecision('unsupported');

        self::assertTrue($decision->isDisabled());
        self::assertSame(0, RedirectManager::$plugin->localCache->clearRedirectCache($decision));
        self::assertSame(0, RedirectManager::$plugin->localCache->countRedirectCacheFiles($decision));
        self::assertSame($runtimePath, Craft::$app->getRuntimePath());
    }

    private function cacheFile(string $type, string $filename): string
    {
        return PluginHelper::getCachePath(RedirectManager::$plugin, $type) . $filename;
    }

    private function writeCacheFile(string $path): void
    {
        FileHelper::createDirectory(dirname($path));
        file_put_contents($path, 'redirect-manager-cache-test');
    }

    private function useMemoryApplicationCache(): InMemoryRedisConnection
    {
        $connection = new InMemoryRedisConnection();
        Craft::$app->set('cache', new RedisCache([
            'redis' => $connection,
            'keyPrefix' => 'redirect-manager-local-cache-test:',
            'forceClusterMode' => false,
        ]));
        RedirectManager::$plugin->getSettings()->cacheStorageMethod = 'redis';

        return $connection;
    }
}
