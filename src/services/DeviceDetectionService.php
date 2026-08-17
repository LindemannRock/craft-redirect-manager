<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use Craft;
use craft\base\Component;
use lindemannrock\base\cache\DisposableCacheStorageDecision;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\traits\DeviceDetectionTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;
use Throwable;

/**
 * Device Detection Service
 *
 * Uses Matomo DeviceDetector library for accurate device, browser, and OS detection
 *
 * @since 5.14.0
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
     */
    public function detectDevice(?string $userAgent = null): array
    {
        $deviceInfo = $this->detectDeviceInfo($userAgent, ['includeLanguage' => false]);
        $deviceInfo['language'] = $this->_detectLanguageSafely();

        return $deviceInfo;
    }

    /**
     * Check if device is mobile (phone or tablet)
     *
     * @param array $deviceInfo
     * @return bool
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
     */
    public function isBot(array $deviceInfo): bool
    {
        return (bool)($deviceInfo['isRobot'] ?? false);
    }

    /**
     * Clear cached device detection results and the request-local detector.
     *
     * @since 5.41.0
     */
    public function clearCache(?DisposableCacheStorageDecision $decision = null): int
    {
        try {
            return RedirectManager::$plugin->localCache->clearFamily(
                LocalCacheService::FAMILY_DEVICE,
                $decision,
            );
        } finally {
            $this->deviceDetection = null;
        }
    }

    /**
     * @inheritdoc
     */
    protected function getDeviceDetectionConfig(): array
    {
        $settings = RedirectManager::$plugin->getSettings();
        $decision = RedirectManager::$plugin->localCache->getStorageDecision();

        return [
            'cacheEnabled' => (bool) $settings->cacheDeviceDetection && !$decision->isDisabled(),
            'cacheStorageMethod' => $decision->usesApplicationCache() ? 'craft' : 'file',
            'cacheDuration' => (int) $settings->deviceDetectionCacheDuration,
            'pluginHandle' => RedirectManager::$plugin->id,
            'cachePath' => $decision->usesFileCache()
                ? PluginHelper::getCachePath(RedirectManager::$plugin, 'device')
                : null,
            'cacheKeyPrefix' => PluginHelper::getCacheKeyPrefix(RedirectManager::$plugin->id, 'device'),
            'includeLanguage' => false,
            'includePlatform' => false,
        ];
    }

    /**
     * Detect language without breaking console/test requests that do not expose
     * web-only query/header helpers used by the shared detector.
     */
    private function _detectLanguageSafely(): string
    {
        $request = Craft::$app->getRequest();

        if (method_exists($request, 'getQueryParam')) {
            try {
                return $this->detectLanguageFromConfig(['includeLanguage' => true]);
            } catch (Throwable $e) {
                $this->logWarning('Failed to detect request language', ['error' => $e->getMessage()]);
            }
        }

        return substr(Craft::$app->getSites()->getPrimarySite()->language, 0, 2);
    }
}
