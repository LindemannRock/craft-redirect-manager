# Analytics @since(5.1.0)

Redirect Manager tracks every 404 that hits your site — whether it was handled by a redirect or went unmatched. The analytics dashboard gives you device breakdowns, geographic data, bot identification, and charts over time. Unhandled 404s can be turned into redirects with a single click.

## What Gets Tracked

Every 404 event records:

| Data | Description |
|------|-------------|
| URL | The path (and optionally query string) that returned 404 |
| Handled | Whether a matching redirect was found and fired |
| Source plugin | Which plugin reported the 404 (e.g., `redirect-manager`, `shortlink-manager`) |
| Device type | Desktop, mobile, or tablet (via Matomo DeviceDetector) |
| Browser | Browser name and version |
| OS | Operating system name |
| Is bot | Whether the visitor was identified as a bot |
| Country | Visitor's country (when geo-detection is enabled) |
| City | Visitor's city (when geo-detection is enabled) |
| IP hash | Salted SHA256 hash — original IP is never stored |

## Enabling Analytics

Analytics is controlled by a master switch:

```php
// config/redirect-manager.php
'enableAnalytics' => true,
```

When disabled, no 404 data is recorded and the Analytics CP section is hidden. Device detection, geo detection, and IP hashing are all subject to this master switch.

## The Analytics Dashboard

Navigate to **Redirect Manager > Analytics** to see:

### 404 Overview

Charts showing 404 volume over time, split by handled vs. unhandled. Use the date range filter to zoom in or compare periods.

### Most Common 404s

A table of the top 404 URLs ranked by hit count. Each row shows:
- The URL
- Total hit count
- Whether it was handled (redirected) or unhandled
- Last seen timestamp
- A "Create Redirect" action button for unhandled entries

### Recent 404s

The most recent 404 events in reverse chronological order. Useful for catching new broken links quickly.

### Device & Browser Breakdown

Bar charts showing the distribution of device types, browsers, and operating systems across all tracked 404s.

### Geographic Breakdown

Country and city distribution charts. Only shown when `enableGeoDetection` is `true`.

### Bot Traffic

A separate view of bot-originated 404s. Bots are identified using Matomo DeviceDetector and are flagged in the data so you can exclude them from human traffic analysis.

## Auto-Refresh

The dashboard can refresh automatically at a configurable interval:

```php
'refreshIntervalSecs' => 30, // 5, 15, 30, or 60 seconds; null = disabled
```

When a user interacts with the page (hover, click, scroll), auto-refresh pauses to avoid disrupting their workflow. It resumes when interaction stops.

## Creating Redirects from 404s

The most common action in the analytics dashboard is fixing unhandled 404s. Each unhandled URL in the "Most Common 404s" table has a **Create Redirect** button. Clicking it opens the new redirect form pre-filled with the 404 URL as the source — just add a destination and save.

## Device Detection

Device detection is powered by [Matomo DeviceDetector](https://github.com/matomo-org/device-detector) @since(5.14.0). It identifies device type, browser name, browser version, OS, and bot status from the user-agent string.

Detection results are cached to avoid re-parsing the same user-agent repeatedly:

```php
'cacheDeviceDetection'          => true,
'deviceDetectionCacheDuration'  => 3600, // seconds
```

## Geographic Detection

Geographic detection is disabled by default. Enable it and configure a provider:

```php
'enableGeoDetection' => true,
'geoProvider'        => 'ip-api.com',  // 'ip-api.com', 'ipapi.co', 'ipinfo.io'
'geoApiKey'          => \craft\helpers\App::env('REDIRECT_MANAGER_GEO_API_KEY'),
```

The `geoApiKey` enables HTTPS for `ip-api.com` and unlocks higher rate limits on all providers.

### Local Development Override

Private IP addresses (127.0.0.1, 192.168.x.x, 10.x.x.x) cannot be geolocated. In development, set a default location to get realistic data:

```php
// config/redirect-manager.php
'defaultCountry' => 'US',
'defaultCity'    => 'New York',
```

Alternatively, use environment variables:

```bash
# .env
REDIRECT_MANAGER_DEFAULT_COUNTRY=US
REDIRECT_MANAGER_DEFAULT_CITY=New York
```

These settings only affect private/local IPs. In production, real visitor IPs use actual geolocation.

**Supported default locations include:** US (New York, Los Angeles, Chicago, San Francisco), GB (London, Manchester), DE (Berlin, Munich), FR (Paris), CA (Toronto, Vancouver), AU (Sydney, Melbourne), JP (Tokyo), SG (Singapore), IN (Mumbai, Delhi), AE (Dubai — the hardcoded fallback when no default is configured).

## Privacy

Redirect Manager is designed to be GDPR-friendly:

- **IP hashing**: IPs are never stored. A salted SHA256 hash is stored instead. Original IPs are unrecoverable.
- **IP hash salt**: Must be generated explicitly with `php craft redirect-manager/security/generate-salt`. Without a salt, an error banner appears in settings.
- **Subnet masking**: Enable `anonymizeIpAddress` to zero out the last IP octet before hashing (e.g., `192.168.1.123` becomes `192.168.1.0`).
- **Geo first, hash after**: When geo-detection is enabled, the country/city is extracted from the real IP, then the original IP is discarded before hashing.

```php
'anonymizeIpAddress' => false, // set true for extra anonymization
'ipHashSalt'         => \craft\helpers\App::env('REDIRECT_MANAGER_IP_SALT'),
```

> [!WARNING]
> Changing the IP hash salt in production will break unique visitor deduplication. All historical hashes will no longer match new hashes from the same IPs.

## Retention and Cleanup

Analytics records are automatically trimmed based on these settings:

```php
'analyticsRetention' => 30,   // Days to keep records (0 = keep forever)
'analyticsLimit'     => 1000, // Max unique 404 URL records
'autoTrimAnalytics'  => true, // Run cleanup automatically
```

When `autoTrimAnalytics` is `true`, cleanup runs as a queue job. To run it manually:

```bash
php craft queue/run
```

## Exporting Analytics

Export 404 analytics as CSV from **Redirect Manager > Analytics > Export CSV**. The export includes all tracked fields: URL, hit count, handled status, device, browser, OS, country, city, and timestamps.

The `redirectManager:exportAnalytics` permission is required to access the export button.

## Analytics Services

The `AnalyticsService` @since(5.7.0) is a facade that delegates to four focused sub-services:

| Sub-service | Responsibility |
|-------------|----------------|
| `AnalyticsQueryService` | Querying and filtering analytics records |
| `AnalyticsTrackingService` | Recording 404 events |
| `AnalyticsBreakdownService` | Computing device, browser, OS, geo breakdowns |
| `AnalyticsExportService` | Generating CSV export data |
