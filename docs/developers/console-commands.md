# Console commands

Use these commands for setup tasks, backup maintenance, and operator discovery. Most day-to-day redirect management happens in the Control Panel, but these commands are useful during deploys, cron jobs, and incident response.

## Console help

Use the plugin-level help command to see the available Redirect Manager commands and focused guidance for each workflow:

```bash title="PHP"
php craft redirect-manager/help
php craft redirect-manager/help backup/create
php craft redirect-manager/help security/generate-salt
php craft redirect-manager/help security/generate-api-token
```

```bash title="DDEV"
ddev craft redirect-manager/help
ddev craft redirect-manager/help backup/create
ddev craft redirect-manager/help security/generate-salt
ddev craft redirect-manager/help security/generate-api-token
```

Craft's native command help is still available when you need the exact Yii option signature:

```bash title="PHP"
php craft help redirect-manager/backup/create
php craft help redirect-manager/security/generate-salt
php craft help redirect-manager/security/generate-api-token
```

```bash title="DDEV"
ddev craft help redirect-manager/backup/create
ddev craft help redirect-manager/security/generate-salt
ddev craft help redirect-manager/security/generate-api-token
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

### `redirect-manager/security/generate-api-token` @since(5.33.0)

Generates a cryptographically secure token for the read-only JSON redirects endpoint. The command prints the token, asks whether to write it to `.env` as `REDIRECT_MANAGER_API_TOKEN`, and asks before replacing an existing token.

```bash title="PHP"
php craft redirect-manager/security/generate-api-token
```

```bash title="DDEV"
ddev craft redirect-manager/security/generate-api-token
```

Rotating this token immediately invalidates external consumers that still send the previous value.

## Backups @since(5.24.0)

### `redirect-manager/backup/create` @since(5.24.0)

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

### `redirect-manager/backup/scheduled` @since(5.24.0)

Runs the scheduled backup based on the `backupSchedule` setting. Checks the time elapsed since the last scheduled backup and creates one if due. Redirect Manager normally schedules backups through Craft's queue; this command is useful for manual checks or direct cron setups.

```bash title="PHP"
php craft redirect-manager/backup/scheduled
```

```bash title="DDEV"
ddev craft redirect-manager/backup/scheduled
```

### `redirect-manager/backup/list` @since(5.24.0)

Lists all available backups with date, reason, size, and redirect count.

```bash title="PHP"
php craft redirect-manager/backup/list
```

```bash title="DDEV"
ddev craft redirect-manager/backup/list
```

### `redirect-manager/backup/clean` @since(5.24.0)

Removes backups older than the configured `backupRetentionDays`. Does nothing if retention is set to `0` (keep forever).

```bash title="PHP"
php craft redirect-manager/backup/clean
```

```bash title="DDEV"
ddev craft redirect-manager/backup/clean
```
