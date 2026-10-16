# Menus in the API

`/api/v1/menus` lists your theme's menu locations and the menu each one
shows. `/api/v1/menus/primary` is that location's menu as your site
shows it, in your site's language, with each item's `label`, `url`,
`description`, `icon`, `image`, `badge`, `class`, `rel`, the theme's own
`fields`, and its `children`.

Menus are changed only in the admin (see
[Menus](../../concepts/menus.md)).
