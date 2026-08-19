# Troubleshooting

Start here when redirects, analytics, scheduled jobs, or settings behave differently than expected. Each section gives quick checks first, then the likely cause.

For redirect matching and JSON API checks, start with [Testing tools](testing-tools.md). **Redirect Manager → Settings → Test** shows which redirect wins, which lower-priority rules also match, whether the JSON API is ready, and the exact API response body and headers.

## Redirects not working

A redirect exists in the CP but visiting the URL does not redirect.

**Quick checks:**

1. **Is the plugin installed and enabled?**

   ```bash title="PHP"
   php craft plugin/list
   ```

   ```bash title="DDEV"
   ddev craft plugin/list
   ```

2. **Are the database tables present?**

   ```bash title="PHP"
   php craft migrate/all --plugin=redirect-manager
   ```

   ```bash title="DDEV"
   ddev craft migrate/all --plugin=redirect-manager
   ```

3. **Is the redirect enabled?** Go to **Redirect Manager > Redirects** and check that the redirect row shows as enabled (not greyed out).

4. **Is the source URL correct?** By default, the plugin matches by path only. If the redirect source is `/old-page`, it matches the path `/old-page` — not `https://example.com/old-page`. If you need domain-specific matching, switch to `redirectSrcMatch = 'fullurl'`. For URLs carrying `utm_*`, `fbclid`, or similar parameters, enable **Strip Query String** so those parameters do not prevent an exact path match. The tester under **Settings → Test** applies the same setting.

5. **Is the redirect cache stale?** Clear caches:

   ```bash title="PHP"
   php craft clear-caches/all
   ```

   ```bash title="DDEV"
   ddev craft clear-caches/all
   ```

   If `cacheStorageMethod` is set to `redis`, also check the logs for a cache-component warning. Redirect Manager logs a warning and skips Redis-specific cache operations when Redis storage is selected but Craft's `cache` component is not Redis-backed.

6. **Check the logs.** Go to **Redirect Manager > Logs** or enable debug logging temporarily:

   ```php
   // config/redirect-manager.php
   'logLevel' => 'debug',
   ```

   Debug logging requires `devMode` to be enabled.

**Why it happens:** The redirect cache may contain an outdated version of the redirect list, or the source URL doesn't match what the plugin is receiving (e.g., query strings, path differences).

---

## Analytics not recording

404s are happening but nothing appears in the analytics dashboard.

**Quick checks:**

1. **Is analytics enabled?** Check `enableAnalytics` is `true` in settings (it is the master switch — disabling it stops all tracking).

2. **Is the URL excluded?** Exclude patterns skip both redirect handling and analytics. Review **Settings → Advanced → URL Filtering** if only certain paths are missing.

3. **Is the IP hash salt configured?** An error banner appears in settings when the salt is missing. Open **Redirect Manager → Setup**, or generate one from the terminal:

   ```bash title="PHP"
   php craft redirect-manager/security/generate-salt
   ```

   ```bash title="DDEV"
   ddev craft redirect-manager/security/generate-salt
   ```

4. **Check the database directly:**

   ```sql
   SELECT COUNT(*) FROM redirectmanager_analytics;
   ```

**Why it happens:** Analytics requires `enableAnalytics = true`, a configured salt, and a URL that is not excluded. `analyticsLimit` does not reject incoming events; it is enforced later by scheduled cleanup.

---

## Analytics temporarily exceeds the configured limit

`analyticsLimit` is a scheduled convergence target rather than a hard request-time cap. Redirect Manager records each handled or unhandled event immediately, then the recurring cleanup job trims the oldest, lowest-hit rows back to the configured limit.

If the row count stays above the limit:

1. Confirm `enableAnalytics` and `autoTrimAnalytics` are both `true`.
2. Confirm a Craft queue worker is running.
3. Run the queue manually to process pending cleanup work:

   ```bash title="PHP"
   php craft queue/run
   ```

   ```bash title="DDEV"
   ddev craft queue/run
   ```

Setting `analyticsRetention` to `0` only disables age-based deletion. It does not disable limit cleanup while `autoTrimAnalytics` is enabled. Retention-only cleanup also remains available by setting a positive retention period with auto-trim disabled. You can clear analytics manually from **Redirect Manager > Analytics** or the Redirect Manager Craft utility if you need immediate removal rather than scheduled convergence.

---

## Scheduled cleanup or backups do not reappear

Redirect Manager schedules recurring queue jobs for analytics cleanup and automatic backups. If the queue is empty after one of those jobs runs:

- Confirm the queue worker is running.
- Visit any CP page to let Redirect Manager bootstrap initial jobs.
- Check that `enableAnalytics` is on and either `analyticsRetention` is greater than `0` or `autoTrimAnalytics` is enabled.
- Check that `backupEnabled` is on and `backupSchedule` is not `disabled` for scheduled backups.

The queued job description shows when that specific queued row is due to run. Craft stores that description when the row is queued, so date/time format changes apply to newly queued rows. Existing delayed rows keep their old label until they run or are requeued. Queue labels stay compact: numeric months render numerically, while short and long month settings both render as short month names.

