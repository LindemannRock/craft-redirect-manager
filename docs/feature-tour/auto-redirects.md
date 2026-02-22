# Auto-Redirects

Redirect Manager can automatically create a redirect whenever a Craft entry's URI changes. This keeps old URLs alive without any manual work, preventing broken links when content is reorganized.

## How It Works

When an entry is saved with a changed URI, the plugin runs a two-step process:

1. **`stashElementUri`** — Before the save, the plugin captures the element's current URI and stores it temporarily.
2. **`handleElementUriChange`** — After the save completes, the plugin compares the stored URI with the new URI. If they differ, a redirect is created from the old URI to the new one.

The resulting redirect uses match type `exact`, status code `301`, and the priority configured in settings (default `0` for auto-created rules).

### When Auto-Redirects Are Created

Auto-redirects are created when all of the following are true:

- `autoCreateRedirects` is `true` (the default)
- The entry has a URI (entries in sections with URIs enabled, or structure entries with a URI template)
- The URI has actually changed (not just the title or other fields)
- The entry is not brand new (no "old" URI to redirect from)

### When They Are NOT Created

Auto-redirects are **not** created when:

- `autoCreateRedirects` is `false`
- The entry is in a section with no URI template (e.g., a single-type section or a section with URIs disabled)
- The entry has no previous URI (first-time publish)
- The old and new URIs are identical

## Enabling and Disabling

Auto-redirect creation is enabled by default. Toggle it in the CP under **Redirect Manager > Settings**, or via config:

```php
// config/redirect-manager.php
'autoCreateRedirects' => true,
```

Set to `false` to disable entirely. You can also re-enable it later — existing redirects are unaffected.

## Undo Detection

A common workflow issue is the "flip-flop": a content editor changes a slug, then immediately changes it back. Without undo detection, this creates a redirect that would loop: `A → B` while the entry is back at `A`.

Redirect Manager solves this with an undo detection window. When a new URI change is detected, the plugin checks whether a redirect already exists pointing from the new URI back to the old URI and was created within the undo window. If so, it deletes that redirect instead of creating a new one.

**Example:**

1. Entry URI: `/about-us`
2. Editor changes slug to `/about` — plugin creates redirect `/about-us → /about`
3. Editor changes it back to `/about-us` (within undo window)
4. Plugin detects the flip-flop: deletes `/about-us → /about` instead of creating `/about → /about-us`
5. No redirect stacking, no loop

### Undo Window Setting

```php
// config/redirect-manager.php
'undoWindowMinutes' => 60, // 0, 30, 60, 120, or 240
```

| Value | Behavior |
|-------|----------|
| `0` | Undo detection disabled — always creates new redirects |
| `30` | 30-minute window |
| `60` | 60-minute window (default) |
| `120` | 2-hour window |
| `240` | 4-hour window |

Setting to `0` disables undo detection entirely. Use a longer window if your editors frequently revise slugs during a content session.

## Multi-Site Behavior

Auto-redirects are created per site. When a multi-site entry changes its URI, a redirect is created for each site where the URI changed. The redirect is scoped to that specific site's `siteId`.

## Reviewing Auto-Created Redirects

Auto-created redirects appear in the redirect list alongside manually created ones. They are identified by a `creationType` of `entry-change` in the underlying data. You can edit, disable, or delete them like any other redirect.

## Programmatic Integration

Other plugins can replicate this behavior using `RedirectHandlingTrait`. See [Plugin Integration](plugin-integration.md) for the full pattern including undo detection support.
