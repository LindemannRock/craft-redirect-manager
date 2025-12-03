<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\twigextensions;

use lindemannrock\redirectmanager\RedirectManager;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Plugin Name Twig Extension
 *
 * Provides centralized access to plugin name variations in Twig templates.
 *
 * Usage in templates:
 * - {{ redirectHelper.displayName }}             // "Redirect" (singular, no Manager)
 * - {{ redirectHelper.pluralDisplayName }}       // "Redirects" (plural, no Manager)
 * - {{ redirectHelper.fullName }}                // "Redirect Manager" (as configured)
 * - {{ redirectHelper.lowerDisplayName }}        // "redirect" (lowercase singular)
 * - {{ redirectHelper.pluralLowerDisplayName }}  // "redirects" (lowercase plural)
 * @since 5.0.0
 */
class PluginNameExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Redirect Manager - Plugin Name Helper';
    }

    /**
     * Make plugin name helper available as global Twig variable
     *
     * @return array
     */
    public function getGlobals(): array
    {
        return [
            'redirectHelper' => new PluginNameHelper(),
        ];
    }
}

/**
 * Plugin Name Helper
 *
 * Helper class that exposes Settings methods as properties for clean Twig syntax.
 */
class PluginNameHelper
{
    /**
     * Get display name (singular, without "Manager")
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return RedirectManager::$plugin->getSettings()->getDisplayName();
    }

    /**
     * Get plural display name (without "Manager")
     *
     * @return string
     */
    public function getPluralDisplayName(): string
    {
        return RedirectManager::$plugin->getSettings()->getPluralDisplayName();
    }

    /**
     * Get full plugin name (as configured)
     *
     * @return string
     */
    public function getFullName(): string
    {
        return RedirectManager::$plugin->getSettings()->getFullName();
    }

    /**
     * Get lowercase display name (singular, without "Manager")
     *
     * @return string
     */
    public function getLowerDisplayName(): string
    {
        return RedirectManager::$plugin->getSettings()->getLowerDisplayName();
    }

    /**
     * Get lowercase plural display name (without "Manager")
     *
     * @return string
     */
    public function getPluralLowerDisplayName(): string
    {
        return RedirectManager::$plugin->getSettings()->getPluralLowerDisplayName();
    }

    /**
     * Magic getter to allow property-style access in Twig
     * Enables: {{ redirectHelper.displayName }} instead of {{ redirectHelper.getDisplayName() }}
     *
     * @param string $name
     * @return string|null
     */
    public function __get(string $name): ?string
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }
}
