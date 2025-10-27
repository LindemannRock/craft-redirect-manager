# Redirect Manager Logging

Redirect Manager uses the [LindemannRock Logging Library](https://github.com/LindemannRock/craft-logging-library) for centralized, structured logging across all LindemannRock plugins.

## Log Levels

- **Error**: Critical errors only (default)
- **Warning**: Errors and warnings
- **Info**: General information
- **Debug**: Detailed debugging (includes performance metrics, requires devMode)

## Configuration

### Control Panel

1. Navigate to **Settings → Redirect Manager → General**
2. Scroll to **Logging Settings**
3. Select desired log level from dropdown
4. Click **Save**

### Config File

```php
// config/redirect-manager.php
return [
    'pluginName' => 'Redirects',  // Optional: Customize plugin name shown in logs interface
    'logLevel' => 'error',         // error, warning, info, or debug
];
```

**Notes:**
- The `pluginName` setting customizes how the plugin name appears in the log viewer interface (page title, breadcrumbs, etc.). If not set, it defaults to "Redirect Manager".
- Debug level requires Craft's `devMode` to be enabled. If set to debug with devMode disabled, it automatically falls back to info level.

## Log Files

- **Location**: `storage/logs/redirect-manager-YYYY-MM-DD.log`
- **Retention**: 30 days (automatic cleanup via Logging Library)
- **Format**: Structured JSON logs with context data
- **Web Interface**: View and filter logs in CP at Redirect Manager → Logs

## What's Logged

The plugin logs meaningful events using context arrays for structured data. All logs include user context when available.

### Redirects Service (RedirectsService)

#### 404 Handling
- **[DEBUG]** `Handling 404` - 404 error handling initiated
  - Context: `originalFullUrl`, `pathForMatching`, `userAgent`
- **[DEBUG]** `URL excluded from redirect handling` - URL matches exclusion pattern
  - Context: `url`
- **[DEBUG]** `Handling external 404` - External 404 handling for element
  - Context: `element` (element type), `url`
- **[INFO]** `External 404 matched redirect` - External 404 matched existing redirect
  - Context: `matchedUrl`, `redirectUrl`
- **[ERROR]** `Error getting URL from request` - Failed to parse request URL
  - Context: `error` (exception message)

#### Redirect Execution
- **[DEBUG]** `Executing redirect` - Redirect about to be executed
  - Context: `sourceUrl`, `destinationUrl`, `statusCode`, `redirect` (redirect details)

#### URI Change Tracking
- **[INFO]** `Stashed element URI from database` - Element URI stored for tracking
  - Context: `elementId`, `siteId`, `uri`
- **[DEBUG]** `No stashed URI found for element` - No previous URI stored
  - Context: `elementId`, `siteId`
- **[INFO]** `Checking URI change` - Checking if element URI changed
  - Context: `elementId`, `elementType`, `oldUri`, `newUri`, `siteId`
- **[DEBUG]** `Looking for recent redirect` - Searching for recent auto-created redirect
  - Context: `sourceUrl`, `timeWindow`
- **[DEBUG]** `Immediate undo check` - Checking for immediate URI change reversal
  - Context: `oldUri`, `newUri`, `recentRedirectId`, `recentSource`, `recentDestination`
- **[INFO]** `Immediate undo detected - cancelled redirect` - URI change reversed, redirect cancelled
  - Context: `cancelledRedirectId`, `oldUri`, `newUri`
- **[INFO]** `Deleted old auto-redirect for element` - Removed previous auto-redirect
  - Context: `deletedRedirectId`, `sourceUrl`, `oldDestinationUrl`
- **[ERROR]** `Cannot create redirect: would create circular loop` - Prevented circular redirect
  - Context: `from`, `to`, `reason`
- **[INFO]** `Auto-created redirect for entry URI change` - Auto-redirect created
  - Context: `id`, `sourceUrl`, `destinationUrl`, `elementId`, `siteId`
- **[DEBUG]** `URI did not change, skipping redirect creation` - No URI change detected
  - Context: `elementId`, `uri`, `siteId`

#### Redirect CRUD Operations
- **[WARNING]** `Redirect already exists` - Duplicate redirect detected
  - Context: `sourceUrl`
- **[ERROR]** `Failed to save redirect` - Redirect save validation failure
  - Context: `errors` (validation errors array)
- **[INFO]** `Redirect created` - New redirect created successfully
  - Context: `id`, `sourceUrl`
- **[ERROR]** `Redirect not found` - Redirect ID not found for update/delete
  - Context: `id`
- **[ERROR]** `Cannot update redirect: would create circular loop` - Update would create loop
  - Context: `from`, `to`, `reason`
- **[ERROR]** `Failed to update redirect` - Redirect update validation failure
  - Context: `id`, `errors` (validation errors array)
- **[INFO]** `Redirect updated` - Redirect updated successfully
  - Context: `id`
- **[ERROR]** `Failed to delete redirect` - Redirect deletion failed
  - Context: `id`
- **[INFO]** `Redirect deleted` - Redirect deleted successfully
  - Context: `id`

#### Redirect Caching
- **[DEBUG]** `Redirect cache hit` - Redirect found in cache
  - Context: `url`
- **[DEBUG]** `Redirect cached` - Redirect stored in cache
  - Context: `url`, `duration`
- **[DEBUG]** `Redirect caches invalidated` - Cache cleared
  - Context: `count` (number of caches cleared)

#### Pattern Matching & Validation
- **[ERROR]** `Invalid exclude pattern regex` - Exclusion pattern regex invalid
  - Context: `pattern`
- **[WARNING]** `Redirect loop detected` - Circular redirect chain detected
  - Context: `chain` (array of URLs in loop)
- **[INFO]** `Checking for next redirect in chain` - Resolving redirect chain
  - Context: `currentUrl`, `depth`
- **[INFO]** `No more redirects in chain` - End of redirect chain reached
  - Context: `stoppedAt`
- **[INFO]** `Found next redirect in chain` - Next redirect in chain found
  - Context: `from`, `to`, `depth`
- **[INFO]** `Resolved redirect chain` - Complete redirect chain resolved
  - Context: `startUrl`, `finalUrl`, `chainLength`, `redirects` (array of redirect IDs)
- **[WARNING]** `Circular redirect detected` - Circular redirect detected during validation
  - Context: `sourceUrl`, `destinationUrl`, `chain`
- **[ERROR]** `Failed to resolve redirect chain` - Error resolving redirect chain
  - Context: `error` (exception message)

### Analytics Service (AnalyticsService)

#### 404 Analytics
- **[ERROR]** `Failed to hash IP address` - IP hashing failed
  - Context: `error` (exception message)
- **[DEBUG]** `Updated 404 analytics record` - Existing 404 record updated
  - Context: `url`, `urlParsed`, `count`, `source` (source plugin)
- **[DEBUG]** `Created 404 analytics record` - New 404 record created
  - Context: `url`, `urlParsed`, `source` (source plugin)
- **[ERROR]** `Failed to save 404 analytics record` - Analytics save failed
  - Context: `errors` (validation errors array)

#### Analytics Management
- **[ERROR]** `Analytics record not found` - Analytics record ID not found
  - Context: `id`
- **[INFO]** `Analytics record deleted` - Analytics record deleted
  - Context: `id`
- **[INFO]** `Analytics cleared` - Analytics cleared for site
  - Context: `count`, `siteId`
- **[INFO]** `Trimmed analytics` - Analytics trimmed to limit
  - Context: `deleted` (number of records deleted)
- **[INFO]** `Cleaned up old analytics` - Old analytics cleaned up
  - Context: `deleted`, `retention` (retention days)

#### Geolocation
- **[WARNING]** `Failed to get location from IP` - IP geolocation lookup failed
  - Context: `error` (exception message)
- **[WARNING]** `IP hash salt not configured - IP tracking disabled` - Missing IP salt configuration
  - Context: `ip` (always 'hidden'), `saltValue` (NULL or unparsed string)

### Import/Export Controller (ImportExportController)

#### CSV Operations
- **[ERROR]** `Failed to parse CSV` - CSV parsing error
  - Context: `error` (exception message)
- **[ERROR]** `Failed to read CSV for mapping` - CSV read error during mapping
  - Context: `error` (exception message)
- **[ERROR]** `Failed to import redirect` - Individual redirect import failure
  - Context: `row` (row number), `error`, `sourceUrl`, `destinationUrl`

#### Backup Operations
- **[INFO]** `Backup created` - Backup created before import
  - Context: `path` (backup file path), `count` (number of redirects)
- **[ERROR]** `Backup failed` - Backup creation failed
  - Context: `error` (exception message)
- **[ERROR]** `Failed to log import history` - Import history logging failed
  - Context: `error` (exception message)
- **[INFO]** `Backup restored` - Backup restored successfully
  - Context: `id`, `count` (number of redirects restored)
- **[ERROR]** `Restore failed` - Backup restore failed
  - Context: `error` (exception message)
- **[INFO]** `Backup deleted` - Backup deleted successfully
  - Context: `id`
- **[ERROR]** `Delete backup failed` - Backup deletion failed
  - Context: `error` (exception message)

### Matching Service (MatchingService)

- **[ERROR]** `Invalid redirect regex` - Regex pattern validation failed
  - Context: `pattern`, `error` (exception message)
- **[ERROR]** `Invalid wildcard pattern` - Wildcard pattern validation failed
  - Context: `pattern`, `error` (exception message)

### Cleanup Analytics Job (CleanupAnalyticsJob)

- **[INFO]** `Analytics cleanup completed` - Scheduled analytics cleanup finished
  - Context: `deleted` (number of records deleted)

### Settings Model (Settings)

#### Log Level Adjustments
- **[WARNING]** `Log level "debug" from config file changed to "info" because devMode is disabled` - Debug level auto-corrected from config file
  - Context: `configFile` (path to config file)
- **[WARNING]** `Log level automatically changed from "debug" to "info" because devMode is disabled` - Debug level auto-corrected from database setting

#### Loading Operations
- **[ERROR]** `Failed to load settings from database` - Database query error
  - Context: `error` (exception message)

#### Save Operations
- **[ERROR]** `Settings validation failed` - Settings validation errors
  - Context: `errors` (validation errors array)
- **[DEBUG]** `Attempting to save settings` - Settings save operation initiated
  - Context: `attributes` (list of attribute names being saved)
- **[INFO]** `Settings saved successfully to database` - Settings saved
- **[ERROR]** `Database update returned false` - Database update operation returned false
- **[ERROR]** `Failed to save settings` - Settings save exception
  - Context: `error` (exception message)
- **[ERROR]** `Column check` - Database column existence check (debug logging)
  - Context: `columnExists` (boolean)

### Main Plugin (RedirectManager)

- **[INFO]** `Could not load settings from database` - Settings loading error during plugin initialization
  - Context: `error` (exception message)
- **[INFO]** `Scheduled initial analytics cleanup job` - Analytics cleanup job scheduled
  - Context: `interval` (cleanup interval)

## Log Management

### Via Control Panel

1. Navigate to **Redirect Manager → Logs**
2. Filter by date, level, or search terms
3. Download log files for external analysis
4. View file sizes and entry counts
5. Auto-cleanup after 30 days (configurable via Logging Library)

### Via Command Line

**View today's log**:

```bash
tail -f storage/logs/redirect-manager-$(date +%Y-%m-%d).log
```

**View specific date**:

```bash
cat storage/logs/redirect-manager-2025-01-15.log
```

**Search across all logs**:

```bash
grep "redirect" storage/logs/redirect-manager-*.log
```

**Filter by log level**:

```bash
grep "\[ERROR\]" storage/logs/redirect-manager-*.log
```

## Log Format

Each log entry follows structured JSON format with context data:

```json
{
  "timestamp": "2025-01-15 14:30:45",
  "level": "INFO",
  "message": "Redirect created",
  "context": {
    "id": 123,
    "sourceUrl": "/old-page",
    "userId": 1
  },
  "category": "lindemannrock\\redirectmanager\\services\\RedirectsService"
}
```

## Using the Logging Trait

All services and controllers in Redirect Manager use the `LoggingTrait` from the LindemannRock Logging Library:

```php
use lindemannrock\logginglibrary\traits\LoggingTrait;

class MyService extends Component
{
    use LoggingTrait;

    public function myMethod()
    {
        // Info level - general operations
        $this->logInfo('Operation started', ['param' => $value]);

        // Warning level - important but non-critical
        $this->logWarning('Missing data', ['key' => $missingKey]);

        // Error level - failures and exceptions
        $this->logError('Operation failed', ['error' => $e->getMessage()]);

        // Debug level - detailed information
        $this->logDebug('Processing item', ['item' => $itemData]);
    }
}
```

## Best Practices

### 1. DO NOT Log in init() ⚠️

The `init()` method is called on **every request** (every page load, AJAX call, etc.). Logging there will flood your logs with duplicate entries.

```php
// ❌ BAD - Causes log flooding
public function init(): void
{
    parent::init();
    $this->logInfo('Plugin initialized');  // Called on EVERY request!
}

// ✅ GOOD - Log actual operations
public function handleRedirect($sourceUrl): void
{
    $this->logInfo('Redirect executed', ['sourceUrl' => $sourceUrl]);
    // ... your logic
}
```

### 2. Always Use Context Arrays

Use the second parameter for variable data, not string concatenation:

```php
// ❌ BAD - Concatenating variables into message
$this->logError('Redirect failed: ' . $e->getMessage());
$this->logInfo('Processing URL: ' . $url);

// ✅ GOOD - Use context array for variables
$this->logError('Redirect failed', ['error' => $e->getMessage()]);
$this->logInfo('Processing URL', ['url' => $url]);
```

**Why Context Arrays Are Better:**
- Structured data for log analysis tools
- Easier to search and filter in log viewer
- Consistent formatting across all logs
- Automatic JSON encoding with UTF-8 support

### 3. Use Appropriate Log Levels

- **debug**: Internal state, variable dumps (requires devMode)
- **info**: Normal operations, user actions
- **warning**: Unexpected but handled situations
- **error**: Actual errors that prevent operation

### 4. Security

- Never log passwords or sensitive data
- Be careful with user input in log messages
- Never log API keys, tokens, or credentials
- IP addresses are hashed when IP tracking is enabled

## Performance Considerations

- **Error/Warning levels**: Minimal performance impact, suitable for production
- **Info level**: Moderate logging, useful for tracking operations
  - Logs redirect creation, updates, and deletion
  - Analytics management operations
  - Import/export operations
  - URI change tracking
- **Debug level**: Extensive logging, use only in development (requires devMode)
  - Logs every 404 handling attempt
  - Redirect cache operations
  - URI change detection details
  - Redirect chain resolution steps
  - Analytics record creation/updates

## Requirements

Redirect Manager logging requires:

- **lindemannrock/logginglibrary** plugin (installed automatically as dependency)
- Write permissions on `storage/logs` directory
- Craft CMS 5.x or later

## Troubleshooting

If logs aren't appearing:

1. **Check permissions**: Verify `storage/logs` directory is writable
2. **Verify library**: Ensure LindemannRock Logging Library is installed and enabled
3. **Check log level**: Confirm log level allows the messages you're looking for
4. **devMode for debug**: Debug level requires `devMode` enabled in `config/general.php`
5. **Check CP interface**: Use Redirect Manager → Logs to verify log files exist

## Common Scenarios

### Redirect Not Working

When redirects don't work as expected:

```bash
grep "404\|redirect" storage/logs/redirect-manager-*.log
```

Look for:

- `Handling 404` - Verify 404 handling is triggered
- `URL excluded from redirect handling` - Check if URL is excluded by pattern
- `Executing redirect` - Confirm redirect is being executed
- `Redirect loop detected` - Check for circular redirects
- `Failed to resolve redirect chain` - Chain resolution issues

Common causes:

- URL matches an exclusion pattern
- Redirect disabled or expired
- Circular redirect chain
- Cache issues (clear redirect caches)
- Invalid regex patterns

### Circular Redirect Issues

Debug circular redirect problems:

```bash
grep -i "circular\|loop" storage/logs/redirect-manager-*.log
```

Look for:

- `Redirect loop detected` - Shows the chain of URLs creating the loop
- `Circular redirect detected` - Validation prevented saving
- `Cannot create redirect: would create circular loop` - Auto-redirect prevention

When you see circular redirects:

- Review the `chain` context to see the URL loop
- Check if manual redirects conflict with auto-created redirects
- Verify destination URLs don't redirect back to source
- Use redirect chain visualization in CP

### Auto-Redirect Not Created

When URI changes don't create redirects:

```bash
grep "URI change\|Auto-created" storage/logs/redirect-manager-*.log
```

Look for:

- `Checking URI change` - Verify URI change was detected
- `URI did not change, skipping redirect creation` - No actual change
- `Auto-created redirect for entry URI change` - Successful creation
- `Cannot create redirect: would create circular loop` - Prevented by loop detection
- `Immediate undo detected - cancelled redirect` - URI change was reversed

If auto-redirects aren't created:

- Enable auto-redirect creation in settings
- Verify element type is enabled for auto-redirects
- Check if URI actually changed (not just saved)
- Look for circular redirect prevention messages
- Confirm no immediate undo occurred

### Analytics Not Tracking

Debug analytics tracking issues:

```bash
grep -i "analytics\|404" storage/logs/redirect-manager-*.log
```

Look for:

- `Created 404 analytics record` - New 404 tracked
- `Updated 404 analytics record` - Existing 404 incremented
- `Failed to save 404 analytics record` - Tracking failure
- `IP hash salt not configured` - IP tracking disabled
- `Failed to hash IP address` - IP hashing error

Common issues:

- IP hash salt not configured (run console command to generate)
- Analytics tracking disabled in settings
- Database write issues
- IP geolocation failures (expected for local development)

### Import/Export Failures

Track import/export operations:

```bash
grep -i "import\|export\|backup\|csv" storage/logs/redirect-manager-*.log
```

Look for:

- `Failed to parse CSV` - CSV format issues
- `Failed to import redirect` - Individual redirect import errors
- `Backup created` - Backup before import
- `Backup failed` - Backup creation error
- `Backup restored` - Successful restore
- `Restore failed` - Restore error

If imports fail:

- Check CSV format matches expected structure
- Review validation errors for specific redirects
- Verify backup creation succeeded before import
- Check for duplicate source URLs
- Ensure proper file encoding (UTF-8)

### Pattern Matching Issues

Debug regex and wildcard pattern problems:

```bash
grep -i "pattern\|regex\|wildcard" storage/logs/redirect-manager-*.log
```

Look for:

- `Invalid redirect regex` - Regex syntax error
- `Invalid wildcard pattern` - Wildcard pattern error
- `Invalid exclude pattern regex` - Exclusion pattern error

When patterns fail:

- Test regex syntax in an online regex tester
- Check for unescaped special characters
- Verify wildcard syntax (*, ?, etc.)
- Review pattern documentation in plugin docs

### Settings Save Issues

Monitor settings operations:

```bash
grep -i "settings" storage/logs/redirect-manager-*.log
```

Look for:

- `Attempting to save settings` - Save initiated
- `Settings saved successfully to database` - Successful save
- `Settings validation failed` - Validation errors
- `Failed to save settings` - Save exception

If settings fail to save:

- Check validation errors for specific fields
- Verify database connectivity
- Ensure database table exists (run migrations)
- Review config file overrides (may prevent saves)

## Development Tips

### Enable Debug Logging

For detailed troubleshooting during development:

```php
// config/redirect-manager.php
return [
    'dev' => [
        'logLevel' => 'debug',
    ],
];
```

This provides:

- Every 404 handling attempt with full context
- Redirect cache hit/miss information
- URI change detection details
- Redirect chain resolution steps
- Analytics record operations
- Exclusion pattern matching details

### Monitor Specific Operations

Track specific operations using grep:

```bash
# Monitor all redirect executions
grep "Executing redirect" storage/logs/redirect-manager-*.log

# Watch logs in real-time
tail -f storage/logs/redirect-manager-$(date +%Y-%m-%d).log

# Check all errors
grep "\[ERROR\]" storage/logs/redirect-manager-*.log

# Monitor 404 handling
grep "404" storage/logs/redirect-manager-*.log

# Track URI changes
grep "URI change" storage/logs/redirect-manager-*.log

# Monitor circular redirects
grep -i "circular\|loop" storage/logs/redirect-manager-*.log

# Watch analytics operations
grep -i "analytics" storage/logs/redirect-manager-*.log

# Track import operations
grep -i "import\|backup" storage/logs/redirect-manager-*.log
```

### Debug Redirect Chains

When troubleshooting redirect chains:

```bash
# Find all chain-related logs
grep -i "chain" storage/logs/redirect-manager-*.log

# Look for loop detection
grep "loop detected" storage/logs/redirect-manager-*.log
```

Review the context to see:

- Chain depth and URLs involved
- Where chains break or loop
- Final resolved destination
- Redirect IDs in the chain

### Performance Monitoring

Track redirect performance:

```bash
# Monitor cache operations
grep "cache" storage/logs/redirect-manager-*.log

# Check redirect creation frequency
grep "Redirect created" storage/logs/redirect-manager-*.log

# Track auto-redirect generation
grep "Auto-created redirect" storage/logs/redirect-manager-*.log
```

Enable debug mode to see:

- Cache hit rates
- Redirect lookup performance
- Chain resolution efficiency
- Pattern matching performance

### IP Tracking Configuration

Monitor IP tracking setup:

```bash
grep "IP hash salt" storage/logs/redirect-manager-*.log
```

If you see `IP hash salt not configured`:

1. Run: `php craft redirect-manager/security/generate-salt`
2. Salt will be added to `.env` file
3. IP tracking will be enabled automatically
4. Check logs again to verify tracking works

**Note**: IP hashing is used for privacy - actual IP addresses are never logged.
