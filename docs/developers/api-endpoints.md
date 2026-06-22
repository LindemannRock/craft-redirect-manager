# API Endpoints @since(5.33.0)

Use the JSON redirects endpoint when another layer needs the redirect table outside Craft's normal request lifecycle. Typical consumers are static build scripts, edge workers, SPA bootstrapping code, and backend services that do not use GraphQL.

The endpoint is read-only. It lists enabled redirects; it does not create, update, delete, resolve, increment hit counts, or write analytics.

## Enable the endpoint

The endpoint is disabled by default.

```php
// config/redirect-manager.php
use craft\helpers\App;

return [
    '*' => [
        'apiEndpointEnabled' => true,
        'apiEndpointToken' => App::env('REDIRECT_MANAGER_API_TOKEN'),
    ],
];
```

Set `REDIRECT_MANAGER_API_TOKEN` in your environment before enabling the endpoint:

```bash title="PHP"
php craft redirect-manager/security/generate-api-token
```

```bash title="DDEV"
ddev craft redirect-manager/security/generate-api-token
```

Or add the value manually:

```dotenv
REDIRECT_MANAGER_API_TOKEN="use-a-long-random-token"
```

If `apiEndpointEnabled` is true and `apiEndpointToken` is empty, requests are rejected with `401`.

## List redirects

```http
GET /actions/redirect-manager/api/get-redirects
```

You can test this endpoint from **Redirect Manager → Settings → Test** after `apiEndpointEnabled` is on and `REDIRECT_MANAGER_API_TOKEN` is configured. The test page uses the configured token server-side and never asks you to paste it into the browser.

With a token:

```bash title="Bearer token"
curl -H "Authorization: Bearer $REDIRECT_MANAGER_API_TOKEN" \
  "https://example.com/actions/redirect-manager/api/get-redirects"
```

You can also send the token in the plugin-specific header:

```bash title="Plugin header"
curl -H "X-Redirect-Manager-Key: $REDIRECT_MANAGER_API_TOKEN" \
  "https://example.com/actions/redirect-manager/api/get-redirects"
```

Example response:

```json
[
  {
    "id": "12",
    "siteId": "1",
    "sourceUrl": "/old-page",
    "sourceUrlParsed": "/old-page",
    "destinationUrl": "/new-page",
    "redirectSrcMatch": "pathonly",
    "matchType": "exact",
    "statusCode": "301",
    "enabled": "1",
    "priority": "0",
    "creationType": "manual",
    "sourcePlugin": "redirect-manager",
    "elementId": null,
    "hitCount": "0",
    "lastHit": null,
    "uid": "00000000-0000-0000-0000-000000000000",
    "dateCreated": "2026-06-18 10:00:00",
    "dateUpdated": "2026-06-18 10:00:00"
  }
]
```

## Filter by site

Pass `siteId` to return redirects for a specific site plus global redirects:

```http
GET /actions/redirect-manager/api/get-redirects?siteId=1
```

Or pass a site handle:

```http
GET /actions/redirect-manager/api/get-redirects?site=en
```

If both `site` and `siteId` are present, `site` is used. Invalid explicit sites return an empty array.

## Status codes

| Status | Meaning |
|--------|---------|
| `200` | Endpoint enabled and request accepted |
| `401` | Token is configured but missing or invalid |
| `404` | Endpoint is disabled |

## JSON API vs GraphQL

Use this endpoint when a consumer wants the full enabled redirect table as plain JSON.

Use [GraphQL](graphql.md) when the frontend already uses Craft GraphQL schemas, needs field selection, or wants to resolve one missed URI and record analytics like a real 404 lookup.
