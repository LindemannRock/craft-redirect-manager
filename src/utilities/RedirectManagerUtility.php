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
 */
class RedirectManagerUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $pluginName = RedirectManager::$plugin->getSettings()->pluginName ?? 'Redirect Manager';
        return $pluginName;
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
        return '@app/icons/tool.svg';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $settings = RedirectManager::$plugin->getSettings();
        $pluginName = $settings->pluginName ?? 'Redirect Manager';

        // Get redirect stats
        $totalRedirects = (new \craft\db\Query())
            ->from('{{%redirectmanager_redirects}}')
            ->count();

        $activeRedirects = (new \craft\db\Query())
            ->from('{{%redirectmanager_redirects}}')
            ->where(['enabled' => true])
            ->count();

        // Get 404 stats
        $total404s = 0;
        $handled = 0;
        $unhandled = 0;

        if ($settings->enableAnalytics) {
            $chartData = RedirectManager::$plugin->statistics->getChartData(null, 7);
            $total404s = array_sum(array_column($chartData, 'handled')) + array_sum(array_column($chartData, 'unhandled'));
            $handled = array_sum(array_column($chartData, 'handled'));
            $unhandled = array_sum(array_column($chartData, 'unhandled'));
        }

        // Get cache file counts
        $deviceCacheFiles = 0;
        $redirectCacheFiles = 0;

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
        ]);
    }
}
