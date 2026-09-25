<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use Composer\Semver\Semver;
use FilesystemIterator;
use lindemannrock\base\cache\DisposableCacheStorageResolver;
use lindemannrock\base\cache\ScopedCache;
use lindemannrock\base\cache\ScopedCacheResult;
use lindemannrock\redirectmanager\services\LocalCacheService;
use lindemannrock\redirectmanager\tests\Support\InstalledBasePackage;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use yii\caching\ArrayCache;

#[CoversClass(LocalCacheService::class)]
final class PortableCacheOwnershipTest extends TestCase
{
    private const EXPECTED_BASE_RUNTIME_FINGERPRINT = 'a4bf4225cff8b774ce4ace25ccfb997faa643cedb6b2f0b9fadb3db7e0d59809';

    public function testPortableCacheContractsResolveFromExpectedBaseSource(): void
    {
        self::assertSame('lindemannrock/craft-plugin-base', InstalledBasePackage::name());

        foreach ([
            DisposableCacheStorageResolver::class => 'cache/DisposableCacheStorageResolver.php',
            ScopedCache::class => 'cache/ScopedCache.php',
            ScopedCacheResult::class => 'cache/ScopedCacheResult.php',
        ] as $class => $relativePath) {
            $expectedSource = InstalledBasePackage::sourceFile($relativePath);
            self::assertFileExists($expectedSource);
            self::assertSame($expectedSource, InstalledBasePackage::reflectedClassFile($class));
        }

        self::assertSame(self::EXPECTED_BASE_RUNTIME_FINGERPRINT, $this->baseRuntimeFingerprint());
    }

    public function testInstalledBaseResolverRejectsMissingAndEscapingResources(): void
    {
        foreach ([
            'missing' => ['cache/not-present.php', 'absent'],
            'escape' => ['../composer.json', 'outside'],
        ] as $case => [$relativePath, $messageFragment]) {
            try {
                InstalledBasePackage::sourceFile($relativePath);
                self::fail("Expected installed Base resolver to reject {$case} resource.");
            } catch (RuntimeException $exception) {
                self::assertStringContainsString($messageFragment, $exception->getMessage());
            }
        }
    }

    public function testComposerRequiresPublishedDependencyFloors(): void
    {
        $composerContents = file_get_contents(dirname(__DIR__, 2) . '/composer.json');
        self::assertIsString($composerContents);
        $composer = json_decode($composerContents, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $baseConstraint = $composer['require']['lindemannrock/craft-plugin-base'] ?? null;
        $loggingConstraint = $composer['require']['lindemannrock/craft-logging-library'] ?? null;
        $phpstanConstraint = $composer['require-dev']['phpstan/phpstan'] ?? null;

        self::assertIsString($baseConstraint);
        self::assertSame('^5.38.2', $baseConstraint);
        self::assertFalse(Semver::satisfies('5.38.1', $baseConstraint));
        self::assertTrue(Semver::satisfies('5.38.2', $baseConstraint));
        self::assertTrue(Semver::satisfies('5.99.0', $baseConstraint));
        self::assertFalse(Semver::satisfies('6.0.0', $baseConstraint));
        self::assertSame('^1.12.33', $phpstanConstraint);

        self::assertIsString($loggingConstraint);
        self::assertSame('^5.19.0', $loggingConstraint);
        self::assertFalse(Semver::satisfies('5.18.2', $loggingConstraint));
        self::assertTrue(Semver::satisfies('5.19.0', $loggingConstraint));
        self::assertTrue(Semver::satisfies('5.99.0', $loggingConstraint));
        self::assertFalse(Semver::satisfies('6.0.0', $loggingConstraint));
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

        $baseDevicePath = InstalledBasePackage::sourceFile('device/DeviceDetection.php');
        $baseDeviceSource = file_get_contents($baseDevicePath);
        self::assertIsString($baseDeviceSource, $baseDevicePath);
        self::assertStringContainsString("new ScopedCache(\$cache, \$context, 'device')", $baseDeviceSource);

        $redirectDeviceSource = file_get_contents($pluginRoot . '/src/services/DeviceDetectionService.php');
        self::assertIsString($redirectDeviceSource);
        self::assertStringContainsString("'pluginHandle' => RedirectManager::\$plugin->id", $redirectDeviceSource);
    }

    private function baseRuntimeFingerprint(): string
    {
        $cacheRoot = InstalledBasePackage::sourceDirectory('cache');
        $devicePath = InstalledBasePackage::sourceFile('device/DeviceDetection.php');
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
            $relativePath = $path === $devicePath
                ? 'src/device/DeviceDetection.php'
                : 'src/cache/' . substr($path, strlen($cacheRoot) + 1);
            $rows[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath) . ':' . $fileHash;
        }
        sort($rows, SORT_STRING);

        return hash('sha256', implode("\n", $rows));
    }
}
