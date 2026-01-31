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
 *
 * @since 5.0.0
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
        // Detect visitor location (country, city) from IP addresses
        'enableGeoDetection' => false,

        // Geo IP lookup provider
        // Options: 'ip-api.com', 'ipapi.co', 'ipinfo.io'
        // - ip-api.com: HTTP free (45/min), HTTPS requires paid key (default, backward compatible)
        // - ipapi.co: HTTPS, 1,000 requests/day free
        // - ipinfo.io: HTTPS, 50,000 requests/month free
        // 'geoProvider' => 'ip-api.com',

        // Geo provider API key
        // Required for ip-api.com HTTPS (Pro tier)
        // Optional for ipapi.co and ipinfo.io (increases rate limits)
        // 'geoApiKey' => App::env('REDIRECT_MANAGER_GEO_API_KEY'),

        // Default location for local development
        // Used when IP address is private/local (127.0.0.1, 192.168.x.x, etc.)
        // 'defaultCountry' => App::env('REDIRECT_MANAGER_DEFAULT_COUNTRY') ?: 'AE', // 2-letter country code (US, GB, AE, etc.)
        // 'defaultCity' => App::env('REDIRECT_MANAGER_DEFAULT_CITY') ?: 'Dubai', // Must match a city in the predefined locations list

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
        'itemsPerPage' => 100,             // Items per page in list views


        // ========================================
        // CACHE SETTINGS
        // ========================================
        // Performance and caching configuration

        // Cache Storage Method
        // 'file' = File system (default, single server)
        // 'redis' = Redis/Database (load-balanced, multi-server, cloud hosting)
        'cacheStorageMethod' => 'file',

        // Redirect Cache
        'enableRedirectCache' => true,     // Enable caching of redirect lookups
        'redirectCacheDuration' => 3600,   // How long to cache redirect lookups (1 hour)

        // Device Detection Cache
        'cacheDeviceDetection' => true,    // Cache device detection results
        'deviceDetectionCacheDuration' => 3600, // Device detection cache duration (1 hour)


        // ========================================
        // BACKUP SETTINGS
        // ========================================
        // Import backup storage configuration and scheduling

        // Enable Backups
        // Master switch for automatic backup functionality
        'backupEnabled' => true,

        // Backup Before Import
        // Automatically create a backup before importing CSV files
        'backupOnImport' => true,

        // Backup Schedule
        // Options: 'manual', 'daily', 'weekly', 'monthly'
        // Uses Craft's queue if running, or set up a cron job:
        // craft redirect-manager/backup/scheduled
        'backupSchedule' => 'manual',

        // Backup Retention Days
        // Number of days to keep automatic backups (0 = keep forever)
        // Manual backups are never deleted automatically
        'backupRetentionDays' => 30,

        // Backup Path
        // Local filesystem path for storing import backups
        // Supports aliases: @storage, @root
        // Supports environment variables: $BACKUP_PATH
        // Default: @storage/redirect-manager/backups
        'backupPath' => '@storage/redirect-manager/backups',

        // Backup Volume UID
        // Optional: Store backups in an asset volume instead of local filesystem
        // When set, this takes precedence over backupPath
        // Example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'
        'backupVolumeUid' => null,


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


        // ========================================
        // BASE PLUGIN OVERRIDES
        // ========================================
        // Override settings from lindemannrock-base config
        // These affect all LindemannRock plugins, but can be overridden here per-plugin
        //
        // Configure globally in: config/lindemannrock-base.php
        // Or override per-plugin below:
        //
        // Date/time formatting:
        // 'timeFormat' => '12',       // '12' (AM/PM) or '24' (military)
        // 'dateOrder' => 'dmy',       // 'dmy', 'mdy', or 'ymd'
        // 'dateSeparator' => '/',     // '/', '-', or '.'
        // 'monthFormat' => 'numeric', // 'numeric', 'short', 'long'
        // 'showSeconds' => false,     // Include seconds in time display
        //
        // Analytics defaults:
        // 'defaultDateRange' => 'last7days',  // Default date range for analytics (today, yesterday, last7days, last30days, thisMonth, lastMonth, last90days, thisYear, allTime)
        //
        // Export formats:
        // 'exports' => [
        //     'excel' => true,
        //     'csv' => true,
        //     'json' => true,
        // ],
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
        'cacheStorageMethod' => 'redis',   // Use Redis for production (Servd/AWS/Platform.sh)
        'cacheDeviceDetection' => true,
        'deviceDetectionCacheDuration' => 7200, // 2 hours
        'redirectCacheDuration' => 86400,  // 24 hours - aggressive caching
    ],
];
