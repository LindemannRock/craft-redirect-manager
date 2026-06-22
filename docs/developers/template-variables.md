# Template variables

Use these variables when a frontend template needs Redirect Manager settings or analytics data. Most sites do not need them for normal redirect handling; the plugin catches 404s automatically before Twig renders.

## `craft.redirectManager`

### `getSettings()` @since(5.0.0)

Returns the plugin settings model.

**Returns:** `\lindemannrock\redirectmanager\models\Settings`

---

### `getPlugin()` @since(5.0.0)

Returns the plugin instance.

**Returns:** `RedirectManager`

---

### `getRedirectAnalytics()` @since(5.1.0)

Returns analytics data for a specific redirect.

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `redirectId` | `int` | — | The redirect ID |
| `dateRange` | `string` | `'last30days'` | Date range filter |

**Returns:** `array`
