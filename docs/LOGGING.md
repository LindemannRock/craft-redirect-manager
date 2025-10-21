# Smart Links Logging

Smart Links uses the [LindemannRock Logging Library](https://github.com/LindemannRock/craft-logging-library) for centralized, structured logging across all LindemannRock plugins.

## Log Levels

- **Error**: Critical errors only (default)
- **Warning**: Errors and warnings
- **Info**: General information
- **Debug**: Detailed debugging (includes performance metrics, requires devMode)

## Configuration

### Control Panel

1. Navigate to **Settings → Smart Links → General**
2. Scroll to **Logging Settings**
3. Select desired log level from dropdown
4. Click **Save**

### Config File

```php
// config/smart-links.php
return [
    'pluginName' => 'Links',     // Optional: Customize plugin name shown in logs interface
    'logLevel' => 'error',       // error, warning, info, or debug
];
```

**Notes:**
- The `pluginName` setting customizes how the plugin name appears in the log viewer interface (page title, breadcrumbs, etc.). If not set, it defaults to "Smart Links".
- Debug level requires Craft's `devMode` to be enabled. If set to debug with devMode disabled, it automatically falls back to info level.

## Log Files

- **Location**: `storage/logs/smart-links-YYYY-MM-DD.log`
- **Retention**: 30 days (automatic cleanup via Logging Library)
- **Format**: Structured JSON logs with context data
- **Web Interface**: View and filter logs in CP at Smart Links → Logs

## What's Logged

The plugin logs meaningful events using context arrays for structured data. All logs include user context when available.

### Analytics Operations (AnalyticsController)

- **[INFO]** `Analytics getData called` - Analytics data retrieval
  - Context: `type`, `dateRange`, `smartLinkId`
- **[ERROR]** `Analytics getData error` - Analytics query failure
  - Context: `error` (exception message), `trace` (stack trace)
- **[ERROR]** `Failed to get analytics data` - Analytics fetch error
  - Context: `error` (exception message)

### Smart Link Operations (SmartLinksController)

- **[ERROR]** `Smart link save failed` - Smart link validation errors
  - Context: `errors` (validation errors array)
- **[ERROR]** `Smart link save error` - Smart link save exception
  - Context: `error` (exception message), `trace` (stack trace)

### QR Code Operations (QrCodeController)

#### Display Operations
- **[INFO]** `SmartLink redirect URL (display)` - Redirect URL for QR display
  - Context: `url`
- **[INFO]** `Full URL for QR` - Full URL including domain
  - Context: `fullUrl`
- **[ERROR]** `Failed to generate QR code` - QR code generation failure (display)
  - Context: `error` (exception message)

#### Generation Operations
- **[INFO]** `SmartLink redirect URL (generate)` - Redirect URL for QR generation
  - Context: `url`
- **[INFO]** `Full URL for QR` - Full URL for QR code
  - Context: `fullUrl`
- **[ERROR]** `Failed to generate QR code` - QR code generation failure (generate)
  - Context: `error` (exception message)

### QR Code Service (QrCodeService)

- **[ERROR]** `Failed to add logo to QR code` - Logo overlay failure
  - Context: `error` (exception message)

### Analytics Service (AnalyticsService)

#### Date Filtering
- **[INFO]** `Today filter` - Today's date range filter applied
  - Context: `start` (start date), `end` (end date)

#### Save Operations
- **[INFO]** `saveAnalytics called` - Analytics save initiated
  - Context: `linkId`
- **[ERROR]** `Failed to save analytics` - Analytics save failure
  - Context: `error` (exception message), `data` (analytics data), `trace` (stack trace)

#### Geolocation
- **[WARNING]** `Failed to get location from IP` - IP geolocation lookup failed
  - Context: `error` (exception message)

### Smart Links Service (SmartLinksService)

- **[INFO]** `Smart link not saved due to validation errors` - Validation failure notice

## Log Management

### Via Control Panel

1. Navigate to **Smart Links → Logs**
2. Filter by date, level, or search terms
3. Download log files for external analysis
4. View file sizes and entry counts
5. Auto-cleanup after 30 days (configurable via Logging Library)

### Via Command Line

**View today's log**:

```bash
tail -f storage/logs/smart-links-$(date +%Y-%m-%d).log
```

**View specific date**:

```bash
cat storage/logs/smart-links-2025-01-15.log
```

**Search across all logs**:

```bash
grep "QR code" storage/logs/smart-links-*.log
```

**Filter by log level**:

```bash
grep "\[ERROR\]" storage/logs/smart-links-*.log
```

## Log Format

Each log entry follows structured JSON format with context data:

```json
{
  "timestamp": "2025-01-15 14:30:45",
  "level": "INFO",
  "message": "Analytics getData called",
  "context": {
    "type": "clicks",
    "dateRange": "7days",
    "smartLinkId": 123,
    "userId": 1
  },
  "category": "lindemannrock\\smartlinks\\controllers\\AnalyticsController"
}
```

## Using the Logging Trait

All services and controllers in Smart Links use the `LoggingTrait` from the LindemannRock Logging Library:

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
public function handleRedirect($slug): void
{
    $this->logInfo('Smart link redirect processed', ['slug' => $slug]);
    // ... your logic
}
```

### 2. Always Use Context Arrays

Use the second parameter for variable data, not string concatenation:

```php
// ❌ BAD - Concatenating variables into message
$this->logError('QR generation failed: ' . $e->getMessage());
$this->logInfo('Processing link: ' . $slug);

// ✅ GOOD - Use context array for variables
$this->logError('QR generation failed', ['error' => $e->getMessage()]);
$this->logInfo('Processing link', ['slug' => $slug]);
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

## Performance Considerations

- **Error/Warning levels**: Minimal performance impact, suitable for production
- **Info level**: Moderate logging, useful for tracking operations
- **Debug level**: Extensive logging, use only in development (requires devMode)
  - Includes performance metrics
  - Logs detailed analytics data
  - Tracks redirect operations
  - Records device detection details

## Requirements

Smart Links logging requires:

- **lindemannrock/logginglibrary** plugin (installed automatically as dependency)
- Write permissions on `storage/logs` directory
- Craft CMS 5.x or later

## Troubleshooting

If logs aren't appearing:

1. **Check permissions**: Verify `storage/logs` directory is writable
2. **Verify library**: Ensure LindemannRock Logging Library is installed and enabled
3. **Check log level**: Confirm log level allows the messages you're looking for
4. **devMode for debug**: Debug level requires `devMode` enabled in `config/general.php`
5. **Check CP interface**: Use Smart Links → Logs to verify log files exist

## Common Scenarios

### QR Code Generation Issues

When QR codes fail to generate, check for:

```bash
grep "Failed to generate QR code" storage/logs/smart-links-*.log
```

Look for:
- Missing GD or ImageMagick library
- File permission issues
- Invalid QR code parameters
- Logo overlay failures

### Analytics Tracking Problems

Debug analytics issues:

```bash
grep "analytics" storage/logs/smart-links-*.log
```

Common issues:
- `Failed to save analytics` - Check database connectivity
- `Failed to get location from IP` - IP geolocation service issues
- `Analytics getData error` - Query or date range problems

### Smart Link Save Failures

Track smart link save issues:

```bash
grep "Smart link save" storage/logs/smart-links-*.log
```

Review validation errors in the context to identify:
- Missing required fields
- Invalid URLs
- Slug conflicts
- Database constraints

### Geolocation Issues

Monitor IP geolocation lookups:

```bash
grep "location from IP" storage/logs/smart-links-*.log
```

**Note**: In local development (DDEV, localhost), geolocation will fail because local IPs cannot be resolved. This is expected behavior. Set default location via environment variables:

```bash
SMART_LINKS_DEFAULT_COUNTRY=AE
SMART_LINKS_DEFAULT_CITY=Dubai
```

## Development Tips

### Enable Debug Logging

For detailed troubleshooting during development:

```php
// config/smart-links.php
return [
    'dev' => [
        'logLevel' => 'debug',
    ],
];
```

This provides:
- Detailed redirect URL calculations
- Full analytics data payloads
- Device detection details
- Request headers and parameters

### Monitor Specific Operations

Track specific operations using grep:

```bash
# Monitor all QR code operations
grep "QR" storage/logs/smart-links-*.log

# Watch analytics in real-time
tail -f storage/logs/smart-links-$(date +%Y-%m-%d).log | grep "analytics"

# Check all errors
grep "\[ERROR\]" storage/logs/smart-links-*.log
```
