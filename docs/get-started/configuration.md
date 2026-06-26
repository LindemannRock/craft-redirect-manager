# Configuration

Configure Redirect Manager from the Control Panel or by creating `config/redirect-manager.php`. Config-file values override the matching Control Panel fields, which is useful when production behavior needs to stay locked across deploys.

## General

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `pluginName` | `string` | `'Redirect Manager'` | The public-facing name of the plugin |
| `autoCreateRedirects` | `bool` | `true` | Automatically create redirects when entry URIs change |
| `undoWindowMinutes` | `int` | `60` | Time window in minutes for detecting immediate undo (`0`, `30`, `60`, `120`, `240`). `0` = unlimited (always undo, no time limit) |
| `redirectSrcMatch` | `string` | `'pathonly'` | Match legacy URL by path (`pathonly`) or full URL (`fullurl`) |

## Interface

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `refreshIntervalSecs` | `?int` | `null` | Dashboard auto-refresh interval in seconds (`0` / `null` = disabled; CP options include 15, 30, 60, and 120 seconds) |
| `itemsPerPage` | `int` | `100` | Items per page in redirect and analytics list views (10-500) |

Redirect Manager also exposes base-owned display settings from the Interface settings page. Leave these unset to inherit from `config/lindemannrock-base.php`; set them in `config/redirect-manager.php` only when this plugin should behave differently.

### Date and time display

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `timeFormat` | `?string` | `null` | Time display override: `12` or `24` |
| `monthFormat` | `?string` | `null` | Month display override: `numeric`, `short`, or `long` |
| `dateOrder` | `?string` | `null` | Date order override: `dmy`, `mdy`, or `ymd` |
| `dateSeparator` | `?string` | `null` | Date separator override: `/`, `-`, or `.` |
| `showSeconds` | `?bool` | `null` | Whether timestamps include seconds |

### Default date range

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `defaultDateRange` | `?string` | `null` | Default date range for dashboard, analytics, logs, and other date-filtered views |

Common values include `today`, `yesterday`, `last7days`, `last30days`, `last90days`, `thisMonth`, `lastMonth`, `thisYear`, `lastYear`, and `all`.

### Export formats

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `exports.csv` | `?bool` | `null` | Enable CSV export for this plugin |
| `exports.json` | `?bool` | `null` | Enable JSON export for this plugin |
| `exports.excel` | `?bool` | `null` | Enable Excel export for this plugin |

## Query String Handling

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `stripQueryString` | `bool` | `false` | Strip query string from 404 URLs before matching |
| `preserveQueryString` | `bool` | `false` | Preserve and pass query string to redirect destination |

## Redirect Response

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `setNoCacheHeaders` | `bool` | `true` | Set no-cache headers on redirect responses |
| `additionalHeaders` | `array` | `[]` | Additional HTTP headers to add to redirect responses |

## JSON API

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `apiEndpointEnabled` | `bool` | `false` | Enable the read-only JSON redirects endpoint at `/actions/redirect-manager/api/get-redirects` |
| `apiEndpointRateLimit` | `int` | `60` | Maximum JSON API requests per minute for the configured token. Set to `0` to disable rate limiting |
| `apiEndpointToken` | `?string` | `null` | Token for the JSON endpoint. Falls back to `REDIRECT_MANAGER_API_TOKEN`; callers must send a bearer token or `X-Redirect-Manager-Key` header |

When enabled and token-configured, test the endpoint from **Redirect Manager → Settings → Test**.

## Analytics

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableAnalytics` | `bool` | `true` | Master switch — controls IP tracking, device detection, geo detection |
| `anonymizeIpAddress` | `bool` | `false` | Anonymize IP addresses before hashing (subnet masking) |
| `ipHashSalt` | `?string` | `null` | IP hash salt. Falls back to `REDIRECT_MANAGER_IP_SALT` env var |
| `stripQueryStringFromStats` | `bool` | `true` | Strip query strings from analytics URLs (group by path) |
| `analyticsLimit` | `int` | `1000` | Maximum number of unique 404 records to retain |
| `analyticsRetention` | `int` | `30` | Days to retain analytics (`0` = keep forever) |
| `autoTrimAnalytics` | `bool` | `true` | Automatically trim analytics beyond retention |

## Geographic Detection

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableGeoDetection` | `bool` | `false` | Enable geographic detection from IP addresses |
| `geoProvider` | `string` | `'ip-api.com'` | Geo IP provider (`ip-api.com`, `ipapi.co`, `ipinfo.io`) |
| `geoApiKey` | `?string` | `null` | API key for paid provider tiers (enables HTTPS for ip-api.com). Use `App::env('YOUR_VAR')` in your config file to load from an environment variable |
| `defaultCountry` | `?string` | `null` | Default country for local dev. Falls back to `REDIRECT_MANAGER_DEFAULT_COUNTRY` env var. Requires `defaultCity`; otherwise private/local IP geo fields stay empty |
| `defaultCity` | `?string` | `null` | Default city for local dev. Falls back to `REDIRECT_MANAGER_DEFAULT_CITY` env var. Requires `defaultCountry`; otherwise private/local IP geo fields stay empty |

