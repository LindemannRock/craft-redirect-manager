# Dashboard Widgets @since(5.1.0)

Redirect Manager provides two Craft dashboard widgets for at-a-glance visibility into your site's redirect health and 404 activity.

## Adding Widgets

1. Go to **Dashboard** in the Craft CP
2. Click **New Widget** (top right)
3. Select a Redirect Manager widget from the list
4. Configure and save

Both widgets require the `redirectManager:viewAnalytics` permission. Users without this permission will not see Redirect Manager widgets in the widget picker.

## Unhandled 404s Widget

Shows the current count of unhandled 404s — URLs that hit a 404 but have no matching redirect rule.

This widget gives you a quick indicator of how many broken links need attention. A count of zero means every tracked 404 is being redirected. A rising count signals new broken links that should be reviewed in the Analytics dashboard.

**Displayed data:**
- Total count of unhandled 404 URLs currently in the analytics table
- A link to the Analytics dashboard to review and act on them

## Analytics Summary Widget

An overview of recent 404 activity across your site, including:

- Total 404 hits (all time or over a recent period)
- Handled vs. unhandled breakdown
- A compact summary to spot trends at a glance

This widget is useful for content managers who want a quick read on redirect health without navigating to the full Analytics section.

## Permissions

Both widgets require:

```
redirectManager:viewAnalytics
```

Users with this permission can add and view the widgets. Sub-permissions (`exportAnalytics`, `clearAnalytics`) are not required for widget display.

See [Permissions](../developers/permissions.md) for the full permission hierarchy.
