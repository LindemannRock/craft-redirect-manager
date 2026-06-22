# URL Filtering

Not every 404 deserves your attention. Bots probe for `/wp-login.php`, vulnerability scanners hammer `.env` and `.git`, and Craft's own `/admin`, `/cpresources`, and `/actions` URLs can all surface as misses. URL Filtering lets you tell Redirect Manager which paths to ignore completely — and it ships one-click presets for the most common cases so you don't have to write the regex yourself.

![The URL Filtering section on the Advanced settings screen with exclude patterns and the Quick Setup preset buttons](images/url-filtering-quick-setup.webp)

## What you'll use it for

- **Keep scanner noise out of analytics** — excluded paths never reach the 404 dashboard, so the data reflects real visitors instead of bots probing for exploits.
- **Skip Craft's own system URLs** — `/admin`, `/cpresources`, `/actions`, and `.well-known` requests shouldn't be treated as broken links.
- **Clean up after a WordPress migration** — silence the steady stream of `wp-admin`, `xmlrpc.php`, and `?p=123` requests that hit a migrated site for months.
- **Add headers to redirect responses** — e.g. send `X-Robots-Tag: noindex` so search engines don't index your old URLs.

## Where it lives

Everything below is on **Redirect Manager → Settings → Advanced**, under the **URL Filtering** heading.

## Exclude Patterns

**Exclude Patterns** are regular expressions matched against the incoming request path. When a path matches, Redirect Manager stops processing that request immediately — **before** it looks for a redirect **and before** it records anything to analytics.

That second part is the important one: an excluded URL is not "seen but skipped", it is ignored entirely. It will never appear in the analytics dashboard, handled or unhandled. Use exclusions for traffic you never want to redirect *or* count.

Each row is one pattern. A few examples:

| Pattern | Matches |
|---------|---------|
| `^/admin` | Any path starting with `/admin` |
| `^/cpresources` | Craft control-panel asset requests |
| `/xmlrpc\.php` | The WordPress XML-RPC endpoint, anywhere in the path |
| `\.sql($\|\.(gz\|zip))` | `.sql`, `.sql.gz`, and `.sql.zip` database-dump probes |

Patterns are evaluated on every 404, so they run on a hot path. Redirect Manager guards against problem patterns: a pattern longer than 500 characters is rejected, and patterns prone to catastrophic backtracking (such as nested `.*` quantifiers) are skipped rather than run. Rejected patterns are logged.

> [!NOTE]
> If `excludePatterns` is set in `config/redirect-manager.php`, the table is locked in the Control Panel and the config file wins. See [Configuration](../get-started/configuration.md).

## Quick Setup Presets

Below the URL Filtering fields are three buttons that apply curated pattern sets, so you don't have to build them by hand. Each preset is **additive and de-duplicating** — it only adds patterns you don't already have, and tells you if everything is already applied. (If the relevant field is overridden by `config/redirect-manager.php`, that preset can't change it.)

### Apply Recommended Settings

Listed under **Quick Setup**. Safe for any installation — it adds the patterns that almost every Craft site should exclude, plus two SEO headers:

| Adds | Value |
|------|-------|
| Exclude patterns | `^/admin`, `^/cms`, `^/cpresources`, `^/actions`, `^/\.well-known`, `^/dist/.*/assets` |
| Additional headers | `X-Robots-Tag: noindex, nofollow` · `X-Redirect-By: Redirect Manager` |

### Apply WordPress Migration Filters

Silences the bot and spam traffic a site keeps receiving after moving off WordPress: `wp-includes`, `wp-content/themes`, `wp-content/plugins`, `wp-json`, `feed`, `?p=` permalinks, `xmlrpc.php`, `wp-login.php`, `wp-admin`, and `wp-config.php`.

> [!WARNING]
> `/wp-content/uploads` URLs are **not** excluded — migrated media files often still need legitimate redirects, so those are left for you to handle.

### Apply Security Probe Filters

Stops the steady background of vulnerability scanning from cluttering your analytics. It adds precise patterns for database dumps (`*.sql`, `dump.sql.gz`), config and secret files (`.env`, `.git/`, `.htaccess`, `.aws`, `.ssh`), admin panels (`/phpmyadmin`, `/pma/`, `adminer.php`), and exploit attempts (`shell.php`, `/cgi-bin/`, `phpinfo.php`). The patterns are deliberately specific so they don't catch legitimate URLs like `/mysql-tips` or `/debugging-guide`.

## Additional Headers

**Additional Headers** are name/value pairs added to the **redirect response** — they're sent when Redirect Manager issues a redirect, not on normal pages. The most common use is `X-Robots-Tag: noindex, nofollow` (added for you by **Apply Recommended Settings**) to keep search engines from indexing the old URLs you're redirecting away from.

## Related

- [Analytics](analytics.md) — exclusions are what keep this dashboard focused on real traffic.
- [Configuration](../get-started/configuration.md) — set `excludePatterns` and `additionalHeaders` in `config/redirect-manager.php` for multi-environment control.
- [Redirects](redirects.md) — how matching works for the requests that *aren't* excluded.
