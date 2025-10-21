# Smart Links Configuration

## Configuration File

You can override plugin settings by creating a `smart-links.php` file in your `config/` directory.

### Basic Setup

1. Copy `vendor/lindemannrock/smart-links/src/config.php` to `config/smart-links.php`
2. Modify the settings as needed

### Available Settings

```php
<?php
return [
    // Plugin settings
    'pluginName' => 'Smart Links',

    // Logging settings
    'logLevel' => 'error', // error, warning, info, or debug

    // Analytics configuration
    'enableAnalytics' => true,
    'analyticsRetention' => 90, // days (0 = unlimited, max 3650)
    'includeDisabledInExport' => false,
    'includeExpiredInExport' => false,

    // QR Code defaults
    'defaultQrSize' => 256, // pixels (100-1000)
    'defaultQrColor' => '#000000',
    'defaultQrBgColor' => '#FFFFFF',
    'defaultQrFormat' => 'png', // png or svg
    'defaultQrErrorCorrection' => 'M', // L, M, Q, H
    'defaultQrMargin' => 4, // quiet zone (0-10)
    'qrModuleStyle' => 'square', // square, rounded, dots
    'qrEyeStyle' => 'square', // square, rounded, leaf
    'qrEyeColor' => null, // null = same as module color
    'qrCodeCacheDuration' => 86400, // 24 hours

    // QR Code logo overlay
    'enableQrLogo' => false,
    'qrLogoVolumeUid' => null, // Asset volume UID for logos
    'defaultQrLogoId' => null, // Default logo asset ID
    'qrLogoSize' => 20, // Logo size percentage (10-30)

    // QR Code downloads
    'enableQrDownload' => true,
    'qrDownloadFilename' => '{slug}-qr-{size}', // Filename pattern

    // Image management
    'imageVolumeUid' => null, // Asset volume UID for smart link images

    // Redirect settings
    'redirectTemplate' => null, // Custom redirect template path
    'notFoundRedirectUrl' => '/', // 404 redirect URL

    // Geographic detection
    'enableGeoDetection' => false,

    // Device detection caching
    'cacheDeviceDetection' => true,
    'deviceDetectionCacheDuration' => 3600, // 1 hour

    // Language detection
    'languageDetectionMethod' => 'browser', // browser, ip, or both

    // Interface settings
    'itemsPerPage' => 100, // Items per page in CP (10-500)
];
```

### Multi-Environment Configuration

You can have different settings per environment:

```php
<?php
return [
    // Global settings
    '*' => [
        'qrCodeSize' => 300,
        'qrCodeForegroundColor' => '#000000',
        'logLevel' => 'error',
    ],

    // Development environment
    'dev' => [
        'enableAnalytics' => false,
        'cacheEnabled' => false,
        'logLevel' => 'debug', // Detailed logging in dev
    ],

    // Staging environment
    'staging' => [
        'enableAnalytics' => true,
        'analyticsRetentionDays' => 30,
        'logLevel' => 'info',
    ],

    // Production environment
    'production' => [
        'enableAnalytics' => true,
        'analyticsRetentionDays' => 180,
        'cacheEnabled' => true,
        'cacheDuration' => 7200,
        'logLevel' => 'warning', // Only warnings and errors in production
    ],
];
```

### Using Environment Variables

All settings support environment variables:

```php
return [
    'enableAnalytics' => getenv('SMART_LINKS_ANALYTICS') === 'true',
    'analyticsRetentionDays' => (int)getenv('SMART_LINKS_RETENTION') ?: 90,
    'qrCodeSize' => (int)getenv('QR_CODE_SIZE') ?: 300,
];
```

### Setting Descriptions

#### Plugin Settings

- **pluginName**: Display name for the plugin in Craft CP navigation

#### Logging Settings

- **logLevel**: What types of messages to log ('debug', 'info', 'warning', 'error')
  - **error**: Critical errors only (default, production recommended)
  - **warning**: Errors and warnings
  - **info**: General information and successful operations
  - **debug**: Detailed debugging information (development only, requires devMode)

#### Analytics Settings

- **enableAnalytics**: Enable/disable click tracking and analytics
- **analyticsRetention**: How many days to keep analytics data (0-3650, 0 = unlimited)
- **includeDisabledInExport**: Include disabled smart links in CSV exports
- **includeExpiredInExport**: Include expired smart links in CSV exports

#### QR Code Settings

