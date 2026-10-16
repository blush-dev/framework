# The menu block

`::menu{name=social}` shows one of your site's [menus](../menus.md), by
its name, inside your content.

```markdown
::menu{name=social}
```

`label` names the navigation for screen readers. In content, no
link is marked as the current page, since the same content is shown on
every page.

Themes usually print what a location shows from their templates instead:
`<?= $template->block('menu', location: 'primary') ?>` (see
[Menus in themes](../../features/menus.md)).
