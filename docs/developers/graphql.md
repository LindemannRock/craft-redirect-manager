# GraphQL @since(5.33.0)

Redirect Manager exposes GraphQL queries for headless and SPA frontends that need the same redirect behavior Craft gets from normal 404 handling.

GraphQL is controlled by Craft's GraphQL schema permissions. There is no plugin-level toggle. Enable the Query Redirect Manager data schema permission on the GraphQL schema used by your frontend token.

## Schema Permission

| Scope | Purpose |
|-------|---------|
| `redirectManager.all:read` | Allows Redirect Manager GraphQL queries |

## Resolve a Redirect

Use `redirectManagerResolveRedirect` when a frontend route misses and needs to ask Craft whether Redirect Manager has a matching redirect.

```graphql
query ResolveRedirect($uri: String!, $site: String) {
  redirectManagerResolveRedirect(uri: $uri, site: $site) {
    id
    sourceUrl
    destinationUrl
    statusCode
    matchType
    redirectSrcMatch
    site
    siteId
  }
}
```

Variables:

```json
{
  "uri": "/old-page",
  "site": "default"
}
```

You can pass either `site` or `siteId`. If both are present, `site` wins. Invalid explicit site handles or IDs return no result instead of falling back to another site.

Resolution uses Redirect Manager's normal matching path, including path-only vs full-URL mode, requested-site base-path stripping, priority ordering, global redirects, wildcard/prefix/RegEx captures, and query-string stripping when that setting is enabled.

This query behaves like a real 404 lookup:

- A matched redirect increments `hitCount` and updates `lastHit`
- A matched redirect records handled analytics with `sourcePlugin = graphql`
- A miss records unhandled analytics with `sourcePlugin = graphql`
- Analytics respects the requested `site` / `siteId`

Because this query intentionally has hit-count and analytics side effects, Redirect Manager disables Craft's GraphQL result cache for operations that include `redirectManagerResolveRedirect`.

## List Redirects

Use `redirectManagerRedirects` when a frontend needs a read-only list of enabled redirects for a site.

```graphql
query Redirects($siteId: Int) {
  redirectManagerRedirects(siteId: $siteId) {
    id
    sourceUrl
    destinationUrl
    statusCode
    enabled
    priority
    site
    siteId
  }
}
```

The list query returns enabled redirects for the requested site plus global redirects where `siteId` is `null`. It does not increment `hitCount` and does not write analytics.

## Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | `Int` | Redirect ID |
| `site` | `String` | Site handle, or `null` for global redirects |
| `siteId` | `Int` | Site ID, or `null` for global redirects |
| `sourceUrl` | `String` | Source URL pattern |
| `sourceUrlParsed` | `String` | Parsed source pattern used for matching |
| `destinationUrl` | `String` | Destination URL |
| `redirectSrcMatch` | `String` | `pathonly` or `fullurl` |
| `matchType` | `String` | `exact`, `regex`, `wildcard`, or `prefix` |
| `statusCode` | `Int` | HTTP status code |
| `enabled` | `Boolean` | Whether the redirect is enabled |
| `priority` | `Int` | Redirect priority |
| `creationType` | `String` | How the redirect was created |
| `sourcePlugin` | `String` | Plugin that created the redirect |
| `elementId` | `Int` | Related element ID, when available |
| `hitCount` | `Int` | Number of matched hits |
| `lastHit` | `String` | Last hit datetime |

## Frontend Pattern

1. Let the SPA router attempt to match the route.
2. On a miss, call `redirectManagerResolveRedirect`.
3. If the result is not `null`, redirect the browser to `destinationUrl` with the returned `statusCode`.
4. If the result is `null`, show the frontend's normal 404 page.
