# Redirect Manager for Craft CMS

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-redirect-manager.svg)](https://packagist.org/packages/lindemannrock/craft-redirect-manager)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.0+-orange.svg)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net/)
[![Logging Library](https://img.shields.io/badge/Logging%20Library-5.0+-green.svg)](https://github.com/LindemannRock/craft-logging-library)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-redirect-manager.svg)](LICENSE)

Intelligent redirect management and 404 handling for Craft CMS.

## Features

- **Automatic 404 Handling** - Catches all 404s and attempts to redirect
- **Multiple Match Types**:
  - Exact Match - Case-insensitive exact URL matching
  - RegEx Match - Full regular expression support
  - Wildcard Match - Simple * wildcards
  - Prefix Match - URL starts with pattern
- **Statistics Tracking** - Track all 404s (handled and unhandled)
- **Auto-Redirect Creation** - Automatically creates redirects when entry URIs change
- **Smart Caching** - Fast redirect lookups with tag-based cache invalidation
- **CSV Export** - Export statistics for analysis
- **Multi-Site Support** - Site-specific or global redirects
- **Logging Integration** - Uses logging-library for consistent logs

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later
- [Logging Library](https://github.com/LindemannRock/craft-logging-library) 5.0 or greater (installed automatically as dependency)

## Installation

### Via Composer (Development)

Until published on Packagist, install directly from the repository:

```bash
cd /path/to/project
composer config repositories.craft-redirect-manager vcs https://github.com/LindemannRock/craft-redirect-manager
composer require lindemannrock/craft-redirect-manager:dev-main
./craft plugin/install redirect-manager
```

### Via Composer (Production - Coming Soon)

Once published on Packagist:

```bash
cd /path/to/project
composer require lindemannrock/craft-redirect-manager
./craft plugin/install redirect-manager
```

### Via Plugin Store (Future)

1. Go to the Plugin Store in your Craft control panel
2. Search for "Redirect Manager"
3. Click "Install"

## Configuration

### Config File

Copy `src/config.php` to `config/redirect-manager.php` and customize:

```php
<?php
return [
    // Auto create redirects when entry URIs change
    'autoCreateRedirects' => true,

    // Statistics retention in days (0 = keep forever)
    'statisticsRetention' => 30,

    // Maximum statistics records
    'statisticsLimit' => 1000,

    // Preserve query strings in redirects
    'preserveQueryString' => false,

    // Log level
    'logLevel' => 'error',
];
```

### Environment-Specific Config

```php
<?php
return [
    '*' => [
        'autoCreateRedirects' => true,
    ],
    'production' => [
        'logLevel' => 'error',
        'statisticsRetention' => 90,
    ],
    'dev' => [
        'logLevel' => 'debug',
        'statisticsRetention' => 7,
    ],
];
```

## Usage

### Creating Redirects

**Via Control Panel:**
1. Navigate to **Redirect Manager → Redirects**
2. Click **New redirect**
3. Fill in:
   - **Source URL**: `/old-page` or `/blog/*` or regex pattern
   - **Destination URL**: `/new-page` or absolute URL
   - **Match Type**: exact, regex, wildcard, or prefix
   - **Status Code**: 301 (permanent) or 302 (temporary)
4. Save

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
```

### Viewing Statistics

**Dashboard:**
- Navigate to **Redirect Manager → Statistics**
- View handled vs unhandled 404s
- See most common 404s
- Create redirects from unhandled 404s with one click

**Export CSV:**
```
Redirect Manager → Statistics → Export CSV
```

### Automatic Entry Redirects

When enabled (default), the plugin automatically creates redirects when:
- Entry slug changes: `/old-slug` → `/new-slug`
- Entry moves in structure: `/parent/child` → `/new-parent/child`

Disable in settings: **Redirect Manager → Settings → Auto Create Redirects**

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

# Check statistics:
# CP → Redirect Manager → Statistics
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

### 5. Test Statistics Cleanup

```php
# Run cleanup job manually:
php craft queue/run

# Or test the service directly:
php craft console/controller/eval \
  "echo lindemannrock\redirectmanager\RedirectManager::\$plugin->statistics->cleanupOldStatistics();"
```

## Permissions

- **View redirects** - View redirect list
- **Create redirects** - Create new redirects
- **Edit redirects** - Modify existing redirects
- **Delete redirects** - Remove redirects
- **View statistics** - Access 404 statistics
- **View logs** - Access plugin logs
- **Manage settings** - Change plugin settings

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

### Statistics Not Recording

1. Check **Settings → Statistics → Record Remote IP** is enabled
2. Check statistics limit hasn't been reached
3. Check database: `SELECT * FROM redirectmanager_statistics`

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

// Statistics
$plugin->statistics->record404($url, $handled);
$plugin->statistics->getAllStatistics($siteId, $limit);
$plugin->statistics->getChartData($siteId, $days);
$plugin->statistics->exportToCsv($siteId);

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
