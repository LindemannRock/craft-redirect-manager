# Redirects

Redirect Manager matches incoming 404 requests against a library of redirect rules and issues the appropriate HTTP redirect. Rules support five match types, priority ordering, all standard redirect status codes, and multi-site scoping.

## Creating Redirects

### Via the Control Panel

1. Go to **Redirect Manager > Redirects**
2. Click **New Redirect**
3. Fill in the redirect fields (see below)
4. Save

### Programmatically

```php
use lindemannrock\redirectmanager\RedirectManager;

RedirectManager::$plugin->redirects->createRedirect([
    'sourceUrl'      => '/old-page',
    'destinationUrl' => '/new-page',
    'matchType'      => 'exact',
    'statusCode'     => 301,
    'enabled'        => true,
    'priority'       => 0,
    'siteId'         => null, // null = all sites
]);
```

## Match Types

Five match types give you precise control over how source URLs are compared.

| Match Type | Behavior | Example Pattern | Matches |
|------------|----------|-----------------|---------|
| `exact` | Case-insensitive exact match | `/old-page` | `/old-page`, `/OLD-PAGE` |
| `contains` | URL contains the pattern anywhere | `old-post` | `/blog/old-post`, `/archive/old-post/123` |
| `wildcard` | `*` wildcards match any characters | `/blog/*` | `/blog/post-1`, `/blog/category/news` |
| `prefix` | URL starts with the pattern | `/old-` | `/old-page`, `/old-blog`, `/old-anything` |
| `regex` | Full regular expression | `^/blog/(\d+)/(.*)$` | `/blog/123/my-post` |

### Exact Match

The simplest and most performant match type. Comparison is case-insensitive.

```
Pattern:  /old-page
Matches:  /old-page
          /OLD-PAGE
No match: /old-page/subpage
          /old-page-2
```

### Contains Match

Matches any URL that includes the pattern string.

```
Pattern:  old-post
Matches:  /blog/old-post
          /archive/old-post/123
          /old-post-title
```

Use this cautiously — a short pattern like `page` would match many unintended URLs.

### Wildcard Match

Replaces `*` with "any characters". Useful for redirecting entire URL subtrees.

```
Pattern:  /blog/*
Matches:  /blog/post-1
          /blog/category/news
          /blog/2024/01/my-post
```

### Prefix Match

Matches any URL that starts with the pattern string.

```
Pattern:  /old-
Matches:  /old-page
          /old-blog
          /old-anything
No match: /new-old-page (does not start with /old-)
```

### Regex Match with Capture Groups

Full regular expression support, including named and positional capture groups. Use `$1`, `$2` (etc.) in the destination URL to substitute captured values.

```
Pattern:     ^/blog/(\d+)/(.*)$
Destination: /article/$1/$2

Matches and redirects:
  /blog/123/my-post  →  /article/123/my-post
  /blog/456/news     →  /article/456/news
```

Capture group substitution is processed by `MatchingService::applyCaptures()` @since(5.10.0).

> [!NOTE]
> Regex patterns are matched against the full path (or full URL, depending on [Source Match Mode](#source-match-mode)). Do not wrap patterns in delimiters.

## Priority

When multiple redirect rules could match the same URL, priority determines which one fires first. Lower numbers are evaluated first.

| Priority | Description | Recommended Use |
|----------|-------------|-----------------|
| 0 | Highest | Specific patterns, exceptions |
| 1–4 | High | Important or frequent redirects |
| 5 | Normal | Standard redirects (default) |
| 6–8 | Low | Broad patterns |
| 9 | Lowest | Catch-all patterns |

**Example:** You have `/blog/featured-post` set to priority 0 and `/blog/*` set to priority 9. Visitors to `/blog/featured-post` hit the exact rule; all other `/blog/` paths fall through to the wildcard.

## Status Codes

| Code | Name | Description |
|------|------|-------------|
| `301` | Moved Permanently | Content has moved permanently. Search engines update their index. Most common for SEO-safe redirects. |
| `302` | Found (Temporary) | Temporary redirect. Search engines retain the original URL. |
| `303` | See Other | Redirect to a different resource, typically after form submission. |
| `307` | Temporary Redirect | Like 302 but guarantees the request method (POST, PUT, etc.) is preserved. |
| `308` | Permanent Redirect | Like 301 but guarantees the request method is preserved. |
| `410` | Gone | Content is permanently deleted. Search engines remove it from their index. |

## Source Match Mode

The source match mode controls what part of the incoming URL is compared against the redirect pattern.

| Mode | Behavior |
|------|----------|
| `pathonly` (default) | Match by path only (`/old-page`). Works across all domains. Full URLs entered in the CP are automatically stripped to their path. |
| `fullurl` | Match by complete URL including domain (`https://example.com/old-page`). Use for domain-specific redirects. |

Configure the global default in `config/redirect-manager.php`:

```php
'redirectSrcMatch' => 'pathonly', // or 'fullurl'
```

Individual redirects can override this at the rule level.

## Multi-Site Support

Redirects can be scoped to a single Craft site or applied globally.

- **Global redirect** (`siteId = null`): Matches on any site. Useful for redirects that apply regardless of domain or language.
- **Site-specific redirect**: Only matches requests for that site. Use when different sites have conflicting URL structures.

When both a site-specific and a global redirect match, site-specific rules take precedence.

## Managing Redirects

### Enabling and Disabling

Individual redirects can be enabled or disabled without deleting them. Disabled redirects are skipped during matching.

### Hit Counts

Each redirect tracks how many times it has fired. Hit counts are visible in the redirect list and help you identify stale rules that are no longer needed.

### Bulk Operations

The redirect list supports bulk enable, bulk disable, and bulk delete. Select rows using the checkboxes and choose an action from the bulk action menu.

## Caching

Redirect Manager caches the enabled redirect list for fast lookups. The cache is automatically invalidated when a redirect is created, updated, or deleted. Cache settings:

```php
'enableRedirectCache'    => true,
'redirectCacheDuration'  => 3600,   // seconds
'cacheStorageMethod'     => 'file', // 'file' or 'redis'
```

See [Configuration](../get-started/configuration.md) for all caching options.
