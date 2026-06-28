# Import / Export

Redirect Manager supports bulk redirect management through CSV import and export. Import hundreds of redirects at once with a guided column-mapping workflow. Export your full redirect library for backup or migration.

![The Import/Export page in the Redirect Manager Control Panel](images/import-export-main.webp)

## Importing Redirects

Navigate to **Redirect Manager > Import/Export** to start an import.

### Import Workflow

1. **Upload CSV** — Select and upload your CSV file. Maximum 4000 rows per import. For larger datasets, split into multiple files.
2. **Map Columns** — The plugin reads your CSV headers and presents a mapping screen. Match each CSV column to the corresponding redirect field.
3. **Preview** — Review a sample of parsed rows before committing. Any validation issues are flagged here.
4. **Import** — Confirm to run the import. The plugin processes each row and reports success/failure counts.

On the **Map CSV Columns** step, match each CSV column to a Redirect Manager field and use the sample data column to catch shifted or empty values before previewing the import:

![Mapping CSV columns to Redirect Manager fields](images/import-export-map.webp)

The **Preview Import** step summarizes total, valid, duplicate, and error rows, then lists the redirects that will be imported before anything is written:

![Import preview showing valid redirects and row counts](images/import-export-preview.webp)

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
| Match Type | `exact` | `exact`, `regex`, `wildcard`, `prefix` |
| Status Code | `301` | `301`, `302`, `303`, `307`, `308`, `410` |
| Priority | `0` | `0`–`9` (lower = higher priority) |
| Enabled | `true` | `true` or `false` |
| Site ID | `null` | Numeric site ID, or blank for all sites |

### Row Validation

Each row is validated before import; problems are flagged in the **Preview** errors bucket and those rows are skipped. A row is rejected when:

- **Source or Destination URL is missing or malformed** — a bare scheme (`https://` with no host), an email-looking value, or a protocol-relative `//host` is rejected. A destination may be a path, a full `http(s)://` URL with a host, a contact link (`mailto:`, `tel:`, `whatsapp:`, `sms:`, `fax:`, `skype://`, `slack:`, `msteams:`), or a capture reference (`$1`, `$2`).
- **A capture reference exceeds the match type** — e.g. `$1` under `exact`, or `$2` when the source has only one `*` / one capturing group. See [Match Types](redirects.md#match-types).
- **Match type or status code is invalid**, the row duplicates an existing redirect, or the source and destination are identical (a loop).

### Import Limits

The maximum is **4000 rows per import**. This limit ensures reliable operation across all hosting environments. For larger redirect libraries, split your CSV into batches of 4000 or fewer rows.

### Backup Before Import

By default, Redirect Manager creates a backup of your current redirects before processing an import. This ensures you can restore if something goes wrong.

```php
// config/redirect-manager.php
'backupOnImport' => true,
```

Disable this setting to skip the pre-import backup (not recommended for large imports).

### Import History @since(5.23.0)

Every import is logged in the Import History tab. Each entry shows:

- Import date and time
- Number of rows imported
- Success and error counts
- The filename used

This log is useful for auditing changes and understanding the state of your redirect library over time.

The `redirectManager:manageImportExport` permission is required to access the history tab. The `redirectManager:clearImportHistory` permission is required to delete history logs.

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
| Clear import history | `redirectManager:clearImportHistory` |

See [Permissions](../developers/permissions.md) for the full permission hierarchy.
