<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use craft\base\Component;
use lindemannrock\base\helpers\CacheHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Clears Redirect Manager-owned local caches.
 *
 * @since 5.36.0
 */
class LocalCacheService extends Component
{
    use LoggingTrait;

    private const REDIRECT_CACHE_KEY_TYPE = 'redirect';
    private const REDIRECT_CACHE_DIRECTORY = 'redirects';
    private const DEVICE_CACHE_KEY_TYPE = 'device';
    private const DEVICE_CACHE_DIRECTORY = 'device';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

    /**
     * Clear redirect lookup cache entries from the configured local backend.
     */
    public function clearRedirectCache(): int
    {
        $settings = RedirectManager::$plugin->getSettings();

        if ($settings->cacheStorageMethod === 'redis') {
            $count = CacheHelper::clearTrackedRedisKeys(
                RedirectManager::$plugin->id,
                self::REDIRECT_CACHE_KEY_TYPE,
            );

            $this->logDebug('Redirect caches invalidated (Redis)', ['count' => $count]);

            return $count;
        }

        $count = CacheHelper::clearCacheFiles($this->redirectCacheDirectory());
        $this->logDebug('Redirect caches invalidated (File)', ['count' => $count]);

        return $count;
    }

    /**
     * Clear device-detection cache entries from the configured local backend.
     */
    public function clearDeviceCache(): int
    {
        $settings = RedirectManager::$plugin->getSettings();

        if ($settings->cacheStorageMethod === 'redis') {
            return CacheHelper::clearTrackedRedisKeys(
                RedirectManager::$plugin->id,
                self::DEVICE_CACHE_KEY_TYPE,
            );
        }

        return CacheHelper::clearCacheFiles($this->deviceCacheDirectory());
    }

    /**
     * Clear every Redirect Manager-owned local cache namespace.
     */
    public function clearAllCaches(): int
    {
        return $this->clearRedirectCache() + $this->clearDeviceCache();
    }

    /**
     * Count redirect lookup cache files.
     */
    public function countRedirectCacheFiles(): int
    {
        $settings = RedirectManager::$plugin->getSettings();
        if ($settings->cacheStorageMethod !== 'file') {
            return 0;
        }

        return CacheHelper::countCacheFiles($this->redirectCacheDirectory());
    }

    /**
     * Count device-detection cache files.
     */
    public function countDeviceCacheFiles(): int
    {
        $settings = RedirectManager::$plugin->getSettings();
        if ($settings->cacheStorageMethod !== 'file') {
            return 0;
        }

        return CacheHelper::countCacheFiles($this->deviceCacheDirectory());
    }

    private function redirectCacheDirectory(): string
    {
        return PluginHelper::getCachePath(RedirectManager::$plugin, self::REDIRECT_CACHE_DIRECTORY);
    }

    private function deviceCacheDirectory(): string
    {
        return PluginHelper::getCachePath(RedirectManager::$plugin, self::DEVICE_CACHE_DIRECTORY);
    }
}
