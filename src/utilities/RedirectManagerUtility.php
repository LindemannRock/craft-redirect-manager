<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\utilities;

use Craft;
use craft\base\Utility;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Redirect Manager Utility
 *
 * @since 5.1.0
 */
class RedirectManagerUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return RedirectManager::$plugin->getSettings()->getFullName();
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'redirect-manager';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return 'arrows-turn-right';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $settings = RedirectManager::$plugin->getSettings();
        $pluginName = $settings->getFullName();

        // Get redirect stats
        $totalRedirects = (new \craft\db\Query())
            ->from('{{%redirectmanager_redirects}}')
            ->count();

        $activeRedirects = (new \craft\db\Query())
            ->from('{{%redirectmanager_redirects}}')
            ->where(['enabled' => true])
            ->count();

        // Get 404 stats (core functionality - always show)
        $chartData = RedirectManager::$plugin->analytics->getChartData(null, 7);
        $total404s = array_sum(array_column($chartData, 'handled')) + array_sum(array_column($chartData, 'unhandled'));
        $handled = array_sum(array_column($chartData, 'handled'));
        $unhandled = array_sum(array_column($chartData, 'unhandled'));

        // Get cache counts (only for file storage)
        $deviceCacheFiles = 0;
        $redirectCacheFiles = 0;

        // Only count files when using file storage (Redis counts are not displayed)
        if ($settings->cacheStorageMethod === 'file') {
            if ($settings->cacheDeviceDetection) {
                $devicePath = Craft::$app->path->getRuntimePath() . '/redirect-manager/cache/device/';
                if (is_dir($devicePath)) {
                    $deviceCacheFiles = count(glob($devicePath . '*.cache'));
                }
            }

            if ($settings->enableRedirectCache) {
                $redirectPath = Craft::$app->path->getRuntimePath() . '/redirect-manager/cache/redirects/';
                if (is_dir($redirectPath)) {
                    $redirectCacheFiles = count(glob($redirectPath . '*.cache'));
                }
            }
        }

        return Craft::$app->getView()->renderTemplate('redirect-manager/utilities/index', [
            'pluginName' => $pluginName,
            'settings' => $settings,
            'totalRedirects' => $totalRedirects,
            'activeRedirects' => $activeRedirects,
            'total404s' => $total404s,
            'handled' => $handled,
            'unhandled' => $unhandled,
            'deviceCacheFiles' => $deviceCacheFiles,
            'redirectCacheFiles' => $redirectCacheFiles,
            'storageMethod' => $settings->cacheStorageMethod,
        ]);
    }
}
