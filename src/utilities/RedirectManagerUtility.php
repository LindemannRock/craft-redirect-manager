<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\utilities;

use Craft;
use craft\base\Utility;
use lindemannrock\base\cache\DisposableCacheStoragePresenter;
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
        return '@lindemannrock/redirectmanager/icon-mask.svg';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $settings = RedirectManager::$plugin->getSettings();
        $pluginName = $settings->getFullName();
        $user = Craft::$app->getUser();

        $totalRedirects = 0;
        $activeRedirects = 0;
        if ($user->getIdentity() && $user->checkPermission('redirectManager:manageRedirects')) {
            $totalRedirects = (new \craft\db\Query())
                ->from('{{%redirectmanager_redirects}}')
                ->count();

            $activeRedirects = (new \craft\db\Query())
                ->from('{{%redirectmanager_redirects}}')
                ->where(['enabled' => true])
                ->count();
        }

        $total404s = 0;
        $handled = 0;
        $unhandled = 0;
        if ($settings->enableAnalytics && $user->getIdentity() && $user->checkPermission('redirectManager:viewAnalytics')) {
            $chartData = RedirectManager::$plugin->analytics->getChartData(null, 7);
            $total404s = array_sum(array_column($chartData, 'handled')) + array_sum(array_column($chartData, 'unhandled'));
            $handled = array_sum(array_column($chartData, 'handled'));
            $unhandled = array_sum(array_column($chartData, 'unhandled'));
        }

        $analyticsCount = 0;
        if ($settings->enableAnalytics && $user->getIdentity() && $user->checkPermission('redirectManager:clearAnalytics')) {
            $analyticsCount = (new \craft\db\Query())
                ->from('{{%redirectmanager_analytics}}')
                ->count();
        }

        // Resolve effective storage once for semantic status and file counting.
        $deviceCacheFiles = 0;
        $redirectCacheFiles = 0;
        $cacheDecision = RedirectManager::$plugin->localCache->getStorageDecision();
        $cacheStorage = (new DisposableCacheStoragePresenter())->present(
            $cacheDecision,
            $settings->enableRedirectCache || $settings->cacheDeviceDetection,
        );
        $showCacheCounts = false;

        if ($user->getIdentity() && $user->checkPermission('redirectManager:clearCache') && $cacheDecision->usesFileCache()) {
            $showCacheCounts = true;
            if ($settings->cacheDeviceDetection) {
                $deviceCacheFiles = RedirectManager::$plugin->localCache->countDeviceCacheFiles($cacheDecision);
            }

            if ($settings->enableRedirectCache) {
                $redirectCacheFiles = RedirectManager::$plugin->localCache->countRedirectCacheFiles($cacheDecision);
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
            'cacheStorage' => $cacheStorage,
            'showCacheCounts' => $showCacheCounts,
            'cacheStorageDisabled' => $cacheDecision->isDisabled(),
            'analyticsCount' => (int) $analyticsCount,
        ]);
    }
}
