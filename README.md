# Redirect Manager for Craft CMS

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-redirect-manager.svg)](https://packagist.org/packages/lindemannrock/craft-redirect-manager)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.0+-orange.svg)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net/)
[![Logging Library](https://img.shields.io/badge/Logging%20Library-5.0+-green.svg)](https://github.com/LindemannRock/craft-logging-library)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-redirect-manager.svg)](LICENSE)

Intelligent redirect management and 404 handling for Craft CMS.

## ⚠️ Beta Notice

This plugin is currently in active development and provided under the MIT License for testing purposes.

**Licensing is subject to change.** We are finalizing our licensing structure and some or all features may require a paid license when officially released on the Craft Plugin Store. Some plugins may remain free, others may offer free and Pro editions, or be fully commercial.

If you are using this plugin, please be aware that future versions may have different licensing terms.

## Features

- **Automatic 404 Handling** - Catches all 404s and attempts to redirect
- **Multiple Match Types**:
  - Exact Match - Case-insensitive exact URL matching
  - RegEx Match - Full regular expression support
  - Wildcard Match - Simple * wildcards
  - Prefix Match - URL starts with pattern
- **Rich Analytics** - Track 404s with device detection, browsers, OS, geographic data, and bot identification
- **Device Detection** - Powered by Matomo DeviceDetector for accurate device, browser, and OS identification
- **Bot Filtering** - Identify and filter bot traffic (GoogleBot, BingBot, etc.)
- **Geographic Detection** - Track visitor location (country, city) via ip-api.com
- **Auto-Redirect Creation** - Automatically creates redirects when entry URIs change
- **Smart Caching** - File or Redis caching for fast redirect lookups and device detection
- **Auto-Refreshing Dashboard** - Configurable auto-refresh (5-60 seconds) with smart pause on user interaction
- **CSV Import/Export** - Import redirects from CSV (up to 4000 rows) and export comprehensive analytics including device and geo data
- **Multi-Site Support** - Site-specific or global redirects
- **Plugin Integration** - Pluggable architecture allowing other plugins to integrate 404 handling
- **Privacy-First** - IP hashing with salt, optional subnet masking, GDPR-friendly
- **Logging Integration** - Uses logging-library for consistent logs

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later
- [Logging Library](https://github.com/LindemannRock/craft-logging-library) 5.0 or greater (installed automatically as dependency)
- [Matomo Device Detector](https://github.com/matomo-org/device-detector) 6.4 or greater (installed automatically as dependency)

## Installation

### Via Composer

```bash
cd /path/to/project
```

```bash
composer require lindemannrock/craft-redirect-manager
```

```bash
./craft plugin/install redirect-manager
```

### Using DDEV

```bash
cd /path/to/project
```

```bash
ddev composer require lindemannrock/craft-redirect-manager
```

```bash
ddev craft plugin/install redirect-manager
```

### Via Control Panel

In the Control Panel, go to Settings → Plugins and click "Install" for Redirect Manager.

### Important: IP Privacy Protection

Redirect Manager uses **privacy-focused IP hashing** with a secure salt:

- ✅ **Rainbow-table proof** - Salted SHA256 prevents pre-computed attacks
- ✅ **Unique visitor tracking** - Same IP = same hash
- ✅ **Maximum privacy** - Original IPs never stored, unrecoverable
- ✅ **Optional subnet masking** - Additional anonymization layer

**Setup Instructions:**
1. Generate salt: `php craft redirect-manager/security/generate-salt`
2. Command automatically adds `REDIRECT_MANAGER_IP_SALT` to your `.env` file
3. **Manually copy** the salt value to staging/production `.env` files
4. **Never regenerate** the salt in production

**How It Works:**
- Plugin automatically reads salt from `.env` (no config file needed!)
- Config file can override if needed: `'ipHashSalt' => App::env('REDIRECT_MANAGER_IP_SALT')`
- If no salt found, error banner shown in settings

**Security Notes:**
- Never commit the salt to version control
- Store salt securely (password manager recommended)
- Use the SAME salt across all environments (dev/staging/production)
- Changing the salt will break unique visitor tracking history

### Local Development: Analytics Location Override

When running locally (DDEV, localhost), analytics will **default to Dubai, UAE** because local IPs can't be geolocated. To set your actual location for testing:

**Option 1: Config File** (recommended for project-wide default)
```php
// config/redirect-manager.php
return [
    'defaultCountry' => 'US',
    'defaultCity' => 'New York',
];
```

**Option 2: Environment Variable** (recommended for per-environment control)
```bash
# .env
REDIRECT_MANAGER_DEFAULT_COUNTRY=US
REDIRECT_MANAGER_DEFAULT_CITY=New York
```

**Fallback Priority:**
1. Config file setting
2. .env variable
3. Hardcoded default: Dubai, UAE

**Supported locations:**

- **US**: New York, Los Angeles, Chicago, San Francisco
- **GB**: London, Manchester
- **AE**: Dubai, Abu Dhabi (default: Dubai)
- **SA**: Riyadh, Jeddah
- **DE**: Berlin, Munich
- **FR**: Paris
- **CA**: Toronto, Vancouver
- **AU**: Sydney, Melbourne
- **JP**: Tokyo
- **SG**: Singapore
- **IN**: Mumbai, Delhi

**Important:** This setting is **safe to use in all environments** (dev, staging, production). It **only affects private/local IP addresses** (127.0.0.1, 192.168.x.x, 10.x.x.x, etc.). Real visitor IPs in production will always use actual geolocation from ip-api.com. This means you can safely commit config file settings without impacting production analytics.

## Configuration

### Config File

Create a `config/redirect-manager.php` file to override default settings:

```bash
cp vendor/lindemannrock/craft-redirect-manager/src/config.php config/redirect-manager.php
```

Example configuration:

```php
<?php
return [
    // Auto create redirects when entry URIs change
    'autoCreateRedirects' => true,

    // Analytics (master switch - controls device detection, geo, IP tracking)
    'enableAnalytics' => true,
    'enableGeoDetection' => false,  // Track visitor location
    'geoProvider' => 'ip-api.com',  // Options: 'ip-api.com', 'ipapi.co', 'ipinfo.io'
    'geoApiKey' => App::env('REDIRECT_MANAGER_GEO_API_KEY'),  // Required for ip-api.com HTTPS
    'anonymizeIpAddress' => false,  // Subnet masking for privacy

    // Analytics retention in days (0 = keep forever)
    'analyticsRetention' => 30,
    'analyticsLimit' => 1000,

    // Dashboard
    'refreshIntervalSecs' => 5,  // Auto-refresh interval (5, 15, 30, or 60 seconds)

    // Performance & Caching
    'cacheStorageMethod' => 'file',  // 'file' or 'redis'
    'enableRedirectCache' => true,
    'redirectCacheDuration' => 3600,  // 1 hour
    'cacheDeviceDetection' => true,
    'deviceDetectionCacheDuration' => 3600,  // 1 hour

    // Query String Handling
    'stripQueryString' => false,           // Strip query string for redirect matching
    'preserveQueryString' => false,        // Pass query string to destination URL
    'stripQueryStringFromStats' => true,   // Group analytics by path only

    // Log level
    'logLevel' => 'error',
];
```

See [Configuration Documentation](docs/CONFIGURATION.md) for all available options.

### Environment-Specific Config

```php
<?php
return [
    '*' => [
        'autoCreateRedirects' => true,
    ],
    'production' => [
        'logLevel' => 'error',
        'analyticsRetention' => 90,
    ],
    'dev' => [
        'logLevel' => 'debug',
        'analyticsRetention' => 7,
    ],
];
```

## Usage

### Creating Redirects

**Via Control Panel:**
1. Navigate to **Redirect Manager → Redirects**
2. Click **New redirect**
3. Fill in:
   - **Source Match Mode**: Path Only (recommended) or Full URL
   - **Match Type**: exact, contains, regex, wildcard, or prefix
   - **Source URL**: `/old-page` or `/blog/*` or regex pattern
   - **Destination URL**: `/new-page` or absolute URL
   - **Priority**: 0-9 (0 = highest priority, 9 = lowest)
   - **Status Code**: 301, 302, 303, 307, 308, or 410
4. Save

**Source Match Mode:**
- **Path Only** (default): Match by path only (e.g., `/old-page`). Works across all domains. Full URLs entered are automatically converted to paths.
- **Full URL**: Match by complete URL including domain (e.g., `https://example.com/old-page`). Use when you need domain-specific redirects.

**Programmatically:**

```php
use lindemannrock\redirectmanager\RedirectManager;

RedirectManager::$plugin->redirects->createRedirect([
    'sourceUrl' => '/old-page',
    'destinationUrl' => '/new-page',
    'matchType' => 'exact',
    'statusCode' => 301,
    'enabled' => true,
    'priority' => 0,
    'siteId' => null, // null = all sites
]);
```

### Match Type Examples

**Exact Match:**
```
Source: /old-page
Matches: /old-page (case-insensitive)
Does NOT match: /old-page/subpage
```

**Contains Match:**
```
Source: old-post
Matches: /blog/old-post, /archive/old-post/123, /old-post-title
```

**Wildcard Match:**
```
Source: /blog/*
Matches: /blog/post-1, /blog/category/news, etc.
```

**Prefix Match:**
```
Source: /old-
Matches: /old-page, /old-blog, /old-anything
```

**RegEx Match:**
```
Source: ^/blog/(\d+)/(.*)$
Destination: /article/$1/$2
Matches: /blog/123/my-post → /article/123/my-post
```

### Priority

Priority controls which redirect matches first when multiple patterns could apply:

| Priority | Description | Use Case |
|----------|-------------|----------|
| 0 | Highest priority | Specific patterns (e.g., `/blog/special-post`) |
| 1-4 | High priority | Important redirects |
| 5 | Normal | Standard redirects |
| 6-8 | Low priority | General patterns |
| 9 | Lowest priority | Catch-all patterns (e.g., `/blog/*`) |

**Example:** Set `/blog/featured-post` to priority 0 and `/blog/*` to priority 9. The specific redirect matches first.

### Status Codes

| Code | Name | Description |
|------|------|-------------|
| 301 | Moved Permanently | Content permanently moved. Search engines update their index. **Most common.** |
| 302 | Found (Temporary) | Temporary redirect. Search engines keep the original URL. |
| 303 | See Other | Redirect to a different resource, typically after form submission. |
| 307 | Temporary Redirect | Like 302 but guarantees request method won't change (POST stays POST). |
| 308 | Permanent Redirect | Like 301 but guarantees request method won't change (POST stays POST). |
| 410 | Gone | Content permanently deleted. Search engines remove it from index. |

### Viewing Analytics

**Dashboard:**
- Navigate to **Redirect Manager → Analytics**
- View handled vs unhandled 404s
- See most common 404s
- Create redirects from unhandled 404s with one click

**Export CSV:**
```
Redirect Manager → Analytics → Export CSV
```

### Import/Export

**Import Redirects:**
1. Navigate to **Redirect Manager → Import/Export**
2. Upload a CSV file (maximum 4000 rows per import)
3. Map CSV columns to redirect fields
4. Preview and confirm import

**Import Limits:**
- Maximum **4000 rows** per CSV file
- For larger imports, split your file into multiple batches
- This limit ensures reliable operation on all hosting environments

**Export Redirects:**
- Export all redirects as CSV for backup or migration
- Includes all fields: source URL, destination, match type, status code, hit counts, etc.

### Automatic Entry Redirects

When enabled (default), the plugin automatically creates redirects when:
- Entry slug changes: `/old-slug` → `/new-slug`
- Entry moves in structure: `/parent/child` → `/new-parent/child`

Disable in settings: **Redirect Manager → Settings → Auto Create Redirects**

## Query String Handling

Redirect Manager provides three settings to control how query strings are handled at different stages:

### 1. Strip Query String (Redirect Matching)

Controls whether query strings affect redirect matching.

**OFF (default):** Exact matching required
- Redirect rule: `/page`
- `/page` ✅ matches
- `/page?foo=bar` ❌ does not match

**ON:** Query strings ignored during matching
- Redirect rule: `/page`
- `/page` ✅ matches
- `/page?foo=bar` ✅ matches

### 2. Preserve Query String (Redirect Destination)

Controls whether query strings are passed to the destination URL.

**OFF (default):** Query strings dropped
- User visits: `/old?ref=email`
- Redirects to: `/new`

**ON:** Query strings preserved
- User visits: `/old?ref=email`
- Redirects to: `/new?ref=email`

### 3. Strip Query String From Stats (Analytics Grouping)

Controls how analytics groups URLs with different query strings.

**ON (default):** Consolidate by path
- Dashboard shows: `/page?source=google` (count: 3)
- Hits: `/page?source=email`, `/page?source=facebook`, `/page?source=google`
- All grouped into one record, displays latest query string

**OFF:** Separate records per unique URL+query
- Dashboard shows: 3 separate rows, each with count: 1
- `/page?source=email`, `/page?source=facebook`, `/page?source=google`

### Common Configurations

**E-commerce/Marketing Sites:**
```php
'stripQueryString' => true,          // Match any UTM params
'preserveQueryString' => true,       // Keep tracking through redirects
'stripQueryStringFromStats' => true, // Consolidate analytics reports
```

**API/Applications:**
```php
'stripQueryString' => false,          // Exact URL matching required
'preserveQueryString' => false,       // Canonical URLs without params
'stripQueryStringFromStats' => false, // Track each unique combination
```

**Note:** Analytics always store paths (e.g., `/page?foo=bar`), never full URLs with domains.

## Testing Guide

### 1. Test Basic Redirect

```bash
# Create a test redirect via CP:
# Source: /test-redirect
# Destination: /
# Match Type: exact
# Status Code: 301

# Test it:
curl -I http://your-site.test/test-redirect
# Should return: HTTP/1.1 301 Moved Permanently
# Location: http://your-site.test/
```

### 2. Test 404 Tracking

```bash
# Visit a non-existent page:
curl -I http://your-site.test/page-that-does-not-exist

# Check analytics:
# CP → Redirect Manager → Analytics
# Should show the 404 with "Handled: No"
```

### 3. Test Auto-Redirect Creation

1. Create a test entry with slug `test-entry`
2. Note the URL (e.g., `/test-entry`)
3. Change the slug to `renamed-entry`
4. Save the entry
5. Check **Redirect Manager → Redirects**
6. Should see auto-created redirect: `/test-entry` → `/renamed-entry`

### 4. Test Wildcard Matching

```bash
# Create redirect:
# Source: /old-blog/*
# Destination: /blog/
# Match Type: wildcard

curl -I http://your-site.test/old-blog/any-post
# Should redirect to /blog/
```

### 5. Test Analytics Cleanup

```php
# Run cleanup job manually:
php craft queue/run

# Or test the service directly:
php craft console/controller/eval \
  "echo lindemannrock\redirectmanager\RedirectManager::\$plugin->analytics->cleanupOldAnalytics();"
```

## Permissions

Redirect Manager provides granular user permissions for fine-grained access control:

### Redirect Management

| Permission | Description |
|------------|-------------|
| **Manage redirects** | Parent permission for all redirect operations. |
| ↳ View redirects | View redirect list. Users can see redirects but not modify them. |
| ↳ Create redirects | Create new redirects. Shows "New Redirect" button and enables creating from 404 list. |
| ↳ Edit redirects | Modify existing redirects. Makes redirect names clickable, shows enable/disable options. |
| ↳ Delete redirects | Remove redirects. Shows delete buttons and enables bulk delete. |

### Import/Export

| Permission | Description |
|------------|-------------|
| **Manage import/export** | Parent permission for import/export operations. |
| ↳ Import redirects | Import redirects from CSV files. |
| ↳ Export redirects | Export redirects to CSV files. |
| ↳ View import history | View import history tab. See past CSV imports and their results. |
| ↳ Clear import history | Clear import history logs. Permanently delete import tracking history. |

### Backups

| Permission | Description |
|------------|-------------|
| **Manage backups** | Parent permission for backup operations. |
| ↳ Create backups | Create manual backups of redirects. |
| ↳ Download backups | Download backup files as ZIP. |
| ↳ Restore backups | Restore redirects from a backup. |
| ↳ Delete backups | Delete backup files. |

### Analytics

| Permission | Description |
|------------|-------------|
| **View analytics** | Access Dashboard (404 list) and Analytics (charts). View 404 tracking data. |
| ↳ Export analytics | Export analytics data to CSV. |
| ↳ Clear analytics | Clear all analytics data. Permanently delete 404 tracking history. |

### Other

| Permission | Description |
|------------|-------------|
| **Clear cache** | Clear redirect caches. Shows cache options in Craft's Clear Caches utility. |
| **View logs** | Parent permission for log access. |
| ↳ View system logs | Access plugin logs. View log entries and file details. |
| &nbsp;&nbsp;↳ Download system logs | Download log files. |
| **Manage settings** | Access Settings section. Modify all plugin configuration. |

### Permission Behavior

- **Nested permissions** - Child permissions are nested under parent permissions in Craft's user permission UI
- **CP navigation visibility** - Plugin nav items only show if user has at least one relevant permission
- **UI adaptation** - Buttons, links, and actions automatically show/hide based on user permissions
- **Controller protection** - All controller actions verify permissions before executing

### Example Configurations

**Editor (Create & Edit only):**
- ✅ Manage redirects → View, Create, Edit
- ❌ Delete redirects
- ❌ Manage import/export
- ❌ Manage settings

**Analyst (View only):**
- ✅ Manage redirects → View
- ✅ View analytics
- ✅ View logs → View system logs
- ❌ Create/Edit/Delete redirects
- ❌ Manage settings

**Full Access:**
- ✅ All permissions enabled

## Logging

Redirect Manager uses the [LindemannRock Logging Library](https://github.com/LindemannRock/craft-logging-library) for centralized logging.

### Log Levels
- **Error**: Critical errors only (default)
- **Warning**: Errors and warnings
- **Info**: General information
- **Debug**: Detailed debugging (includes performance metrics, requires devMode)

### Configuration
```php
// config/redirect-manager.php
return [
    'logLevel' => 'error', // error, warning, info, or debug
];
```

**Note:** Debug level requires Craft's `devMode` to be enabled. If set to debug with devMode disabled, it automatically falls back to info level.

### Log Files
- **Location**: `storage/logs/redirect-manager-YYYY-MM-DD.log`
- **Retention**: 30 days (automatic cleanup via Logging Library)
- **Format**: Structured JSON logs with context data
- **Web Interface**: View and filter logs in CP at Redirect Manager → Logs

### Log Management
Access logs through the Control Panel:
1. Navigate to Redirect Manager → Logs
2. Filter by date, level, or search terms
3. Download log files for external analysis
4. View file sizes and entry counts
5. Auto-cleanup after 30 days (configurable via Logging Library)

**Requires:** `lindemannrock/craft-logging-library` plugin (installed automatically as dependency)

See [docs/LOGGING.md](docs/LOGGING.md) for detailed logging documentation.

## Troubleshooting

### Redirects Not Working

1. **Check plugin is installed:**
   ```bash
   php craft plugin/list
   ```

2. **Check database tables exist:**
   ```bash
   php craft migrate/all --plugin=redirect-manager
   ```

3. **Check logs:**
   ```
   CP → Redirect Manager → Logs
   ```

4. **Enable debug logging:**
   ```php
   // config/redirect-manager.php
   return [
       'logLevel' => 'debug',
   ];
   ```

5. **Clear caches:**
   ```bash
   php craft clear-caches/all
   ```

### Analytics Not Recording

1. Check **Settings → Analytics → Enable Analytics** is enabled (master switch)
2. Check analytics limit hasn't been reached
3. Ensure IP hash salt is configured (run: `php craft redirect-manager/security/generate-salt`)
4. Check database: `SELECT * FROM redirectmanager_analytics`

### Auto-Redirects Not Creating

1. Check **Settings → General → Auto Create Redirects** is enabled
2. Check entry has a URI (not disabled, not in a disabled section)
3. Check logs for any errors

## Events

Listen to redirect events in your own plugins:

```php
use lindemannrock\redirectmanager\services\RedirectsService;
use lindemannrock\redirectmanager\events\RedirectEvent;
use yii\base\Event;

Event::on(
    RedirectsService::class,
    RedirectsService::EVENT_BEFORE_SAVE_REDIRECT,
    function(RedirectEvent $event) {
        // Modify redirect before saving
        $event->redirect['statusCode'] = 302;

        // Or prevent saving
        // $event->isValid = false;
    }
);
```

Available events:
- `EVENT_BEFORE_SAVE_REDIRECT`
- `EVENT_AFTER_SAVE_REDIRECT`
- `EVENT_BEFORE_DELETE_REDIRECT`
- `EVENT_AFTER_DELETE_REDIRECT`

## Plugin Integration

Redirect Manager provides a **pluggable architecture** that allows other plugins to integrate their 404 handling, similar to how plugins use the Logging Library.

### RedirectHandlingTrait - What It Provides

The trait provides **2 methods** that other plugins can use:

| Method | Returns | Description |
|--------|---------|-------------|
| `handleRedirect404(string $url, string $source, array $context)` | `?array` | Check if a redirect exists for a 404 URL |
| `createRedirectRule(array $attributes)` | `bool` | Create a new redirect in Redirect Manager |

**Important:** The trait does NOT provide handler functions like `handleDeletedItem()` or `handle404()`. Those are examples of functions **you write** in your own plugin that call the trait's methods.

### Integration Method 1: Handle 404s

When your plugin encounters a 404, check if Redirect Manager has a matching redirect.

**Example implementation (you write this code):**

```php
use lindemannrock\redirectmanager\traits\RedirectHandlingTrait;

class MyController extends Controller
{
    use RedirectHandlingTrait;

    /**
     * Your custom 404 handler (not provided by trait)
     */
    private function handle404(): Response
    {
        $url = Craft::$app->getRequest()->getUrl();

        // Call the trait's handleRedirect404() method
        $redirect = $this->handleRedirect404($url, 'my-plugin', [
            'type' => 'custom-404',
            'context' => 'additional-metadata'
        ]);

        if ($redirect) {
            // Redirect found! Use it
            return $this->redirect($redirect['destinationUrl'], $redirect['statusCode']);
        }

        // No redirect found, use your fallback
        return $this->redirect('/', 302);
    }
}
```

**What happens:**
1. Your plugin encounters a 404 (e.g., `/my-plugin/xyz` doesn't exist)
2. You call `handleRedirect404()` to check if a redirect exists
3. Redirect Manager searches for matching redirects
4. Analytics are recorded with your plugin as the source
5. Returns redirect data if found, or `null` if not

### Integration Method 2: Push Redirects

Your plugin can automatically create redirects when certain events occur (item deleted, slug changed, link expired, etc.).

**Example implementation (you write this code):**

```php
use lindemannrock\redirectmanager\traits\RedirectHandlingTrait;

class MyService extends Component
{
    use RedirectHandlingTrait;

    /**
     * Your custom deletion handler (not provided by trait)
     *
     * This is example code showing how YOU would implement auto-redirect creation.
     * The function name "handleDeletedItem" is just an example - name it whatever you want.
     */
    public function handleDeletedItem($item): void
    {
        // Your business logic here
        if ($item->hits === 0) {
            return; // Don't create redirect for unused items
        }

        // Call the trait's createRedirectRule() method
        $this->createRedirectRule([
            'sourceUrl' => '/items/' . $item->slug,
            'sourceUrlParsed' => '/items/' . $item->slug,
            'destinationUrl' => '/items',
            'matchType' => 'exact',              // exact|regex|wildcard|prefix
            'redirectSrcMatch' => 'pathonly',    // pathonly|fullurl (REQUIRED)
            'statusCode' => 301,
            'siteId' => $item->siteId,
            'enabled' => true,
            'priority' => 0,
            'creationType' => 'item-deleted',    // What happened (max 50 chars)
            'sourcePlugin' => 'my-plugin',       // Your plugin handle in kebab-case (max 50 chars)
        ], true); // Show notification to user
    }
}
```

**Slug Change Example with Undo Detection:**

```php
use lindemannrock\redirectmanager\traits\RedirectHandlingTrait;

class MyService extends Component
{
    use RedirectHandlingTrait;

    public function handleSlugChange(string $oldSlug, MyElement $element): void
    {
        $oldUrl = '/items/' . $oldSlug;
        $newUrl = '/items/' . $element->slug;

        // Check for immediate undo (A→B→A within time window)
        if ($this->handleUndoRedirect($oldUrl, $newUrl, $element->siteId, 'item-slug-change', 'my-plugin')) {
            return; // Undo was detected and handled
        }

        // Create redirect with notification
        $this->createRedirectRule([
            'sourceUrl' => $oldUrl,
            'sourceUrlParsed' => $oldUrl,
            'destinationUrl' => $newUrl,
            'matchType' => 'exact',
            'redirectSrcMatch' => 'pathonly',
            'statusCode' => 301,
            'siteId' => $element->siteId,
            'enabled' => true,
            'priority' => 0,
            'creationType' => 'item-slug-change',
            'sourcePlugin' => 'my-plugin',
        ], true); // Show notification
    }
}
```

**Trait Methods:**

`createRedirectRule(array $attributes, bool $showNotification = false): bool`
- Creates a redirect in Redirect Manager
- `$showNotification` - Set to `true` to show "Redirect created: X → Y" notification to user
- Returns `true` on success, `false` on failure

`handleUndoRedirect(string $oldUrl, string $newUrl, int $siteId, string $creationType, string $sourcePlugin): bool`
- Detects and handles immediate undo (flip-flop redirects within undo window)
- Example: Change A→B, then quickly change B→A - deletes the A→B redirect instead of creating B→A
- Respects Redirect Manager's "Undo Window" setting (30-240 minutes)
- Shows "Slug change undone - previous redirect removed." notification
- Returns `true` if undo was handled, `false` otherwise

`handleRedirect404(string $url, string $source, array $context = []): ?array`
- Checks if Redirect Manager has a redirect for a 404 URL
- Returns redirect data if found, `null` otherwise

**Important Constraints:**
- `creationType` - Maximum 50 characters (e.g., `'code-change'`, `'item-deleted'`, `'smart-link-expired'`)
- `sourcePlugin` - Maximum 50 characters, **always kebab-case** (e.g., `'shortlink-manager'`, `'smart-links'`, `'my-plugin'`)
- `redirectSrcMatch` - **REQUIRED** - Must be `'pathonly'` or `'fullurl'`
- `elementId` - (Optional) Element ID if redirect was created by element URI change. Used to track and clean up redirect chains.
- `sourceUrl` / `sourceUrlParsed` - Maximum 255 characters
- `destinationUrl` - Maximum 500 characters

**Plugin Handle Format:**
- ✅ `'shortlink-manager'` (correct)
- ✅ `'smart-links'` (correct)
- ❌ `'ShortLink Manager'` (wrong - never use title case)
- ❌ `'shortlink_manager'` (wrong - use kebab-case, not snake_case)

**What happens:**
1. Your plugin detects an event (deletion, slug change, etc.)
2. You call `handleUndoRedirect()` to check for immediate undo
3. If no undo, call `createRedirectRule()` to create the redirect
4. Redirect Manager validates, saves, and shows notification (if enabled)
5. Cache is invalidated
6. Future requests to the old URL will be redirected

### Source Plugin Tracking

All 404s tracked through external plugins are recorded with their source plugin identifier:

```php
// The second parameter ('shortlink-manager') becomes the source
$redirect = $this->handleRedirect404($url, 'shortlink-manager', [
    'type' => 'shortlink-not-found',
    'code' => 'abc123'
]);
```

**Analytics dashboard shows breakdown by source:**
```
Redirect Manager: 145 (handled: 98)
ShortLink Manager: 67 (handled: 54)
Smart Links: 43 (handled: 38)
```

This helps you identify which plugin or area is generating the most 404s.

### Real-World Example: ShortLink Manager

See how ShortLink Manager integrates:

**404 Handling:** [`RedirectController::redirectToNotFound()`](https://github.com/LindemannRock/craft-shortlink-manager/blob/main/src/controllers/RedirectController.php)
- Calls `handleRedirect404()` when shortlink not found
- Executes redirect if found
- Falls back to configured URL if not

**Auto-Redirect Creation:** [`ShortLinksService`](https://github.com/LindemannRock/craft-shortlink-manager/blob/main/src/services/ShortLinksService.php)
- `handleCodeChange()` - Creates redirect when shortlink code changes
- `handleExpiredShortLink()` - Creates redirect when shortlink expires
- `handleDeletedShortLink()` - Creates redirect when shortlink deleted (if has traffic)

### Benefits

✅ **Centralized 404 Tracking** - See all 404s across your entire site in one dashboard
✅ **Auto-Healing** - Broken links automatically fixed when redirects exist
✅ **Source Attribution** - Know which plugin or area is generating 404s
✅ **Loose Coupling** - Plugins work independently, integration is optional
✅ **Easy Integration** - Add trait, write your handlers, call the methods, done!

## API

### Services

```php
use lindemannrock\redirectmanager\RedirectManager;

// Redirects
$plugin = RedirectManager::$plugin;
$plugin->redirects->createRedirect([...]);
$plugin->redirects->updateRedirect($id, [...]);
$plugin->redirects->deleteRedirect($id);
$plugin->redirects->findRedirect($fullUrl, $pathOnly);
$plugin->redirects->handleExternal404($url, $context); // For plugin integration

// Analytics
$plugin->analytics->record404($url, $handled, $context); // Context tracks source plugin
$plugin->analytics->getAllAnalytics($siteId, $limit);
$plugin->analytics->getChartData($siteId, $days);
$plugin->analytics->getDeviceBreakdown($siteId, $days);
$plugin->analytics->getBrowserBreakdown($siteId, $days);
$plugin->analytics->getOsBreakdown($siteId, $days);
$plugin->analytics->getBotStats($siteId, $days);
$plugin->analytics->getLocationFromIp($ip);
$plugin->analytics->exportToCsv($siteId);

// Device Detection
$plugin->deviceDetection->detectDevice($userAgent);
$plugin->deviceDetection->isMobileDevice($deviceInfo);
$plugin->deviceDetection->isBot($deviceInfo);

// Matching
$plugin->matching->matches($matchType, $pattern, $url);
```

## Support

- **Documentation**: [https://github.com/LindemannRock/craft-redirect-manager](https://github.com/LindemannRock/craft-redirect-manager)
- **Issues**: [https://github.com/LindemannRock/craft-redirect-manager/issues](https://github.com/LindemannRock/craft-redirect-manager/issues)
- **Email**: [support@lindemannrock.com](mailto:support@lindemannrock.com)

## License

This plugin is licensed under the MIT License. See [LICENSE](LICENSE) for details.

---

Developed by [LindemannRock](https://lindemannrock.com)
