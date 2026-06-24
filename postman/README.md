# Redirect Manager — Postman Files

User-facing Postman collection and environment template for the Redirect Manager JSON API.

## Files

- **`Redirect-Manager.postman_collection.json`** — examples for the read-only redirects endpoint and token/JSON/rate-limit checks.
- **`Redirect-Manager.postman_environment.json`** — reusable environment template with placeholders only.

## Setup

1. Import both files into Postman.
2. Duplicate **Redirect Manager API** once per target environment, for example:
   - **Redirect Manager — DDEV**
   - **Redirect Manager — Staging**
   - **Redirect Manager — UAT**
   - **Redirect Manager — Production**
3. Select the duplicated environment from Postman's environment dropdown.
4. Set:
   - `base_url` → your Craft site URL, no trailing slash.
   - `api_token` → the value from `REDIRECT_MANAGER_API_TOKEN`.
   - `site_id` → a real Craft site ID, if you want to test site filtering.
   - `site_handle` → a real Craft site handle, if you want to test handle filtering.

The token value is a Postman secret variable in the environment template. The shipped file contains no real token.

## Recommended Test Flows

### 1. Endpoint Readiness

1. Enable the JSON API endpoint in **Redirect Manager → Settings → Advanced**.
2. Configure `REDIRECT_MANAGER_API_TOKEN` in the Craft environment.
3. Run **Redirects API → List redirects - all sites**.

Expected: the request returns `200` and a JSON array.

### 2. Site Filtering

Use the **Redirects API** folder:

1. Run **List redirects - by siteId** with `site_id` set to an existing site ID.
2. Run **List redirects - by site handle** with `site_handle` set to an existing site handle.
3. Run **List redirects - unknown siteId returns empty array** with `unknown_site_id` left at a value that does not exist.

Expected: existing sites return matching site-specific redirects plus global redirects; unknown explicit sites return an empty array.

### 3. Enforcement Checks

Use the **Enforcement Checks** folder:

- **Missing token - 401** confirms the endpoint requires `REDIRECT_MANAGER_API_TOKEN`.
- **Invalid token - 401** confirms wrong tokens are rejected.
- **Missing Accept header - 400** confirms callers must request JSON. This request intentionally omits `Accept: application/json`, so the response body may be an HTML Craft/Yii error page; the expected result is the `400` status.

### 4. Rate Limit Probe

Set a low **API Rate Limit** in Redirect Manager, such as `3` requests per minute. Then run **Enforcement Checks → Rate limit probe - repeat with Runner** with more iterations than the cap.

Expected: requests within the limit return `200`; requests beyond the limit return `429` with `Retry-After` and `X-RateLimit-*` headers.

## Notes

- The collection sends `X-Redirect-Manager-Key` from the `api_token` environment variable. In Craft, that value is configured as `REDIRECT_MANAGER_API_TOKEN`.
- Every normal request sends `Accept: application/json`.
- The JSON API is read-only. It lists enabled redirects; it does not create, update, delete, resolve redirects, increment hit counts, or write analytics.
