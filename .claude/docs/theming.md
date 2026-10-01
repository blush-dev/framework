# Theming

Decisions: D-009, D-010, D-020 through D-035, and D-102 through D-125 (M5).
Unresolved items are listed at the bottom.

**Status:** M5a and M5b (D-102 to D-121) implemented everything here except
image derivatives (`image()`) and `requires` enforcement. Menus and
regions came later (D-199 to D-204). M5c (D-122 to D-124) added the feed and sitemap templates.

## Principles

1. **Presentation only** (D-020). Themes never own content, routes, or
   behavior. Switching themes never breaks content or URLs.
2. **Simplicity for theme authors above all** (D-021). Everything beyond a
   manifest and a stylesheet is opt-in.
3. **Data-first** (D-022). Manifest and settings are data. PHP is
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
  views/
    layouts/        base.php, …
    parts/          header.php, footer.php, pagination.php, …
    components/     {slug}-card.php, gallery.php (core overrides), … (D-171)
    single.php  collection.php  …   (template hierarchy files)
  src/              Optional PHP: ThemeProvider, component classes, context providers
  lang/             Message catalogs (D-028)
  resources/        Build sources (never served, D-155): scss/, js/, fonts/, …
  public/           Built assets and the build's manifest (build tool is the theme's choice)
  package.json  vite.config.js   Optional build config, kept with the theme (D-167; private, D-168)
  screenshot.webp
```

Themes live in `user/themes/{slug}`, each optionally its own repo
(D-166, D-167). Composer-installed themes may live in `vendor/` (D-034).
With the same slug, `user/themes` beats Composer. Any data file may be JSON or YAML, and **JSON wins** if both
exist (D-032).

### `theme.json`
```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/theme.schema.json",
	"name": "Nova",
	"version": "1.0.0",
	"parent": null,
	"requires": { "blush": "^2.0", "features": ["search"] },
	"styles": ["style.css"],
	"imageSizes": { "card": [640, 360, "crop"], "wide": [1600, 0] },
	"menus": {
		"primary": { "label": "Primary", "depth": 2, "fields": { "columns": { "type": "number", "integer": true, "default": 1 } } },
		"social": "Social"
	},
	"regions": { "sidebar": { "label": "Sidebar", "items": [{ "component": "menu", "name": "social" }] } },
	"settings": {
		"showReadingTime": { "type": "bool", "default": true, "label": "Show reading time" },
		"archiveLayout": { "type": "enum", "options": ["grid", "list"], "default": "list" }
	},
	"provider": "Nova\\ThemeProvider",
	"autoload": { "psr-4": { "Nova\\": "src/" } }
}
```
- Blush ships a JSON Schema (`resources/schemas/theme.schema.json`, D-206)
  so editors autocomplete and validate it.
- A page loads the **active** theme's `styles` and `scripts`, each resolved
  through the chain. Ancestors' own lists aren't loaded automatically
  (D-105).
- `settings` use the **same field types as content schemas**, so the future
  admin renders both with one form system. Definitions merge down the chain;
  values come from `user/data/theme.json` (D-117).
- `provider` registers after extensions' and before the site's, ancestors
  first; `autoload.psr-4` is registered for local themes (D-116).
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
- The chain applies to views, components, assets, settings defaults,
  and message catalogs.
- Theme-scoped site overrides go in `resources/views/themes/{slug}/…` and apply
  only while that theme is active.
- `theme:why <view>` (CLI) shows which file in the chain wins. This makes
  layering easy to debug.

## Configuration layers (D-022)

| Layer | Where | Who edits |
|---|---|---|
| Theme defaults | `user/themes/{slug}/theme.json` | Theme author |
| Site code config | `config/theme.php` → `ThemeConfig` (active theme, component overrides) | Developer |
| Site data | `user/data/theme.json` (setting values, location maps), `user/data/menus/`, `user/data/regions/` | Site owner, later the admin |

## Templates

Plain PHP (D-009) in an isolated scope. `$template` is the template API
(D-158); `$this` isn't available.

```php
<?php declare(strict_types=1);

$template->layout('base', title: $entry->title);
?>

<article class="entry">
	<h1><?= e($entry->title) ?></h1>

	<?php if ($template->setting('showReadingTime')) : ?>
		<p><?= e($template->t('reading_time', minutes: $entry->readingTime)) ?></p>
	<?php endif ?>

	<?= raw($entry->body()) ?>

	<?= $template->component('notebook/entry-terms', entry: $entry, taxonomy: 'category') ?>
