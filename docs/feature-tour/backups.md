# Backups @since(5.23.0)

Redirect Manager can automatically back up your redirect library before imports and on a scheduled basis. Backups are stored locally or in a Craft asset volume, and can be restored from the CP or CLI.

## How Backups Work

A backup is a snapshot of your redirect library at a point in time, saved as a file in a configured storage location. Backups are created:

- **Automatically before CSV imports** (when `backupOnImport` is `true`)
- **On a schedule** (daily, weekly, or monthly, when `backupSchedule` is not `disabled`)
- **Manually** from the Backups CP section or via console command

## Configuration

```php
// config/redirect-manager.php
'backupEnabled'       => true,
'backupOnImport'      => true,
'backupSchedule'      => 'disabled', // 'disabled', 'daily', 'weekly', 'monthly'
'backupRetentionDays' => 30,       // 0 = keep forever, max 365
'backupPath'          => '@storage/redirect-manager/backups',
'backupVolumeUid'     => null,     // Asset volume UID (optional)
```

### Storage Location

By default, backups are stored on the local filesystem at `@storage/redirect-manager/backups`. This path supports Craft's `@storage` and `@root` aliases plus `$VARIABLE` environment variable substitution. Environment variables must resolve inside Craft's storage directory or a project-root subfolder.

To store backups in a Craft asset volume instead, set `backupVolumeUid` to the UID of the target volume. You can find volume UIDs in **Settings > Assets**. Local volumes cannot resolve inside `@webroot`, so public upload volumes are rejected for backup storage. Remote volumes such as Amazon S3 are allowed; configure bucket/object access policies in the storage provider so backups are private.

When both `backupPath` and `backupVolumeUid` are set, the volume takes precedence.

### Scheduled Backups

| Value | Behavior |
|-------|----------|
| `disabled` | No automatic schedule; backups only run on demand or before imports |
| `daily` | Backup runs once per day (via `redirect-manager/backup/scheduled` console command) |
| `weekly` | Backup runs once per week |
| `monthly` | Backup runs once per month |

Scheduled backups require a cron job that runs `redirect-manager/backup/scheduled`. The command checks the configured schedule and skips the run if a backup has already been created in the current period.

### Retention

Set `backupRetentionDays` to control how long backups are kept:

```php
'backupRetentionDays' => 30, // delete backups older than 30 days
```

Set to `0` to keep all backups indefinitely. The maximum value is `365` days. Cleanup runs automatically when a new backup is created.

## Managing Backups in the CP

Navigate to **Redirect Manager > Backups** to:

- View a list of existing backups with timestamps and file sizes
- Create a manual backup
- Download a backup as a ZIP file
- Restore redirects from a backup
- Delete individual backups

Downloaded ZIP files are portable archives. To use one on another install without an upload flow, extract the ZIP and place its files in the expected backup folder structure under that install's configured backup storage.

## Restoring from a Backup

To restore your redirect library to a previous state:

1. Go to **Redirect Manager > Backups**
2. Find the backup you want to restore
3. Click **Restore**
4. Confirm the action — this will replace your current redirects with the backup contents

> [!WARNING]
> Restoring a backup replaces your current redirect library. If you want to keep your current redirects, create a manual backup first.

Restore requires an intact backup folder with `metadata.json`, `redirects.json`, and a valid SHA-256 checksum in the metadata. Backups with missing metadata, missing checksum data, or modified JSON contents are rejected before redirects are replaced.

## Console Commands

### Create a Backup

```bash title="PHP"
php craft redirect-manager/backup/create
```

```bash title="DDEV"
ddev craft redirect-manager/backup/create
```

Options:

| Option | Description |
|--------|-------------|
| `--reason=<text>` | Optional label for the backup (e.g., `--reason="before-migration"`) |
| `--clean` | Run retention cleanup after creating the backup |

### Run Scheduled Backup

Checks whether a backup is due based on `backupSchedule` and creates one if needed:

```bash title="PHP"
php craft redirect-manager/backup/scheduled
```

```bash title="DDEV"
ddev craft redirect-manager/backup/scheduled
```

Add this to your server's cron schedule to automate backups:

```cron
0 2 * * * /path/to/craft redirect-manager/backup/scheduled
```

### List Backups

```bash title="PHP"
php craft redirect-manager/backup/list
```

```bash title="DDEV"
ddev craft redirect-manager/backup/list
```

### Clean Up Old Backups

Removes backups that exceed the retention period:

```bash title="PHP"
php craft redirect-manager/backup/clean
```

```bash title="DDEV"
ddev craft redirect-manager/backup/clean
```

## Permissions

| Action | Permission Required |
|--------|---------------------|
| Access Backups section | `redirectManager:manageBackups` |
| Create manual backup | `redirectManager:createBackups` |
| Download backup files | `redirectManager:downloadBackups` |
| Restore from a backup | `redirectManager:restoreBackups` |
| Delete backup files | `redirectManager:deleteBackups` |

See [Permissions](../developers/permissions.md) for the full permission hierarchy.
