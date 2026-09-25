# Theming

Decisions: D-009, D-010, D-020 through D-035. Unresolved items are listed at the
bottom.

## Principles

1. **Presentation only** (D-020). Themes never own content, routes, or
   behavior. Switching themes never breaks content or URLs.
2. **Simplicity for theme authors above all** (D-021). Everything beyond a
   manifest and a stylesheet is opt-in.
3. **Data-first** (D-022). Manifest, tokens, and settings are data. PHP is
   only for presentation logic.
4. **Layered** (D-024). Any part of a theme can be overridden without forking
   it.
5. **One renderer** for the web, static export, and admin preview.
6. **Accessible by default** (D-030).

## Anatomy

The smallest valid theme:
```
user/themes/minimal/
  theme.json        { "name": "Minimal", "version": "1.0.0" }
  style.css
```
Every template and component it doesn't provide falls back to the framework
default theme.

A full theme:
```
user/themes/nova/
  theme.json        Manifest, settings schema, image sizes, menus, regions
  tokens.json       Design tokens (DTCG format)
  views/
    layouts/        base.php, …
    parts/          header.php, footer.php, pagination.php, …
    components/     card.php, gallery.php, …
    single.php  collection.php  …   (template hierarchy files)
  src/              Optional PHP: ThemeProvider, component classes, context providers
  lang/             Message catalogs (D-028)
  assets/  dist/    Source and built assets (build tool is the theme's choice)
  screenshot.webp
```

Themes live in `user/themes/{slug}`. Composer-installed themes may live in
`vendor/` (D-034). Any data file may be JSON or YAML, and **JSON wins** if both
exist (D-032).

### `theme.json`
```json
{
	"$schema": "https://…/theme.schema.json",
	"name": "Nova",
	"version": "1.0.0",
	"parent": null,
	"requires": { "blush": "^2.0", "features": ["search"] },
	"styles": ["style.css"],
	"imageSizes": { "card": [640, 360, "crop"], "wide": [1600, 0] },
	"menus": { "primary": "Primary navigation", "social": "Social links" },
	"regions": { "sidebar": "Sidebar" },
	"settings": {
		"showReadingTime": { "type": "bool", "default": true, "label": "Show reading time" },
		"archiveLayout": { "type": "enum", "options": ["grid", "list"], "default": "list" }
	},
	"provider": "Nova\\ThemeProvider"
}
```
- Blush publishes a JSON Schema so editors autocomplete and validate it.
- `settings` use the **same field types as content schemas**, so the future
  admin renders both with one form system.

## Resolution chain

```
site overrides (resources/views, config, user/data)
  → active theme
    → its parent(s) (any depth, cycle-checked)
      → framework default theme (includes the core content components, D-033)
```
- The chain applies to views, components, assets, tokens, settings defaults,
  and message catalogs.
- Theme-scoped site overrides go in `resources/views/themes/{slug}/…` and apply
  only while that theme is active.
- `theme:why <view>` (CLI) shows which file in the chain wins. This makes
  layering easy to debug.

## Configuration layers (D-022)

| Layer | Where | Who edits |
|---|---|---|
| Theme defaults | `user/themes/{slug}/theme.json`, `tokens.json` | Theme author |
| Site code config | `config/theme.php` → `ThemeConfig` (active theme, component overrides) | Developer |
| Site data | `user/data/theme.json` (setting values, token overrides) | Site owner, later the admin |

## Templates

Plain PHP (D-009) in an isolated scope. `$this` is the template API.

```php
<?php declare(strict_types=1);

$this->layout('base', title: $entry->title);
?>

<article class="entry">
	<h1><?= e($entry->title) ?></h1>

	<?php if ($this->setting('showReadingTime')) : ?>
		<p><?= e($this->t('reading_time', minutes: $entry->readingTime)) ?></p>
	<?php endif ?>

	<?= raw($entry->body()) ?>

	<?= $this->component('entry-terms', entry: $entry, taxonomy: 'category') ?>
</article>
```

The template API (kept deliberately small):
| Method | Purpose |
|---|---|
| `layout($name, ...$data)` | Wrap this template in a layout |
| `start($section)` / `stop()` / `section($name, default: '')` | Define and output sections |
| `insert($partial, ...$data)` | Include a partial |
| `component($name, ...$props)` | Render a component; `->slot($name, $content)` for named slots |
| `t($key, ...$params)` | Translate (D-028) |
| `setting($key)` / `token($path)` | Theme setting and token values |
| `asset($path)` / `image($media, $size)` | Asset URLs; responsive `<img>` output |
| `head()` | The `Head` manager (title, meta, OpenGraph, and so on) |

