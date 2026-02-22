# Redirect Manager for Craft CMS

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-redirect-manager.svg)](https://packagist.org/packages/lindemannrock/craft-redirect-manager)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.0+-orange.svg)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net/)
[![Logging Library](https://img.shields.io/badge/Logging%20Library-5.0+-green.svg)](https://github.com/LindemannRock/craft-logging-library)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-redirect-manager.svg)](LICENSE)

Intelligent redirect management and 404 handling for Craft CMS.

## License

This is a commercial plugin licensed under the [Craft License](https://craftcms.github.io/license/). It will be available on the [Craft Plugin Store](https://plugins.craftcms.com) soon. See [LICENSE.md](LICENSE.md) for details.

## ⚠️ Pre-Release

This plugin is in active development and not yet available on the Craft Plugin Store. Features and APIs may change before the initial public release.

## Features

- **Automatic 404 Handling** — catches all 404s and attempts to redirect
- **Multiple Match Types** — exact, contains, regex, wildcard, prefix
- **Auto-Redirect Creation** — creates redirects when entry URIs change with undo detection
- **Rich Analytics** — track 404s with device, browser, OS, geographic data, and bot detection
- **Geographic Detection** — visitor location via ip-api.com, ipapi.co, or ipinfo.io
- **CSV Import/Export** — import redirects (up to 4000 rows) and export analytics
- **Backup System** — automatic/scheduled backups with retention and restore
- **Smart Caching** — file or Redis caching for fast redirect lookups
- **Multi-Site Support** — site-specific or global redirects
- **Plugin Integration** — pluggable architecture for other plugins to handle 404s
- **Privacy-First** — salted IP hashing, optional subnet masking, GDPR-friendly
- **Query String Control** — strip, preserve, or consolidate query strings

## Requirements

- Craft CMS 5.0+
- PHP 8.2+
- [Logging Library](https://github.com/LindemannRock/craft-logging-library) 5.0+ (installed automatically)
- [Matomo Device Detector](https://github.com/matomo-org/device-detector) 6.4+ (installed automatically)

## Installation

### Via Composer

```bash
composer require lindemannrock/craft-redirect-manager
```

```bash
php craft plugin/install redirect-manager
```

```bash
php craft redirect-manager/security/generate-salt
```

### Using DDEV

```bash
ddev composer require lindemannrock/craft-redirect-manager
```

```bash
ddev craft plugin/install redirect-manager
```

```bash
ddev craft redirect-manager/security/generate-salt
```

## Documentation

Full documentation is available in the [docs](docs/) folder.

## Support

- **Issues**: [GitHub Issues](https://github.com/LindemannRock/craft-redirect-manager/issues)
- **Email**: [support@lindemannrock.com](mailto:support@lindemannrock.com)

## License

This plugin is licensed under the [Craft License](https://craftcms.github.io/license/). See [LICENSE.md](LICENSE.md) for details.

---

Developed by [LindemannRock](https://lindemannrock.com)
