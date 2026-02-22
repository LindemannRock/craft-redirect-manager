# Troubleshooting

Common issues and solutions for Redirect Manager.

## Redirects Not Working

A redirect exists in the CP but visiting the URL does not redirect.

**Quick checks:**

1. **Is the plugin installed and enabled?**

   ```bash
   php craft plugin/list
   ```

2. **Are the database tables present?**

   ```bash
   php craft migrate/all --plugin=redirect-manager
   ```

3. **Is the redirect enabled?** Go to **Redirect Manager > Redirects** and check that the redirect row shows as enabled (not greyed out).

4. **Is the source URL correct?** By default, the plugin matches by path only. If the redirect source is `/old-page`, it matches the path `/old-page` — not `https://example.com/old-page`. If you need domain-specific matching, switch to `redirectSrcMatch = 'fullurl'`.

5. **Is the redirect cache stale?** Clear caches:

   ```bash
   php craft clear-caches/all
   ```

6. **Check the logs.** Go to **Redirect Manager > Logs** or enable debug logging temporarily:

   ```php
   // config/redirect-manager.php
   'logLevel' => 'debug',
   ```

   Debug logging requires `devMode` to be enabled.

**Why it happens:** The redirect cache may contain an outdated version of the redirect list, or the source URL doesn't match what the plugin is receiving (e.g., query strings, path differences).

---

## Analytics Not Recording

404s are happening but nothing appears in the analytics dashboard.

**Quick checks:**

1. **Is analytics enabled?** Check `enableAnalytics` is `true` in settings (it is the master switch — disabling it stops all tracking).

2. **Has the analytics limit been reached?** The default limit is 1000 unique 404 records. When the limit is reached, new records are not added. Check:

   ```php
   'analyticsLimit' => 1000, // increase if needed
   ```

3. **Is the IP hash salt configured?** An error banner appears in settings when the salt is missing. Generate one:

   ```bash
   php craft redirect-manager/security/generate-salt
   ```

4. **Check the database directly:**

   ```sql
   SELECT COUNT(*) FROM redirectmanager_analytics;
   ```

**Why it happens:** Analytics requires `enableAnalytics = true`, a configured salt, and available capacity within `analyticsLimit`.

---

## Auto-Redirects Not Being Created

Entry URIs change but no redirects appear in the redirect list.

**Quick checks:**

1. **Is auto-redirect creation enabled?** Check **Redirect Manager > Settings > Auto Create Redirects** is on, or in config:

   ```php
   'autoCreateRedirects' => true,
   ```

2. **Does the entry have a URI?** Entries in sections with no URI template (or with URIs disabled) will not trigger auto-redirects. Check that the entry's section has a URI format set.

3. **Was this a new entry?** Auto-redirects are only created when an existing URI changes, not on first publish.

4. **Check the logs** for any errors during the save event.

**Why it happens:** The plugin hooks into Craft's element save events. If the entry has no previous URI or URIs are disabled for the section, the event fires but no redirect is created.

---

## Debug Logging Not Showing Up

Set `logLevel` to `debug` but debug entries are not appearing.

**Cause:** Debug logging requires `devMode` to be enabled in Craft's general config. When `logLevel` is set to `debug` but `devMode` is `false`, the level automatically falls back to `info`.

```php
// config/general.php
'devMode' => true, // required for debug log level
```

**Fix:** Enable `devMode` in your development environment, or use `info` or `warning` as the log level in production.

---

## Redirect Creates a Loop

A redirect fires but the browser reports "Too many redirects" or the destination sends back to the source.

**Common causes:**

- The destination URL matches the source URL (e.g., `/page` → `/page`)
- A wildcard or prefix redirect accidentally matches its own destination
- A chain of redirects creates a cycle (A → B → C → A)

**Fix:**

1. Review the redirect in the CP for obvious source/destination overlap
2. Enable debug logging and check logs for the redirect chain
3. Use `exact` match type for specific pages instead of `wildcard` or `prefix` to avoid unintended matches

---

## Geo-Location Showing Wrong Country

All 404s show the same country or show "Unknown" in the geographic breakdown.

**In local development:** Private IP addresses (127.0.0.1, 192.168.x.x, 10.x.x.x) cannot be geolocated. Set a development default:

```php
// config/redirect-manager.php
'defaultCountry' => 'US',
'defaultCity'    => 'New York',
```

**In production:** Verify that:

1. `enableGeoDetection` is `true`
2. The geo provider (`ip-api.com` by default) is accessible from your server
3. If using the free `ip-api.com` tier, check that you have not exceeded the rate limit. Consider adding a `geoApiKey` for a paid tier.

---

## Import Fails or Produces Unexpected Redirects

A CSV import completed but some redirects are wrong or missing.

**Quick checks:**

1. **Check the import count.** The import summary shows how many rows succeeded vs. failed.
2. **Review the CSV format.** Column mapping happens during the import wizard — verify the mapping was correct.
3. **Check for rows over the limit.** Maximum 4000 rows per import. Rows beyond this limit are silently skipped.
4. **Restore from backup.** If `backupOnImport` is `true` (default), a backup was created before the import. Go to **Redirect Manager > Backups** and restore the pre-import snapshot.

---

## Getting Help

- Enable debug logging and check **Redirect Manager > Logs**
- Check Craft's general log at `storage/logs/web.log`
- For persistent issues, include your Redirect Manager version, Craft version, and relevant log entries
