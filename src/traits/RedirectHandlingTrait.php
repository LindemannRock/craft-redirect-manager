<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\traits;

use Craft;

/**
 * Redirect Handling Trait
 *
 * Provides consistent 404 handling across plugins by integrating with Redirect Manager
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
trait RedirectHandlingTrait
{
    /**
     * Handle 404 by checking Redirect Manager for matching redirect
     *
     * @param string $url The 404 URL
     * @param string $source Plugin identifier (e.g., 'shortlink-manager', 'smart-links')
     * @param array $context Additional metadata (type, code, etc.)
     * @return array|null Redirect data if found, null otherwise
     */
    protected function handleRedirect404(string $url, string $source, array $context = []): ?array
    {
        // Check if Redirect Manager is installed
        if (!class_exists(\lindemannrock\redirectmanager\RedirectManager::class)) {
            return null;
        }

        // Add source to context
        $context['source'] = $source;

        // Call Redirect Manager's external 404 handler
        return \lindemannrock\redirectmanager\RedirectManager::$plugin
            ->redirects->handleExternal404($url, $context);
    }

    /**
     * Create redirect rule in Redirect Manager
     *
     * Use this for auto-creating redirects when shortlinks expire, get deleted, or need permanent redirects
     *
     * @param array $attributes Redirect attributes (sourceUrl, destinationUrl, matchType, etc.)
     * @return bool Success
     */
    protected function createRedirectRule(array $attributes): bool
    {
        // Check if Redirect Manager is installed
        if (!class_exists(\lindemannrock\redirectmanager\RedirectManager::class)) {
            return false;
        }

        // Create the redirect
        return \lindemannrock\redirectmanager\RedirectManager::$plugin
            ->redirects->createRedirect($attributes);
    }
}
