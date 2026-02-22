# Query String Handling

Redirect Manager provides three independent settings that control how query strings are treated at three different points in the request lifecycle: during redirect matching, when issuing the redirect response, and when grouping analytics records.

Understanding these settings independently — and how they interact — lets you tune the plugin for your site's specific needs.

## The Three Settings

| Setting | Stage | Default |
|---------|-------|---------|
| `stripQueryString` | Matching — should query strings be ignored when looking for a redirect? | `false` |
| `preserveQueryString` | Destination — should query strings be passed to the redirect target? | `false` |
| `stripQueryStringFromStats` | Analytics — should different query strings be grouped as one URL? | `true` |

## 1. Strip Query String (Matching)

Controls whether the query string portion of a 404 URL is stripped before comparing against redirect patterns.

**Default: OFF (`false`)**

When OFF, the full URL including query string must match your redirect pattern. A rule for `/page` will not match `/page?foo=bar`.

```
Redirect rule: /page

OFF (default):          ON:
/page      → match      /page          → match
/page?a=1  → no match   /page?a=1      → match
/page?b=2  → no match   /page?b=2      → match
```

**When to enable:** Sites where query strings carry tracking parameters (UTM, referrer, campaign IDs) that should not prevent a redirect from firing. A rule for `/old-landing` should fire whether the visitor came via `/old-landing?utm_source=email` or just `/old-landing`.

```php
'stripQueryString' => true,
```

## 2. Preserve Query String (Destination)

Controls whether the query string from the original 404 URL is appended to the redirect destination URL.

**Default: OFF (`false`)**

When OFF, the query string is dropped when the redirect fires. The visitor ends up at the clean destination URL.

```
Visitor arrives at: /old-page?ref=email&campaign=spring

OFF (default):      Redirected to: /new-page
ON:                 Redirected to: /new-page?ref=email&campaign=spring
```

**When to enable:** Sites that rely on query string parameters for tracking, attribution, or session state. Enabling this ensures tracking parameters survive through a redirect.

```php
'preserveQueryString' => true,
```

## 3. Strip Query String From Stats (Analytics)

Controls whether analytics groups all hits to a URL regardless of query string, or tracks each unique URL+query combination separately.

**Default: ON (`true`)**

When ON, analytics records are grouped by path only. Multiple hits to the same path with different query strings are counted together and displayed as a single row. The most recent query string is shown in the display.

```
Visits:
  /page?source=google
  /page?source=email
  /page?source=facebook

ON (default): One record — /page?source=facebook (count: 3)
OFF:          Three records — each with count: 1
```

**When to disable:** Sites that need to track query string combinations separately — for example, to identify which specific parameter values are generating the most 404s.

```php
'stripQueryStringFromStats' => false,
```

## Interaction Between Settings

These three settings are completely independent. Each operates at a different stage:

```
Request arrives: /old-page?utm_source=email
        ↓
[stripQueryString] — strip for matching?
        ↓
Matching runs against: /old-page  (or full URL with query)
        ↓
Redirect found: /new-page
        ↓
[preserveQueryString] — carry query to destination?
        ↓
Response issued: 301 /new-page  (or /new-page?utm_source=email)
        ↓
[stripQueryStringFromStats] — group by path in analytics?
        ↓
Analytics recorded: /old-page  (or /old-page?utm_source=email)
```

## Common Configuration Recipes

### E-commerce and Marketing Sites

Match regardless of UTM/tracking params, preserve them through to the destination, and consolidate analytics by path:

```php
'stripQueryString'          => true,   // Match any UTM params
'preserveQueryString'       => true,   // Pass tracking through redirects
'stripQueryStringFromStats' => true,   // Clean analytics reports
```

### API or Application Sites

Require exact URL matching (params matter for behavior), drop query strings at destination (canonical clean URLs), and track each unique combination:

```php
'stripQueryString'          => false,  // Exact URL matching required
'preserveQueryString'       => false,  // Canonical URLs without params
'stripQueryStringFromStats' => false,  // Track each unique combination
```

### Standard Content Sites (Default)

Default behavior: params must match if present, are dropped at destination, analytics consolidated by path:

```php
'stripQueryString'          => false,
'preserveQueryString'       => false,
'stripQueryStringFromStats' => true,
```

> [!NOTE]
> Analytics always stores paths and query strings together (e.g., `/page?foo=bar`), never full URLs with domains. The `stripQueryStringFromStats` setting controls grouping, not what is stored.
