# Console Commands

Redirect Manager provides the following console commands.

## Console Help

Use the plugin-level help command to see the available Redirect Manager commands and focused guidance for each workflow:

```bash title="DDEV"
ddev craft redirect-manager/help
ddev craft redirect-manager/help backup/create
ddev craft redirect-manager/help security/generate-salt
```

Craft's native command help is still available when you need the exact Yii option signature:

```bash title="DDEV"
ddev craft help redirect-manager/backup/create
ddev craft help redirect-manager/security/generate-salt
```

## Security

### `redirect-manager/security/generate-salt` @since(5.1.0)

Generates a cryptographically secure IP hash salt and adds it to your `.env` file as `REDIRECT_MANAGER_IP_SALT`.

```bash title="PHP"
php craft redirect-manager/security/generate-salt
```

```bash title="DDEV"
ddev craft redirect-manager/security/generate-salt
```

## Backups @since(5.23.0)

### `redirect-manager/backup/create` @since(5.23.0)

Creates a manual backup of all redirects. By default, the command also cleans old backups when backup retention is enabled.

```bash title="PHP"
php craft redirect-manager/backup/create
```

```bash title="DDEV"
ddev craft redirect-manager/backup/create
```

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--reason` | `?string` | `'console'` | Reason for the backup |
| `--clean` | `bool` | `true` | Clean old backups after creating |

### `redirect-manager/backup/scheduled` @since(5.23.0)

Runs the scheduled backup based on the `backupSchedule` setting. Checks the time elapsed since the last scheduled backup and creates a new one if due. Intended for cron jobs.

```bash title="PHP"
php craft redirect-manager/backup/scheduled
```

```bash title="DDEV"
ddev craft redirect-manager/backup/scheduled
```

### `redirect-manager/backup/list` @since(5.23.0)

Lists all available backups with date, reason, size, and redirect count.

```bash title="PHP"
php craft redirect-manager/backup/list
```

```bash title="DDEV"
ddev craft redirect-manager/backup/list
```

### `redirect-manager/backup/clean` @since(5.23.0)

Removes backups older than the configured `backupRetentionDays`. Does nothing if retention is set to `0` (keep forever).

```bash title="PHP"
php craft redirect-manager/backup/clean
```

```bash title="DDEV"
ddev craft redirect-manager/backup/clean
```
