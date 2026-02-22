# Template Variables

Redirect Manager provides Twig variables for use in your templates.

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
