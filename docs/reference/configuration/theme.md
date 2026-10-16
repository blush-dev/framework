# Theme

Which theme is active.

`config/theme.php` · `Blush\Theme\ThemeConfig`

| Option | Default | What it does |
|---|---|---|
| `active` | `'blush/default'` | The active theme's name, such as `'acme/notebook'` |

`bin/blush theme:activate` writes this file for you. A theme activated
on the admin's [Themes](../../admin/themes.md) screen wins over it.
