# Configuration

Configure Redirect Manager from the Control Panel or by creating `config/redirect-manager.php`. Config-file values override the matching Control Panel fields, which is useful when production behavior needs to stay locked across deploys.

```bash title="PHP"
cp vendor/lindemannrock/craft-redirect-manager/src/config.php config/redirect-manager.php
```

## General

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `pluginName` | `string` | `'Redirect Manager'` | The public-facing name of the plugin |
| `autoCreateRedirects` | `bool` | `true` | Automatically create redirects when entry URIs change |
| `undoWindowMinutes` | `int` | `60` | Time window in minutes for detecting immediate undo (`0`, `30`, `60`, `120`, `240`). `0` = unlimited (always undo, no time limit) |
| `redirectSrcMatch` | `string` | `'pathonly'` | Match legacy URLs by path (`pathonly`) or complete site URL (`fullurl`). Path-only Exact/Prefix inputs accept HTTP(S) URLs and store only the path, without host/query/fragment; automatic full-URL redirects retain each element site's domain and base path |

## Interface

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `refreshIntervalSecs` | `?int` | `null` | Dashboard auto-refresh interval in seconds (`0` / `null` = disabled; CP options include 15, 30, 60, and 120 seconds) |
| `itemsPerPage` | `int` | `100` | Items per page in redirect and analytics list views (10-500) |

## Base display and export overrides

The **Settings → Interface** screen also includes base-owned display and export controls after **Dashboard Refresh Interval**. Leave these unset to inherit from `config/lindemannrock-base.php`; set them in `config/redirect-manager.php` only when Redirect Manager should override the global base value.

When the Control Panel value is **Use global default**, the setting cascades from `config/lindemannrock-base.php`. A value in `config/redirect-manager.php` locks the plugin-specific value and disables the matching CP field.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `timeFormat` | `string\|null` | `null` | Time display override: `'12'` (AM/PM) or `'24'` |
| `monthFormat` | `string\|null` | `null` | Month display override: `'numeric'`, `'short'`, or `'long'` |
| `dateOrder` | `string\|null` | `null` | Date order override: `'dmy'`, `'mdy'`, or `'ymd'` |
| `dateSeparator` | `string\|null` | `null` | Date separator override: `'/'`, `'-'`, or `'.'` |
| `showSeconds` | `bool\|null` | `null` | Whether timestamps include seconds |
| `defaultDateRange` | `string\|null` | `null` | Default date range for dashboard, analytics, logs, and other date-filtered views. Common values: `today`, `yesterday`, `last7days`, `last30days`, `last90days`, `thisMonth`, `lastMonth`, `thisYear`, `lastYear`, `all` |
| `exports` | `array\|null` | `null` | Export format overrides, e.g. `['csv' => true, 'json' => true, 'excel' => true]` |

## Query String Handling

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `stripQueryString` | `bool` | `false` | Strip the query before matching across frontend, GraphQL, the URL tester, and plugin integrations; when `false`, the query remains part of the matching input |
| `preserveQueryString` | `bool` | `false` | Append the incoming query after existing destination parameters and before any `#fragment`; applies after capture substitution across the same resolution paths |

## Redirect Response

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `setNoCacheHeaders` | `bool` | `true` | Set no-cache headers on redirect responses |
| `additionalHeaders` | `array` | `[]` | Additional HTTP headers to add to redirect responses |

## JSON API

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `apiEndpointEnabled` | `bool` | `false` | Enable the read-only JSON redirects endpoint at `/actions/redirect-manager/api/get-redirects` |
| `apiEndpointRateLimit` @since(5.35.0) | `int` | `60` | Maximum JSON API requests per minute for the configured token (max `100000`). Set to `0` to disable rate limiting |
| `apiEndpointToken` | `?string` | `null` | Token for the JSON endpoint. Falls back to `REDIRECT_MANAGER_API_TOKEN`; callers must send a bearer token or `X-Redirect-Manager-Key` header |

When enabled and token-configured, test the endpoint from **Redirect Manager → Settings → Test**. See [Testing tools](../resources/testing-tools.md) for the full Control Panel workflow and Postman download.

## Analytics

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableAnalytics` | `bool` | `true` | Master switch — controls IP tracking, device detection, geo detection |
| `anonymizeIpAddress` | `bool` | `false` | Anonymize IP addresses before hashing (subnet masking) |
| `ipHashSalt` | `?string` | `null` | IP hash salt. Falls back to `REDIRECT_MANAGER_IP_SALT` env var |
| `stripQueryStringFromStats` | `bool` | `true` | Strip query strings from analytics URLs (group by path) |
| `analyticsLimit` | `int` | `1000` | Target maximum number of unique 404 records after scheduled limit cleanup |
| `analyticsRetention` | `int` | `30` | Days to retain analytics by age (`0` = disable age-based deletion) |
| `autoTrimAnalytics` | `bool` | `true` | Enforce `analyticsLimit` during scheduled cleanup |

Retention and limit cleanup are independent. With `analyticsRetention` set to `0`, age-based deletion is disabled, but `autoTrimAnalytics` can still enforce `analyticsLimit`. Automatic cleanup runs through Craft's queue, so the table can temporarily exceed the limit until the scheduled job runs. Analytics recording itself remains immediate, including hit counts and request metadata.

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

Config-file cadence changes do not require a Control Panel save. When Redirect Manager next bootstraps, it reconciles the pending scheduled occurrence to the effective `backupSchedule`; an unchanged cadence keeps the existing occurrence, while a daily, weekly, or monthly change queues the new timing.

When `backupVolumeUid` resolves to a valid volume, it takes precedence over `backupPath`. Redirect Manager performs backup operations through the Craft volume, so the volume's configured subpath is honored for creation, listing, downloads, restores, deletion, and retention. New backups live beneath that subpath at `redirect-manager/backups`.

For compatibility, Redirect Manager also recognizes backups created by earlier versions at the exact filesystem-root prefix `redirect-manager/backups`. It does not scan arbitrary locations or move those backups automatically. Canonical backups beneath the current volume subpath take precedence when both locations contain the same backup name, and the Backups page shows which location owns each listed entry.

Craft Cloud's application filesystem is ephemeral. On Craft Cloud, neither `backupPath` nor a volume backed by Craft's local-filesystem interface is suitable for persistent backups; use a volume configured with the **Cloud** filesystem type. See Craft's [local filesystem guidance](https://craftcms.com/docs/cloud/assets.html#local).

The CP warning evaluates the effective settings after `config/redirect-manager.php` overrides. It appears on an ephemeral host for an intentional custom path or a valid local volume. A valid resolved non-local filesystem suppresses this warning only—it is not a compatibility endorsement for a third-party filesystem.

An explicitly configured missing, invalid, or unresolved volume instead shows a separate unavailable-volume error on durable and ephemeral hosts. Backup creation, listing, download, restore, deletion, and retention fail closed until the volume is restored or the effective `backupVolumeUid` is changed. Redirect Manager does not clear the UID or silently write to `backupPath` or `@storage`.

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
        // Leave unset to inherit from config/lindemannrock-base.php.
        // 'timeFormat' => '24',
        // 'monthFormat' => 'short',
        // 'dateOrder' => 'dmy',
        // 'dateSeparator' => '/',
        // 'showSeconds' => false,
        // 'defaultDateRange' => 'last7days',
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
