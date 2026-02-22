# Plugin Integration @since(5.3.0)

Redirect Manager provides a pluggable architecture that lets other plugins participate in its redirect system. By using `RedirectHandlingTrait`, a plugin can query Redirect Manager when it encounters a 404, create redirect rules on demand, and detect undo situations — all while keeping both plugins loosely coupled.

## RedirectHandlingTrait Overview

`RedirectHandlingTrait` is a PHP trait located at `src/traits/RedirectHandlingTrait.php`. Include it in any controller, service, or component in your plugin. It provides three methods:

| Method | Returns | Purpose |
|--------|---------|---------|
| `handleRedirect404(string $url, string $source, array $context)` | `?array` | Check if Redirect Manager has a matching redirect for a 404 URL |
| `createRedirectRule(array $attributes, bool $showNotification)` | `bool` | Create a new redirect rule in Redirect Manager |
| `handleUndoRedirect(string $oldUrl, string $newUrl, int $siteId, string $creationType, string $sourcePlugin)` | `bool` | Detect and handle an immediate undo (flip-flop redirect within the undo window) |

> [!NOTE]
> The trait only provides these three methods. Functions like `handleDeletedItem()` or `handle404()` are examples you write in your own plugin. The trait's methods are the building blocks you call from those functions.

## Integration Pattern 1: Handling 404s

When your plugin encounters a 404, call `handleRedirect404()` to check whether Redirect Manager has a matching redirect. If it does, use the returned data to issue the redirect.

```php
use lindemannrock\redirectmanager\traits\RedirectHandlingTrait;

class MyController extends Controller
{
    use RedirectHandlingTrait;

    private function handle404(): Response
    {
        $url = Craft::$app->getRequest()->getUrl();

        $redirect = $this->handleRedirect404($url, 'my-plugin', [
            'type'    => 'custom-404',
            'context' => 'additional-metadata',
        ]);

        if ($redirect) {
            return $this->redirect($redirect['destinationUrl'], $redirect['statusCode']);
        }

        // No redirect found — use your fallback
        return $this->redirect('/', 302);
    }
}
```

**What happens behind the scenes:**

1. Your plugin calls `handleRedirect404()` with the URL and your plugin's handle as `$source`
2. Redirect Manager searches for a matching redirect rule
3. If found, the 404 is recorded in analytics as "handled" with your plugin as the source
4. If not found, it's recorded as "unhandled" with your plugin as the source
5. The method returns the redirect array or `null`

## Integration Pattern 2: Creating Redirects

When your plugin performs an operation that should create a redirect (item deleted, slug changed, link expired), call `createRedirectRule()`.

```php
use lindemannrock\redirectmanager\traits\RedirectHandlingTrait;

class MyService extends Component
{
    use RedirectHandlingTrait;

    public function handleDeletedItem($item): void
    {
        if ($item->hits === 0) {
            return; // Don't create redirect for unused items
        }

        $this->createRedirectRule([
            'sourceUrl'       => '/items/' . $item->slug,
            'sourceUrlParsed' => '/items/' . $item->slug,
            'destinationUrl'  => '/items',
            'matchType'       => 'exact',
            'redirectSrcMatch'=> 'pathonly',   // REQUIRED
            'statusCode'      => 301,
            'siteId'          => $item->siteId,
            'enabled'         => true,
            'priority'        => 0,
            'creationType'    => 'item-deleted',   // max 50 chars
            'sourcePlugin'    => 'my-plugin',      // kebab-case, max 50 chars
        ], true); // true = show "Redirect created" CP notification
    }
}
```

## Integration Pattern 3: Slug Changes with Undo Detection

When a slug changes, first call `handleUndoRedirect()` to check whether the change is an immediate reversal of a previous change. If the undo is detected, the old redirect is removed and the method returns `true` — no new redirect should be created. If it returns `false`, proceed with `createRedirectRule()`.

