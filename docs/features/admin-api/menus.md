# Menus

Menus and the theme's menu locations. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

With `menus.edit` (a 403 otherwise), a menu is named by its name:

- `GET menus` lists them, as `menus` (`name`, `label`, `items` counted
  at every level, and the `locations` showing it), with the active
  theme's `locations` (`name`, `label`, `depth`, `defaults`, the number
  of default items, and the `menu` assigned), the theme's label
  (`theme`), the link `kinds`, and the site's `locale`.
- `GET menus/{name}` answers `menu`: its `name`, `label`, where it's kept
  (`where`), and its `items` as nodes, each the item's stored keys
  without `children` (`item`), what its link leads to now (`link`:
  `kind`, `value`, `ref`, `title`, `address`, and `state`, one of `live`,
  `draft`, `scheduled`, `hidden`, `trash`, or `missing`, with a
  `message` when it doesn't lead anywhere), and its `children`. A menu
  whose stored shape is wrong is `editable: false`, with its `problems`.
- `POST menus` writes a menu whole: `{was, name, label, items,
  locations}`, `was` `null` for a new one. A `name` other than `was`
  renames it. The theme's locations listed show it afterward, and those
  showing it that aren't listed go back to their default. It's refused
  (422, with `problems`) when an item has neither a label nor a link,
  more than one link, or a link that isn't written right, and (409) when
  the name is another menu's. It answers as `GET menus/{name}` does.
- `DELETE menus/{name}` removes a menu and its assignments, and answers
  what it was (`deleted`) and the `locations` it was in, which posted
  back put it back.
- `PUT menu-locations/{location}` with `{menu}` assigns a location a
  menu, or with `null` its default, and answers as `GET menus` does.
- `GET menu-links?search=` searches what an item can link to: entries
  (terms of types with term pages as `term` links), types' listings
  (`collection`), and named routes without parameters, as `links`
  shaped as a node's `link`, at most 10, with how many matched
  (`total`). The best matches come first: the title itself, then titles
  starting with the search, then a word in them; among matches as good,
  listings, terms, pages, other entries, then routes.
