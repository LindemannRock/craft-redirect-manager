# Permissions

Redirect Manager registers granular permissions that can be assigned to user groups via **Settings → Users → User Groups → [Group Name] → Redirect Manager**.

## Permission Structure

### Redirects

| Permission | Description |
|------------|-------------|
| **`redirectManager:manageRedirects`** | Access the redirects section (view and access) |
| └─ `redirectManager:createRedirects` | Create new redirects |
| └─ `redirectManager:editRedirects` | Edit existing redirects |
| └─ `redirectManager:deleteRedirects` | Delete redirects |

### Import/Export

| Permission | Description |
|------------|-------------|
| **`redirectManager:manageImportExport`** | Access the import/export section (view and access) |
| └─ `redirectManager:importRedirects` | Import redirects |
| └─ `redirectManager:exportRedirects` | Export redirects |
| └─ `redirectManager:viewImportHistory` | View import history |
| └─ `redirectManager:clearImportHistory` | Clear import history |

### Backups

| Permission | Description |
|------------|-------------|
| **`redirectManager:manageBackups`** | Access the backups section (view and access) |
| └─ `redirectManager:createBackups` | Create backups |
| └─ `redirectManager:downloadBackups` | Download backups |
| └─ `redirectManager:restoreBackups` | Restore backups |
| └─ `redirectManager:deleteBackups` | Delete backups |

### Analytics

| Permission | Description |
|------------|-------------|
| **`redirectManager:viewAnalytics`** | Parent — view the analytics dashboard |
| └─ `redirectManager:exportAnalytics` | Export analytics data |
| └─ `redirectManager:clearAnalytics` | Clear analytics data |

### Cache

| Permission | Description |
|------------|-------------|
| `redirectManager:clearCache` | Clear redirect caches |

### Logs

| Permission | Description |
|------------|-------------|
| **`redirectManager:viewLogs`** | Parent — view plugin logs |
| └─ **`redirectManager:viewSystemLogs`** | View system-level logs |
|     └─ `redirectManager:downloadSystemLogs` | Download system log files |

### Settings

| Permission | Description |
|------------|-------------|
| `redirectManager:manageSettings` | Access and modify plugin settings |

## Checking Permissions

In Twig:

```twig
{% if currentUser.can('redirectManager:manageRedirects') %}
    {# Show redirects management UI #}
{% endif %}

{% if currentUser.can('redirectManager:viewAnalytics') %}
    <a href="{{ url('redirect-manager/analytics') }}">View Analytics</a>
{% endif %}
```

In PHP:

```php
if (Craft::$app->getUser()->checkPermission('redirectManager:manageRedirects')) {
    // User has permission
}

// In a controller
$this->requirePermission('redirectManager:manageRedirects');
```

## Nested Permission Pattern

Craft's nested permissions are a UI convenience — the parent permission does not automatically grant child permissions at runtime.

- **"Manage" permissions** (e.g., `manageRedirects`) are the access/view permission — checking this grants visibility of the section in the CP subnav
- **Write permissions** (e.g., `createRedirects`, `editRedirects`, `deleteRedirects`) are nested under manage and control specific write operations

To give a user read-only access, grant only `manageRedirects`. For full access, also grant the specific write permissions needed.
