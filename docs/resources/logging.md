# Logging

Redirect Manager uses the [LindemannRock Logging Library](https://github.com/LindemannRock/craft-logging-library) for structured, dedicated plugin logs. Log files are separate from Craft's general log, making it easy to filter and download redirect-specific activity.

## Log Levels

Four log levels are available, in order of verbosity:

| Level | What Is Logged |
|-------|----------------|
| `error` | Critical errors only (default) |
| `warning` | Errors and warnings |
| `info` | General informational messages |
| `debug` | Detailed debugging including performance metrics |

Each level includes all messages from levels above it. `error` is the least verbose; `debug` is the most.

> [!WARNING]
> Debug level requires Craft's `devMode` to be enabled. If `logLevel` is set to `debug` but `devMode` is `false`, the plugin automatically falls back to `info`. Never set `logLevel` to `debug` in production.

## Configuration

```php
// config/redirect-manager.php
'logLevel' => 'error', // 'error', 'warning', 'info', 'debug'
```

### Environment-Specific Log Levels

```php
// config/redirect-manager.php
return [
    '*' => [
        'logLevel' => 'error',
    ],
    'production' => [
        'logLevel' => 'error',
    ],
    'staging' => [
        'logLevel' => 'warning',
    ],
    'dev' => [
        'logLevel' => 'debug',
    ],
];
```

## Log File Location

```
storage/logs/redirect-manager-YYYY-MM-DD.log
```

Log files are rotated daily. Retention is managed by the Logging Library (30 days by default).

Logs are written in structured JSON format with context data alongside each message. This makes them suitable for ingestion into log aggregators.

## Viewing Logs in the CP

Navigate to **Redirect Manager > Logs** to:

1. Browse log entries for the current and recent days
2. Filter by log level
3. Search log messages and context
4. View file sizes and entry counts per file
5. Download individual log files for external analysis

The `redirectManager:viewLogs` permission is required to access the Logs section. The `redirectManager:downloadLogs` sub-permission is required to download log files.

## What Gets Logged

The level of detail depends on your configured `logLevel`:

**Error (`error`):**
- Failed redirect saves or deletes
- Analytics recording failures
- Backup failures
- Database errors

**Warning (`warning`):**
- Redirects that could not be matched due to malformed patterns
- Geo-detection provider errors (rate limiting, API key issues)
- Import rows that failed validation

**Info (`info`):**
- Plugin events (redirects created, deleted, imported)
- Scheduled backup runs
- Analytics cleanup jobs

**Debug (`debug`):**
- Each incoming 404 URL and the matching result
- Cache hit/miss for redirect lookups
- Device detection parsing details
- Geo-detection lookup results
- Performance timing for redirect matching

## Disabling Debug in Production

Never leave `logLevel` set to `debug` in production. Debug logging writes a log entry for every 404 request, which on a busy site generates significant log volume. Use `error` or `warning` in production environments.

```php
'*' => [
    'logLevel' => 'error',
],
'dev' => [
    'logLevel' => 'debug',
],
```

## Permissions

| Action | Permission |
|--------|------------|
| View log entries in CP | `redirectManager:viewLogs` |
| Download log files | `redirectManager:downloadLogs` |

See [Permissions](../developers/permissions.md) for the full permission hierarchy.
