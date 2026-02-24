# Installation & Setup

> [!NOTE]
> Redirect Manager is in active development and not yet available on the Craft Plugin Store. Install via Composer for now.

## Composer

Add the package to your project using Composer and the command line.

1. Open your terminal and go to your Craft project:

```bash
cd /path/to/project
```

2. Then tell Composer to require the plugin, and Craft to install it:

```bash title="Composer"
composer require lindemannrock/craft-redirect-manager && php craft plugin/install redirect-manager
```

```bash title="DDEV"
ddev composer require lindemannrock/craft-redirect-manager && ddev craft plugin/install redirect-manager
```

3. **Optional** — Install [Logging Library](https://github.com/LindemannRock/craft-logging-library) for log viewing:

```bash title="PHP"
php craft plugin/install logging-library
```

```bash title="DDEV"
ddev craft plugin/install logging-library
```

Or via the Control Panel: **Settings → Plugins → Logging Library → Install**

## Post-Install: Generate IP Hash Salt

After installation, generate the IP hash salt so analytics can properly track and anonymize visitors:

```bash title="PHP"
php craft redirect-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft redirect-manager/security/generate-salt
```

This command automatically adds `REDIRECT_MANAGER_IP_SALT` to your `.env` file. Copy this value to your staging and production `.env` files manually.

> [!TIP]
> Skipping this step won't break anything — redirects work normally without it. Analytics still tracks 404s, devices, and referrers, but IP hashing and geo-location won't be available. You can generate the salt later and full tracking resumes immediately.

## Copy Config File (Optional)

For advanced configuration, copy the config file to your project:

```bash
cp vendor/lindemannrock/craft-redirect-manager/src/config.php config/redirect-manager.php
```

This gives you full control over analytics, caching, geo-detection, and all plugin settings. See [Configuration](configuration.md) for details.

## Quick Start

See [Quickstart](quickstart.md) for the fastest path from install to first result.