- **defaultQrSize**: Default size in pixels for generated QR codes (100-1000)
- **defaultQrColor**: Hex color for QR code foreground
- **defaultQrBgColor**: Hex color for QR code background
- **defaultQrFormat**: Output format (png or svg)
- **defaultQrErrorCorrection**: Error correction level
  - `L` - ~7% correction
  - `M` - ~15% correction (default)
  - `Q` - ~25% correction
  - `H` - ~30% correction
- **defaultQrMargin**: White space margin around QR code (0-10)
- **qrModuleStyle**: QR code module style (square, rounded, dots)
- **qrEyeStyle**: QR code eye/finder pattern style (square, rounded, leaf)
- **qrEyeColor**: Custom color for eye patterns (null = same as module color)
- **qrCodeCacheDuration**: QR code cache duration in seconds

#### QR Code Logo Settings

- **enableQrLogo**: Enable logo overlay on QR codes
- **qrLogoVolumeUid**: Asset volume UID for logo selection (null = all volumes)
- **defaultQrLogoId**: Default logo asset ID (required when enableQrLogo is true)
- **qrLogoSize**: Logo size as percentage of QR code (10-30)

#### QR Code Download Settings

- **enableQrDownload**: Enable QR code download functionality
- **qrDownloadFilename**: Filename pattern for downloads (supports {slug} and {size})

#### Asset Management Settings

- **imageVolumeUid**: Asset volume UID for smart link images (null = all volumes)

#### Redirect Settings

- **redirectTemplate**: Path to custom redirect template (null = use default)
- **notFoundRedirectUrl**: URL to redirect when smart link not found

#### Geographic Settings

- **enableGeoDetection**: Enable geographic detection for analytics

#### Caching Settings

- **cacheDeviceDetection**: Enable/disable caching of device detection results
- **deviceDetectionCacheDuration**: Device detection cache duration in seconds

#### Language Detection Settings

- **languageDetectionMethod**: Method for detecting user language
  - `browser` - Use Accept-Language header (default)
  - `ip` - Use IP-based detection
  - `both` - Combine browser and IP detection

#### Interface Settings

- **itemsPerPage**: Number of items per page in CP element index (10-500)

### Precedence

Settings are loaded in this order (later overrides earlier):

1. Default plugin settings
2. Database-stored settings (from CP)
3. Config file settings
4. Environment-specific config settings

**Note:** Config file settings always override database settings, making them ideal for production environments where you want to enforce specific values.

## Read-Only Mode & Production Environments

Smart Links fully supports Craft's `allowAdminChanges` setting for production deployments.

### Enabling Read-Only Mode

Add to your `.env` file:

```bash
CRAFT_ALLOW_ADMIN_CHANGES=false
```

### What Happens in Read-Only Mode

When `allowAdminChanges` is disabled:

1. **Settings Pages** - Display with a read-only notice banner
2. **Form Fields** - All inputs are disabled (can view but not edit)
3. **Field Layout Designer** - Completely disabled, no drag-and-drop
4. **Save Actions** - Return 403 Forbidden HTTP errors
5. **Config Overrides** - Config file settings remain the source of truth

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

Use `config/smart-links.php` to manage settings:

```php
<?php
return [
    'production' => [
        'enableAnalytics' => true,
        'analyticsRetention' => 90,
        'defaultQrSize' => 256,
        'slugPrefix' => 'go',
        // ... other production settings
    ],
];
```

### Field Layout Management

Field layouts are stored in project config and sync across environments:

- **Location:** `config/project/smart-links/fieldLayouts/{uid}.yaml`
- **Syncing:** Automatically applied when project config is synced
- **Read-Only:** Cannot be modified in CP when `allowAdminChanges=false`

To modify field layouts in production:
1. Make changes in development environment
2. Commit the updated YAML files in `config/project/`
3. Deploy to production
4. Run `php craft project-config/apply` if needed

### Performance Recommendations

For production environments:

```php
'production' => [
    'enableAnalytics' => true,
    'analyticsRetentionDays' => 90, // Balance data vs storage
    'cacheEnabled' => true,
    'cacheDuration' => 7200, // 2 hours
    'trackBotClicks' => false, // Reduce noise in analytics
],
```

### Security Recommendations

```php
// Restrict QR code generation
'qrCodeSize' => min((int)getenv('QR_CODE_SIZE') ?: 300, 1000), // Max 1000px
'qrCodeFormat' => 'png', // PNG is safer than SVG for user input
```