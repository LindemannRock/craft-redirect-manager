<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use craft\helpers\FileHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\LocalCacheService;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

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
        $this->clearLocalCacheFiles();
    }

    protected function tearDown(): void
    {
        RedirectManager::$plugin->getSettings()->cacheStorageMethod = 'file';
        $this->clearLocalCacheFiles();
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

    private function clearLocalCacheFiles(): void
    {
        RedirectManager::$plugin->localCache->clearAllCaches();
        @unlink($this->cacheFile('redirects', 'keep.txt'));
        @unlink($this->cacheFile('device', 'keep.txt'));
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
}
