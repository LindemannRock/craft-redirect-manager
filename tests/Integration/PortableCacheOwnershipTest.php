<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use FilesystemIterator;
use lindemannrock\base\cache\DisposableCacheStorageResolver;
use lindemannrock\base\cache\ScopedCache;
use lindemannrock\base\cache\ScopedCacheResult;
use lindemannrock\redirectmanager\services\LocalCacheService;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use yii\caching\ArrayCache;

#[CoversClass(LocalCacheService::class)]
final class PortableCacheOwnershipTest extends TestCase
{
    private const EXPECTED_BASE_RUNTIME_FINGERPRINT = 'a4bf4225cff8b774ce4ace25ccfb997faa643cedb6b2f0b9fadb3db7e0d59809';

    public function testPortableCacheContractsResolveFromExpectedBaseSource(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $baseRoot = dirname($pluginRoot) . '/base';

        foreach ([
            DisposableCacheStorageResolver::class,
            ScopedCache::class,
            ScopedCacheResult::class,
        ] as $class) {
            $source = (new ReflectionClass($class))->getFileName();
            self::assertIsString($source);
            self::assertStringStartsWith(realpath($baseRoot . '/src/cache') . DIRECTORY_SEPARATOR, realpath($source));
        }

        self::assertSame(self::EXPECTED_BASE_RUNTIME_FINGERPRINT, $this->baseRuntimeFingerprint($baseRoot));
    }

    public function testRedirectLookupFamilyConstructsWithoutPluginOrDatabaseAccess(): void
    {
        $scopedCache = new ScopedCache(
            new ArrayCache(),
            'redirect-manager',
            LocalCacheService::FAMILY_REDIRECT_LOOKUPS,
        );

        self::assertTrue($scopedCache->set('lookup', ['version' => 2, 'state' => 'negative'], 60, ['siteId' => 1]));
        $result = $scopedCache->get('lookup', ['siteId' => 1]);

        self::assertTrue($result->isHit());
        self::assertSame(['version' => 2, 'state' => 'negative'], $result->value);
    }

    public function testRuntimeUsesBackendNeutralOwnedCacheFamilies(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $sourceFiles = [
            $pluginRoot . '/src/services/LocalCacheService.php',
            $pluginRoot . '/src/services/RedirectsService.php',
            $pluginRoot . '/src/services/DeviceDetectionService.php',
        ];

        foreach ($sourceFiles as $sourceFile) {
            $source = file_get_contents($sourceFile);
            self::assertIsString($source);
            self::assertStringNotContainsString('getRedisCacheOrLog', $source);
            self::assertStringNotContainsString('clearTrackedRedisKeys', $source);
            self::assertStringNotContainsString('executeCommand(', $source);
            self::assertStringNotContainsString('SMEMBERS', $source);
            self::assertStringNotContainsString('SRANDMEMBER', $source);
            self::assertStringNotContainsString('SCAN', $source);
            self::assertStringNotContainsString('flush()', $source);
            self::assertStringNotContainsString('FLUSHDB', $source);
        }

        $localCacheSource = file_get_contents($pluginRoot . '/src/services/LocalCacheService.php');
        self::assertIsString($localCacheSource);
        self::assertStringContainsString("FAMILY_REDIRECT_LOOKUPS = 'redirect-lookups'", $localCacheSource);
        self::assertStringContainsString("FAMILY_DEVICE = 'device'", $localCacheSource);

        $baseDevicePath = dirname($pluginRoot) . '/base/src/device/DeviceDetection.php';
        $baseDeviceSource = file_get_contents($baseDevicePath);
        self::assertIsString($baseDeviceSource, $baseDevicePath);
        self::assertStringContainsString("new ScopedCache(\$cache, \$context, 'device')", $baseDeviceSource);

        $redirectDeviceSource = file_get_contents($pluginRoot . '/src/services/DeviceDetectionService.php');
        self::assertIsString($redirectDeviceSource);
        self::assertStringContainsString("'pluginHandle' => RedirectManager::\$plugin->id", $redirectDeviceSource);
    }

    private function baseRuntimeFingerprint(string $baseRoot): string
    {
        $cacheRoot = $baseRoot . '/src/cache';
        $devicePath = $baseRoot . '/src/device/DeviceDetection.php';
        self::assertDirectoryExists($cacheRoot);
        self::assertFileExists($devicePath);

        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheRoot, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $paths[] = $file->getPathname();
            }
        }
        $paths[] = $devicePath;

        $rows = [];
        foreach ($paths as $path) {
            $fileHash = hash_file('sha256', $path);
            if ($fileHash === false) {
                throw new RuntimeException("Unable to fingerprint Base runtime source: {$path}");
            }
            $relativePath = substr($path, strlen($baseRoot) + 1);
            $rows[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath) . ':' . $fileHash;
        }
        sort($rows, SORT_STRING);

        return hash('sha256', implode("\n", $rows));
    }
}
