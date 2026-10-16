# Icons

`:icon` shows an icon inside your text:

```markdown
Subscribe to the :icon[RSS feed]{name=rss} feed, or go :icon[]{name=house} home.
```

Blush comes with about 130 icons from [Lucide](https://lucide.dev)
(arrows, people, files, media, messages, alerts, and more); run
`bin/blush icon:list` to see them all. Use Lucide's name for one:
`house`, `arrow-right`, `circle-alert`.

- **The label is for screen readers.** With one (`:icon[Home]{…}`), the
  icon is announced by it; with empty brackets (`:icon[]{…}`), it's
  decoration and skipped. Give a label whenever the icon means
  something the text around it doesn't say.
- Icons are as tall as the text around them and take its color, so
  they fit in headings, links, and buttons alike.
- An icon Blush doesn't know shows nothing.

## In templates

In a template, `<?= $template->block('icon', name: 'house') ?>` shows a
decorative icon, and `<?= $template->block('icon', name: 'rss', label: 'RSS feed') ?>`
a labeled one.

## Icon names and adding icons

Icons are named like blocks: the built-in ones are `blush/{name}`
(or just `{name}`), and others are in a theme's, a plugin's, or an
[icon pack's](../../icon-packs/README.md) namespace. To add some, put SVG
files in a folder:

| Where                                   | Name                                     |
|-----------------------------------------|------------------------------------------|
| Your theme's `icons/badge.svg`          | `notebook/badge` (the theme's namespace) |
| An icon pack's `github.svg`             | `brands/github` (the pack's namespace)   |

A plugin can bring icons too: see [Icons from a plugin](../../features/icons.md).

## Giving another namespace's icon your own look

To give another namespace's icon your own look, add a file named after
it in a subfolder named for its namespace: a theme's
`icons/core/house.svg` or `icons/brands/github.svg` (a
[child theme](../../themes/child-themes.md) can do this for the theme
it builds on). The active theme's file wins, then its parents', then
icon packs' and plugins'. Draw
icons in `currentColor` so they take the text's color.

## Labels

Labels shown in lists (and, later, the admin) are translatable, under
`icons.{name}.label` in the same catalog as the namespace's
[block text](../../features/blocks/labels-and-translations.md).
