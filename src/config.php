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
    // Global settings
    '*' => [
        // ========================================
        // GENERAL SETTINGS
        // ========================================
        // Basic plugin configuration and redirect behavior

        'pluginName' => 'Redirect Manager',

        // IP Privacy Protection
        // Generate salt with: php craft redirect-manager/security/generate-salt
        // Store in .env as: REDIRECT_MANAGER_IP_SALT="your-64-char-salt"
        'ipHashSalt' => App::env('REDIRECT_MANAGER_IP_SALT'),

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

        // Logging Settings
        'logLevel' => 'error',             // Log level: 'debug', 'info', 'warning', 'error'


        // ========================================
        // ANALYTICS SETTINGS
        // ========================================
        // 404 tracking, device detection, and data retention

        // Enable Analytics
        // Track 404 analytics including device, browser, and visitor data (master switch)
        // When enabled, IP addresses are always captured and hashed with salt
        'enableAnalytics' => true,

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

        // Strip Query String From Stats
        // Should query strings be stripped from analytics URLs
        'stripQueryStringFromStats' => true,

        // Analytics Limit
        // Maximum number of unique 404 records to retain
        'analyticsLimit' => 1000,

        // Analytics Retention
        // Number of days to retain analytics (0 = keep forever)
        'analyticsRetention' => 30,

        // Auto Trim Analytics
        // Whether analytics should be automatically trimmed
        'autoTrimAnalytics' => true,


        // ========================================
        // INTERFACE SETTINGS
        // ========================================
        // Control panel display options

        'refreshIntervalSecs' => 5,        // Dashboard refresh interval in seconds
        'redirectsDisplayLimit' => 100,    // How many redirects to display in the CP
        'analyticsDisplayLimit' => 100,    // How many analytics to display in the CP
        'itemsPerPage' => 100,             // Items per page in list views


        // ========================================
        // CACHE SETTINGS
        // ========================================
        // Performance and caching configuration

        // Redirect Cache
        'enableRedirectCache' => true,     // Enable caching of redirect lookups
        'redirectCacheDuration' => 3600,   // How long to cache redirect lookups (1 hour)

        // Device Detection Cache
        'cacheDeviceDetection' => true,    // Cache device detection results
        'deviceDetectionCacheDuration' => 3600, // Device detection cache duration (1 hour)


        // ========================================
        // ADVANCED SETTINGS
        // ========================================
        // API endpoints, exclusion patterns, and custom headers

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
    ],

    // Dev environment settings
    'dev' => [
        'logLevel' => 'debug',             // More verbose logging in dev
        'analyticsRetention' => 30,        // Keep less data in dev
        'cacheDeviceDetection' => false,   // No cache - testing
        'enableRedirectCache' => true,
        'redirectCacheDuration' => 60,     // 1 minute - see changes quickly
    ],

    // Staging environment settings
    'staging' => [
        'logLevel' => 'info',              // Moderate logging in staging
        'analyticsRetention' => 90,
        'cacheDeviceDetection' => true,
        'deviceDetectionCacheDuration' => 1800, // 30 minutes
        'redirectCacheDuration' => 3600,   // 1 hour
    ],

    // Production environment settings
    'production' => [
        'logLevel' => 'error',             // Only errors in production
        'analyticsRetention' => 365,       // Keep more data in production
        'cacheDeviceDetection' => true,
        'deviceDetectionCacheDuration' => 7200, // 2 hours
        'redirectCacheDuration' => 86400,  // 24 hours - aggressive caching
    ],
];
