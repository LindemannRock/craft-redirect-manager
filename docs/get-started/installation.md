# Installation and setup

## Composer

Add the package to your project using Composer and the command line.

1. Open your terminal and go to your Craft project:

```bash title="Terminal"
cd /path/to/project
```

2. Then tell Composer to require the plugin, and Craft to install it:

```bash title="Composer"
composer require lindemannrock/craft-redirect-manager && php craft plugin/install redirect-manager
```

```bash title="DDEV"
ddev composer require lindemannrock/craft-redirect-manager && ddev craft plugin/install redirect-manager
```

3. **Optional** — Enable [Logging Library](https://github.com/LindemannRock/craft-logging-library) for log viewing:

> [!NOTE]
> Logging Library is included as a Composer dependency and downloaded automatically. Activate it in Craft to enable log viewing.

```bash title="PHP"
php craft plugin/install logging-library
```

```bash title="DDEV"
ddev craft plugin/install logging-library
```

Or via the Control Panel: **Settings → Plugins → Logging Library → Install**

## Post-Install Setup

After installing, open **Redirect Manager → Setup** in the Control Panel. The Setup page is a short readiness checklist that confirms the plugin is configured before you rely on analytics. It shows a **Setup** badge while a step is outstanding and a **Ready** badge once everything is in place.

Until setup is complete, every Redirect Manager screen shows a **Setup incomplete** notice with an **Open setup** button — that's expected, not an error. The notice disappears everywhere at once once the checklist is finished.

The only required step is the IP hash salt below — redirect matching works without it, but 404 analytics tracking waits until the salt is set.

### Generate an IP hash salt

Generate a secure salt for analytics privacy and unique visitor tracking:

```bash title="PHP"
php craft redirect-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft redirect-manager/security/generate-salt
```

This writes `REDIRECT_MANAGER_IP_SALT` to your `.env` file. Keep the same salt across all environments — changing it resets unique visitor tracking.

### Review configuration

See [Configuration](configuration.md) for all available settings. Most can be managed from **Redirect Manager → Settings** without a config file.

## Quick start

See [Quickstart](quickstart.md) for the fastest path from install to first result.
