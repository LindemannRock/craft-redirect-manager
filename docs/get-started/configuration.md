# Configuration

Configure Redirect Manager by creating a config file at `config/redirect-manager.php`.

## General

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `pluginName` | `string` | `'Redirect Manager'` | The public-facing name of the plugin |
| `autoCreateRedirects` | `bool` | `true` | Automatically create redirects when entry URIs change |
| `undoWindowMinutes` | `int` | `60` | Time window in minutes for detecting immediate undo (`0`, `30`, `60`, `120`, `240`). `0` = unlimited (always undo, no time limit) |
| `redirectSrcMatch` | `string` | `'pathonly'` | Match legacy URL by path (`pathonly`) or full URL (`fullurl`) |
| `itemsPerPage` | `int` | `100` | Items per page in list views (10-500) |

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
| `refreshIntervalSecs` | `?int` | `null` | Dashboard auto-refresh interval in seconds (`null` = disabled) |

## Geographic Detection

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableGeoDetection` | `bool` | `false` | Enable geographic detection from IP addresses |
| `geoProvider` | `string` | `'ip-api.com'` | Geo IP provider (`ip-api.com`, `ipapi.co`, `ipinfo.io`) |
| `geoApiKey` | `?string` | `null` | API key for paid provider tiers (enables HTTPS for ip-api.com). Use `App::env('YOUR_VAR')` in your config file to load from an environment variable |
| `defaultCountry` | `?string` | `null` | Default country for local dev. Falls back to `REDIRECT_MANAGER_DEFAULT_COUNTRY` env var |
| `defaultCity` | `?string` | `null` | Default city for local dev. Falls back to `REDIRECT_MANAGER_DEFAULT_CITY` env var |

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
| `backupSchedule` | `string` | `'manual'` | Schedule (`manual`, `daily`, `weekly`, `monthly`) |
| `backupRetentionDays` | `int` | `30` | Days to keep backups (`0` = keep forever, max 365) |
| `backupPath` @since(5.0.0) | `string` | `'@storage/redirect-manager/backups'` | Local filesystem path for backups. Supports `$VARIABLE` env vars. |
| `backupVolumeUid` @since(5.0.0) | `?string` | `null` | Optional asset volume UID for storing backups. |

## Advanced

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `excludePatterns` | `array` | `[]` | Regex patterns to exclude URLs from redirect handling |
| `logLevel` | `string` | `'error'` | Log level (`debug`, `info`, `warning`, `error`). Debug requires devMode |

## Environment Variables

| Variable | Setting | Description |
|----------|---------|-------------|
| `REDIRECT_MANAGER_IP_SALT` | `ipHashSalt` | IP hash salt for privacy-focused analytics |
| `REDIRECT_MANAGER_DEFAULT_COUNTRY` | `defaultCountry` | Default country code for local development |
| `REDIRECT_MANAGER_DEFAULT_CITY` | `defaultCity` | Default city for local development |

## Example Configuration

```php
<?php
// config/redirect-manager.php

return [
    '*' => [
        'pluginName' => 'Redirect Manager',
        'autoCreateRedirects' => true,
        'undoWindowMinutes' => 60,
        'redirectSrcMatch' => 'pathonly',

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
    ],
];
```
