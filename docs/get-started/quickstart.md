# Quickstart

Get Redirect Manager running in under 5 minutes. By the end of this guide you'll have redirects catching 404s automatically.

## 1. Install the Plugin

See [Installation](installation.md) for full details including DDEV and Composer options.

## 2. Generate IP Salt

Run the security command to set up privacy-focused IP hashing for analytics:

```bash title="PHP"
php craft redirect-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft redirect-manager/security/generate-salt
```

This adds `REDIRECT_MANAGER_IP_SALT` to your `.env` file automatically.

## 3. Create Your First Redirect

1. Go to **Redirect Manager > Redirects**
2. Click **New Redirect**
3. Set **Source URL** to `/old-page`
4. Set **Destination URL** to `/new-page`
5. Leave **Match Type** as `exact` and **Status Code** as `301`
6. Save

## 4. Test It

Visit `/old-page` in your browser — you should be redirected to `/new-page`.

## 5. Enable Auto-Redirects

Auto-redirect creation is enabled by default. When you change an entry's slug, the plugin automatically creates a redirect from the old URL to the new one.

## What's Next

- [Configuration](configuration.md) — tune analytics, caching, query string handling, and backups
- Check **Redirect Manager > Analytics** to monitor 404s across your site
