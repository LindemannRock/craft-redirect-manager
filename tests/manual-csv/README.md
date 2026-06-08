# Redirect Manager — Manual CSV Fixtures

**Manual QA CSV fixtures** for the CSV import flow through the CP UI (Import/Export → upload → map columns → preview → import). Not loaded by PHPUnit — automated coverage is in `../Integration/ImportUrlValidationTest`.

Redirect Manager imports a **`sourceUrl`** (the pattern to match) and a **`destinationUrl`** (the redirect target). Both are required. Validation:

- **Source** must start with `/` or `http(s)://` (regex/wildcard types also allow a leading `^`). An email-looking source is rejected.
- **Destination** (`ImportExportController::isValidDestinationFormat()`) must be a relative path, an `http(s)` URL, a recognized contact/app protocol (`mailto:`, `tel:`, `whatsapp:`, `sms:`, `fax:`, `skype:`, `slack://`, `msteams:`), or a regex capture group (`$1`, `$2`, …).
- Executable schemes (`javascript:`, `vbscript:`, `data:`, `file:`) are rejected up front by `UrlSafetyHelper::hasDangerousScheme()`, including whitespace/`//`-obfuscated variants.
- `ftp:` is **not** in the destination allowlist → rejected. Note Redirect **accepts** `mailto:`/`tel:` (legitimate redirect targets), unlike ShortLink (which rejects them).
- `matchType` ∈ `exact|regex|wildcard|prefix` (unrecognized values default to `exact`). `statusCode` ∈ `301|302|303|307|308|410`.

All files share the header: `sourceUrl,destinationUrl,matchType,statusCode,enabled`.

## Test Files

### `redirect-valid.csv` — positive control
Should import cleanly: `http(s)` destination, relative `/new-page`, `mailto:`/`tel:` targets, a `regex` rule with a `$1` capture group, a `wildcard` rule, and a `410` (Gone) status.

### `redirect-malicious.csv` — security
Should be blocked:
- `javascript:` in `destinationUrl` (plain, `//%0a`-obfuscated, leading-space)
- `data:text/html`, `vbscript:`, `file:///`
- `javascript:` in the **`sourceUrl`** (rejected as an invalid source format — extra coverage of the source path)

### `redirect-edge-cases.csv` — boundary conditions
- Empty source **and** destination (required → error)
- Source present, destination empty (required → error)
- Bare `example.com` destination (no scheme/leading `/` → rejected)
- `ftp://` destination (not in the allowlist → rejected)
- `//evil.com` destination (matches `^/` → **accepted**; resolves to an external origin — acceptable since redirects target external URLs by design)
- Email-looking source `/john@example.com` (→ "appears to be an email address")
- Path-like email destination `/john@example.com` (→ "use mailto: prefix"). Note: the friendly mailto hint only fires for a value that first passes the format check (i.e. has a leading `/`). A bare `john@example.com` is rejected one step earlier as "Invalid destination URL format".
- Invalid `statusCode` `999` (→ rejected)

## How to run a pass

1. **Baseline:** export current redirects to confirm the round-trip format.
2. **Valid:** import `redirect-valid.csv`; confirm all rows preview as importable, including `mailto:`/`tel:`/regex/wildcard/410.
3. **Malicious:** import `redirect-malicious.csv`; confirm every dangerous-scheme row (source or destination) lands in **errors** and none reach the DB.
4. **Edge cases:** import `redirect-edge-cases.csv`; confirm required-field, email, `ftp:`, and bad-status rejections, and that `//evil.com` behaves as documented.

## Expected behavior summary

| Input | Expected |
|---|---|
| `javascript:`/`vbscript:`/`data:`/`file:` in source or destination (+ obfuscated) | Rejected |
| `http(s)`, relative `/path`, `mailto:`/`tel:`/`whatsapp:`/…, regex `$1` (destination) | Accepted |
| `ftp:`, bare `domain.com` (destination) | Rejected |
| `//host` (destination) | Accepted (resolves external — by design for redirects) |
| Email-looking source or unprefixed-email destination | Rejected |
| `statusCode` outside `301/302/303/307/308/410` | Rejected |
| Missing `sourceUrl` or `destinationUrl` | Rejected |
