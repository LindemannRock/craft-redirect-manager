# Redirect Manager — Manual CSV Fixtures

**Manual QA CSV fixtures** for the CSV import flow through the CP UI (Import/Export → upload → map columns → preview → import). Not loaded by PHPUnit — automated coverage is in `../Integration/ImportUrlValidationTest`.

Redirect Manager imports a **`sourceUrl`** (the pattern to match) and a **`destinationUrl`** (the redirect target). Both are required. Validation:

- **Source** must start with `/` or be an `http(s)://` URL **with a host** (regex/wildcard types also allow a leading `^`). A bare `https://` (no host) or an email-looking source is rejected.
- **Destination** (`RedirectRecord::isValidDestination()`, shared by the CP form and import) must be a relative path (not protocol-relative `//host`), an `http(s)` URL with a host, a recognized contact/app protocol (`mailto:`, `tel:`, `whatsapp:`, `sms:`, `fax:`, `skype:`, `slack://`, `msteams:`), or a capture reference (`$1`, `$2`, …).
- **Capture references** must be producible by the match type / source (`RedirectRecord::captureReferenceError()`): `exact` → none; `prefix` → `$1`; `wildcard` → one per `*` in the source; `regex` → one per capturing group. Referencing more than exist is rejected.
- Executable schemes (`javascript:`, `vbscript:`, `data:`, `file:`) are rejected up front by `UrlSafetyHelper::hasDangerousScheme()`, including whitespace/`//`-obfuscated variants.
- `ftp:` is **not** in the destination allowlist → rejected. Protocol-relative `//host` is rejected (resolves to an external origin). Note Redirect **accepts** `mailto:`/`tel:` (legitimate redirect targets), unlike ShortLink (which rejects them).
- `matchType` ∈ `exact|regex|wildcard|prefix` (unrecognized values default to `exact`). `statusCode` ∈ `301|302|303|307|308|410`.

All files share the header: `sourceUrl,destinationUrl,matchType,statusCode,enabled`.

## Test Files

### `redirect-valid.csv` — positive control
Should import cleanly: `http(s)` destination, relative `/new-page`, `mailto:`/`tel:` targets, a `regex` rule with a `$1` capture group, a `wildcard` rule, and a `410` (Gone) status. Also includes valid capture references: `wildcard` `/blog-wild/*` → `/news/$1` and `prefix` `/docs-prefix` → `/help/$1`.

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
- `//evil.com` destination (protocol-relative → **rejected**; the browser resolves `//host` to an external origin, so the shared validator blocks it)
- Email-looking source `/john@example.com` (→ "appears to be an email address")
- Path-like email destination `/john@example.com` (→ "use mailto: prefix"). Note: the friendly mailto hint only fires for a value that first passes the format check (i.e. has a leading `/`). A bare `john@example.com` is rejected one step earlier as "Invalid destination URL format".
- Invalid `statusCode` `999` (→ rejected)
- Bare-scheme destination `https://` (no host → rejected)
- Bare-scheme source `https://` (no host → rejected)
- Capture under `exact` `/cap-exact` → `/landing/$1` (exact produces no captures → rejected)
- `prefix` `/cap-prefix` → `/landing/$2` (prefix produces only `$1` → rejected)
- `regex` `^/no-group$` → `/landing/$1` (source has no capturing group → rejected)
- `wildcard` `/cap-wild/*` → `/landing/$2` (one `*` produces only `$1` → rejected)

## How to run a pass

1. **Baseline:** export current redirects to confirm the round-trip format.
2. **Valid:** import `redirect-valid.csv`; confirm all rows preview as importable, including `mailto:`/`tel:`/regex/wildcard/410.
3. **Malicious:** import `redirect-malicious.csv`; confirm every dangerous-scheme row (source or destination) lands in **errors** and none reach the DB.
4. **Edge cases:** import `redirect-edge-cases.csv`; confirm required-field, email, `ftp:`, bad-status, bare-scheme (`https://` in source and destination), `//evil.com`, and over-referenced capture (`$1`/`$2` beyond what the match type produces) rejections.

## Expected behavior summary

| Input | Expected |
|---|---|
| `javascript:`/`vbscript:`/`data:`/`file:` in source or destination (+ obfuscated) | Rejected |
| `http(s)` with a host, relative `/path`, `mailto:`/`tel:`/`whatsapp:`/…, valid capture ref (destination) | Accepted |
| `ftp:`, bare `domain.com` (destination) | Rejected |
| Bare `https://` (no host), in source or destination | Rejected |
| `//host` (destination) | Rejected (protocol-relative → resolves to an external origin) |
| Capture ref beyond match-type capacity (`$1` under exact; `$2` under prefix/one-`*` wildcard; `$1` with no regex group) | Rejected |
| Email-looking source or unprefixed-email destination | Rejected |
| `statusCode` outside `301/302/303/307/308/410` | Rejected |
| Missing `sourceUrl` or `destinationUrl` | Rejected |
