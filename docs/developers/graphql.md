# GraphQL @since(5.33.0)

Use Redirect Manager from a headless or SPA frontend when Craft is still the source of redirect rules. Your frontend can ask Craft whether a missed route has a redirect, while editors continue to manage redirects and review 404 activity in the Control Panel.

Redirect Manager exposes two read-oriented GraphQL queries:

- `redirectManagerResolveRedirect` resolves one missed URI and records redirect/404 analytics
- `redirectManagerRedirects` lists enabled redirects for frontend clients that want a read-only redirect table

There are no GraphQL mutations and no JSON API endpoints for creating or editing redirects. Editors manage redirects in the Craft Control Panel. If you need a plain read-only JSON list of enabled redirects, use the [API endpoints](api-endpoints.md) guide.

## Before you query

GraphQL access is controlled by Craft schemas. Redirect Manager does not have a separate GraphQL toggle.

Enable these on the schema used by your frontend token:

| Area | Required access |
|---|---|
| Sites | The site the frontend queries, for example `en` |
| Redirect Manager | `Query Redirect Manager data` |

The Redirect Manager scope is:

| Scope | Purpose |
|---|---|
| `redirectManager.all:read` | Allows Redirect Manager GraphQL queries |

For public frontend requests, use the GraphQL token for a schema that has the site and Redirect Manager permission enabled. A logged-in Control Panel user may see different results in GraphiQL than an external client using a token.

## Resolve a redirect

Use `redirectManagerResolveRedirect` after the frontend router misses a route.

At minimum, pass the URI you want to resolve:

```graphql
{
  redirectManagerResolveRedirect(uri: "/old-page") {
    destinationUrl
  }
}
```

You can also pass a site ID:

```graphql
{
  redirectManagerResolveRedirect(uri: "/old-page", siteId: 1) {
    destinationUrl
  }
}
```

Or pass a site handle:

```graphql
{
  redirectManagerResolveRedirect(uri: "/old-page", site: "en") {
    destinationUrl
  }
}
```

You can pass either `site` or `siteId`. If both are present, `site` wins. Invalid explicit site handles or IDs return no result instead of falling back to another site.

When no redirect matches, `null` is returned:

```json
{
  "data": {
    "redirectManagerResolveRedirect": null
  }
}
```

When a redirect matches, the requested fields are returned:

```json
{
  "data": {
    "redirectManagerResolveRedirect": {
      "destinationUrl": "/new-page"
    }
  }
}
```

Most frontends only need `destinationUrl` and `statusCode`, but you can query the full redirect object:

```graphql
{
  redirectManagerResolveRedirect(uri: "/old-page", site: "en") {
    id
    siteId
    site
    elementId
    enabled
    sourceUrl
    sourceUrlParsed
    redirectSrcMatch
    matchType
    destinationUrl
    statusCode
    hitCount
    lastHit
  }
}
```

Example response:

```json
{
  "data": {
    "redirectManagerResolveRedirect": {
      "id": 1882,
      "siteId": 1,
      "site": "en",
      "elementId": null,
      "enabled": true,
      "sourceUrl": "https://example.com/old-page",
      "sourceUrlParsed": "https://example.com/old-page",
      "redirectSrcMatch": "fullurl",
      "matchType": "exact",
      "destinationUrl": "https://example.com/new-page",
      "statusCode": 301,
      "hitCount": 12,
      "lastHit": "2026-06-18 06:06:07"
    }
  }
}
```

### Resolver behavior

Resolution uses Redirect Manager's normal matching path, including path-only vs full-URL mode, requested-site base-path stripping, priority ordering, global redirects, wildcard/prefix/RegEx captures, and query-string stripping when that setting is enabled.

This query behaves like a real 404 lookup:

- A matched redirect increments `hitCount` and updates `lastHit`
- A matched redirect records handled analytics with `sourcePlugin = graphql`
- A miss records unhandled analytics with `sourcePlugin = graphql`
- Analytics respects the requested `site` / `siteId`

Because this query intentionally has hit-count and analytics side effects, Redirect Manager disables Craft's GraphQL result cache for operations that include `redirectManagerResolveRedirect`.

### Arguments

```graphql
redirectManagerResolveRedirect(uri: "/old-page", siteId: 1, site: "en")
```

| Argument | Type | Required | Description |
|---|---|---|---|
| `uri` | `String` | Yes | URI or full URL to resolve |
| `siteId` | `Int` | No | Site ID to resolve against |
| `site` | `String` | No | Site handle to resolve against |

## List redirects

Use `redirectManagerRedirects` when a frontend needs a read-only list of enabled redirects.

```graphql
{
  redirectManagerRedirects(siteId: 1) {
    matchType
    sourceUrl
    destinationUrl
    statusCode
  }
}
```

Example response:

```json
{
  "data": {
    "redirectManagerRedirects": [
      {
        "matchType": "exact",
        "sourceUrl": "/old-page",
        "destinationUrl": "/new-page",
        "statusCode": 301
      },
      {
        "matchType": "regex",
        "sourceUrl": "^/branches/(.{4})$",
        "destinationUrl": "/locations/$1",
        "statusCode": 301
      }
    ]
  }
}
```

You can also pass a site handle:

```graphql
{
  redirectManagerRedirects(site: "en") {
    matchType
    sourceUrl
    destinationUrl
  }
}
```

The list query returns enabled redirects for the requested site plus global redirects where `siteId` is `null`. It does not increment `hitCount` and does not write analytics.

### Arguments

```graphql
redirectManagerRedirects(siteId: 1, site: "en")
```

| Argument | Type | Required | Description |
|---|---|---|---|
| `siteId` | `Int` | No | Site ID to list redirects for |
| `site` | `String` | No | Site handle to list redirects for |

## Field reference

| Field | Type | Description |
|---|---|---|
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

## Frontend pattern

1. Let the SPA router attempt to match the route.
2. On a miss, call `redirectManagerResolveRedirect`.
3. If the result is not `null`, redirect the browser to `destinationUrl` with the returned `statusCode`.
4. If the result is `null`, show the frontend's normal 404 page.

## Troubleshooting

### The query is missing from the schema

Enable `Redirect Manager` → `Query Redirect Manager data` on the GraphQL schema used by your token.

### The schema cannot access the site

Enable the requested site on the same GraphQL schema. Craft checks site access before Redirect Manager resolves the URI.

### Hits do not appear to update in GraphiQL

Reload the GraphiQL page or test with an external client such as Postman. Redirect Manager disables result caching for the resolver query, but the GraphiQL UI can still show stale results until the page refreshes.
