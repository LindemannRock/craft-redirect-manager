# Logging

Redirect Manager writes structured, per-day log files through the bundled [Logging Library](https://github.com/LindemannRock/craft-logging-library).

> [!NOTE]
> Logging Library is included as a Composer dependency and downloaded automatically. Activate it in Craft to enable log viewing.

```bash title="PHP"
php craft plugin/install logging-library
```

```bash title="DDEV"
ddev craft plugin/install logging-library
```

Or via the Control Panel: **Settings → Plugins → Logging Library → Install**

Use this page when you need to check what Redirect Manager did: redirect saves, 404 matching, auto-redirects, analytics, imports, exports, backups, and debug-level diagnostics.

## Log levels

Four log levels are available, in order of verbosity:

| Level | What is logged |
|-------|----------------|
| `error` | Critical errors only |
| `warning` | Errors and warnings |
| `info` | General informational messages |
| `debug` | Detailed debugging, including timing and step-by-step diagnostics |

Each level includes all messages from the levels above it. `error` is the least verbose; `debug` is the most.

> [!WARNING]
> Debug level requires Craft's `devMode` to be enabled. If `logLevel` is set to `debug` while `devMode` is disabled, Redirect Manager falls back to `info` and records a warning. Use `debug` for local development or short diagnostic sessions, because it can create much more log output.

## Configuration

```php
// config/redirect-manager.php
return [
    'logLevel' => 'error', // 'error', 'warning', 'info', or 'debug'
];
```

For environment-specific logging, keep production quieter and enable debug only where Craft's `devMode` is enabled:

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

## Log file location

```text
storage/logs/redirect-manager-YYYY-MM-DD.log
```

Log files are rotated daily. Retention is managed by Logging Library, with a 30-day default.

Logs are written as structured JSON with context data alongside each message, so they can be searched in the Control Panel or ingested by external logging tools.

## Viewing logs in the CP

The **Redirect Manager → Logs** screen reads, filters, and downloads these log files without leaving the Control Panel.

![Redirect Manager log viewer in the Control Panel](images/logging-log-viewer.webp)

From there you can:

- Browse log entries for the current and recent days
- Filter by log level
- Search log messages and context
- View file sizes and entry counts
- Download individual log files for external analysis

The `redirectManager:viewSystemLogs` permission is required to access the Logs section. The `redirectManager:downloadSystemLogs` sub-permission is required to download log files. In the Craft permissions UI, both are nested under the `redirectManager:viewLogs` parent group.

## What gets logged

The level of detail depends on your configured `logLevel`.

### Error (`error`)

- Failed redirect saves or deletes
- Analytics recording failures
- Backup failures
- Import or export failures
- Database errors

### Warning (`warning`)

- Redirects that could not be matched due to malformed patterns
- Geo-detection provider errors (rate limiting, API key issues)
- Import rows that failed validation
- Debug fallback when `logLevel` is set to `debug` without `devMode`

### Info (`info`)

- Plugin events (redirects created, deleted, imported)
- Scheduled backup runs
- Analytics cleanup jobs
- Import and export runs
- Backup operations

### Debug (`debug`)

- Each incoming 404 URL and the matching result
- Cache hit/miss for redirect lookups
- Device detection parsing details
- Geo-detection lookup results
- Performance timing for redirect matching

## Permissions

| Action | Permission |
|--------|------------|
| Access the Logs section in the CP | `redirectManager:viewSystemLogs` |
| Download log files | `redirectManager:downloadSystemLogs` |
| Logs group (parent, Craft permissions UI only) | `redirectManager:viewLogs` |

See [Permissions](../developers/permissions.md) for the full permission hierarchy.