</article>
```

The template API (kept deliberately small; D-103):
| Method | Purpose |
|---|---|
| `layout($name, ...$data)` | Wrap this template in a layout (`layouts/{name}`) |
| `start($section)` / `stop()` / `section($name, default: '')` / `hasSection($name)` | Define and output sections |
| `include($views, ...$data)` | Include a partial (shared data plus what it's given); a list tries each in turn (D-159) |
| `includeIf()` / `includeWhen($when, ...)` / `includeUnless($unless, ...)` | Include only if a view exists, or on a condition (1.x's names, D-159) |
| `each($views, $items, as:, empty:, ...$data)` | Include a partial per item (with `$index`), or `empty` when there are none (D-159) |
| `component($name, ...$props)` | Render a component; `->content($html)` and `->slot($name, $html)` fill slots |
| `t($key, ...$params)` | Translate from the `theme` domain (D-028, D-107) |
| `setting($key, $default)` | A theme setting's value |
| `asset($path)` / `image($media, $size)` | Versioned asset URLs; responsive `<img>` output (`image()` later) |
| `inline($path)` | A servable theme asset's contents, such as an SVG (D-151) |
| `widont($text)` | Escaped text with its last two words joined by `&nbsp;` (1.x's `runt()`, D-153) |
| `cache($key, $render)` | A fragment kept per content version and active theme (D-152) |
| `head()` | The `Head` manager (title, meta, OpenGraph, and so on; D-109) |
| `permalink($entry)` / `route($name, $params)` | Entry and named-route URLs |
| `terms($entry, $taxonomy)` | An entry's published term entries |
| `parent($entry)` / `ancestors($entry)` / `children($entry)` | A page's or hierarchical term's published parent, parents from the top down, and children by title (D-257) |
| `date($date, $format)` | A localized date (`long`, or an ICU pattern) |
| `bodyClass()` | The `<body>` classes |

Every template also gets `$site` (name, URL, locale, `lang`). Content
pages get `$page`, `$entry`, `$entries`, `$type`, and `$title`; error pages
get `$status`, `$reason`, `$title`, `$entry`, `$description`, and
`$message` (debug only).
This page data is shared (D-146): layouts, partials, and components see
it without it being passed, and what a template passes wins. On an
entry's page the head also gets its description and, from its `image`
field, `og:image` and a Twitter card (D-149).

Global escaping helpers (D-106): `e()`, `attr()`, `url()`, `js()`,
`css()`, `raw()`.

### Template hierarchy
Front matter `template` (1.x's `view`) always comes first. 1.x's view names
aren't candidates (D-104).
- **Single entry:** `single-{type}-{slug}` → `single-{type}` → `single`.
- **Collection:** `collection-{type}` → `collection-taxonomy` (a
  taxonomy's listing only, D-147) → `collection`.
- **Term:** `term-{taxonomy}-{slug}` → `term-{taxonomy}` → `term` →
  `collection`.
- **Date archive:** `archive-date-{type}` → `archive-date` → `collection`.
- **Authors** (a type's, D-329): `authors-{type}` → `authors` →
  `collection`; `$entries` holds the authors, `$entry` the type's
  `_authors` page.
- **Author archive:** `author-{type}-{slug}` → `author-{type}` →
  `author` → `collection`; `$entry` is the author.
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

- **Names (D-171, D-173):** `{namespace}/{name}` (`ComponentName`):
  `blush` for core, a theme's slug, `app` for the site, an extension's
  vendor. Only core components have short names (`callout` is
  `blush/callout`). The template is `components/{namespace}-{name}.php`;
  a core component's may also be `components/{name}.php`, and the
  highest-precedence directory wins whichever name it uses
  (`ViewFinder::nearest()`). Subfolders of `components/` aren't
  components.
- **Templates get `$component` (D-195, D-196):** a component's template
  gets `$component` and `$template`; content is `content()`, named slots
  `$component->slots->name`, and role methods (`caption()`, `text()`,
  `heading()`) return the content or else the escaped `label`. Props are the
  class's public properties (`$component->tone`), computed values are
  methods, and `$component->attributes()` prints the root element's
  `class` (block, `modifiers()`, the `class` prop), `id`, and
  `rootAttributes()`. `$component->prop()` reads any prop as given.
- **A class-backed component** extends `Component\Component`: typed props
  via constructor promotion (strings from Markdown are cast to
  `int`/`float`/`bool` or a backed enum, whose unknown values fall back
  to the default), services by autowiring, `template()`,
  `shouldRender()`, `modifiers()`, `rootAttributes()`, `t()`, and
  `CONTENT`, plus its template. Every core component has one.
- **A template-only component** is just its template; its `$component`
  is a `TemplateComponent`, read with `prop()`. It renders without being
  registered. Whether to require a class for every component is open.
- **Classes (D-182):** a component's classes are BEM-style with a
  `component-` prefix (`component-callout`, `component-callout--warning`,
  `component-callout__title`); the default theme's core components use
  it.
- **Variants (D-266):** a named style of any component, `variant=name`,
  whose modifier is `component-{name}--{variant}` (or the variant's own
  modifier). Default is always there, writes nothing, and adds no class.
  A variant is a `Variant` (name, registrant, optional modifier); a class
  declares its own in `VARIANTS`, a template-only component with
  `register(…, variants:)`, a theme in `theme.json` `variants`, and
  anyone else from `ComponentVariantsCollecting` (fired once per
  component when its variants are first needed). `ComponentVariants`
  collects them and drops a theme's outside the chain; an unknown
  variant renders as Default. Templates get `$component->variant` and
  `isVariant()`, and `components/{name}-{variant}` wins over the
  component's template. Text is
  `components.{name}.variants.{variant}.label` and `.description` in the
  registrant's domain. `content:lint` (`VariantCheck`) and `theme:check`
  report problems.
- **Image variants (D-268):** a Markdown image isn't a component, but a
  theme lists classes for it under `theme.json`'s `variants.image`
  (`{.stretch-wide}`; `FigureRenderer` puts them on the figure), with
  text at `images.variants.{name}.label` and `.description` in the
  theme's catalog. `ComponentVariants::forImages()` collects them from
  the chain, leaving out the framework default theme's unless it's the
  active theme (only the active theme's stylesheet loads). The default
  theme offers and styles `stretch-wide`, `stretch-full`, `inline-left`,
  and `inline-right`.
- **Slots:** `$component->content()` holds the default slot and
  `$component->slots->name` named slots (`''` when unfilled).
- **Registry (D-172, D-173):** `ComponentRegistry::register($name, $class,
  $content, $props)` stores a `ComponentDefinition` by full name: a
  class, or none for a template-only component, plus what it wraps
  (`ComponentContent`) and its props as schema `Field`s (read from the
  constructor and `CONTENT` when not given). Registering is what puts a
  component in the admin's inserter later. Short names must be core; new
  `blush/*` names are refused; a provider may replace a core component.
- **Text (D-172, D-173):** `Views::componentText($name, 'label')` reads
  `components.{name}.{key}` from the namespace's catalog domain: `blush`,
  `theme` (the chain's slugs), `app` (`resources/lang`), or the vendor
  (enabled extensions' `lang/`). Missing text falls back to
  `ComponentName::label()`.
- **Discovery (D-164, D-173):** `ComponentType` declares the core
  components and their classes (the registrar seeds them all).
  `Views::components()` lists every name the chain can render as
  `ComponentListing`s (core, registered, and every `components/*.php`
  named for a component), with labels; `strayComponentFiles()` returns
  the rest. `component:list` prints both; `theme:check` warns about a
  class with no template and a stray file in the theme, and notes the
  theme's registered components without a label.
- **In Markdown** (D-026), the same components are available to content:
  ```
  :::gallery{columns=3}
  ![](a.jpg) ![](b.jpg)
  :::

  This is :notebook/badge[new]{variant=outline}.
  ```
  An unknown directive, or a short name that isn't core, renders as plain
  content. The framework default theme
  ships the core content components (D-033, D-113): `callout`, `gallery`,
  `figure` (a container for anything captioned, D-267), and `embed`, plus the layout components `group`, `grid`, and
  `row` (D-177, which set their structural CSS inline and read
  `--layout-gap`; each renders as its `tag`, D-298), and the media components `audio`, `video`, and
  `file` (D-179), and the inline components `abbr`, `kbd`, and `time`
  (D-180) and `badge`, `cite`, `dfn`, `ins`, `samp`, `small`, and `var`
  (D-305), `toc` (D-183), `icon` (D-187), `progress` and `meter`
  (D-188), and `button` (D-189), so they work under any theme. A registered
  component's `media` props (`#[MediaProp]` on a class parameter) are
  resolved against the entry's folder, like images (D-179). Directives render with
  the request's theme; attributes are props, the `[label]` is `$slot` (and
  the `label` prop), and a container's blocks are `$slot` (D-112).

## Context providers

Classes attached to view names or patterns that supply data, e.g. a
`PrimaryMenu` provider for `parts/header`. They keep queries out of templates.
They are registered in the theme or site provider:
`ContextProviders::add('parts/*', PrimaryMenu::class)`. Their data are
defaults; data given explicitly wins (D-114).

## Design (no token system, D-160)

Blush sets no rules for how a theme styles itself: a theme's stylesheets
are plain CSS, and the framework compiles nothing into the head. The
default theme keeps its palette and scale as custom properties in
`style.css`, with `light-dark()` for dark mode.

A design token system (D-023, D-118, D-148) was built in M5b and removed
in D-160. It may return as an add-on; notes on the old design:

- **Format:** W3C Design Tokens (DTCG) in `tokens.json` (or `.yaml`), with
  aliases (`{color.brand}`, compiled to `var(--color-brand)`), types, and
  groups. Scalar leaves were a shorthand for `$value`.
- **Modes:** a Blush extension, `"$extensions": {"blush": {"modes": {"dark":
  …}}}`, compiled into `prefers-color-scheme` and `[data-scheme]` blocks.
- **Merge order:** default theme → ancestors → theme → `user/data/theme.json`
  `tokens` → entry front matter `tokens`. `"inheritTokens": false` in
  `theme.json` started the chain at that theme. An override replaced a
  token in every mode unless it set its own modes.
- **Output:** CSS custom properties inlined in the head (`blush-tokens`,
  per-entry `blush-entry-tokens`), cached per content version in a
  `tokens` cache namespace; `$template->token($path, $mode)` returned a
  concrete value.
- **Validation:** unsafe values were dropped; `theme:check` reported
  tokens that didn't compile or resolve, and measured WCAG AA contrast
  for `[foreground, background]` pairs listed in `theme.json` `contrast`.
- **Lessons:** jtcom's CSS didn't use tokens, so every page carried an
  unused block until `inheritTokens` (D-148); a token layer imposes a
  design vocabulary before the site-owner admin exists to benefit from it.
  If it returns, it should be opt-in per theme (no default-theme tokens
  leaking into children).

## Per-entry presentation (D-027)

Built-in front matter: `layout`, `template`, `stylesheet`, `class`.
These let a single post have its own design without a custom theme.
`layout` replaces the page template's layout (if it exists), `class` is added
to `<body>` (D-109), and `stylesheet` is a URL or a theme asset path (D-119).

## Assets (D-031)

- A theme lists its stylesheets and scripts in `theme.json`, and templates and
  components can request more.
- `Head` prints each asset once, in order.
- **Serving:** the `theme.asset` route (`/themes/{slug}/{path}`) streams
  allowed files from any installed theme until they're published (D-105).
- **Resolving:** from a Vite-style manifest if present:
  `public/.vite/manifest.json` (D-155), `dist/.vite/manifest.json`, or
  either without `.vite/` (built files, their `css`, built scripts as
  modules), otherwise the file itself (D-119).
- **Versioning:** every URL gets `?v=` and a CRC32 of the file's
  contents (D-194). A theme's
  `resources/`, `src/`, `vendor/`, and `node_modules/` folders and its
  `*.config.js` files (D-168) are never served.
- **Publishing:** `theme:publish` copies servable theme assets (never PHP,
  views, or manifests; never a symlink) to `public/themes/{slug}/`. Static
  export includes them.

## Media and images

- The theme declares image sizes, and Blush generates derivatives (see
  `architecture.md` → Media).
- `$template->image($media, 'card')` outputs `<img>` with `srcset`, `sizes`,
  `width`/`height`, and `loading`.

## Navigation and regions

D-199 to D-204; the user guide is `docs/menus.md`.

- **Locations:** `theme.json` `menus` and `regions` declare locations,
  each a label string or an object. A menu location may set `depth` and
  extra per-item `fields` (content schema field types, D-200); a region
  location may list default `items`, shown when the site has no file for
  it (D-201).
- **Site data:** `user/data/menus/{name}.*` and `user/data/regions/{name}.*`
  fill the locations of the same name. `user/data/theme.json` may map a
  location to another name (`"menus": {"main": "primary"}`).
- **Menu items** link to an `entry` (`{type}/{key}`), `term`,
  `collection`, `route`, or `url`, with optional `label`, `children`,
  `icon`, `description`, `image`, `badge`, `class`, and `rel`. Links are
  resolved and cached per content version; the current item gets
  `aria-current="page"` at render time. Dropdowns use the disclosure
  pattern, never `role="menu"`; the theme owns the behavior.
- **Region items** are a `component` (props as sibling keys),
  `markdown`, an `entry`'s body, or a `view`.
- **Text values** may be locale maps (`{en: About, fr_CA: À propos}`,
  D-202).
- **Templates:** the core `menu` component, `$template->menu($name)` for
  custom markup, and `$template->region($name)` / `hasRegion()`.
- **Location labels** name the menu's `<nav>`: short, without
  "navigation".
- **Problems** (unresolved links, unknown keys) leave the item out, are
  logged, and show in `menu:list`, `menu:show`, and `theme:check`.
- **Brand icons** (for social menus) come from the theme's own icon
  namespace, not core (D-203).
- **Later:** front matter menu entries, mega-menu `panel` entries, and
  per-page region conditions.

## Translation (D-028)

Themes ship `lang/{locale}.json` catalogs in the `theme` domain, and templates
call `$template->t()`. A child theme overrides its ancestors message by message
(D-107). The translator itself is CMS-wide; see
`architecture.md` → Translation.

## Accessibility (D-030)

- The default theme targets WCAG 2.2 AA.
- `theme:check` verifies: base layout landmarks and skip link, `lang` on
  `<html>`, and one `<h1>` (D-121). Contrast checking went with tokens
  (D-160).

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
