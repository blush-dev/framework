# Menus

Listing, showing, assigning, and fixing menus.

| Command | What it does |
|---|---|
| `menu:list` | List your theme's menu locations, the [menu](../../concepts/menus.md) assigned to each (or the theme's default), where each menu is kept, and any problems: items that can't be shown, or a location assigned a menu you don't have. `--theme=` lists another theme's, by name. |
| `menu:show <location>` | Show what a location shows as a page sees it, with every URL. `--locale=fr` shows it in another language. |
| `menu:assign <location> [menu]` | Assign a menu to one of your theme's locations, by the menu's name (`menu:assign primary main`), or with `--clear` take it away, so the location shows the theme's default. Each theme keeps its own; `--theme=` assigns for another theme, by name. |
| `menu:refs` | List the menus with `entry` and `term` links not filed with the entry's id, or naming an entry that was renamed. `--write` files them. Fails when any are listed. |
