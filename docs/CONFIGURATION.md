# Redirect Manager Configuration

## Configuration File

You can override plugin settings by creating a `redirect-manager.php` file in your `config/` directory.

### Basic Setup

1. Copy `vendor/lindemannrock/craft-redirect-manager/src/config.php` to `config/redirect-manager.php`
2. Modify the settings as needed

### Available Settings

```php
<?php
use craft\helpers\App;

return [
    // General Settings
    'pluginName' => 'Redirect Manager',
    'autoCreateRedirects' => true,
    'undoWindowMinutes' => 60,
    'redirectSrcMatch' => 'pathonly',
    'stripQueryString' => false,
    'preserveQueryString' => false,
    'setNoCacheHeaders' => true,
    'logLevel' => 'error',

    // Analytics Settings
    'enableAnalytics' => true,
    'anonymizeIpAddress' => false,
    'enableGeoDetection' => false,
    'stripQueryStringFromStats' => true,
    'analyticsLimit' => 1000,
    'analyticsRetention' => 30,
    'autoTrimAnalytics' => true,

    // Interface Settings
    'refreshIntervalSecs' => 5,
    'redirectsDisplayLimit' => 100,
    'analyticsDisplayLimit' => 100,
    'itemsPerPage' => 100,

    // Cache Settings
    'enableRedirectCache' => true,
    'redirectCacheDuration' => 3600,
    'cacheDeviceDetection' => true,
    'deviceDetectionCacheDuration' => 3600,

    // Advanced Settings
    'enableApiEndpoint' => false,
    'excludePatterns' => [],
    'additionalHeaders' => [],

    // IP Privacy Protection (required)
    'ipHashSalt' => App::env('REDIRECT_MANAGER_IP_SALT'),
];
```

### Multi-Environment Configuration

You can have different settings per environment:

```php
<?php
return [
    // Global settings
    '*' => [
        'pluginName' => 'Redirect Manager',
        'autoCreateRedirects' => true,
        'logLevel' => 'error',
    ],

    // Development environment
    'dev' => [
        'logLevel' => 'debug',
        'analyticsRetention' => 30,
        'enableRedirectCache' => true,
        'redirectCacheDuration' => 60,
    ],

    // Staging environment
    'staging' => [
        'logLevel' => 'info',
        'analyticsRetention' => 90,
        'redirectCacheDuration' => 3600,
    ],

    // Production environment
    'production' => [
        'logLevel' => 'error',
        'analyticsRetention' => 365,
        'redirectCacheDuration' => 86400,
    ],
];
```

### Setting Descriptions

#### General Settings

##### pluginName
Display name for the plugin in Craft CP navigation.
- **Type:** `string`
- **Default:** `'Redirect Manager'`

##### autoCreateRedirects
Automatically creates redirects when entry URIs change.
- **Type:** `bool`
- **Default:** `true`

##### undoWindowMinutes
Time window in minutes for detecting immediate undo (A → B → A). If editor changes slug back within this window, the redirect is cancelled instead of creating a pair.
- **Type:** `int`
- **Options:** `30`, `60`, `120`, `240`
- **Default:** `60`

##### redirectSrcMatch
Default source match mode for new redirects. Each redirect can override this individually.
- **Type:** `string`
- **Options:** `'pathonly'` (match `/page` on any domain), `'fullurl'` (match `https://example.com/page` exactly)
- **Default:** `'pathonly'`

##### stripQueryString
Controls whether query strings affect redirect matching.
- **Type:** `bool`
- **Default:** `false`
- **OFF:** `/page?foo=bar` only matches rule `/page?foo=bar` (exact match required)
- **ON:** `/page?foo=bar` matches rule `/page` (query ignored)

##### preserveQueryString
Controls whether query strings are passed to the destination URL.
- **Type:** `bool`
- **Default:** `false`
- **OFF:** User visits `/old?ref=email` → redirects to `/new` (query dropped)
- **ON:** User visits `/old?ref=email` → redirects to `/new?ref=email` (query preserved)

##### setNoCacheHeaders
Set no-cache headers on redirect responses to prevent browser caching.
- **Type:** `bool`
- **Default:** `true`