## Scheduled-backup reconciliation is deferred

The logs show that scheduled-backup bootstrap reconciliation was deferred because the lifecycle or portable queue lock is busy.

This warning is expected when a scheduled backup or another scheduling operation already owns the lock. Redirect Manager leaves the queue unchanged, allows the request to continue, and retries reconciliation during a later Craft bootstrap.

If the warning continues after the backup or settings operation has finished, confirm the queue worker is healthy and check for a stuck scheduled-backup job before restarting the worker.

## Duplicate scheduled backup jobs keep appearing

Scheduled backups and analytics cleanup should normally have one delayed queue row per next run. Redirect Manager checks for existing pending rows during bootstrap, collapses duplicate pending rows automatically, and keeps one row for the next scheduled run.

If duplicates keep returning after a deployment, check whether multiple app instances are running different plugin versions or whether scheduling settings are overridden in `config/redirect-manager.php`. For analytics, check `enableAnalytics`, `analyticsRetention`, and `autoTrimAnalytics`; for backups, check `backupSchedule` and `backupEnabled`. Config overrides prevent CP changes from taking effect, so update the config value directly.

---

## Settings save shows a validation error

Numeric settings such as cache duration, analytics limits, and backup retention must be whole numbers within the field's allowed range. If a value is invalid, Redirect Manager keeps you on the same settings page and shows the field error inline.

When a setting is overridden in `config/redirect-manager.php`, the Control Panel field is skipped during save. Change the config file value instead.

---

## Auto-redirects not being created

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

**In local development:** Private IP addresses (127.0.0.1, 192.168.x.x, 10.x.x.x) cannot be geolocated automatically. Redirect Manager leaves geo fields empty unless you set both defaults:

```php
// config/redirect-manager.php
'defaultCountry' => 'US',
'defaultCity'    => 'New York',
```

If either value is missing or does not match a supported location, Redirect Manager leaves the geo fields empty instead of inventing a fallback location.

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
4. **Check site permissions.** Rows whose **Site ID** points at a site your account cannot edit are skipped and counted as failures. Ask an admin to grant edit access to those sites, or import as a user who has it. Rows with a blank Site ID (all sites) are unaffected.
5. **Restore from backup.** If `backupOnImport` is `true` (default), a backup was created before the import. Go to **Redirect Manager > Backups** and restore the pre-import snapshot.

---

## Redirect or Import Rejected as Invalid URL

Saving a redirect or importing a row fails with an invalid destination/source URL or a capture-reference error.

**Quick checks:**

1. **Use a complete URL.** A bare scheme like `https://` (no host) is rejected — enter a full URL with a host (`https://example.com/page`) or a relative path (`/page`).
2. **Avoid protocol-relative URLs.** `//host` is rejected because the browser resolves it to an external origin. Use a path (`/host`) or a full `https://` URL.
3. **Check capture references.** `$1`, `$2` only work where the match type produces captures: Wildcard (one per `*`), Prefix (`$1` = the part after the prefix), and Regex (one per capturing group). Exact Match supports only `$0`, the full matched URL. Referencing more captures than the source defines is rejected. See [Match Types](../feature-tour/redirects.md#match-types).
4. **Contact links are allowed.** `mailto:`, `tel:`, `whatsapp:`, `sms:`, `fax:`, `skype://`, `slack:`, and `msteams:` destinations are valid; executable schemes (`javascript:`, `data:`) are not.
5. **Keep captures inside a fixed destination.** Use `/new/$1` or `https://example.com/$1?from=$2`. A bare `$1`, `https://$1/path`, or a capture in the user-info or port portion of an HTTP(S) URL is rejected because the request value would control the destination's trust boundary.

If an older published redirect or an integration-created row contains an unsafe template, it remains in the database but cannot win resolution. Redirect Manager skips it and continues to the next matching safe rule by priority. If every matching rule is unsafe, no redirect is issued and the request is recorded as unhandled. Skipped rules do not receive hits, handled analytics, or positive cache entries. Edit or replace the unsafe row; do not raise its priority to work around the protection.

---

## Getting Help

- Enable debug logging and check **Redirect Manager > Logs**
- Check Craft's general log at `storage/logs/web.log`
- For persistent issues, include your Redirect Manager version, Craft version, and relevant log entries

## SQL Errors on PostgreSQL (Column Does Not Exist / Ambiguous)

```text
SQLSTATE[42703]: column "..." does not exist
SQLSTATE[42702]: column reference "..." is ambiguous
```

Either of these on a PostgreSQL install — in analytics pages, dashboards, or tracking — means you're on a version whose SQL was only exercised on MySQL. PostgreSQL folds unquoted identifiers to lowercase and resolves upsert column references differently; MySQL surfaces neither, so the issues were invisible there.

**Fix:** Update to the latest version. All plugin SQL is now dialect-safe on both MySQL and PostgreSQL.
