# Twig Globals

Redirect Manager provides the following global variables in your Twig templates.

## `redirectHelper` @since(5.0.0)

*Provided by `lindemannrock/base`*

| Property | Description |
|----------|-------------|
| `redirectHelper.displayName` | Display name (singular, without "Manager") |
| `redirectHelper.pluralDisplayName` | Plural display name (without "Manager") |
| `redirectHelper.fullName` | Full plugin name (as configured) |
| `redirectHelper.lowerDisplayName` | Lowercase display name (singular) |
| `redirectHelper.pluralLowerDisplayName` | Lowercase plural display name |

### Examples

```twig
{{ redirectHelper.displayName }}
{{ redirectHelper.pluralDisplayName }}
{{ redirectHelper.fullName }}
{{ redirectHelper.lowerDisplayName }}
{{ redirectHelper.pluralLowerDisplayName }}
```
