# Icon packs

With `extensions.icon-packs.view`, **Extend → Icon Packs** shows every
installed [icon pack](../icon-packs/README.md), and turns them on and off.

## The cards

Each pack is shown by label, as a card of its first
icons, with its version, where it's installed, how many icons it has,
and a switch. A pack that's off adds no icons, so anywhere one of them
is used shows nothing. A pack whose [requirements](../plugins/requirements.md)
aren't met can't be turned on, and says what it needs; one that's on
adds no icons until they're met. Turning a pack off names any plugins
that stopped with it. Blush's own icons are the **Core** card, always
on. A pack that can't be used is listed by where it was found, with the
reason. Only icon packs are listed, not the icons themes and plugins
carry. The filters above the cards work as on [Themes](themes.md#filters), and
so does the switch to a compact list, where each row is marked with
the pack's first icon.

## On and off

A pack in `extensions/` is off until it's turned on here or named in
`config/icons.php`'s `enabled` list; a Composer pack is on. As with
plugins, once a switch is used the saved list names every pack that's
on, Composer's included. The switches are saved in `user/data/settings/icons.json`, over that list, with
**Use `config/icons.php`'s list** to go back.

## A pack's details

A pack's name, or **Icon pack details** in its menu,
opens its details: every icon in it, with a filter, then **Details** and
**Dependencies**, as a plugin's are, with its namespace and how many
icons it has; click one to copy how it's used
(`weather/sun`). **Delete Icon Pack** removes a pack's folder (or a
broken pack's) from `extensions/`. **Install Icon Pack** installs one
from a `.zip`, as **Install Theme** does (see
[Installing extensions](installing-extensions.md)); it arrives turned off.
