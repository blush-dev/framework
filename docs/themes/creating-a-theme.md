# Creating a theme

A theme is a folder in `extensions/` with a `theme.json` manifest, a
stylesheet, and the templates it wants to draw itself.

## Starting one

```sh
bin/blush theme:new acme/notebook
bin/blush theme:activate acme/notebook
```

In a terminal, `bin/blush theme:new` on its own asks for the name, a
label, a parent theme to build on (or none), a description, and whether
to activate it now.

That makes the smallest valid theme, in a folder at its name:

```
extensions/acme/notebook/
  theme.json    {"$schema": "…", "name": "acme/notebook", "label": "Notebook", "namespace": "acme-notebook", "version": "1.0.0", "styles": ["style.css"]}
  style.css
  lang/en.json  {"@@locale": "en", "@@domain": "acme/notebook"}, for your theme's text
```

`--label`, `--namespace`, and `--parent` (another theme's name) set those
instead of the defaults.

## Blush's own templates

Every template your theme doesn't include comes from its parent, if it
has one, and last from Blush's own templates. Those are plain on
purpose:

- **`layouts/base`** is the page skeleton: the `<head>`
  (`$template->head()`), a skip link, the page in `<main id="main">`,
  and the end of the body (`$template->foot()`). It has no site header
  or footer, so most themes include their own `layouts/base.php`.
  `bin/blush theme:check --strict` notes a theme without one.
- **`single`, `collection`, and `error`**, with the parts `entries`,
  `entry-summary`, `entry-meta`, and `pagination`, are bare HTML, so a
  page still shows when your theme doesn't have a template for it.
- **The welcome page, feeds, and sitemaps** are Blush's, and your theme
  can replace any of them.

## Starting from the default theme

To start from the default theme's design instead, make it your theme's
parent (`bin/blush theme:new acme/notebook --parent=blush/default`).
Its **stylesheet** still isn't loaded: only the active theme's `styles`
are, so your `style.css` starts from a blank page. Copy the default
theme's `style.css` in as a starting point if you like.

## A theme's folders

A full theme can have:

```
extensions/acme/notebook/
  theme.json      The manifest
  style.css       Styles (list more in "styles")
  views/
    layouts/      base.php, and any others
    partials/     header.php, footer.php, and other pieces
    blocks/       Your look for blocks, such as callout.php
    components/   Your theme's own components, for its templates
    single.php  collection.php  ...
  icons/          SVG icons, such as badge.svg (see Blocks)
  lang/           Translations, such as en.json
  src/            Optional PHP classes
```

Next: the manifest, [`theme.json`](theme-json.md), and
[templates](templates.md).
