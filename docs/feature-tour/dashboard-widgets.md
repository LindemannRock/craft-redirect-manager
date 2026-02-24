# Dashboard Widgets @since(5.1.0)

Redirect Manager provides two Craft dashboard widgets for at-a-glance visibility into your site's redirect health and 404 activity.

## Adding Widgets

1. Go to **Dashboard** in the Craft CP
2. Click **New Widget** (top right)
3. Select a Redirect Manager widget from the list
4. Configure and save

Both widgets require the `redirectManager:viewAnalytics` permission. Users without this permission will not see Redirect Manager widgets in the widget picker.

## Unhandled 404s Widget

Lists the most common unhandled 404s — URLs that hit a 404 but have no matching redirect rule. This gives you a quick, actionable view of broken links without navigating to the full Analytics section.

**Displayed data:**
- A list of the top unhandled 404 URLs, sorted by hit count
- A link to the Analytics dashboard to review and act on them

**Widget settings:**

| Setting | Default | Range | Description |
|---------|---------|-------|-------------|
| `limit` | `10` | 5–50 | Maximum number of unhandled 404 URLs to display |

## Analytics Summary Widget

An overview of recent 404 activity across your site, including:

- Total 404 hits in the configured time window
- Handled vs. unhandled breakdown and percentage
- A compact summary to spot trends at a glance

This widget is useful for content managers who want a quick read on redirect health without navigating to the full Analytics section.

**Widget settings:**

| Setting | Default | Range | Description |
|---------|---------|-------|-------------|
| `days` | `7` | 1–365 | Number of days of 404 activity to show |

## Permissions

Both widgets require:

```
redirectManager:viewAnalytics
```

Users with this permission can add and view the widgets. Sub-permissions (`exportAnalytics`, `clearAnalytics`) are not required for widget display.

See [Permissions](../developers/permissions.md) for the full permission hierarchy.
