# Postman Collection @since(5.35.0)

Redirect Manager ships a ready-made Postman collection and environment template for the read-only [JSON API](api-endpoints.md). Use it to confirm the endpoint is enabled, exercise site filtering, and verify token and rate-limit enforcement without writing any client code.

## Get the files

Two ways to obtain the collection:

- **From the Control Panel** — go to **Redirect Manager → Settings → Test** and click **Download Postman collection**. You get a zip containing both files.
- **From the plugin package** — they live in the plugin's `postman/` folder:
  - `Redirect-Manager.postman_collection.json` — example requests for the redirects endpoint plus token, JSON, and rate-limit checks.
  - `Redirect-Manager.postman_environment.json` — a reusable environment template with placeholders only (no real token ships in the file).

## Setup

1. Import both files into Postman.
2. Duplicate the **Redirect Manager API** environment once per target, e.g. **DDEV**, **Staging**, **UAT**, **Production**.
3. Select the duplicated environment from Postman's environment dropdown.
4. Set the variables:

| Variable | Value |
|----------|-------|
| `base_url` | Your Craft site URL, no trailing slash |
| `api_token` | The value of `REDIRECT_MANAGER_API_TOKEN` |
| `site_id` | A real Craft site ID (only for site-filter tests) |
| `site_handle` | A real Craft site handle (only for handle-filter tests) |

`api_token` is a Postman secret variable in the template, so the shipped file never contains a real token.

## Recommended test flows

### 1. Endpoint readiness

1. Enable the JSON API in **Redirect Manager → Settings → Advanced**.
2. Configure `REDIRECT_MANAGER_API_TOKEN` in the Craft environment.
3. Run **Redirects API → List redirects - all sites**.

Expected: `200` and a JSON array of enabled redirects.

### 2. Site filtering

In the **Redirects API** folder:

1. **List redirects - by siteId** — with `site_id` set to an existing site.
2. **List redirects - by site handle** — with `site_handle` set to an existing handle.
3. **List redirects - unknown siteId returns empty array** — with `unknown_site_id` set to a non-existent site.

Expected: existing sites return their site-specific redirects plus global redirects; unknown explicit sites return an empty array.

### 3. Enforcement checks

In the **Enforcement Checks** folder:

- **Missing token - 401** — confirms the endpoint requires a token.
- **Invalid token - 401** — confirms wrong tokens are rejected.
- **Missing Accept header - 400** — confirms callers must request JSON. This request intentionally omits `Accept: application/json`, so the body may be an HTML Craft/Yii error page; the expected result is the `400` status.

### 4. Rate-limit probe

Set a low **API Rate Limit** in Redirect Manager (e.g. `3` per minute), then run **Enforcement Checks → Rate limit probe - repeat with Runner** with more iterations than the cap.

Expected: requests within the limit return `200`; requests over it return `429` with `Retry-After` and `X-RateLimit-*` headers. See [Rate limiting](api-endpoints.md).

## Notes

- The collection sends the token in the `X-Redirect-Manager-Key` header from the `api_token` variable. In Craft that value is `REDIRECT_MANAGER_API_TOKEN`.
- Every normal request sends `Accept: application/json`.
- The JSON API is read-only — it lists enabled redirects and never creates, updates, deletes, resolves, increments hit counts, or writes analytics.