##### logLevel
What types of messages to log.
- **Type:** `string`
- **Options:** `'debug'`, `'info'`, `'warning'`, `'error'`
- **Default:** `'error'`
- **Note:** Debug level requires Craft's `devMode` to be enabled

#### Analytics Settings

##### enableAnalytics
Master switch that controls all analytics tracking including IP tracking, device detection, and geo detection.
- **Type:** `bool`
- **Default:** `true`

##### anonymizeIpAddress
Mask IP addresses before hashing (subnet masking for maximum privacy).
- **Type:** `bool`
- **Default:** `false`
- **IPv4:** Masks last octet (192.168.1.123 → 192.168.1.0)
- **IPv6:** Masks last 80 bits
- **Trade-off:** Reduces unique visitor accuracy

##### enableGeoDetection
Detect visitor location (country, city) from IP addresses.
- **Type:** `bool`
- **Default:** `false`
- **Uses:** ip-api.com (free service with 45 requests per minute limit)

##### stripQueryStringFromStats
Controls how analytics groups URLs with different query strings.
- **Type:** `bool`
- **Default:** `true`
- **ON:** `/page?source=email`, `/page?source=facebook` → grouped as one record (shows latest query)
- **OFF:** Each unique URL+query creates separate record

##### analyticsLimit
Maximum number of unique 404 records to retain.
- **Type:** `int`
- **Default:** `1000`

##### analyticsRetention
Number of days to retain analytics (0 = keep forever).
- **Type:** `int`
- **Default:** `30`

##### autoTrimAnalytics
Whether analytics should be automatically trimmed to respect the limit.
- **Type:** `bool`
- **Default:** `true`

#### Interface Settings

##### refreshIntervalSecs
Dashboard auto-refresh interval in seconds. Set to 0 to disable.
- **Type:** `int`
- **Options:** `5`, `15`, `30`, `60`
- **Default:** `5`

##### redirectsDisplayLimit
How many redirects to display in the CP.
- **Type:** `int`
- **Default:** `100`

##### analyticsDisplayLimit
How many analytics to display in the CP.
- **Type:** `int`
- **Default:** `100`

##### itemsPerPage
Items per page in list views.
- **Type:** `int`
- **Range:** `10-500`
- **Default:** `100`

#### Cache Settings

##### enableRedirectCache
Enable caching of redirect lookups for improved performance.
- **Type:** `bool`
- **Default:** `true`

##### redirectCacheDuration
How long to cache redirect lookups in seconds.
- **Type:** `int`
- **Default:** `3600` (1 hour)

##### cacheDeviceDetection
Cache device detection results for better performance.
- **Type:** `bool`
- **Default:** `true`

##### deviceDetectionCacheDuration
Device detection cache duration in seconds.
- **Type:** `int`
- **Default:** `3600` (1 hour)

#### Advanced Settings

##### enableApiEndpoint
Whether to enable the GraphQL endpoint.
- **Type:** `bool`
- **Default:** `false`

##### excludePatterns
Regular expressions to exclude URLs from redirect handling.
- **Type:** `array`
- **Default:** `[]`
- **Format:** `[['pattern' => '^/admin'], ['pattern' => '^/cpresources']]`
- **Note:** Don't exclude static assets - you want to track missing CSS/JS/images to fix them!

##### additionalHeaders
Additional HTTP headers to add to redirect responses.
- **Type:** `array`
- **Default:** `[]`
- **Format:** `[['name' => 'X-Robots-Tag', 'value' => 'noindex']]`
- **Common examples:**
  - `X-Robots-Tag: noindex, nofollow` - Prevents search engines from indexing old URLs
  - `X-Redirect-By: Redirect Manager` - Debugging/tracking

##### ipHashSalt
Secure salt for IP address hashing (required for analytics).
- **Type:** `string`
- **Default:** From `.env` file (`REDIRECT_MANAGER_IP_SALT`)
- **Generate:** `php craft redirect-manager/security/generate-salt`

### Query String Handling Examples

