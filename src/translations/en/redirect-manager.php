<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 * @since     5.0.0
 */

return [
    'Redirect Manager' => 'Redirect Manager',
    'Create redirects, monitor 404s, and keep traffic flowing cleanly from one control panel workspace.' => 'Create redirects, monitor 404s, and keep traffic flowing cleanly from one control panel workspace.',
    'Open Redirect Manager' => 'Open Redirect Manager',
    '{name} plugin loaded' => '{name} plugin loaded',
    '{pluginName} Cache' => '{pluginName} Cache',

    // =========================================================================
    // Navigation & Settings Sidebar
    // =========================================================================

    'Dashboard' => 'Dashboard',
    'Redirects' => 'Redirects',
    'Analytics' => 'Analytics',
    'Settings' => 'Settings',
    'Logs' => 'Logs',
    'General' => 'General',
    'Interface' => 'Interface',
    'Backup' => 'Backup',
    'Cache' => 'Cache',
    'Advanced' => 'Advanced',
    'Test' => 'Test',

    // =========================================================================
    // Permissions
    // =========================================================================

    'View redirects' => 'View redirects',
    'Create redirects' => 'Create redirects',
    'Edit redirects' => 'Edit redirects',
    'Delete redirects' => 'Delete redirects',
    'View analytics' => 'View analytics',
    'View system logs' => 'View system logs',
    'Manage settings' => 'Manage settings',

    // =========================================================================
    // Common / Shared
    // =========================================================================

    'Save Settings' => 'Save Settings',
    'All' => 'All',
    'All Types' => 'All Types',
    'All Sites' => 'All Sites',
    'Yes' => 'Yes',
    'No' => 'No',
    'Site' => 'Site',
    'URL' => 'URL',
    'Hits' => 'Hits',
    'Status' => 'Status',
    'Type' => 'Type',
    'Enabled' => 'Enabled',
    'Disabled' => 'Disabled',
    'Handled' => 'Handled',
    'Unhandled' => 'Unhandled',
    'Count' => 'Count',
    'Referrer' => 'Referrer',
    'Source URL' => 'Source URL',
    'Destination URL' => 'Destination URL',
    'Match Type' => 'Match Type',
    'Status Code' => 'Status Code',
    'Priority' => 'Priority',
    'Hit Count' => 'Hit Count',
    'Last Hit' => 'Last Hit',
    'Created' => 'Created',
    'Updated' => 'Updated',
    'Learn more' => 'Learn more',
    'Enable' => 'Enable',
    'Disable' => 'Disable',
    'Import' => 'Import',
    'Cancel' => 'Cancel',

    // Match Types
    'Exact Match' => 'Exact Match',
    'RegEx Match' => 'RegEx Match',
    'Wildcard Match' => 'Wildcard Match',
    'Prefix Match' => 'Prefix Match',
    'Match' => 'Match',

    // =========================================================================
    // Dashboard (404 Analytics Table)
    // =========================================================================

    'Search URLs...' => 'Search URLs...',
    'Request Type' => 'Request Type',
    'Normal' => 'Normal',
    'Bot' => 'Bot',
    'Security Probe' => 'Security Probe',
    'Probe' => 'Probe',
    'Device' => 'Device',
    'Browser' => 'Browser',
    'No analytics found.' => 'No analytics found.',
    'New {singularName}' => 'New {singularName}',
    'Visit URL' => 'Visit URL',
    'Edit handling redirect' => 'Edit handling redirect',
    'Security vulnerability scanning attempt' => 'Security vulnerability scanning attempt',
    'Regular browser request' => 'Regular browser request',
    'Edit {item}' => 'Edit {item}',
    'Create {item}' => 'Create {item}',
    'Clear All' => 'Clear All',
    'Clear' => 'Clear',
    'Are you sure you want to clear ALL analytics? This cannot be undone.' => 'Are you sure you want to clear ALL analytics? This cannot be undone.',

    // =========================================================================
    // Redirects Listing
    // =========================================================================

    'Creation Type' => 'Creation Type',
    'Manual' => 'Manual',
    'Auto-created' => 'Auto-created',
    'Auto' => 'Auto',
    'Search {pluginName}...' => 'Search {pluginName}...',
    'No {items} found.' => 'No {items} found.',
    'Source' => 'Source',
    'Entry Changes' => 'Entry Changes',
    'User' => 'User',

    // =========================================================================
    // Redirect Edit Page
    // =========================================================================

    'Edit {singularName}' => 'Edit {singularName}',
    'Source Match Mode' => 'Source Match Mode',
    'Match by complete URL including domain (e.g., https://example.com/old-page).' => 'Match by complete URL including domain (e.g., https://example.com/old-page).',
    'Match by path only (e.g., /old-page). Works across all domains.' => 'Match by path only (e.g., /old-page). Works across all domains.',
    'Path Only' => 'Path Only',
    'Full URL' => 'Full URL',
    'Full URLs entered will be automatically converted to paths when saving.' => 'Full URLs entered will be automatically converted to paths when saving.',
    'How the source URL should be matched' => 'How the source URL should be matched',
    'Enter the full URL to match (e.g., https://example.com/old-page).' => 'Enter the full URL to match (e.g., https://example.com/old-page).',
    'Enter the path to match (e.g., /old-page). Full URLs will be automatically converted to paths.' => 'Enter the path to match (e.g., /old-page). Full URLs will be automatically converted to paths.',
    'Test your pattern at' => 'Test your pattern at',
    'before saving.' => 'before saving.',
    'Full URL (https://example.com) or path (/page)' => 'Full URL (https://example.com) or path (/page)',
    'Redirects are checked in priority order (0 = highest priority, 9 = lowest). Use this when you have overlapping patterns. For example, set a specific pattern to priority 0 and a general catch-all to priority 9.' => 'Redirects are checked in priority order (0 = highest priority, 9 = lowest). Use this when you have overlapping patterns. For example, set a specific pattern to priority 0 and a general catch-all to priority 9.',
    'Highest priority' => 'Highest priority',
    'Lowest priority' => 'Lowest priority',
    'The HTTP status code to use for the redirect' => 'The HTTP status code to use for the redirect',
    'Most common: Use' => 'Most common: Use',
    'for permanent moves.' => 'for permanent moves.',
    'Learn more about HTTP status codes' => 'Learn more about HTTP status codes',
    '301 - Moved Permanently' => '301 - Moved Permanently',
    '302 - Found (Temporary)' => '302 - Found (Temporary)',
    '303 - See Other' => '303 - See Other',
    '307 - Temporary Redirect' => '307 - Temporary Redirect',
    '308 - Permanent Redirect' => '308 - Permanent Redirect',
    '410 - Gone' => '410 - Gone',
    'Are you sure you want to delete this {item}?' => 'Are you sure you want to delete this {item}?',
    'Live' => 'Live',
    'Hit count' => 'Hit count',
    'Last hit' => 'Last hit',

    // Redirect edit: source match mode instructions (JS)
    'Match by path pattern (regex). Works across all domains.' => 'Match by path pattern (regex). Works across all domains.',
    'Match by full URL pattern (regex) including domain.' => 'Match by full URL pattern (regex) including domain.',
    'Enter a regex pattern to match paths (e.g., ^/blog/.* or /category/[^/]+).' => 'Enter a regex pattern to match paths (e.g., ^/blog/.* or /category/[^/]+).',
    'Enter a regex pattern to match full URLs (e.g., ^https://example.com/blog/.*).' => 'Enter a regex pattern to match full URLs (e.g., ^https://example.com/blog/.*).',
    'Match by path pattern (wildcard). Works across all domains.' => 'Match by path pattern (wildcard). Works across all domains.',
    'Match by full URL pattern (wildcard) including domain.' => 'Match by full URL pattern (wildcard) including domain.',
    'Enter a wildcard pattern to match paths. Use * for any characters (e.g., /blog/* or /category/*/posts).' => 'Enter a wildcard pattern to match paths. Use * for any characters (e.g., /blog/* or /category/*/posts).',
    'Enter a wildcard pattern to match full URLs. Use * for any characters (e.g., https://example.com/blog/*).' => 'Enter a wildcard pattern to match full URLs. Use * for any characters (e.g., https://example.com/blog/*).',
    'Match any path starting with the pattern. Works across all domains.' => 'Match any path starting with the pattern. Works across all domains.',
    'Match any URL starting with the pattern including domain.' => 'Match any URL starting with the pattern including domain.',
    'Enter the starting path (e.g., /blog matches /blog, /blog/post, /blog/category).' => 'Enter the starting path (e.g., /blog matches /blog, /blog/post, /blog/category).',
    'Enter the starting URL (e.g., https://example.com/blog matches all URLs starting with it).' => 'Enter the starting URL (e.g., https://example.com/blog matches all URLs starting with it).',
    'Match any path containing the pattern. Works across all domains.' => 'Match any path containing the pattern. Works across all domains.',
    'Match any URL containing the pattern including domain.' => 'Match any URL containing the pattern including domain.',
    'Enter text to match anywhere in the path (e.g., old-post matches /blog/old-post/123).' => 'Enter text to match anywhere in the path (e.g., old-post matches /blog/old-post/123).',
    'Enter text to match anywhere in the URL (e.g., old-post matches any URL containing it).' => 'Enter text to match anywhere in the URL (e.g., old-post matches any URL containing it).',

    // =========================================================================
    // Redirect Analytics Panel (redirect edit → analytics tab)
    // =========================================================================

    'Total Hits' => 'Total Hits',
    'Human Visits' => 'Human Visits',
    'Bot Visits' => 'Bot Visits',
    'Top Referrers' => 'Top Referrers',
    'Devices' => 'Devices',
    'Browsers' => 'Browsers',
    'Countries' => 'Countries',
    'Country' => 'Country',
    'No analytics data for this redirect yet.' => 'No analytics data for this redirect yet.',
    'Data will appear here once this redirect handles some requests.' => 'Data will appear here once this redirect handles some requests.',

    // =========================================================================
    // Analytics Page (404 Analytics)
    // =========================================================================

    '404 Not Found' => '404 Not Found',
    'Overview' => 'Overview',
    'Traffic & Devices' => 'Traffic & Devices',
    'Geographic' => 'Geographic',

    // Analytics: Overview tab
    'Total 404s' => 'Total 404s',
    'Success Rate' => 'Success Rate',
    '404 Trend' => '404 Trend',
    'Most Common 404s (Top 15)' => 'Most Common 404s (Top 15)',
    'No 404s recorded yet' => 'No 404s recorded yet',
    'Recent Unhandled 404s' => 'Recent Unhandled 404s',
    'Loading…' => 'Loading…',

    // Analytics: Traffic & Devices tab
    'Traffic Analysis' => 'Traffic Analysis',
    'Bot vs Human Traffic' => 'Bot vs Human Traffic',
    'Top Bots' => 'Top Bots',
    'Bot Name' => 'Bot Name',
    '404s' => '404s',
    'Device Analytics' => 'Device Analytics',
    'Device Types' => 'Device Types',
    'Browser Usage' => 'Browser Usage',
    'Operating Systems' => 'Operating Systems',

    // Analytics: Geographic tab
    'Geographic Analytics' => 'Geographic Analytics',
    'Top Countries' => 'Top Countries',
    'Percentage' => 'Percentage',
    'Top Cities' => 'Top Cities',
    'City' => 'City',
    'Geographic detection is disabled.' => 'Geographic detection is disabled.',
    'Enable in Settings' => 'Enable in Settings',

    // Analytics: JS strings
    'No trend data available for the selected filters.' => 'No trend data available for the selected filters.',
    'No bot data available for the selected filters.' => 'No bot data available for the selected filters.',
    'No device data available for the selected filters.' => 'No device data available for the selected filters.',
    'No browser data available for the selected filters.' => 'No browser data available for the selected filters.',
    'No OS data available for the selected filters.' => 'No OS data available for the selected filters.',
    'of traffic is from bots' => 'of traffic is from bots',
    'No unhandled 404s! Great job!' => 'No unhandled 404s! Great job!',
    'No bot data available' => 'No bot data available',
    'No country data available' => 'No country data available',
    'No city data available' => 'No city data available',
    'Create redirect' => 'Create redirect',

    // =========================================================================
    // Settings: General
    // =========================================================================

    'General Settings' => 'General Settings',
    'Plugin Name' => 'Plugin Name',
    'The name of the plugin as it appears in the Control Panel menu' => 'The name of the plugin as it appears in the Control Panel menu',
    'This is being overridden by the <code>pluginName</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>pluginName</code> setting in <code>config/redirect-manager.php</code>.',
    'Auto Create Redirects' => 'Auto Create Redirects',
    'Automatically create redirects when entry URIs change' => 'Automatically create redirects when entry URIs change',
    'This is being overridden by the <code>autoCreateRedirects</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>autoCreateRedirects</code> setting in <code>config/redirect-manager.php</code>.',
    'Undo Window' => 'Undo Window',
    'How long to detect and cancel immediate slug changes back and forth (A → B → A). Prevents creating unnecessary redirect pairs when editors quickly fix mistakes.' => 'How long to detect and cancel immediate slug changes back and forth (A → B → A). Prevents creating unnecessary redirect pairs when editors quickly fix mistakes.',
    'Disabled (always allow undo)' => 'Disabled (always allow undo)',
    '30 minutes' => '30 minutes',
    '1 hour' => '1 hour',
    '2 hours' => '2 hours',
    '4 hours' => '4 hours',
    'This is being overridden by the <code>undoWindowMinutes</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>undoWindowMinutes</code> setting in <code>config/redirect-manager.php</code>.',
    'Default Source Match Mode' => 'Default Source Match Mode',
    'Default mode for new redirects. Each redirect can override this individually.' => 'Default mode for new redirects. Each redirect can override this individually.',
    'This is being overridden by the <code>redirectSrcMatch</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>redirectSrcMatch</code> setting in <code>config/redirect-manager.php</code>.',
    'Query String Handling' => 'Query String Handling',
    'Strip Query String' => 'Strip Query String',
    'Strip query string from all 404 URLs before evaluation' => 'Strip query string from all 404 URLs before evaluation',
    'This is being overridden by the <code>stripQueryString</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>stripQueryString</code> setting in <code>config/redirect-manager.php</code>.',
    'Preserve Query String' => 'Preserve Query String',
    'Preserve and pass query string to destination URL' => 'Preserve and pass query string to destination URL',
    'This is being overridden by the <code>preserveQueryString</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>preserveQueryString</code> setting in <code>config/redirect-manager.php</code>.',
    'HTTP Headers' => 'HTTP Headers',
    'Set No-Cache Headers' => 'Set No-Cache Headers',
    'Set no-cache headers on redirect responses' => 'Set no-cache headers on redirect responses',
    'To add additional custom headers, visit <a href="{url}">Advanced Settings</a>.' => 'To add additional custom headers, visit <a href="{url}">Advanced Settings</a>.',
    'This is being overridden by the <code>setNoCacheHeaders</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>setNoCacheHeaders</code> setting in <code>config/redirect-manager.php</code>.',
    'Logging Settings' => 'Logging Settings',
    'Log Level' => 'Log Level',
    'Choose what types of messages to log. Debug level requires devMode to be enabled.' => 'Choose what types of messages to log. Debug level requires devMode to be enabled.',
    'Error (Critical errors only)' => 'Error (Critical errors only)',
    'Warning (Errors and warnings)' => 'Warning (Errors and warnings)',
    'Info (General information)' => 'Info (General information)',
    'Debug (Detailed debugging)' => 'Debug (Detailed debugging)',
    'This is being overridden by the <code>logLevel</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>logLevel</code> setting in <code>config/redirect-manager.php</code>.',

    // =========================================================================
    // Settings: Analytics
    // =========================================================================

    'Analytics Settings' => 'Analytics Settings',
    'Enable Analytics' => 'Enable Analytics',
    'Track 404 analytics and visitor data' => 'Track 404 analytics and visitor data',
    'This is being overridden by the <code>enableAnalytics</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>enableAnalytics</code> setting in <code>config/redirect-manager.php</code>.',
    'When enabled, {pluginName} will track 404 errors including device types, browsers, geographic data, and bot traffic.' => 'When enabled, {pluginName} will track 404 errors including device types, browsers, geographic data, and bot traffic.',
    'Geographic Detection' => 'Geographic Detection',
    'Enable Geographic Detection' => 'Enable Geographic Detection',
    'Detect visitor location for analytics' => 'Detect visitor location for analytics',
    'This is being overridden by the <code>enableGeoDetection</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>enableGeoDetection</code> setting in <code>config/redirect-manager.php</code>.',

    // Geo provider settings (from base partial)
    'Geo Provider' => 'Geo Provider',
    'Select the geo IP lookup provider. HTTPS providers recommended for privacy.' => 'Select the geo IP lookup provider. HTTPS providers recommended for privacy.',
    'ip-api.com (HTTP free, HTTPS paid)' => 'ip-api.com (HTTP free, HTTPS paid)',
    'ipapi.co (HTTPS, 1k/day free)' => 'ipapi.co (HTTPS, 1k/day free)',
    'ipinfo.io (HTTPS, 50k/month free)' => 'ipinfo.io (HTTPS, 50k/month free)',
    'This is being overridden by the <code>geoProvider</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>geoProvider</code> setting in <code>config/redirect-manager.php</code>.',
    'API Key' => 'API Key',
    'Optional. Required for paid tiers (enables HTTPS for ip-api.com Pro).' => 'Optional. Required for paid tiers (enables HTTPS for ip-api.com Pro).',
    'This is being overridden by the <code>geoApiKey</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>geoApiKey</code> setting in <code>config/redirect-manager.php</code>.',
    'ip-api.com free tier uses HTTP. IP addresses will be transmitted unencrypted. Add an API key for HTTPS (Pro tier) or switch to ipapi.co/ipinfo.io.' => 'ip-api.com free tier uses HTTP. IP addresses will be transmitted unencrypted. Add an API key for HTTPS (Pro tier) or switch to ipapi.co/ipinfo.io.',
    'ip-api.com: HTTP free tier (45 requests/min). Add API key for HTTPS (Pro tier, $13/month). IP addresses transmitted unencrypted without API key.' => 'ip-api.com: HTTP free tier (45 requests/min). Add API key for HTTPS (Pro tier, $13/month). IP addresses transmitted unencrypted without API key.',
    'ipapi.co: HTTPS with 1,000 free requests/day. API key optional (increases rate limits).' => 'ipapi.co: HTTPS with 1,000 free requests/day. API key optional (increases rate limits).',
    'ipinfo.io: HTTPS with 50,000 free requests/month. API key optional (increases rate limits).' => 'ipinfo.io: HTTPS with 50,000 free requests/month. API key optional (increases rate limits).',

    // IP salt error banner (from base partial)
    'error' => 'error',
    'Configuration Required' => 'Configuration Required',
    'IP hash salt is missing.' => 'IP hash salt is missing.',
    'Analytics tracking requires a secure salt for privacy protection.' => 'Analytics tracking requires a secure salt for privacy protection.',
    'Run one of these commands in your terminal:' => 'Run one of these commands in your terminal:',
    'Standard:' => 'Standard:',
    'COPY' => 'COPY',
    'DDEV:' => 'DDEV:',
    'This will automatically add' => 'This will automatically add',
    'to your' => 'to your',
    'file.' => 'file.',
    'Warning:' => 'Warning:',
    'Copy the same salt to staging and production environments.' => 'Copy the same salt to staging and production environments.',
    'COPIED!' => 'COPIED!',
    'Failed to copy to clipboard' => 'Failed to copy to clipboard',

    // Analytics: IP Privacy
    'IP Address Privacy' => 'IP Address Privacy',
    'Anonymize IP Addresses' => 'Anonymize IP Addresses',
    'Mask IP addresses before storage for maximum privacy. <strong>IPv4</strong>: masks last octet (192.168.1.123 → 192.168.1.0). <strong>IPv6</strong>: masks last 80 bits. <strong>Trade-off</strong>: Reduces unique visitor accuracy (users on same subnet counted as one visitor). Geo-location still works normally.' => 'Mask IP addresses before storage for maximum privacy. <strong>IPv4</strong>: masks last octet (192.168.1.123 → 192.168.1.0). <strong>IPv6</strong>: masks last 80 bits. <strong>Trade-off</strong>: Reduces unique visitor accuracy (users on same subnet counted as one visitor). Geo-location still works normally.',
    'This is being overridden by the <code>anonymizeIpAddress</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>anonymizeIpAddress</code> setting in <code>config/redirect-manager.php</code>.',

    // Analytics: Additional
    'Additional Settings' => 'Additional Settings',
    'Strip Query String From Stats' => 'Strip Query String From Stats',
    'Strip query strings from analytics URLs to consolidate similar requests' => 'Strip query strings from analytics URLs to consolidate similar requests',
    'This is being overridden by the <code>stripQueryStringFromStats</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>stripQueryStringFromStats</code> setting in <code>config/redirect-manager.php</code>.',

    // Analytics: Data Retention
    'Data Retention' => 'Data Retention',
    'Analytics Retention (Days)' => 'Analytics Retention (Days)',
    'Number of days to retain analytics (0 = keep forever)' => 'Number of days to retain analytics (0 = keep forever)',
    'This is being overridden by the <code>analyticsRetention</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>analyticsRetention</code> setting in <code>config/redirect-manager.php</code>.',
    'Analytics Limit' => 'Analytics Limit',
    'Maximum number of unique 404 records to retain' => 'Maximum number of unique 404 records to retain',
    'This is being overridden by the <code>analyticsLimit</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>analyticsLimit</code> setting in <code>config/redirect-manager.php</code>.',
    'Auto Trim Analytics' => 'Auto Trim Analytics',
    'Automatically trim analytics to respect the limit' => 'Automatically trim analytics to respect the limit',
    'This is being overridden by the <code>autoTrimAnalytics</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>autoTrimAnalytics</code> setting in <code>config/redirect-manager.php</code>.',
    'Performance & Caching' => 'Performance & Caching',
    'Configure device detection and redirect caching for better performance.' => 'Configure device detection and redirect caching for better performance.',
    'Go to Cache Settings' => 'Go to Cache Settings',

    // =========================================================================
    // Settings: Interface
    // =========================================================================

    'Interface Settings' => 'Interface Settings',
    'Items Per Page' => 'Items Per Page',
    'Number of items to display per page in lists' => 'Number of items to display per page in lists',
    'This is being overridden by the <code>itemsPerPage</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>itemsPerPage</code> setting in <code>config/redirect-manager.php</code>.',
    'Dashboard Refresh Interval' => 'Dashboard Refresh Interval',
    'How often to refresh dashboard data. Set to Off to disable auto-refresh.' => 'How often to refresh dashboard data. Set to Off to disable auto-refresh.',
    'Off' => 'Off',
    '15 seconds' => '15 seconds',
    '30 seconds' => '30 seconds',
    '60 seconds (1 minute)' => '60 seconds (1 minute)',
    '120 seconds (2 minutes)' => '120 seconds (2 minutes)',
    'This is being overridden by the <code>refreshIntervalSecs</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>refreshIntervalSecs</code> setting in <code>config/redirect-manager.php</code>.',

    // =========================================================================
    // Settings: Backup
    // =========================================================================

    'Backup Settings' => 'Backup Settings',
    'Enable Backups' => 'Enable Backups',
    'Enable automatic backup functionality for redirects' => 'Enable automatic backup functionality for redirects',
    'This is being overridden by the <code>backupEnabled</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>backupEnabled</code> setting in <code>config/redirect-manager.php</code>.',
    'Backup Before Import' => 'Backup Before Import',
    'Automatically create a backup before importing CSV files' => 'Automatically create a backup before importing CSV files',
    'This is being overridden by the <code>backupOnImport</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>backupOnImport</code> setting in <code>config/redirect-manager.php</code>.',
    'Backup Schedule' => 'Backup Schedule',
    "How often to create automatic backups. Uses Craft's queue if running, or set up a cron job:" => "How often to create automatic backups. Uses Craft's queue if running, or set up a cron job:",
    'Manual Only' => 'Manual Only',
    'Daily' => 'Daily',
    'Weekly' => 'Weekly',
    'Monthly' => 'Monthly',
    'This is being overridden by the <code>backupSchedule</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>backupSchedule</code> setting in <code>config/redirect-manager.php</code>.',
    'Retention Period' => 'Retention Period',
    'Number of days to keep automatic backups (0 = keep forever). Manual backups are never deleted automatically.' => 'Number of days to keep automatic backups (0 = keep forever). Manual backups are never deleted automatically.',
    'This is being overridden by the <code>backupRetentionDays</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>backupRetentionDays</code> setting in <code>config/redirect-manager.php</code>.',
    'Backup Storage Volume' => 'Backup Storage Volume',
    'Select an asset volume or use a custom path for storing backups.' => 'Select an asset volume or use a custom path for storing backups.',
    'Use custom path' => 'Use custom path',
    'This is being overridden by the <code>backupVolumeUid</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>backupVolumeUid</code> setting in <code>config/redirect-manager.php</code>.',
    'Custom Backup Path' => 'Custom Backup Path',
    'The custom path where backups should be stored (only used when no volume is selected)' => 'The custom path where backups should be stored (only used when no volume is selected)',
    'Use Craft path aliases: <code>@storage/redirect-manager/backups</code> (recommended) or <code>@root/backups/redirect-manager</code>. Paths must be outside webroot for security. Environment variables like <code>$ENV_VAR</code> are supported.' => 'Use Craft path aliases: <code>@storage/redirect-manager/backups</code> (recommended) or <code>@root/backups/redirect-manager</code>. Paths must be outside webroot for security. Environment variables like <code>$ENV_VAR</code> are supported.',
    'This is being overridden by the <code>backupPath</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>backupPath</code> setting in <code>config/redirect-manager.php</code>.',
    'Backup Location:' => 'Backup Location:',

    // =========================================================================
    // Settings: Cache
    // =========================================================================

    'Cache Settings' => 'Cache Settings',
    'Cache Storage Settings' => 'Cache Storage Settings',
    'Cache Storage Method' => 'Cache Storage Method',
    'How to store cache data. Use Redis/Database for load-balanced or multi-server environments.' => 'How to store cache data. Use Redis/Database for load-balanced or multi-server environments.',
    'File System (default, single server)' => 'File System (default, single server)',
    'Redis/Database (load-balanced, multi-server, cloud hosting)' => 'Redis/Database (load-balanced, multi-server, cloud hosting)',
    'This is being overridden by the <code>cacheStorageMethod</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>cacheStorageMethod</code> setting in <code>config/redirect-manager.php</code>.',
    'Cache Location' => 'Cache Location',
    "Using Craft's configured Redis cache from <code>config/app.php</code>" => "Using Craft's configured Redis cache from <code>config/app.php</code>",
    'Redis Not Configured' => 'Redis Not Configured',
    "To use Redis caching, install <code>yiisoft/yii2-redis</code> and configure it in <code>config/app.php</code>." => "To use Redis caching, install <code>yiisoft/yii2-redis</code> and configure it in <code>config/app.php</code>.",
    'Device Detection Caching' => 'Device Detection Caching',
    'Cache Device Detection' => 'Cache Device Detection',
    'Cache device detection results for better performance' => 'Cache device detection results for better performance',
    'This is being overridden by the <code>cacheDeviceDetection</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>cacheDeviceDetection</code> setting in <code>config/redirect-manager.php</code>.',
    'Device Detection Cache Duration' => 'Device Detection Cache Duration',
    'Cache duration in seconds. Current:' => 'Cache duration in seconds. Current:',
    'Min: 60 (1 minute), Max: 604800 (7 days)' => 'Min: 60 (1 minute), Max: 604800 (7 days)',
    'This is being overridden by the <code>deviceDetectionCacheDuration</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>deviceDetectionCacheDuration</code> setting in <code>config/redirect-manager.php</code>.',
    'How it works' => 'How it works',
    'Device detection parses user-agent strings to identify devices, browsers, and operating systems' => 'Device detection parses user-agent strings to identify devices, browsers, and operating systems',
    'Results are cached to avoid re-parsing the same user-agent repeatedly' => 'Results are cached to avoid re-parsing the same user-agent repeatedly',
    'Recommended to keep enabled for production sites' => 'Recommended to keep enabled for production sites',
    'Device detection caching is only available when Analytics is enabled. Go to' => 'Device detection caching is only available when Analytics is enabled. Go to',
    'to enable analytics.' => 'to enable analytics.',
    'Redirect Caching' => 'Redirect Caching',
    'Enable Redirect Cache' => 'Enable Redirect Cache',
    'Cache redirect lookups for improved performance. Recommended for production sites.' => 'Cache redirect lookups for improved performance. Recommended for production sites.',
    'This is being overridden by the <code>enableRedirectCache</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>enableRedirectCache</code> setting in <code>config/redirect-manager.php</code>.',
    'Redirect Cache Duration' => 'Redirect Cache Duration',
    'Min: 60 (1 minute), Max: 86400 (1 day)' => 'Min: 60 (1 minute), Max: 86400 (1 day)',
    'This is being overridden by the <code>redirectCacheDuration</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>redirectCacheDuration</code> setting in <code>config/redirect-manager.php</code>.',
    '{count} second' => '{count} second',
    '{count} seconds' => '{count} seconds',
    '{count} minute' => '{count} minute',
    '{count} minutes' => '{count} minutes',
    '{count} hour' => '{count} hour',
    '{count} hours' => '{count} hours',
    '{count} day' => '{count} day',
    '{count} days' => '{count} days',

    // =========================================================================
    // Settings: Advanced
    // =========================================================================

    'Advanced Settings' => 'Advanced Settings',
    'API Settings' => 'API Settings',
    'Enable API Endpoint' => 'Enable API Endpoint',
    'Enable GraphQL endpoint for headless implementations<br><strong>Note:</strong> GraphQL API coming soon in future update.' => 'Enable GraphQL endpoint for headless implementations<br><strong>Note:</strong> GraphQL API coming soon in future update.',
    'Coming soon - GraphQL API is planned for a future release' => 'Coming soon - GraphQL API is planned for a future release',
    'This is being overridden by the <code>enableApiEndpoint</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>enableApiEndpoint</code> setting in <code>config/redirect-manager.php</code>.',
    'URL Filtering' => 'URL Filtering',
    'Exclude Patterns' => 'Exclude Patterns',
    '[Regular expressions](https://regexr.com/) to match URIs that should be excluded from {pluginName}.' => '[Regular expressions](https://regexr.com/) to match URIs that should be excluded from {pluginName}.',
    'RegEx pattern to exclude' => 'RegEx pattern to exclude',
    'This is being overridden by the <code>excludePatterns</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>excludePatterns</code> setting in <code>config/redirect-manager.php</code>.',
    'Additional Headers' => 'Additional Headers',
    'Additional headers to add to the redirected request' => 'Additional headers to add to the redirected request',
    'Header Name' => 'Header Name',
    'Header Value' => 'Header Value',
    'This is being overridden by the <code>additionalHeaders</code> setting in <code>config/redirect-manager.php</code>.' => 'This is being overridden by the <code>additionalHeaders</code> setting in <code>config/redirect-manager.php</code>.',
    'Quick Setup' => 'Quick Setup',
    'Apply recommended exclude patterns and SEO headers.' => 'Apply recommended exclude patterns and SEO headers.',
    'Apply Recommended Settings' => 'Apply Recommended Settings',
    'Adds exclude patterns for Craft admin/CMS URLs, system resources (.well-known), versioned build assets (dist/assets), and SEO headers (X-Robots-Tag: noindex). Safe for all installations.' => 'Adds exclude patterns for Craft admin/CMS URLs, system resources (.well-known), versioned build assets (dist/assets), and SEO headers (X-Robots-Tag: noindex). Safe for all installations.',
    'WordPress Migration' => 'WordPress Migration',
    'Filter out WordPress bot traffic and spam 404s.' => 'Filter out WordPress bot traffic and spam 404s.',
    'Apply WordPress Migration Filters' => 'Apply WordPress Migration Filters',
    'Adds exclude patterns for WordPress bot traffic (wp-includes, wp-json, wp-admin, wp-login, xmlrpc.php, feeds, ?p= permalinks, etc.). <strong>Note:</strong> /wp-content/uploads URLs are NOT excluded - those media files may need legitimate redirects.' => 'Adds exclude patterns for WordPress bot traffic (wp-includes, wp-json, wp-admin, wp-login, xmlrpc.php, feeds, ?p= permalinks, etc.). <strong>Note:</strong> /wp-content/uploads URLs are NOT excluded - those media files may need legitimate redirects.',
    'Security Probe Filters' => 'Security Probe Filters',
    'Filter out malicious vulnerability scanning attempts.' => 'Filter out malicious vulnerability scanning attempts.',
    'Apply Security Probe Filters' => 'Apply Security Probe Filters',
    'Adds specific exclude patterns for security probes: database dumps (*.sql, dump.sql.gz), config files (.env, .git/, .htaccess), admin panels (/phpmyadmin, /pma/, adminer.php), and exploit attempts (shell.php, /cgi-bin/). Patterns are precise to avoid blocking legitimate URLs like /mysql-tips or /debugging-guide.' => 'Adds specific exclude patterns for security probes: database dumps (*.sql, dump.sql.gz), config files (.env, .git/, .htaccess), admin panels (/phpmyadmin, /pma/, adminer.php), and exploit attempts (shell.php, /cgi-bin/). Patterns are precise to avoid blocking legitimate URLs like /mysql-tips or /debugging-guide.',

    // =========================================================================
    // Settings: Test
    // =========================================================================

    'Test Redirects' => 'Test Redirects',
    'Test URL Redirects' => 'Test URL Redirects',
    'Test if a URL matches any of your configured redirects without actually visiting it. Useful for validating Source Match Mode (path vs full URL) and Match Type logic.' => 'Test if a URL matches any of your configured redirects without actually visiting it. Useful for validating Source Match Mode (path vs full URL) and Match Type logic.',
    'Test URL' => 'Test URL',
    'Enter a URL to test (can be a full URL like https://example.com/old-page or a path like /old-page)' => 'Enter a URL to test (can be a full URL like https://example.com/old-page or a path like /old-page)',

    // =========================================================================
    // Backups Page
    // =========================================================================

    'Backups' => 'Backups',
    'Create Backup Now' => 'Create Backup Now',
    'Backups are automatically created when you import redirects (if enabled). You can restore or download any backup.' => 'Backups are automatically created when you import redirects (if enabled). You can restore or download any backup.',
    'No backup history yet. Backups are created automatically when you import redirects.' => 'No backup history yet. Backups are created automatically when you import redirects.',
    'Date' => 'Date',
    'Created By' => 'Created By',
    'Redirect Count' => 'Redirect Count',
    'Size' => 'Size',
    'Actions' => 'Actions',
    'Failed to load backups: ' => 'Failed to load backups: ',
    'Backup created.' => 'Backup created.',
    'Failed to create backup.' => 'Failed to create backup.',
    'Are you sure you want to restore this backup? This will replace all current redirects. A backup of the current state will be created before restoring.' => 'Are you sure you want to restore this backup? This will replace all current redirects. A backup of the current state will be created before restoring.',
    'Backup contains' => 'Backup contains',
    'redirects.' => 'redirects.',
    'Restoring backup...' => 'Restoring backup...',
    'Backup restored.' => 'Backup restored.',
    'Failed to restore backup.' => 'Failed to restore backup.',
    'Are you sure you want to delete this backup? This action cannot be undone.' => 'Are you sure you want to delete this backup? This action cannot be undone.',
    'Deleting backup...' => 'Deleting backup...',
    'Backup deleted.' => 'Backup deleted.',
    'Failed to delete backup.' => 'Failed to delete backup.',

    // =========================================================================
    // Import/Export
    // =========================================================================

    'Import/Export' => 'Import/Export',
    'Import History' => 'Import History',
    'Export Redirects' => 'Export Redirects',
    'Download all your current redirects as a CSV file for backup or migration to another site.' => 'Download all your current redirects as a CSV file for backup or migration to another site.',
    'Export All Redirects as CSV' => 'Export All Redirects as CSV',
    'You do not have permission to export redirects.' => 'You do not have permission to export redirects.',
    'Import Redirects' => 'Import Redirects',
    'CSV Format' => 'CSV Format',
    'Required columns:' => 'Required columns:',
    'sourceUrl' => 'sourceUrl',
    'The URL to redirect from' => 'The URL to redirect from',
    'destinationUrl' => 'destinationUrl',
    'The URL to redirect to' => 'The URL to redirect to',
    'Optional columns:' => 'Optional columns:',
    'statusCode' => 'statusCode',
    'HTTP status (301, 302, etc.)' => 'HTTP status (301, 302, etc.)',
    'matchType' => 'matchType',
    'exact, regex, wildcard, or prefix' => 'exact, regex, wildcard, or prefix',
    'redirectSrcMatch' => 'redirectSrcMatch',
    'pathonly or fullurl (default: pathonly)' => 'pathonly or fullurl (default: pathonly)',
    'siteId' => 'siteId',
    'Site ID (blank = all sites)' => 'Site ID (blank = all sites)',
    'priority' => 'priority',
    '0-9 (default: 0)' => '0-9 (default: 0)',
    'enabled' => 'enabled',
    '1 or 0 (default: 1)' => '1 or 0 (default: 1)',
    'Example:' => 'Example:',
    'Import from CSV' => 'Import from CSV',
    "Import redirects from a CSV file. You'll be able to map columns and preview before importing." => "Import redirects from a CSV file. You'll be able to map columns and preview before importing.",
    'CSV File' => 'CSV File',
    'Select a CSV file to import redirects' => 'Select a CSV file to import redirects',
    'CSV Delimiter' => 'CSV Delimiter',
    'Character used to separate values in your CSV (auto-detect is default)' => 'Character used to separate values in your CSV (auto-detect is default)',
    'Auto (detect)' => 'Auto (detect)',
    'Comma (,)' => 'Comma (,)',
    'Semicolon (;)' => 'Semicolon (;)',
    'Tab' => 'Tab',
    'Pipe (|)' => 'Pipe (|)',
    'Create Backup Before Import' => 'Create Backup Before Import',
    'Automatically backup existing redirects before importing (recommended)' => 'Automatically backup existing redirects before importing (recommended)',
    'Backups are disabled in settings.' => 'Backups are disabled in settings.',
    'The maximum file size is {size} and the import is limited to {rows} rows per file.' => 'The maximum file size is {size} and the import is limited to {rows} rows per file.',
    'Upload & Map Columns' => 'Upload & Map Columns',
    'You do not have permission to import redirects.' => 'You do not have permission to import redirects.',
    'Recent CSV imports and their results.' => 'Recent CSV imports and their results.',
    'Clear history' => 'Clear history',
    'Filename' => 'Filename',
    'Imported' => 'Imported',
    'Failed' => 'Failed',
    'View' => 'View',
    'No import history yet.' => 'No import history yet.',
    'Are you sure you want to clear all import logs? This action cannot be undone.' => 'Are you sure you want to clear all import logs? This action cannot be undone.',
    'Failed to clear history.' => 'Failed to clear history.',

    // Import: Map columns
    'Map CSV Columns' => 'Map CSV Columns',
    'Map Columns' => 'Map Columns',
    'Your CSV has {count} rows. Map each CSV column to a redirect field.' => 'Your CSV has {count} rows. Map each CSV column to a redirect field.',
    'Backup will be created automatically before importing to protect your existing redirects.' => 'Backup will be created automatically before importing to protect your existing redirects.',
    'Preview of CSV Data' => 'Preview of CSV Data',
    'Showing first 5 rows. {total} total rows will be imported.' => 'Showing first 5 rows. {total} total rows will be imported.',
    'Column Mapping' => 'Column Mapping',
    'Map your CSV columns to redirect fields. Required fields must be mapped.' => 'Map your CSV columns to redirect fields. Required fields must be mapped.',
    '-- Do not import --' => '-- Do not import --',
    'Source URL (required)' => 'Source URL (required)',
    'Destination URL (required)' => 'Destination URL (required)',
    'Site ID' => 'Site ID',
    'Source Match Mode (pathonly/fullurl)' => 'Source Match Mode (pathonly/fullurl)',
    'Match Type (exact/regex/wildcard/prefix)' => 'Match Type (exact/regex/wildcard/prefix)',
    'Status Code (301/302/etc.)' => 'Status Code (301/302/etc.)',
    'Priority (0-9)' => 'Priority (0-9)',
    'Enabled (1/0)' => 'Enabled (1/0)',
    'Last Hit (datetime)' => 'Last Hit (datetime)',
    'Creation Type (manual/auto/import)' => 'Creation Type (manual/auto/import)',
    'Source Plugin' => 'Source Plugin',
    'Element ID (for auto-detection)' => 'Element ID (for auto-detection)',
    'CSV Column' => 'CSV Column',
    'Maps to Field' => 'Maps to Field',
    'Sample Data' => 'Sample Data',
    'Preview Import' => 'Preview Import',

    // Import: Preview
    'Import Preview' => 'Import Preview',
    'Preview' => 'Preview',
    'Total Rows' => 'Total Rows',
    'Valid' => 'Valid',
    'Duplicates' => 'Duplicates',
    'Errors' => 'Errors',
    'Backup will be created with {count} existing redirect(s) before importing to protect your data.' => 'Backup will be created with {count} existing redirect(s) before importing to protect your data.',
    'No existing redirects to backup - backup will be skipped.' => 'No existing redirects to backup - backup will be skipped.',
    'Valid Redirects to Import' => 'Valid Redirects to Import',
    'Source Match' => 'Source Match',
    'Duplicate Redirects (will be skipped)' => 'Duplicate Redirects (will be skipped)',
    'These redirects already exist with the same source URL, match type, and source match mode.' => 'These redirects already exist with the same source URL, match type, and source match mode.',
    'Reason' => 'Reason',
    'Invalid Rows (will be skipped)' => 'Invalid Rows (will be skipped)',
    'Row' => 'Row',
    'Error' => 'Error',
    'Ready to Import' => 'Ready to Import',
    'Click the button below to import {count} valid redirect(s).' => 'Click the button below to import {count} valid redirect(s).',
    '{duplicates} duplicate(s) will be skipped.' => '{duplicates} duplicate(s) will be skipped.',
    '{errors} invalid row(s) will be skipped.' => '{errors} invalid row(s) will be skipped.',
    'No valid redirects found to import.' => 'No valid redirects found to import.',
    'Import {count} Redirects' => 'Import {count} Redirects',
    'No Valid Redirects to Import' => 'No Valid Redirects to Import',
    'Successfully imported {imported} {pluginName}.' => 'Successfully imported {imported} {pluginName}.',

    // =========================================================================
    // Utilities Page
    // =========================================================================

    'All Active' => 'All Active',
    'Good' => 'Good',
    'Check' => 'Check',
    'Monitor 404 handling, manage redirects, and optimize cache performance.' => 'Monitor 404 handling, manage redirects, and optimize cache performance.',
    'Redirects Status' => 'Redirects Status',
    'Active {pluginName}' => 'Active {pluginName}',
    'Total' => 'Total',
    'Active' => 'Active',
    '404 Handling' => '404 Handling',
    'Success rate (last 7 days)' => 'Success rate (last 7 days)',
    'Cache Status' => 'Cache Status',
    'Total cached entries' => 'Total cached entries',
    'Manage Redirects' => 'Manage Redirects',
    'View Analytics' => 'View Analytics',
    'View Settings' => 'View Settings',
    'Navigation' => 'Navigation',
    'Access main plugin sections' => 'Access main plugin sections',
    'Clear Redirect Cache' => 'Clear Redirect Cache',
    'Clear Device Cache' => 'Clear Device Cache',
    'Clear All Caches' => 'Clear All Caches',
    'Cache Management' => 'Cache Management',
    'Clear cached data to force regeneration. Useful when troubleshooting redirect issues.' => 'Clear cached data to force regeneration. Useful when troubleshooting redirect issues.',
    'Analytics Data Management' => 'Analytics Data Management',
    'Permanently delete all analytics tracking data. This action cannot be undone!' => 'Permanently delete all analytics tracking data. This action cannot be undone!',
    'Clear All Analytics' => 'Clear All Analytics',
    'Clear all caches?' => 'Clear all caches?',
    'Are you sure you want to permanently delete ALL analytics data? This action cannot be undone!' => 'Are you sure you want to permanently delete ALL analytics data? This action cannot be undone!',
    'This will delete all 404 tracking data. Are you absolutely sure?' => 'This will delete all 404 tracking data. Are you absolutely sure?',

    // =========================================================================
    // Widgets
    // =========================================================================

    // Stats Summary Widget
    'View full analytics' => 'View full analytics',
    'No 404s recorded' => 'No 404s recorded',
    '404 errors will appear here when they occur.' => '404 errors will appear here when they occur.',
    'Number of Days' => 'Number of Days',
    'Show analytics for the last X days (1-365)' => 'Show analytics for the last X days (1-365)',

    // Unhandled 404s Widget
    'Last seen' => 'Last seen',
    'View all 404s' => 'View all 404s',
    'No unhandled 404s' => 'No unhandled 404s',
    'Great! All 404s are being handled by {pluginName}.' => 'Great! All 404s are being handled by {pluginName}.',
    'Number of 404s' => 'Number of 404s',
    'How many unhandled 404s to display (5-50)' => 'How many unhandled 404s to display (5-50)',

    // =========================================================================
    // Messages (flash, notices, errors)
    // =========================================================================

    'Scheduled initial analytics cleanup job' => 'Scheduled initial analytics cleanup job',
    'Redirect saved successfully' => 'Redirect saved successfully',
    'Redirect deleted successfully' => 'Redirect deleted successfully',
    'Analytics cleared successfully' => 'Analytics cleared successfully',
];
