# Admin themes

Checking admin themes.

| Command | What it does |
|---|---|
| `admin-theme:check [name]` | Check every [admin theme](../../admin-themes/README.md)'s manifest, `tokens.json`, [requirements](../../plugins/requirements.md), and contrast, or one theme's, by name. One that's off is checked as if it were on. A theme that's turned on but can't be offered fails the command; one that's off, a broken one (a `tokens.json` that sets something it can't included), a `version` Composer can't read, an abandoned theme, text that's hard to read in either color scheme, and a `lang/` catalog whose `@@locale` or `@@domain` doesn't match are warnings. A file written to an older [schema version](../../concepts/extensions.md) is a warning, showing it written for the current one. |