**E-commerce/Marketing Sites:**
```php
return [
    'stripQueryString' => true,          // Match any UTM params
    'preserveQueryString' => true,       // Keep tracking through redirects
    'stripQueryStringFromStats' => true, // Consolidate analytics reports
];
```

**API/Applications:**
```php
return [
    'stripQueryString' => false,          // Exact URL matching required
    'preserveQueryString' => false,       // Canonical URLs without params
    'stripQueryStringFromStats' => false, // Track each unique combination
];
```

### Precedence

Settings are loaded in this order (later overrides earlier):

1. Default plugin settings
2. Database-stored settings (from CP)
3. Config file settings
4. Environment-specific config settings

**Note:** Config file settings always override database settings, making them ideal for production environments where you want to enforce specific values.

### Using Environment Variables

All settings support environment variables:

```php
use craft\helpers\App;

return [
    'enableAnalytics' => (bool)App::env('REDIRECT_MANAGER_ANALYTICS') ?: true,
    'analyticsRetention' => (int)App::env('REDIRECT_MANAGER_RETENTION') ?: 30,
    'logLevel' => App::env('REDIRECT_MANAGER_LOG_LEVEL') ?: 'error',
];
```

### IP Privacy Protection Setup

Analytics requires a secure salt for IP hashing:

1. Generate salt: `php craft redirect-manager/security/generate-salt`
2. Command automatically adds `REDIRECT_MANAGER_IP_SALT` to your `.env` file
3. **Manually copy** the salt value to staging/production `.env` files
4. **Never regenerate** the salt in production

**Security Notes:**
- Never commit the salt to version control
- Store salt securely (password manager recommended)
- Use the SAME salt across all environments
- Changing the salt will break unique visitor tracking history

## Read-Only Mode & Production Environments

Redirect Manager fully supports Craft's `allowAdminChanges` setting for production deployments.

### Enabling Read-Only Mode

Add to your `.env` file:

```bash
CRAFT_ALLOW_ADMIN_CHANGES=false
```

### What Happens in Read-Only Mode

When `allowAdminChanges` is disabled:

1. **Settings Pages** - Display with a read-only notice banner
2. **Form Fields** - All inputs are disabled (can view but not edit)
3. **Save Actions** - Return 403 Forbidden HTTP errors
4. **Config Overrides** - Config file settings remain the source of truth

### Best Practices

**Development Environment:**
```bash
# .env
CRAFT_ALLOW_ADMIN_CHANGES=true
```

Configure settings through the Control Panel, which saves to the database.

**Staging/Production Environments:**
```bash
# .env
CRAFT_ALLOW_ADMIN_CHANGES=false
```

Use `config/redirect-manager.php` to manage settings:

```php
<?php
return [
    'production' => [
        'autoCreateRedirects' => true,
        'enableAnalytics' => true,
        'analyticsRetention' => 365,
        'enableRedirectCache' => true,
        'redirectCacheDuration' => 86400,
        'logLevel' => 'error',
        'excludePatterns' => [
            ['pattern' => '^/admin'],
            ['pattern' => '^/cpresources'],
        ],
        'additionalHeaders' => [
            ['name' => 'X-Robots-Tag', 'value' => 'noindex, nofollow'],
        ],
    ],
];
```

### Performance Recommendations

For production environments:

```php
'production' => [
    'enableRedirectCache' => true,
    'redirectCacheDuration' => 86400,        // 24 hours - aggressive caching
    'cacheDeviceDetection' => true,
    'deviceDetectionCacheDuration' => 7200,  // 2 hours
    'analyticsRetention' => 365,             // Balance data vs storage
    'logLevel' => 'error',                   // Only log errors
],
```

### Security Recommendations

```php
// Exclude sensitive URLs from redirect handling
'excludePatterns' => [
    ['pattern' => '^/admin'],           // Admin panel
    ['pattern' => '^/cpresources'],     // Admin resources
    ['pattern' => '^/actions'],         // Controller actions
    ['pattern' => '^/\\.well-known'],   // Security files
],

// Prevent search engines from indexing old URLs
'additionalHeaders' => [
    ['name' => 'X-Robots-Tag', 'value' => 'noindex, nofollow'],
],
```
