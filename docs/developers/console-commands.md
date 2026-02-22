# Console Commands

Redirect Manager provides the following console commands.

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

### `redirect-manager/backup/create`

Creates a manual backup of all redirects.

```bash title="PHP"
php craft redirect-manager/backup/create
```

```bash title="DDEV"
ddev craft redirect-manager/backup/create
```

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--reason` | `string` | `'console'` | Reason for the backup |
| `--clean` | `bool` | `true` | Clean old backups after creating |

### `redirect-manager/backup/scheduled`

Runs the scheduled backup based on the `backupSchedule` setting. Checks the time elapsed since the last scheduled backup and creates a new one if due. Intended for cron jobs.

```bash title="PHP"
php craft redirect-manager/backup/scheduled
```

```bash title="DDEV"
ddev craft redirect-manager/backup/scheduled
```

### `redirect-manager/backup/list`

Lists all available backups with date, reason, size, and redirect count.

```bash title="PHP"
php craft redirect-manager/backup/list
```

```bash title="DDEV"
ddev craft redirect-manager/backup/list
```

### `redirect-manager/backup/clean`

Removes backups older than the configured `backupRetentionDays`. Does nothing if retention is set to `0` (keep forever).

```bash title="PHP"
php craft redirect-manager/backup/clean
```

```bash title="DDEV"
ddev craft redirect-manager/backup/clean
```
