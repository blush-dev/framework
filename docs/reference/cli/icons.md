# Icons and icon packs

Listing icons and checking icon packs.

| Command | What it does |
|---|---|
| `icon:list` | List the icons your theme can use: each one's full name, its label, and the file that draws it. `--theme=` lists another theme's, by name. |
| `icon-pack:check [name]` | Check every [icon pack](../../icon-packs/README.md)'s manifest, [requirements](../../plugins/requirements.md), [conflicts](../../plugins/conflicts.md), and [replaces](../../plugins/replacing-extensions.md), or one pack's, by name, as `plugin:check` checks plugins. One that's off is checked as if it were on. A pack that's turned on but can't load fails the command; one that's off, a broken one, a `version` Composer can't read, an abandoned pack, and a `lang/` catalog whose `@@locale` or `@@domain` doesn't match are warnings. A file written to an older [schema version](../../concepts/extensions.md) is a warning, showing it written for the current one. |