```php
use lindemannrock\redirectmanager\traits\RedirectHandlingTrait;

class MyService extends Component
{
    use RedirectHandlingTrait;

    public function handleSlugChange(string $oldSlug, MyElement $element): void
    {
        $oldUrl = '/items/' . $oldSlug;
        $newUrl = '/items/' . $element->slug;

        // Check for immediate undo (A→B then B→A within the undo window)
        if ($this->handleUndoRedirect($oldUrl, $newUrl, $element->siteId, 'item-slug-change', 'my-plugin')) {
            return; // Undo detected and handled — no new redirect needed
        }

        // No undo — create the redirect
        $this->createRedirectRule([
            'sourceUrl'       => $oldUrl,
            'sourceUrlParsed' => $oldUrl,
            'destinationUrl'  => $newUrl,
            'matchType'       => 'exact',
            'redirectSrcMatch'=> 'pathonly',
            'statusCode'      => 301,
            'siteId'          => $element->siteId,
            'enabled'         => true,
            'priority'        => 0,
            'creationType'    => 'item-slug-change',
            'sourcePlugin'    => 'my-plugin',
        ], true);
    }
}
```

## Method Reference

### `handleRedirect404(string $url, string $source, array $context = []): ?array`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$url` | `string` | The 404 URL to look up |
| `$source` | `string` | Your plugin handle in kebab-case (e.g., `'shortlink-manager'`) |
| `$context` | `array` | Optional metadata about the 404 (stored in analytics) |

Returns the matching redirect as an array with `destinationUrl` and `statusCode` keys, or `null` if no match.

### `createRedirectRule(array $attributes, bool $showNotification = false): bool`

| Attribute | Required | Description |
|-----------|----------|-------------|
| `sourceUrl` | Yes | The source URL pattern (max 255 chars) |
| `sourceUrlParsed` | Yes | Normalized form of `sourceUrl` (max 255 chars) |
| `destinationUrl` | Yes | The redirect destination (max 500 chars) |
| `matchType` | Yes | `exact`, `contains`, `regex`, `wildcard`, or `prefix` |
| `redirectSrcMatch` | **Yes** | `pathonly` or `fullurl` — this field is required |
| `statusCode` | Yes | HTTP status code (301, 302, etc.) |
| `siteId` | No | Craft site ID, or `null` for all sites |
| `enabled` | No | Whether the redirect is active (default `true`) |
| `priority` | No | 0–9, lower = higher priority (default `0`) |
| `creationType` | No | What triggered creation (max 50 chars, e.g., `'item-deleted'`) |
| `sourcePlugin` | No | Your plugin handle in kebab-case (max 50 chars) |
| `elementId` | No | Element ID for tracking redirect chains |

Returns `true` on success, `false` on failure.

### `handleUndoRedirect(string $oldUrl, string $newUrl, int $siteId, string $creationType, string $sourcePlugin): bool`

Looks for an existing redirect created within the undo window where `source = $newUrl` and `destination = $oldUrl`. If found, deletes it and returns `true`. Otherwise returns `false`.

Returns `true` if an undo was detected and handled, `false` otherwise.

## Constraints

| Field | Constraint |
|-------|------------|
| `creationType` | Maximum 50 characters |
| `sourcePlugin` | Maximum 50 characters, always kebab-case |
| `sourceUrl`, `sourceUrlParsed` | Maximum 255 characters |
| `destinationUrl` | Maximum 500 characters |

Plugin handle format:

```
✓  'shortlink-manager'   (kebab-case)
✓  'smart-links'
✗  'ShortLink Manager'   (no spaces or title case)
✗  'shortlink_manager'   (no underscores)
```

## Source Attribution in Analytics

Every 404 reported through `handleRedirect404()` is attributed to the source plugin you pass as `$source`. The analytics dashboard shows a breakdown by source:

```
redirect-manager:    145 (handled: 98)
shortlink-manager:    67 (handled: 54)
smart-links:          43 (handled: 38)
```

This lets you see at a glance which plugin or area of your site is generating the most 404s.

## Real-World Example

ShortLink Manager integrates Redirect Manager in two places:

- **404 Handling** (`RedirectController::redirectToNotFound()`): When a shortlink is not found, it calls `handleRedirect404()`. If a redirect exists, it fires. Otherwise, falls back to the configured not-found URL.
- **Auto-Redirect Creation** (`ShortLinksService`): When a shortlink code changes, expires, or is deleted, it calls `handleUndoRedirect()` then `createRedirectRule()` to keep the old code redirecting to the right place.

## Benefits of Integration

- **Centralized 404 tracking** — All 404s across your whole site in one dashboard
- **Auto-healing** — Broken links automatically resolved when redirects exist
- **Source attribution** — See which plugin or area generates the most misses
- **Loose coupling** — Both plugins work independently; integration is optional and additive
- **Undo-safe** — Built-in flip-flop detection prevents redirect stacking
