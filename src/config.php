<?php
/**
 * Redirect Manager config.php
 *
 * This file exists only as a template for the Redirect Manager settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'redirect-manager.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

use craft\helpers\App;

return [
    // Plugin Name
    // The public-facing name of the plugin
    'pluginName' => 'Redirect Manager',

    // Auto Create Redirects
    // Controls whether redirects are automatically created when entry URIs change
    'autoCreateRedirects' => true,

    // Undo Window
    // Time window in minutes for detecting immediate undo (A → B → A)
    // Options: 30, 60, 120, 240 (default: 60)
    // If editor changes slug back within this window, the redirect is cancelled instead of creating a pair
    'undoWindowMinutes' => 60,

    // Redirect Source Match
    // Should the legacy URL be matched by path ('pathonly') or full URL ('fullurl')
    'redirectSrcMatch' => 'pathonly',

    // Strip Query String
    // Should the query string be stripped from all 404 URLs before evaluation
    'stripQueryString' => false,

    // Preserve Query String
    // Should the query string be preserved and passed to the destination
    'preserveQueryString' => false,

    // Set No-Cache Headers
    // Should no-cache headers be set on redirect responses
    'setNoCacheHeaders' => true,

    // Enable Analytics
    // Track 404 statistics including device, browser, and visitor data (master switch)
    // When enabled, IP addresses are always captured and hashed with salt
    'enableAnalytics' => true,

    // IP Privacy Protection
    // Generate salt with: php craft redirect-manager/security/generate-salt
    // Store in .env as: REDIRECT_MANAGER_IP_SALT="your-64-char-salt"
    'ipHashSalt' => App::env('REDIRECT_MANAGER_IP_SALT'),

    // Anonymize IP Addresses
    // Mask IP addresses before hashing (subnet masking for maximum privacy)
    // IPv4: masks last octet (192.168.1.123 → 192.168.1.0)
    // IPv6: masks last 80 bits
    // Trade-off: Reduces unique visitor accuracy (users on same subnet counted as one)
    'anonymizeIpAddress' => false,

    // Enable Geographic Detection
    // Detect visitor location (country, city) from IP addresses using ip-api.com
    // Free service with 45 requests per minute limit
    'enableGeoDetection' => false,

    // Cache Device Detection
    // Cache device detection results for better performance
    'cacheDeviceDetection' => true,
    'deviceDetectionCacheDuration' => 3600, // 1 hour

    // Strip Query String From Stats
    // Should query strings be stripped from statistics URLs
    'stripQueryStringFromStats' => true,

    // Statistics Limit
    // Maximum number of unique 404 records to retain
    'statisticsLimit' => 1000,

    // Statistics Retention
    // Number of days to retain statistics (0 = keep forever)
    'statisticsRetention' => 30,

    // Auto Trim Statistics
    // Whether statistics should be automatically trimmed
    'autoTrimStatistics' => true,

    // Refresh Interval
    // Dashboard refresh interval in seconds
    'refreshIntervalSecs' => 5,

    // Redirects Display Limit
    // How many redirects to display in the CP
    'redirectsDisplayLimit' => 100,

    // Statistics Display Limit
    // How many statistics to display in the CP
    'statisticsDisplayLimit' => 100,

    // Items Per Page
    // Items per page in list views
    'itemsPerPage' => 100,

    // Enable API Endpoint
    // Whether to enable the GraphQL endpoint
    'enableApiEndpoint' => false,

    // Exclude Patterns
    // Regular expressions to exclude URLs from redirect handling
    // Note: Don't exclude static assets - you want to track missing CSS/JS/images to fix them!
    'excludePatterns' => [
        // Recommended exclusions (uncomment as needed):
        // ['pattern' => '^/admin'],                    // Craft admin panel
        // ['pattern' => '^/cms'],                      // Custom admin URLs
        // ['pattern' => '^/cpresources'],              // Admin panel resources
        // ['pattern' => '^/actions'],                  // Craft controller actions
        // ['pattern' => '^/\\.well-known'],            // Security/verification files
    ],

    // Additional Headers
    // Additional HTTP headers to add to redirect responses
    // Note: Cache headers are controlled by the 'setNoCacheHeaders' setting
    'additionalHeaders' => [
        // Recommended headers (uncomment as needed):
        // ['name' => 'X-Robots-Tag', 'value' => 'noindex, nofollow'], // IMPORTANT: Prevents search engines from indexing old URLs
        // ['name' => 'X-Redirect-By', 'value' => 'Redirect Manager'],  // Debugging/tracking
    ],

    // Log Level
    // Log level for the logging library (debug, info, warning, error)
    'logLevel' => 'error',

    // Enable Redirect Cache
    // Enable caching of redirect lookups for improved performance
    'enableRedirectCache' => true,

    // Redirect Cache Duration
    // How long to cache redirect lookups in seconds (default: 1 hour)
    'redirectCacheDuration' => 3600,
];
