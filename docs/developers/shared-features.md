# Shared features

Redirect Manager builds on shared LindemannRock packages instead of duplicating common plugin infrastructure. This page explains which behavior comes from the base plugin or Logging Library so developers know where a setting, helper, or UI pattern originates.

## `lindemannrock/base`

| Feature | Description |
|---------|-------------|
| `PluginHelper::bootstrap()` | Initializes base module, Twig globals, and logging configuration |
| `PluginHelper::applyPluginNameFromConfig()` | Overrides plugin name from config file |
| `GqlHelper` | Shared schema permission checks plus `site` / `siteId` argument resolution |
| `SettingsConfigTrait` | Config file override detection and log level validation |
| `SettingsDisplayNameTrait` | Standardized plugin name helper methods |
| `SettingsPersistenceTrait` | Database persistence for Settings models |
| `ColorHelper` | Color palette utilities for status badge color sets |
| `GeoHelper` | Geographic utilities (country code to name conversion) |

### Details

- `PluginHelper::bootstrap()` initializes the base module, plugin-name Twig helpers, shared templates, and logging configuration.
- `PluginHelper::applyPluginNameFromConfig()` lets `config/redirect-manager.php` override the Control Panel display name.
- `GqlHelper` handles Craft GraphQL schema permission checks, `site` / `siteId` argument resolution, virtual site fields, and empty string normalization. Redirect Manager still owns its query names, field list, matching behavior, and analytics side effects.
- `SettingsConfigTrait` detects config-file overrides so disabled Control Panel fields show the correct warning.
- `SettingsDisplayNameTrait` provides display-name helpers such as `getDisplayName()`, `getFullName()`, and `getPluralDisplayName()`.
- `SettingsPersistenceTrait` stores settings in the plugin database table with type conversion for boolean, integer, float, and JSON fields.
- `ColorHelper` provides palette colors for request type, match type, and creation type badges registered through `PluginHelper::bootstrap()`.
- `GeoHelper` provides ISO 3166-1 alpha-2 country code utilities.

---

## `lindemannrock/logging-library`

| Feature | Description |
|---------|-------------|
| `LoggingLibrary::configure()` | Dedicated plugin logging configuration |
| `LoggingTrait` | Convenient logging methods (logInfo, logWarning, logError, logDebug) |
| `LoggingLibrary::addLogsNav()` | Adds "Logs" subnav to plugin CP navigation |

### Details

- `LoggingLibrary::configure()` writes dedicated log files at `storage/logs/{plugin-handle}-{date}.log`.
- `LoggingTrait` provides standardized plugin log methods such as `logInfo()`, `logWarning()`, `logError()`, and `logDebug()`.
- `LoggingLibrary::addLogsNav()` adds the Logs section to the Redirect Manager Control Panel navigation.
