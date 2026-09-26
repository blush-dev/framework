# Themes

A theme controls how your site looks. It never owns your content or your
URLs, so you can switch themes at any time without breaking anything.

Blush ships with a **default theme**: plain, accessible, light and dark,
with no build step. Every other theme builds on it, so a theme only has to
include what it changes.

There are three ways to change how your site looks, from least to most
work:

1. [Adjust the active theme's settings and colors](#settings-and-colors)
   with a data file.
2. [Override a few templates](#overriding-templates) from your site.
3. [Build your own theme](#building-a-theme).

## Managing themes

```sh
bin/blush theme:list              # installed themes, and which is active
bin/blush theme:activate notebook # switch themes
```

Themes live in `user/themes/{slug}/`. They can also be installed with
Composer (package type `blush-theme`). The active theme is set in
`config/theme.php`, which `theme:activate` writes for you.

In development, add `?theme=notebook` to any URL to preview another theme.

## Settings and colors

Create `user/data/theme.json` to adjust the active theme without touching
its files:

```json
{
	"settings": {
		"excerpts": false
	},
	"tokens": {
		"color": {
			"accent": "#0a6640"
		}
	}
}
```

- **`settings`** are the options a theme offers. The default theme has one:
  `excerpts` (show summaries in listings; on by default).
- **`tokens`** override the theme's [design tokens](#design-tokens): its
  colors, fonts, and spacing.

An overridden color is used in dark mode too, unless you give it a dark
value of its own:

```json
"accent": {
	"$value": "#0a6640",
	"$extensions": { "blush": { "modes": { "dark": "#7fd6a8" } } }
}
```

Run `bin/blush theme:check` afterward: it warns you when a color doesn't
have enough contrast to read.

A single entry can change its own look too, with front matter:

```yaml
class: wide-page             # extra classes on <body>
stylesheet: /media/zine.css  # an extra stylesheet
tokens:
  color:
    accent: "#b3261e"
```

## Overriding templates

To change one piece of the active theme, copy its template into your site's
`resources/views/` folder and edit the copy. Your copy wins.

For example, to change the site footer, copy the default theme's
`views/parts/footer.php` to `resources/views/parts/footer.php`.

To override a template for one theme only, use
`resources/views/themes/{slug}/` instead.

Not sure which file is in charge of a view? Ask:

```sh
bin/blush theme:why parts/footer
```

## Building a theme

Create one:

```sh
bin/blush theme:new notebook
bin/blush theme:activate notebook
```

That makes the smallest valid theme:

```
user/themes/notebook/
  theme.json    {"name": "Notebook", "version": "1.0.0", "styles": ["style.css"]}
  style.css
```

Every template your theme doesn't include comes from the default theme.
Its **stylesheet** doesn't, though: only the active theme's `styles` are
loaded, so your `style.css` starts from a blank page. Copy the default
theme's `style.css` in as a starting point if you like.

A full theme can have:

```
user/themes/notebook/
  theme.json      The manifest
  style.css       Styles (list more in "styles")
  tokens.json     Design tokens
  views/
    layouts/      base.php, and any others
    parts/        header.php, footer.php, and other pieces
    components/   Components for templates and Markdown
    single.php  collection.php  ...
  lang/           Translations, such as en.json
  src/            Optional PHP classes
```

### `theme.json`

```json
{
	"name": "Notebook",
	"version": "1.0.0",
	"description": "A theme for writers.",
	"parent": "default",
	"styles": ["style.css"],
	"scripts": ["app.js"],
	"settings": {
		"showDate": {
			"type": "bool",
			"default": true,
			"label": "Show the date on posts"
		}
	},
	"contrast": [["color.text", "color.background"]]
}
```

Only `name` is required.

- **`parent`:** build on another theme instead of starting from the
  default. Anything this theme doesn't include comes from its parent.
- **`settings`:** options site owners set in `user/data/theme.json`. They
  use the same field types as [custom fields](content-types.md#custom-fields).
- **`contrast`:** pairs of color tokens that `theme:check` tests against
  WCAG AA.

### Templates

Templates are plain PHP. Here's a simplified `single.php`:

```php
<?php

declare(strict_types=1);

$this->layout('base');

?>
<article class="entry">
	<h1><?= e($entry->title) ?></h1>

	<?php if ($this->setting('showDate')) : ?>
		<p><?= e($this->date($entry->published)) ?></p>
	<?php endif ?>

	<?= raw($entry->body()) ?>
</article>
```

**Always escape output.** Use `e()` for text, `attr()` for attributes,
`url()` for links, and `raw()` only for HTML you trust, such as a rendered
entry body.

What a template can use:

| Call | What it does |
|---|---|
| `$this->layout('base')` | Wrap this template in `layouts/base.php` |
| `$this->section('content')` | In a layout, print the wrapped template |
| `$this->start('name')` … `$this->stop()` | Capture a named section |
| `$this->insert('parts/header', key: $value)` | Include another template |
| `$this->component('card', title: '...')` | Render a component |
| `$this->permalink($entry)` | An entry's URL |
| `$this->terms($entry, 'tag')` | An entry's terms in a taxonomy |
| `$this->date($entry->published)` | A date, formatted for the site's locale |
| `$this->setting('name')` | A theme setting |
| `$this->token('color.accent')` | A design token's value |
| `$this->asset('app.js')` | A theme file's URL, versioned |
| `$this->t('key')` | A translated string from `lang/` |
| `$this->head()` | Add to the `<head>`: title, meta tags, styles, scripts |
| `$this->bodyClass()` | The `<body>` classes |

Every template gets `$site` (name, URL, and language). Content pages also
get `$entry` (the entry), `$entries` (a listing, when there is one),
`$type`, and `$title`. An entry offers `title`, `slug`, `published`,
`updated`, `body()`, `summary()`, `excerpt()`, `subtitle()`, and
`field('name')` for anything in its front matter.

### Which template is used

Blush picks the most specific template your theme (or its parents) has:

| Page | Templates tried, in order |
|---|---|
| An entry | `single-{type}-{slug}`, `single-{type}`, `single` |
| A listing | `collection-{type}`, `collection` |
| A term | `term-{taxonomy}-{slug}`, `term-{taxonomy}`, `term`, `collection` |
| A date archive | `archive-date-{type}`, `archive-date`, `collection` |
| The home page | `home`, then whatever it shows |
| An error | `error-{status}`, `error` |

An entry's `template` front matter is always tried first. Feeds
(`feed-rss`, `feed-atom`, `feed-json`) and sitemaps (`sitemap`,
`sitemap-index`) are templates too, if you need to change them.

### Components

A component is a reusable piece of a template, and the same components can
be used in Markdown. The simplest is a file in `views/components/`:

```php
<?php // views/components/badge.php

declare(strict_types=1);

$tone = $props['tone'] ?? 'info';

?>
<span class="badge badge--<?= attr($tone) ?>"><?= raw($slot) ?></span>
```

Use it in a template with `<?= $this->component('badge', tone: 'new')->content('New') ?>`,
or in Markdown with `:badge[New]{tone=new}`. Each prop is also its own
variable, and `$slot` holds the content (in Markdown, the `[label]` or the
wrapped block).

### Design tokens

Tokens are your theme's colors, fonts, sizes, and spacing, kept in
`tokens.json` in the [W3C design tokens](https://www.designtokens.org/)
format. Blush turns them into CSS custom properties:

```json
{
	"color": {
		"$type": "color",
		"background": {
			"$value": "#fdfcfb",
			"$extensions": { "blush": { "modes": { "dark": "#141312" } } }
		},
		"accent": { "$value": "#a3285b" },
		"link": { "$value": "{color.accent}" }
	}
}
```

becomes `--color-background`, `--color-accent`, and `--color-link` (which
follows `--color-accent`), with a dark-mode value used automatically. Use
them in your stylesheet:

```css
a { color: var(--color-link); }
```

Because they're tokens, site owners can change them in
`user/data/theme.json` and single entries in front matter, without editing
your CSS.

### Check your theme

```sh
bin/blush theme:check
```

It checks the manifest, settings, and tokens; measures color contrast in
light and dark modes; and makes sure the base layout has the landmarks and
skip link screen reader users rely on.

### Going live with a theme

Theme files are served straight from the theme folder by default. On a
live site, copy them into `public/` so the web server can hand them out:

```sh
bin/blush theme:publish
```

Run it again after changing theme files. A [static
export](going-live.md#static-export) includes them automatically.
