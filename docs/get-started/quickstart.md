# Quickstart

Get Redirect Manager running in under 5 minutes. By the end of this guide you'll have redirects catching 404s automatically.

## Before you start

Complete [Installation and setup](installation.md#post-install-setup) first. The setup page should show that the IP hash salt is configured before you rely on analytics.

## 1. Create your first redirect

1. Go to **Redirect Manager > Redirects**
2. Click **New Redirect**
3. Set **Source URL** to `/old-page`
4. Set **Destination URL** to `/new-page`
5. Leave **Match Type** as `exact` and **Status Code** as `301`
6. Save

![The Redirect Manager new redirect form with source and destination URLs filled in](images/quickstart-new-redirect.webp)

## 2. Test it

Visit `/old-page` in your browser — you should be redirected to `/new-page`.

## 3. Enable auto-redirects

Auto-redirect creation is enabled by default. When you change an entry's slug, the plugin automatically creates a redirect from the old URL to the new one.

## What's next

- [Configuration](configuration.md) — tune analytics, caching, query string handling, and backups
- Check **Redirect Manager > Analytics** to monitor 404s across your site
