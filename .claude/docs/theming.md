# Theming

Decisions: D-009, D-010, D-020 through D-035, and D-102 through D-125 (M5).
Unresolved items are listed at the bottom.

**Status:** M5a and M5b (D-102 to D-121) implemented everything here except
image derivatives (`image()`), menus and regions, and `requires`
enforcement. M5c (D-122 to D-124) added the feed and sitemap templates.

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

The smallest valid theme (only `name` is required; `styles` defaults to
`["style.css"]`, D-105):
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
	"provider": "Nova\\ThemeProvider",
	"autoload": { "psr-4": { "Nova\\": "src/" } },
	"contrast": [["color.text", "color.background"]]
}
```
- Blush publishes a JSON Schema so editors autocomplete and validate it.
- A page loads the **active** theme's `styles` and `scripts`, each resolved
  through the chain. Ancestors' own lists aren't loaded automatically
  (D-105).
- `settings` use the **same field types as content schemas**, so the future
  admin renders both with one form system. Definitions merge down the chain;
  values come from `user/data/theme.json` (D-117).
- `provider` registers after extensions' and before the site's, ancestors
  first; `autoload.psr-4` is registered for local themes (D-116).
- `contrast` lists the `[foreground, background]` token pairs `theme:check`
  measures (D-121).
- Themes may also be Composer packages of type `blush-theme` (slug from
  `extra.blush.slug`, else the package name); a local theme with the same
  slug wins (D-115).

## Resolution chain

```
site overrides (resources/views, config, user/data)
  → active theme
    → its parent(s) (any depth, cycle-checked)
      → framework default theme (`resources/themes/default`, slug `default`;
        includes the core content components, D-033)