Global escaping helpers: `e()`, `attr()`, `url()`, `js()`, `css()`, `raw()`.

### Template hierarchy
- **Single entry:** front matter `template` → `single-{type}-{slug}` →
  `single-{type}` → `single`.
- **Collection:** `collection-{type}` → `collection`.
- **Term:** `term-{taxonomy}-{slug}` → `term-{taxonomy}` → `term` →
  `collection`.
- **Date archive:** `archive-date-{type}` → `archive-date` → `collection`.
- **Home:** `home` → then the hierarchy of whatever it aliases.
- **Errors:** `error-{status}` → `error`.
- **Feeds and sitemaps:** `feed-{format}`, `sitemap`, `sitemap-index`.
  Framework-owned, overridable (D-029).
- Themes can add candidates through their provider (for example by post
  format or by term).

## Components (D-025)

- **A template-only component** is just `views/components/{name}.php`, with its
  props passed in as variables. That's the simple path.
- **A class-backed component** is for props that need logic: typed props via
  constructor promotion plus the same template.
- **Slots:** `$slot` holds the default slot and `$slots->name` holds named
  slots.
- **Registry:** components are resolved by key through the chain. A site
  overrides a theme component by providing the same key.
- **In Markdown** (D-026), the same components are available to content:
  ```
  :::gallery{columns=3}
  ![](a.jpg) ![](b.jpg)
  :::

  This is :badge[new]{tone=info}.
  ```
  An unknown directive renders as plain content. The framework default theme
  ships the core content components (D-033), so they work under any theme.

## Context providers

Classes attached to view names or patterns that supply data, e.g. a
`PrimaryMenu` provider for `parts/header`. They keep queries out of templates.
They are registered in the theme or site provider.

## Design tokens (D-023)

- **Format:** W3C Design Tokens (DTCG) in `tokens.json`, with aliases
  (`{color.brand}`), types, and groups.
- **Modes:** a Blush extension (light/dark and more), compiled into
  `prefers-color-scheme` and `[data-scheme]` blocks.
- **Merge order:** default theme → ancestors → theme → `user/data/theme.json`
  → entry front matter `tokens` (D-027).
- **Output:** compiled CSS custom properties, cached per content version.
  Per-entry overrides are emitted inline and scoped.
- **Validation:** `theme:check` computes palette contrast from tokens.

## Per-entry presentation (D-027)

Built-in front matter: `layout`, `template`, `stylesheet`, `class`, `tokens`.
These let a single post have its own design without a custom theme.

## Assets (D-031)

- A theme lists its stylesheets and scripts in `theme.json`, and templates and
  components can request more.
- `Head` prints each asset once, in order.
- **Versioning:** from `dist/manifest.json` if present, otherwise the file
  mtime.
- **Publishing:** `theme:publish` copies or symlinks theme assets to
  `public/themes/{slug}/`. Static export includes them.

## Media and images

- The theme declares image sizes, and Blush generates derivatives (see
  `architecture.md` → Media).
- `$this->image($media, 'card')` outputs `<img>` with `srcset`, `sizes`,
  `width`/`height`, and `loading`.

## Navigation and regions

- **Menus** are site data (`user/data/menus.*`) validated against the menus the
  theme declares.
- **Regions** are filled with components configured in site config (later,
  editable in the admin).

## Translation (D-028)

Themes ship `lang/{locale}.json` catalogs in the `theme` domain, and templates
call `$this->t()`. The translator itself is CMS-wide; see
`architecture.md` → Translation.

## Accessibility (D-030)

- The default theme targets WCAG 2.2 AA.
- `theme:check` verifies: base layout landmarks and skip link, `lang` on
  `<html>`, heading structure in the core templates, and token palette
  contrast.

## CLI

`theme:list`, `theme:activate <slug>`, `theme:new <slug> [--parent=]`,
`theme:check`, `theme:why <view>`, `theme:publish`.

## Theme switching (D-035)

In the dev environment only, `?theme={slug}` renders the request with another
theme. Anything more (such as admin preview) comes later.

## Open questions

- **Future template engine:** how would it coexist with PHP templates? (The
  current thinking is one `ViewEngine` interface chosen by file extension.)
- **Which core content components ship first**, beyond gallery, figure,
  callout, and embed?
