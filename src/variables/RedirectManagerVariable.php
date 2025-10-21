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
 * @since     1.0.0
 */
class RedirectManagerVariable
{
    /**
     * Get plugin settings
     *
     * @return \lindemannrock\redirectmanager\models\Settings
     */
    public function getSettings()
    {
        return RedirectManager::$plugin->getSettings();
    }

    /**
     * Get plugin instance
     *
     * @return RedirectManager
     */
    public function getPlugin()
    {
        return RedirectManager::$plugin;
    }

    /**
     * Get backup history
     *
     * @return array
     */
    public function getBackupHistory(): array
    {
        return (new \craft\db\Query())
            ->from('{{%redirectmanager_import_history}}')
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
    }
}
