<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use craft\base\Component;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Matching Service
 *
 * Handles different types of URL matching for redirects
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class MatchingService extends Component
{
    use LoggingTrait;

    /**
     * Initialize the service
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('redirect-manager');
    }

    /**
     * Check if a URL matches a redirect pattern
     *
     * @param string $matchType
     * @param string $pattern
     * @param string $url
     * @return bool
     */
    public function matches(string $matchType, string $pattern, string $url): bool
    {
        return match ($matchType) {
            'exact' => $this->exactMatch($pattern, $url),
            'regex' => $this->regexMatch($pattern, $url),
            'wildcard' => $this->wildcardMatch($pattern, $url),
            'prefix' => $this->prefixMatch($pattern, $url),
            default => false,
        };
    }

    /**
     * Exact match - case-insensitive comparison
     *
     * @param string $pattern
     * @param string $url
     * @return bool
     */
    private function exactMatch(string $pattern, string $url): bool
    {
        return strcasecmp($pattern, $url) === 0;
    }

    /**
     * Regex match - matches URL against a regular expression
     *
     * @param string $pattern
     * @param string $url
     * @return bool
     */
    private function regexMatch(string $pattern, string $url): bool
    {
        try {
            $regex = '`' . $pattern . '`i';
            return preg_match($regex, $url) === 1;
        } catch (\Exception $e) {
            $this->logError('Invalid redirect regex', ['pattern' => $pattern, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Wildcard match - supports * as wildcard character
     *
     * @param string $pattern
     * @param string $url
     * @return bool
     */
    private function wildcardMatch(string $pattern, string $url): bool
    {
        // Convert wildcard pattern to regex
        // Escape special regex characters except *
        $regex = preg_quote($pattern, '/');

        // Replace escaped \* with .*
        $regex = str_replace('\*', '.*', $regex);

        // Add anchors
        $regex = '/^' . $regex . '$/i';

        try {
            return preg_match($regex, $url) === 1;
        } catch (\Exception $e) {
            $this->logError('Invalid wildcard pattern', ['pattern' => $pattern, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Prefix match - checks if URL starts with the pattern
     *
     * @param string $pattern
     * @param string $url
     * @return bool
     */
    private function prefixMatch(string $pattern, string $url): bool
    {
        return stripos($url, $pattern) === 0;
    }

    /**
     * Get all available match types
     *
     * @return array
     */
    public function getMatchTypes(): array
    {
        return [
            'exact' => 'Exact Match',
            'regex' => 'RegEx Match',
            'wildcard' => 'Wildcard Match',
            'prefix' => 'Prefix Match',
        ];
    }
}