```
- Views resolve through `resources/views/themes/{active}`, then
  `resources/views`, then each theme's `views/` (D-103).
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

The template API (kept deliberately small; D-103):
| Method | Purpose |
|---|---|
| `layout($name, ...$data)` | Wrap this template in a layout (`layouts/{name}`) |
| `start($section)` / `stop()` / `section($name, default: '')` / `hasSection($name)` | Define and output sections |
| `insert($partial, ...$data)` | Include a partial (shared data plus what it's given) |
| `component($name, ...$props)` | Render a component; `->content($html)` and `->slot($name, $html)` fill slots |
| `t($key, ...$params)` | Translate from the `theme` domain (D-028, D-107) |
| `setting($key, $default)` / `token($path, $mode)` | Theme setting and concrete token values |
| `asset($path)` / `image($media, $size)` | Versioned asset URLs; responsive `<img>` output (`image()` later) |
| `head()` | The `Head` manager (title, meta, OpenGraph, and so on; D-109) |
| `permalink($entry)` / `route($name, $params)` | Entry and named-route URLs |
| `terms($entry, $taxonomy)` | An entry's published term entries |
| `date($date, $format)` | A localized date (`long`, or an ICU pattern) |
| `bodyClass()` | The `<body>` classes |

Every template also gets `$site` (name, URL, locale, `lang`). Content
pages get `$page`, `$entry`, `$entries`, `$type`, and `$title`; error pages
get `$status`, `$reason`, `$title`, `$entry`, `$description`, and
`$message` (debug only).

Global escaping helpers (D-106): `e()`, `attr()`, `url()`, `js()`,
`css()`, `raw()`.

### Template hierarchy
Front matter `template` (1.x's `view`) always comes first. 1.x's view names
aren't candidates (D-104).
- **Single entry:** `single-{type}-{slug}` → `single-{type}` → `single`.
- **Collection:** `collection-{type}` → `collection`.
- **Term:** `term-{taxonomy}-{slug}` → `term-{taxonomy}` → `term` →
  `collection`.
- **Date archive:** `archive-date-{type}` → `archive-date` → `collection`.
- **Home:** `home` → then the hierarchy of whatever it aliases.
- **Welcome:** `welcome` (a site with no home page yet, D-108).
- **Errors:** `error-{status}` → `error`, filled from
  `user/content/_errors/{status}.md` (or 1.x's `_error/{status}.md`) when
  it exists (D-108).
- **Feeds and sitemaps:** `feed-{format}-{type}` → `feed-{format}` (`rss`,
  `atom`, `json`; they get `$feed`), `sitemap-{type}` → `sitemap` (`$urls`),
  and `sitemap-index` (`$sitemaps`). Framework-owned, overridable, and
  rendered without a layout (D-029, D-122, D-123).
- Themes can add candidates through their provider (for example by post
  format or by term).

## Components (D-025, D-111)

- **A template-only component** is just `views/components/{name}.php`, with its
  props passed in as variables (and all of them as `$props`). That's the
  simple path.
- **A class-backed component** extends `View\Component\Component` for props
  that need logic: typed props via constructor promotion (strings from
  Markdown are cast to `int`/`float`/`bool`), services by autowiring,
  `data()`, `template()`, and `shouldRender()`, plus the same template.
  Register it with `ComponentRegistry::register($key, $class)` in a provider.
- **Slots:** `$slot` holds the default slot and `$slots->name` holds named
  slots (`''` when unfilled).
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
  ships the core content components (D-033, D-113): `callout`, `gallery`,
  `figure`, and `embed`, so they work under any theme. Directives render with
  the request's theme; attributes are props, the `[label]` is `$slot` (and
  the `label` prop), and a container's blocks are `$slot` (D-112).

## Context providers

Classes attached to view names or patterns that supply data, e.g. a
`PrimaryMenu` provider for `parts/header`. They keep queries out of templates.
They are registered in the theme or site provider:
`ContextProviders::add('parts/*', PrimaryMenu::class)`. Their data are
defaults; data given explicitly wins (D-114).

## Design tokens (D-023)

- **Format:** W3C Design Tokens (DTCG) in `tokens.json` (or `.yaml`), with
  aliases (`{color.brand}`, compiled to `var(--color-brand)`), types, and
  groups. Scalar leaves are a shorthand for `$value` (D-118).
- **Modes:** a Blush extension, `"$extensions": {"blush": {"modes": {"dark":
  …}}}`, compiled into `prefers-color-scheme` and `[data-scheme]` blocks.
- **Merge order:** default theme → ancestors → theme → `user/data/theme.json`
  → entry front matter `tokens` (D-027). An override replaces a token in
  every mode unless it sets its own modes.
- **Output:** CSS custom properties inlined in the head (`blush-tokens`),
  built once per chain per process (caching per content version is M6's).
  Per-entry overrides follow in their own block (`blush-entry-tokens`).
- **Validation:** unsafe values are dropped; `theme:check` reports tokens
  that don't compile or resolve, and computes palette contrast.

## Per-entry presentation (D-027)

Built-in front matter: `layout`, `template`, `stylesheet`, `class`, `tokens`.
These let a single post have its own design without a custom theme.
`layout` replaces the page template's layout (if it exists), `class` is added
to `<body>` (D-109), `stylesheet` is a URL or a theme asset path (D-119), and
`tokens` override the theme's on that page (D-118).

## Assets (D-031)

- A theme lists its stylesheets and scripts in `theme.json`, and templates and
  components can request more.
- `Head` prints each asset once, in order.
- **Serving:** the `theme.asset` route (`/themes/{slug}/{path}`) streams
  allowed files from any installed theme until they're published (D-105).
- **Versioning:** from a Vite-style `dist/.vite/manifest.json` or
  `dist/manifest.json` if present (hashed files, their `css`, built scripts
  as modules), otherwise the file mtime (`?v=`) (D-119).
- **Publishing:** `theme:publish` copies servable theme assets (never PHP,
  views, or manifests; never a symlink) to `public/themes/{slug}/`. Static
  export includes them.

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
call `$this->t()`. A child theme overrides its ancestors message by message
(D-107). The translator itself is CMS-wide; see
`architecture.md` → Translation.

## Accessibility (D-030)

- The default theme targets WCAG 2.2 AA.
- `theme:check` verifies: base layout landmarks and skip link, `lang` on
  `<html>`, one `<h1>`, and token palette contrast in every mode (D-121).

## CLI

`theme:list`, `theme:activate <slug>`, `theme:new <slug> [--parent=]`,
`theme:check [slug]`, `theme:why <view>`, `theme:publish [--all]` (D-120; see
`cli.md`).

## Theme switching (D-035)

In the dev environment only, `?theme={slug}` renders the request with another
theme. Anything more (such as admin preview) comes later.

## Open questions

- **Future template engine:** how would it coexist with PHP templates? (The
  current thinking is one `ViewEngine` interface chosen by file extension.)
