![Redirect Manager](docs/images/hero.webp)

# Redirect Manager for Craft CMS

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-redirect-manager.svg)](https://packagist.org/packages/lindemannrock/craft-redirect-manager)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.10+-orange.svg)](https://craftcms.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net/)
[![Logging Library](https://img.shields.io/badge/Logging%20Library-5.13.1%2B-green.svg)](https://github.com/LindemannRock/craft-logging-library)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-redirect-manager.svg)](LICENSE)

Intelligent redirect management and 404 handling for Craft CMS.

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
- **GraphQL Support** — resolve redirects and list enabled redirects for headless/SPAs
- **Read-only JSON API** — expose enabled redirects to static builds, SPAs, edge workers, or backend integrations
- **In-CP API testing** — try the JSON API and download a Postman collection without leaving Craft
- **Plugin Integration** — pluggable architecture for other plugins to handle 404s
- **Privacy-First** — salted IP hashing, optional subnet masking, GDPR-friendly
- **Query String Control** — strip, preserve, or consolidate query strings
- **URL Filtering** — exclude bot/scanner/system URLs with regex, plus one-click recommended, WordPress, and security-probe presets

## Requirements

- Craft CMS 5.10+
- PHP 8.2+
- [Logging Library](https://github.com/LindemannRock/craft-logging-library) 5.13.1+ (installed automatically)

## Installation

### Composer

```bash
composer require lindemannrock/craft-redirect-manager && php craft plugin/install redirect-manager
```

### DDEV

```bash
ddev composer require lindemannrock/craft-redirect-manager && ddev craft plugin/install redirect-manager
```

### Post-install

Generate the IP hash salt used by privacy-conscious analytics:

```bash
php craft redirect-manager/security/generate-salt
```

```bash
ddev craft redirect-manager/security/generate-salt
```

## Documentation

Full documentation is available in the [docs](docs/) folder.

Postman collection setup notes are available in [postman/README.md](postman/README.md).

## Support

- **Issues**: [GitHub Issues](https://github.com/LindemannRock/craft-redirect-manager/issues)
- **Email**: [support@lindemannrock.com](mailto:support@lindemannrock.com)

## License

This plugin is licensed under the [Craft License](https://craftcms.github.io/license/). See [LICENSE.md](LICENSE.md) for details.

---

Developed by [LindemannRock](https://lindemannrock.com)
