<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\variables;

use lindemannrock\redirectmanager\RedirectManager;

/**
 * Redirect Manager Variable
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class RedirectManagerVariable
{
    /**
     * Get plugin settings
     *
     * @return \lindemannrock\redirectmanager\models\Settings
     * @since 5.0.0
     */
    public function getSettings()
    {
        return RedirectManager::$plugin->getSettings();
    }

    /**
     * Get plugin instance
     *
     * @return RedirectManager
     * @since 5.0.0
     */
    public function getPlugin()
    {
        return RedirectManager::$plugin;
    }

    /**
     * Get analytics data for a specific redirect
     *
     * @param int $redirectId
     * @param string $dateRange
     * @return array
     * @since 5.1.0
     */
    public function getRedirectAnalytics(int $redirectId, string $dateRange = 'last30days'): array
    {
        return RedirectManager::$plugin->analytics->getRedirectAnalytics($redirectId, $dateRange);
    }
}
