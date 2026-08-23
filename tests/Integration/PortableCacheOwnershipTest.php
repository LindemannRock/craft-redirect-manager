<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\base\cache\DisposableCacheStorageResolver;
use lindemannrock\base\cache\ScopedCache;
use lindemannrock\base\cache\ScopedCacheResult;
use lindemannrock\redirectmanager\services\LocalCacheService;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use yii\caching\ArrayCache;

#[CoversClass(LocalCacheService::class)]
final class PortableCacheOwnershipTest extends TestCase
{
    private const APPROVED_BASE_COMMIT = '8fc9269ac46b71d2f67f3114861e04851d6374a5';

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

        self::assertSame(self::APPROVED_BASE_COMMIT, $this->repositoryHead($baseRoot));
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

    private function repositoryHead(string $repositoryRoot): string
    {
        $gitDirectory = $repositoryRoot . '/.git';
        $head = file_get_contents($gitDirectory . '/HEAD');
        self::assertIsString($head);
        $head = trim($head);

        if (!str_starts_with($head, 'ref: ')) {
            return $head;
        }

        $ref = substr($head, 5);
        $looseRef = $gitDirectory . '/' . $ref;
        if (is_file($looseRef)) {
            $commit = file_get_contents($looseRef);
            self::assertIsString($commit);

            return trim($commit);
        }

        $packedRefs = file($gitDirectory . '/packed-refs', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($packedRefs);
        foreach ($packedRefs as $line) {
            if (str_starts_with($line, '#') || str_starts_with($line, '^')) {
                continue;
            }

            [$commit, $packedRef] = array_pad(explode(' ', $line, 2), 2, null);
            if ($packedRef === $ref) {
                return $commit;
            }
        }

        self::fail('The Base repository HEAD could not be resolved.');
    }
}
