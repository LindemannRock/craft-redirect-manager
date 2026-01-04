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
        $result = $this->matchWithCaptures($matchType, $pattern, $url);
        return $result['matched'];
    }

    /**
     * Check if a URL matches a redirect pattern and return captured groups
     *
     * Returns an array with:
     * - 'matched' (bool): Whether the URL matched the pattern
     * - 'captures' (array): Captured groups from regex/wildcard matches
     *   Index 0 = full match, 1+ = capture groups ($1, $2, etc.)
     *
     * @param string $matchType The type of match (exact, regex, wildcard, prefix)
     * @param string $pattern The pattern to match against
     * @param string $url The URL to test
     * @return array{matched: bool, captures: array}
     */
    public function matchWithCaptures(string $matchType, string $pattern, string $url): array
    {
        return match ($matchType) {
            'exact' => $this->exactMatchWithCaptures($pattern, $url),
            'regex' => $this->regexMatchWithCaptures($pattern, $url),
            'wildcard' => $this->wildcardMatchWithCaptures($pattern, $url),
            'prefix' => $this->prefixMatchWithCaptures($pattern, $url),
            default => ['matched' => false, 'captures' => []],
        };
    }

    /**
     * Apply captured groups to a destination URL
     *
     * Replaces $0, $1, $2, etc. in the destination with captured values.
     * - $0 = full matched string
     * - $1, $2, ... = captured groups from parentheses
     *
     * @param string $destination The destination URL template (e.g., "/new/$1/page")
     * @param array $captures The captured groups from matching
     * @return string The destination with captures applied
     */
    public function applyCaptures(string $destination, array $captures): string
    {
        if (empty($captures)) {
            return $destination;
        }

        // Replace $0, $1, $2, etc. with captured values
        // Start from highest number to avoid $1 replacing part of $10
        $maxIndex = count($captures) - 1;

        for ($i = $maxIndex; $i >= 0; $i--) {
            $placeholder = '$' . $i;
            if (str_contains($destination, $placeholder)) {
                $destination = str_replace($placeholder, $captures[$i] ?? '', $destination);
            }
        }

        // Also handle any remaining $n references that weren't captured (replace with empty)
        $destination = preg_replace('/\$(\d+)/', '', $destination);

        // Clean up any double slashes that might result from empty captures
        $destination = preg_replace('#(?<!:)//+#', '/', $destination);

        return $destination;
    }

    /**
     * Exact match - case-insensitive comparison
     *
     * For exact matches, $0 contains the full URL if matched.
     * No additional capture groups are available.
     *
     * @param string $pattern
     * @param string $url
     * @return array{matched: bool, captures: array}
     */
    private function exactMatchWithCaptures(string $pattern, string $url): array
    {
        $matched = strcasecmp($pattern, $url) === 0;
        return [
            'matched' => $matched,
            'captures' => $matched ? [$url] : [],
        ];
    }

    /**
     * Regex match - matches URL against a regular expression
     *
     * Captures are available as $0 (full match), $1, $2, etc.
     *
     * @param string $pattern
     * @param string $url
     * @return array{matched: bool, captures: array}
     */
    private function regexMatchWithCaptures(string $pattern, string $url): array
    {
        try {
            $regex = '`' . $pattern . '`i';
            $matches = [];
            $result = preg_match($regex, $url, $matches);

            if ($result === 1) {
                $this->logDebug('Regex match successful', [
                    'pattern' => $pattern,
                    'url' => $url,
                    'captures' => $matches,
                ]);
                return [
                    'matched' => true,
                    'captures' => $matches,
                ];
            }

            return ['matched' => false, 'captures' => []];
        } catch (\Exception $e) {
            $this->logError('Invalid redirect regex', ['pattern' => $pattern, 'error' => $e->getMessage()]);
            return ['matched' => false, 'captures' => []];
        }
    }

    /**
     * Wildcard match - supports * as wildcard character
     *
     * Each * becomes a capture group accessible as $1, $2, etc.
     * For example: /blog/* matching /blog/my-post captures "my-post" as $1
     *
     * @param string $pattern
     * @param string $url
     * @return array{matched: bool, captures: array}
     */
    private function wildcardMatchWithCaptures(string $pattern, string $url): array
    {
        // Convert wildcard pattern to regex with capture groups
        // Escape special regex characters except *
        $regex = preg_quote($pattern, '/');

        // Replace escaped \* with (.*) to create capture groups
        $regex = str_replace('\*', '(.*)', $regex);

        // Add anchors
        $regex = '/^' . $regex . '$/i';

        try {
            $matches = [];
            $result = preg_match($regex, $url, $matches);

            if ($result === 1) {
                $this->logDebug('Wildcard match successful', [
                    'pattern' => $pattern,
                    'url' => $url,
                    'captures' => $matches,
                ]);
                return [
                    'matched' => true,
                    'captures' => $matches,
                ];
            }

            return ['matched' => false, 'captures' => []];
        } catch (\Exception $e) {
            $this->logError('Invalid wildcard pattern', ['pattern' => $pattern, 'error' => $e->getMessage()]);
            return ['matched' => false, 'captures' => []];
        }
    }

    /**
     * Prefix match - checks if URL starts with the pattern
     *
     * For prefix matches:
     * - $0 = the full URL
     * - $1 = the remainder of the URL after the prefix
     *
     * @param string $pattern
     * @param string $url
     * @return array{matched: bool, captures: array}
     */
    private function prefixMatchWithCaptures(string $pattern, string $url): array
    {
        if (stripos($url, $pattern) === 0) {
            // Capture the remainder after the prefix
            $remainder = substr($url, strlen($pattern));

            $this->logDebug('Prefix match successful', [
                'pattern' => $pattern,
                'url' => $url,
                'remainder' => $remainder,
            ]);

            return [
                'matched' => true,
                'captures' => [$url, $remainder],
            ];
        }

        return ['matched' => false, 'captures' => []];
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
