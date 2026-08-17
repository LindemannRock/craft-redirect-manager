<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use Craft;
use craft\base\Component;
use lindemannrock\base\cache\DisposableCacheStorageDecision;
use lindemannrock\base\cache\DisposableCacheStorageResolver;
use lindemannrock\base\cache\ScopedCache;
use lindemannrock\base\helpers\CacheHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Resolves and operates Redirect Manager's disposable cache storage.
 *
 * @since 5.36.0
 */
class LocalCacheService extends Component
{
    use LoggingTrait;

    /** @since 5.41.0 */
    public const FAMILY_REDIRECT_LOOKUPS = 'redirect-lookups';

    /** @since 5.41.0 */
    public const FAMILY_DEVICE = 'device';

    private const REDIRECT_CACHE_DIRECTORY = 'redirects';
    private const DEVICE_CACHE_DIRECTORY = 'device';
    private const FILE_CACHE_WRITE_MUTEX = 'redirect-manager:redirect-cache-file-write';

    /** @var array<string, true> */
    private const OWNED_FAMILIES = [
        self::FAMILY_REDIRECT_LOOKUPS => true,
        self::FAMILY_DEVICE => true,
    ];

    /** @var array<string, true> */
    private static array $loggedFailures = [];

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

    /**
     * @since 5.41.0
     */
    public function getStorageDecision(?string $configuredStorage = null): DisposableCacheStorageDecision
    {
        $configuredStorage ??= RedirectManager::$plugin->getSettings()->cacheStorageMethod;

        return (new DisposableCacheStorageResolver())->resolve(
            configuredStorageToken: $configuredStorage,
            diagnosticContext: RedirectManager::$plugin->id . ':disposable-cache',
        );
    }

    /**
     * @since 5.41.0
     */
    public function getScopedCache(
        DisposableCacheStorageDecision $decision,
        string $family,
    ): ?ScopedCache {
        $this->assertOwnedFamily($family);
        if (!$decision->usesApplicationCache() || $decision->applicationCache === null) {
            return null;
        }

        try {
            return new ScopedCache(
                $decision->applicationCache,
                RedirectManager::$plugin->id,
                $family,
            );
        } catch (\Throwable $exception) {
            $this->logFailure($family, 'initialize', $exception);
            return null;
        }
    }

    /**
     * Invalidate redirect lookups affected by a committed mutation.
     *
     * Application-cache entries are isolated by site. Any global redirect
     * mutation invalidates the complete family because global rows participate
     * in every site's lookup result.
     *
     * @since 5.41.0
     */
    public function invalidateRedirectMutation(?int $oldSiteId, ?int $newSiteId): bool
    {
        try {
            $decision = $this->getStorageDecision();
            if ($decision->isDisabled()) {
                return true;
            }

            if ($decision->usesFileCache()) {
                return $this->invalidateFileFamily(self::FAMILY_REDIRECT_LOOKUPS);
            }

            $cache = $this->getScopedCache($decision, self::FAMILY_REDIRECT_LOOKUPS);
            if ($cache === null) {
                return false;
            }

            if ($oldSiteId === null || $newSiteId === null) {
                return $this->invalidateScopedFamily($cache, self::FAMILY_REDIRECT_LOOKUPS);
            }

            $invalidated = true;
            foreach (array_unique([$oldSiteId, $newSiteId]) as $siteId) {
                if (!$cache->invalidateScope(['siteId' => $siteId])) {
                    $invalidated = false;
                    $this->logFailure(self::FAMILY_REDIRECT_LOOKUPS, 'invalidate-site');
                }
            }

            return $invalidated;
        } catch (\Throwable $exception) {
            $this->logFailure(self::FAMILY_REDIRECT_LOOKUPS, 'invalidate-mutation', $exception);
            return false;
        }
    }

    /**
     * Invalidate every redirect lookup after a bulk operation.
     *
     * @since 5.41.0
     */
    public function invalidateRedirectFamily(): bool
    {
        try {
            $decision = $this->getStorageDecision();
            if ($decision->isDisabled()) {
                return true;
            }

            if ($decision->usesFileCache()) {
                return $this->invalidateFileFamily(self::FAMILY_REDIRECT_LOOKUPS);
            }

            $cache = $this->getScopedCache($decision, self::FAMILY_REDIRECT_LOOKUPS);

            return $cache !== null && $this->invalidateScopedFamily($cache, self::FAMILY_REDIRECT_LOOKUPS);
        } catch (\Throwable $exception) {
            $this->logFailure(self::FAMILY_REDIRECT_LOOKUPS, 'invalidate-family', $exception);
            return false;
        }
    }

    /**
     * Clear cached redirect lookup entries from the effective backend.
     */
    public function clearRedirectCache(?DisposableCacheStorageDecision $decision = null): int
    {
        return $this->clearFamily(self::FAMILY_REDIRECT_LOOKUPS, $decision);
    }

    /**
     * Clear device-detection cache entries from the effective backend.
     */
    public function clearDeviceCache(?DisposableCacheStorageDecision $decision = null): int
    {
        return RedirectManager::$plugin->deviceDetection->clearCache($decision);
    }

