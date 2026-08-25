# Template variables

Use these variables when a frontend template needs Redirect Manager settings or analytics data. Most sites do not need them for normal redirect handling; the plugin catches 404s automatically before Twig renders.

## `craft.redirectManager`

### `getSettings()`

Returns the plugin settings model.

**Returns:** `\lindemannrock\redirectmanager\models\Settings`

Use it when template output depends on an effective plugin setting:

```twig
{% set redirectSettings = craft.redirectManager.getSettings() %}

{% if redirectSettings.enableAnalytics %}
    <p>404 analytics is enabled.</p>
{% endif %}
```

---

### `getPlugin()`

Returns the plugin instance.

**Returns:** `RedirectManager`

The plugin instance exposes standard Craft plugin properties such as its handle:

```twig
{% set redirectPlugin = craft.redirectManager.getPlugin() %}

<span data-plugin="{{ redirectPlugin.id }}">Redirect Manager</span>
```

---

### `getRedirectAnalytics()` @since(5.1.0)

Returns analytics data for a specific redirect.

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `redirectId` | `int` | — | The redirect ID |
| `dateRange` | `string` | `'last30days'` | Date range filter |

**Returns:** `array`

Pass a redirect ID and an optional date range, then use the returned summary:

```twig
{% set analytics = craft.redirectManager.getRedirectAnalytics(42, 'last30days') %}

<p>{{ analytics.totalHits }} redirect hits in the last 30 days.</p>

{% if analytics.deviceBreakdown %}
    <ul>
        {% for device, hits in analytics.deviceBreakdown %}
            <li>{{ device }}: {{ hits }}</li>
        {% endfor %}
    </ul>
{% endif %}
```

The result contains `totalHits`, `recordCount`, `deviceBreakdown`, `browserBreakdown`, `countryBreakdown`, `referrerBreakdown`, and `botVsHuman`.
