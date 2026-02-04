<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use craft\base\Component;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\traits\DeviceDetectionTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Device Detection Service
 *
 * Uses Matomo DeviceDetector library for accurate device, browser, and OS detection
 *
 * @since 5.1.0
 */
class DeviceDetectionService extends Component
{
    use LoggingTrait;
    use DeviceDetectionTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

    /**
     * Detect device information from user agent
     *
     * @param string|null $userAgent
     * @return array Device information array
     * @since 5.1.0
     */
    public function detectDevice(?string $userAgent = null): array
    {
        return $this->detectDeviceInfo($userAgent);
    }

    /**
     * Check if device is mobile (phone or tablet)
     *
     * @param array $deviceInfo
     * @return bool
     * @since 5.1.0
     */
    public function isMobileDevice(array $deviceInfo): bool
    {
        return in_array($deviceInfo['deviceType'] ?? '', ['mobile', 'tablet', 'smartphone', 'phablet']);
    }

    /**
     * Check if device is a bot
     *
     * @param array $deviceInfo
     * @return bool
     * @since 5.1.0
     */
    public function isBot(array $deviceInfo): bool
    {
        return (bool)($deviceInfo['isRobot'] ?? false);
    }

    /**
     * @inheritdoc
     */
    protected function getDeviceDetectionConfig(): array
    {
        $settings = RedirectManager::$plugin->getSettings();

        return [
            'cacheEnabled' => (bool) $settings->cacheDeviceDetection,
            'cacheStorageMethod' => $settings->cacheStorageMethod,
            'cacheDuration' => (int) $settings->deviceDetectionCacheDuration,
            'cachePath' => PluginHelper::getCachePath(RedirectManager::$plugin, 'device'),
            'cacheKeyPrefix' => PluginHelper::getCacheKeyPrefix(RedirectManager::$plugin->id, 'device'),
            'cacheKeySet' => PluginHelper::getCacheKeySet(RedirectManager::$plugin->id, 'device'),
            'includeLanguage' => false,
            'includePlatform' => false,
        ];
    }
}