## Device Detection

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `cacheDeviceDetection` | `bool` | `true` | Cache device detection results |
| `deviceDetectionCacheDuration` | `int` | `3600` | Cache duration in seconds |

## Caching

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableRedirectCache` | `bool` | `true` | Enable redirect lookup caching |
| `redirectCacheDuration` | `int` | `3600` | Redirect cache duration in seconds |
| `cacheStorageMethod` | `string` | `'file'` | Cache storage method (`file` or `redis`) |

## Backups @since(5.23.0)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `backupEnabled` | `bool` | `true` | Enable automatic backups |
| `backupOnImport` | `bool` | `true` | Create backup before CSV import |
| `backupSchedule` | `string` | `'disabled'` | Schedule (`disabled`, `daily`, `weekly`, `monthly`) |
| `backupRetentionDays` | `int` | `30` | Days to keep backups (`0` = keep forever, max 365) |
| `backupPath` | `string` | `'@storage/redirect-manager/backups'` | Local filesystem path for backups. Supports `@storage`, `@root` subfolders, or `$VARIABLE` env vars that resolve inside those roots. |
| `backupVolumeUid` @since(5.32.0) | `?string` | `null` | Optional asset volume UID for storing backups. Local volumes inside `@webroot` are rejected; remote volume access must be restricted in the storage provider. |

## Advanced

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `excludePatterns` | `array` | `[]` | Regex patterns for URLs to exclude from both redirect handling and analytics. See [URL Filtering](../feature-tour/url-filtering.md) |
| `logLevel` | `string` | `'error'` | Log level (`debug`, `info`, `warning`, `error`). Debug requires devMode |

## Environment Variables

| Variable | Setting | Description |
|----------|---------|-------------|
| `REDIRECT_MANAGER_IP_SALT` | `ipHashSalt` | IP hash salt for privacy-focused analytics |
| `REDIRECT_MANAGER_DEFAULT_COUNTRY` | `defaultCountry` | Default country code for local development |
| `REDIRECT_MANAGER_DEFAULT_CITY` | `defaultCity` | Default city for local development |
| `REDIRECT_MANAGER_API_TOKEN` | `apiEndpointToken` | Token required by the read-only JSON redirects endpoint |

Generate a secure JSON API token with:

```bash title="PHP"
php craft redirect-manager/security/generate-api-token
```

```bash title="DDEV"
ddev craft redirect-manager/security/generate-api-token
```

## Example Configuration

```php
<?php
// config/redirect-manager.php

use craft\helpers\App;

return [
    '*' => [
        'pluginName' => 'Redirect Manager',
        'autoCreateRedirects' => true,
        'undoWindowMinutes' => 60,
        'redirectSrcMatch' => 'pathonly',

        // JSON API
        'apiEndpointEnabled' => false,
        'apiEndpointRateLimit' => 60,
        'apiEndpointToken' => App::env('REDIRECT_MANAGER_API_TOKEN'),

        // Analytics
        'enableAnalytics' => true,
        'anonymizeIpAddress' => false,
        'analyticsRetention' => 30,
        'analyticsLimit' => 1000,

        // Geo
        'enableGeoDetection' => false,
        'geoProvider' => 'ip-api.com',

        // Caching
        'enableRedirectCache' => true,
        'redirectCacheDuration' => 3600,
        'cacheStorageMethod' => 'file',

        // Query strings
        'stripQueryString' => false,
        'preserveQueryString' => false,
        'stripQueryStringFromStats' => true,

        // Backups
        'backupEnabled' => true,
        'backupOnImport' => true,
        'backupSchedule' => 'daily',
        'backupRetentionDays' => 30,

        // Logging
        'logLevel' => 'error',

        // Optional base-setting overrides for this plugin only
        // Leave unset to inherit from config/lindemannrock-base.php
        // 'timeFormat' => '24',
        // 'defaultDateRange' => 'last30days',
        // 'exports' => [
        //     'csv' => true,
        //     'json' => true,
        //     'excel' => true,
        // ],
    ],
];
```

## Translations

Redirect Manager includes translations for 12 languages. See [Translations](../resources/translations.md) for the full list and override instructions.