    /**
     * Clear every Redirect Manager-owned disposable cache family.
     */
    public function clearAllCaches(?DisposableCacheStorageDecision $decision = null): int
    {
        $decision ??= $this->getStorageDecision();
        $count = 0;
        $failures = [];

        foreach ([
            self::FAMILY_REDIRECT_LOOKUPS => fn(): int => $this->clearRedirectCache($decision),
            self::FAMILY_DEVICE => fn(): int => $this->clearDeviceCache($decision),
        ] as $family => $clear) {
            try {
                $count += $clear();
            } catch (\Throwable $exception) {
                $failures[] = sprintf('%s: %s', $family, $exception->getMessage());
            }
        }

        if ($failures !== []) {
            throw new \RuntimeException('Unable to clear all required caches (' . implode('; ', $failures) . ').');
        }

        return $count;
    }

    /**
     * Clear one owned family and report required invalidation failures.
     *
     * @since 5.41.0
     */
    public function clearFamily(
        string $family,
        ?DisposableCacheStorageDecision $decision = null,
    ): int {
        $this->assertOwnedFamily($family);
        $decision ??= $this->getStorageDecision();

        if ($decision->isDisabled()) {
            return 0;
        }

        if ($decision->usesApplicationCache()) {
            $cache = $this->getScopedCache($decision, $family);
            if ($cache === null || !$cache->invalidateFamily()) {
                $this->logFailure($family, 'invalidate');
                throw new \RuntimeException(sprintf('Unable to invalidate the %s cache family.', $family));
            }

            return 0;
        }

        return $this->clearFileFamily($family);
    }

    /**
     * Count redirect lookup cache files when files are the effective backend.
     */
    public function countRedirectCacheFiles(?DisposableCacheStorageDecision $decision = null): int
    {
        return $this->countFiles(self::FAMILY_REDIRECT_LOOKUPS, $decision);
    }

    /**
     * Count device-detection cache files when files are the effective backend.
     */
    public function countDeviceCacheFiles(?DisposableCacheStorageDecision $decision = null): int
    {
        return $this->countFiles(self::FAMILY_DEVICE, $decision);
    }

    /**
     * @since 5.41.0
     */
    public function getDisplayFilePath(?DisposableCacheStorageDecision $decision = null): ?string
    {
        $decision ??= $this->getStorageDecision();
        if (!$decision->usesFileCache() || !$decision->filePathEligible) {
            return null;
        }

        return PluginHelper::getCacheBasePath(RedirectManager::$plugin);
    }

    private function countFiles(
        string $family,
        ?DisposableCacheStorageDecision $decision,
    ): int {
        $this->assertOwnedFamily($family);
        $decision ??= $this->getStorageDecision();
        if (!$decision->usesFileCache()) {
            return 0;
        }

        try {
            return CacheHelper::countCacheFiles($this->getFilePath($family));
        } catch (\Throwable $exception) {
            $this->logFailure($family, 'count-files', $exception);
            return 0;
        }
    }

    private function invalidateScopedFamily(ScopedCache $cache, string $family): bool
    {
        if ($cache->invalidateFamily()) {
            return true;
        }

        $this->logFailure($family, 'invalidate');
        return false;
    }

    private function invalidateFileFamily(string $family): bool
    {
        try {
            $this->clearFileFamily($family);
            return true;
        } catch (\Throwable $exception) {
            $this->logFailure($family, 'clear-files', $exception);
            return false;
        }
    }

    private function clearFileFamily(string $family): int
    {
        $cachePath = $this->getFilePath($family);
        if ($family !== self::FAMILY_REDIRECT_LOOKUPS) {
            return $this->clearAndVerifyFiles($cachePath, $family);
        }

        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire(self::FILE_CACHE_WRITE_MUTEX, 3)) {
            throw new \RuntimeException('Unable to acquire the redirect result cache write lock.');
        }

        try {
            return $this->clearAndVerifyFiles($cachePath, $family);
        } finally {
            $mutex->release(self::FILE_CACHE_WRITE_MUTEX);
        }
    }

    private function clearAndVerifyFiles(string $cachePath, string $family): int
    {
        $cleared = CacheHelper::clearCacheFiles($cachePath);
        if (CacheHelper::countCacheFiles($cachePath) !== 0) {
            throw new \RuntimeException(sprintf('Unable to clear every required %s cache file.', $family));
        }

        return $cleared;
    }

    private function getFilePath(string $family): string
    {
        $this->assertOwnedFamily($family);
        $directory = $family === self::FAMILY_REDIRECT_LOOKUPS
            ? self::REDIRECT_CACHE_DIRECTORY
            : self::DEVICE_CACHE_DIRECTORY;

        return PluginHelper::getCachePath(RedirectManager::$plugin, $directory);
    }

    private function assertOwnedFamily(string $family): void
    {
        if (!isset(self::OWNED_FAMILIES[$family])) {
            throw new \InvalidArgumentException('Unsupported Redirect Manager cache family.');
        }
    }

    private function logFailure(string $family, string $operation, ?\Throwable $exception = null): void
    {
        $key = $family . ':' . $operation;
        if (isset(self::$loggedFailures[$key])) {
            return;
        }
        self::$loggedFailures[$key] = true;

        Craft::warning(sprintf(
            'Redirect Manager %s cache %s failed%s; the value will be recomputed.',
            $family,
            $operation,
            $exception === null ? '' : ' (' . $exception::class . ')',
        ), RedirectManager::$plugin->id);
    }
}
