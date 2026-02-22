# Import / Export

Redirect Manager supports bulk redirect management through CSV import and export. Import hundreds of redirects at once with a guided column-mapping workflow. Export your full redirect library for backup or migration.

## Importing Redirects

Navigate to **Redirect Manager > Import/Export** to start an import.

### Import Workflow

1. **Upload CSV** — Select and upload your CSV file. Maximum 4000 rows per import. For larger datasets, split into multiple files.
2. **Map Columns** — The plugin reads your CSV headers and presents a mapping screen. Match each CSV column to the corresponding redirect field.
3. **Preview** — Review a sample of parsed rows before committing. Any validation issues are flagged here.
4. **Import** — Confirm to run the import. The plugin processes each row and reports success/failure counts.

### CSV Format

Your CSV file must have a header row. Column names do not need to match exactly — you map them in step 2.

Required fields for each redirect:

| Field | Description | Example |
|-------|-------------|---------|
| Source URL | The incoming URL pattern | `/old-page` |
| Destination URL | Where to redirect | `/new-page` |

Optional fields (defaults are used when omitted):

| Field | Default | Description |
|-------|---------|-------------|
| Match Type | `exact` | `exact`, `contains`, `regex`, `wildcard`, `prefix` |
| Status Code | `301` | `301`, `302`, `303`, `307`, `308`, `410` |
| Priority | `0` | `0`–`9` (lower = higher priority) |
| Enabled | `true` | `true` or `false` |
| Site ID | `null` | Numeric site ID, or blank for all sites |

### Import Limits

The maximum is **4000 rows per import**. This limit ensures reliable operation across all hosting environments. For larger redirect libraries, split your CSV into batches of 4000 or fewer rows.

### Backup Before Import

By default, Redirect Manager creates a backup of your current redirects before processing an import. This ensures you can restore if something goes wrong.

```php
// config/redirect-manager.php
'backupOnImport' => true,
```

Disable this setting to skip the pre-import backup (not recommended for large imports).

### Import History @since(5.24.0)

Every import is logged in the Import History tab. Each entry shows:

- Import date and time
- Number of rows imported
- Success and error counts
- The filename used

This log is useful for auditing changes and understanding the state of your redirect library over time.

The `redirectManager:viewImportHistory` permission is required to access the history tab. The `redirectManager:clearImportHistory` permission is required to delete history logs.

### Clearing Import History

Import history can be cleared from the Import History tab. This permanently deletes the log entries but does not affect the redirects themselves.

## Exporting Redirects

To export your full redirect list as CSV:

1. Go to **Redirect Manager > Import/Export**
2. Click **Export Redirects**

The export includes all fields: source URL, destination URL, match type, status code, priority, enabled status, hit count, and creation date.

The `redirectManager:exportRedirects` permission is required.

## Permissions Summary

| Action | Permission Required |
|--------|---------------------|
| Access Import/Export section | `redirectManager:manageImportExport` |
| Import redirects from CSV | `redirectManager:importRedirects` |
| Export redirects to CSV | `redirectManager:exportRedirects` |
| View import history | `redirectManager:viewImportHistory` |
| Clear import history | `redirectManager:clearImportHistory` |

See [Permissions](../developers/permissions.md) for the full permission hierarchy.
