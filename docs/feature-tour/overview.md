# Features Overview

Redirect Manager is a comprehensive redirect and 404 management plugin for Craft CMS. It catches 404s, routes them to matching redirects, tracks unhandled misses, and gives you the analytics to act on them.

> [!TIP]
> New to Redirect Manager? Start with the [Quickstart](../get-started/quickstart.md) guide to get your first redirect in place within minutes.

## What It Does

At its core, Redirect Manager intercepts every 404 response on your site, checks it against a library of redirect rules, and either fires the redirect or records the miss as an analytics entry. Every 404 — whether it came from a renamed entry, a deleted page, or an external link pointing at the wrong URL — is captured and actionable.

## Core Capabilities

- **[Redirects](redirects.md)** — Create and manage redirects with five match types: exact, contains, regex, wildcard, and prefix. Assign priority to control which rule wins when multiple patterns match. Supports all common status codes (301, 302, 303, 307, 308, 410) and full multi-site configuration.

- **[Auto-Redirects](auto-redirects.md)** — When an entry's URI changes, Redirect Manager automatically creates a redirect from the old path to the new one. Includes an undo detection window so that immediately reversing a slug change removes the redirect instead of stacking redirects.

- **[Analytics](analytics.md) @since(5.1.0)** — Track every 404 with device type, browser, OS, and geographic data. The dashboard shows handled vs. unhandled 404s, top sources, and real-time charts. One-click redirect creation from unhandled 404 rows.

- **[Import / Export](import-export.md)** — Import redirects in bulk from CSV (up to 4000 rows per batch). Export your full redirect list for backup or migration. Import history tracking shows a log of past imports.

- **[Backups](backups.md) @since(5.23.0)** — Automatic backups before imports, scheduled backups (daily, weekly, monthly), and manual backups from the CP or CLI. Restore from any saved backup file. Store locally or in a Craft asset volume.

- **[Query String Handling](query-strings.md)** — Three independent settings control query strings at matching time, at redirect time, and in analytics grouping. Configure independently for e-commerce, API, or standard content sites.

- **[Plugin Integration](plugin-integration.md) @since(5.3.0)** — `RedirectHandlingTrait` lets other plugins push redirects into Redirect Manager and query its redirect table when handling their own 404s. Source attribution keeps the analytics dashboard accurate across integrations.

- **[Dashboard Widgets](dashboard-widgets.md) @since(5.1.0)** — Two Craft dashboard widgets: an unhandled 404 counter and an analytics summary. Add them from **Dashboard > New Widget**.

- **Craft Utility @since(5.1.0)** — A utility page under **Utilities → Redirect Manager** that shows redirect counts, recent 404 activity (last 7 days), and cache file counts at a glance.

## Privacy by Default

Redirect Manager never stores plain IP addresses. Visitor IPs are hashed with a salted SHA256 before storage. Optional subnet masking adds a second anonymization layer. See [Analytics](analytics.md) for details on the IP salt setup.

## Multi-Site Support

Redirects can be scoped to a specific site or set to apply globally (`siteId = null`). Analytics are also filtered by site. All CP views support per-site filtering.

## Next Steps

If you're new to Redirect Manager:

1. [Install and configure the plugin](../get-started/quickstart.md)
2. [Create your first redirect](redirects.md)
3. [Review auto-redirect behavior](auto-redirects.md)
4. [Monitor 404s in the dashboard](analytics.md)
