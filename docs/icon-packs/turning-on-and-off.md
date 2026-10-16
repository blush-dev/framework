# Turning icon packs on and off

A pack in your site's `extensions/` folder is off until it's turned on,
like a local plugin: in **Extend → Icon Packs**, or in
`config/icons.php`'s `enabled` list (see
[Installing extensions](../concepts/installing-extensions.md)).

Every installed pack that's on has its icons in `bin/blush icon:list`
and the admin's icon inserter. Turn one off on the admin's
[Icon Packs](../admin/icon-packs.md) screen, or by its name in
[`config/icons.php`](../reference/configuration/README.md); its icons
then show nowhere.

## Restyling an icon from a theme

A theme (or a child theme) can restyle one with
`icons/brands/github.svg` (see the [icon block](../concepts/blocks/README.md)).
