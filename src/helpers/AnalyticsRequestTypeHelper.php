<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\helpers;

/**
 * Analytics Request Type Helper
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.32.0
 */
class AnalyticsRequestTypeHelper
{
    /**
     * Security probe URL patterns (vulnerability scanning attempts).
     */
    private const SECURITY_PROBE_PATTERNS = [
        // Database dumps
        '/\.sql($|\.)/i',
        '/\/(dump|backup|database|db)\.(sql|zip|tar|gz|rar|7z)/i',

        // Config/sensitive files
        '/\/\.env($|\.)/i',
        '/\/\.git($|\/)/i',
        '/\/\.htaccess$/i',
        '/\/\.htpasswd$/i',
        '/\/\.aws($|\/)/i',
        '/\/\.ssh($|\/)/i',
        '/\/\.DS_Store$/i',
        '/^\/composer\.(json|lock)$/i',
        '/^\/package(-lock)?\.json$/i',
        '/\/wp-config\.php/i',

        // Admin panels / tools
        '/\/phpmyadmin/i',
        '/\/adminer\.php/i',
        '/^\/pma($|\/)/i',
        '/^\/mysql($|\/)/i',
        '/^\/myadmin($|\/)/i',

        // Shell/exploit attempts
        '/\/shell\.php/i',
        '/\/cmd\.php/i',
        '/\/c99\.php/i',
        '/\/r57\.php/i',
        '/\/webshell/i',
        '/\/cgi-bin\//i',
        '/\/eval-stdin/i',

        // Common scanner paths
        '/\/sftp(-config)?\.json/i',
        '/^\/debug($|\/)/i',
        '/\/phpinfo\.php/i',
        '/^\/server-status($|\/)/i',
        '/\.axd$/i',
        '/\/web\.config$/i',
        '/\/xmlrpc\.php$/i',
    ];

    /**
     * Detect the analytics request type.
     *
     * @param string $url
     * @param bool|null $isRobot
     * @return string 'probe', 'bot', or 'normal'
     */
    public static function detect(string $url, ?bool $isRobot = false): string
    {
        foreach (self::SECURITY_PROBE_PATTERNS as $pattern) {
            if (preg_match($pattern, $url) === 1) {
                return 'probe';
            }
        }

        return $isRobot ? 'bot' : 'normal';
    }
}
