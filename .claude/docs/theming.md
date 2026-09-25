# Theming (exploration)

**Status:** being explored (D-010). Nothing here is decided until it's
recorded in `decisions.md`. The goal is a theming system good enough for a
distributable product. It starts with plain PHP templates (D-009) and leaves
room for a custom engine later.

## Goals

1. Anyone can swap themes without touching content.
2. A site can override any part of a theme without forking it.
3. Themes can be distributed and versioned (Composer and/or a zip).
4. Designers work with **tokens** (colors, type, spacing), not only raw CSS.
5. Themes can contain logic (components, data for views) without becoming
   plugins.
6. The same theme renders the web, static export, and admin preview.

## Proposed model

### Theme package
```
themes/nova/
  theme.php            Manifest: returns a typed ThemeManifest object
  tokens.php|json      Design tokens (see below)
  views/               Templates (layouts/, parts/, components/, single.php, …)
  components/          Component classes (optional; or under src/)
  src/                 Theme PHP (namespaced; ThemeProvider)
  assets/              Source assets
  dist/                Built assets + manifest.json
  screenshot.webp
```
- **`ThemeManifest`:** name, slug, version, description, author, `parent`,
  required Blush version, `supports` (enum flags such as `Feeds`,
  `DarkMode`, `Search`), declared regions and menus, image sizes, asset
  entrypoints, and a settings schema.
- **Theme provider:** an optional service provider, booted after the
  framework's and before the site's, that registers components, view data,
  listeners, and routes.

### Resolution and inheritance
- **View lookup chain:**
  `site overrides (resources/views)` → `child theme` → `parent theme` →
  `framework defaults`.
- Overrides can be scoped: `resources/views/themes/nova/…` overrides only when
  nova is active.
- Components and assets resolve along the same chain.
- A single level of parent/child, at least at first.

### Templates
- **Layouts and sections:** `$this->layout('layouts/base')`, then
  `$this->start('content')…$this->stop()`, and `$this->section('sidebar', default: …)`.
- **Partials:** `$this->insert('parts/pagination', ['paginator' => $p])`.
- **Template hierarchy:** a value object per route type, which front matter
  `template:` can override. A theme can register extra hierarchy candidates.
- **Typed view data:** controllers pass view models (`SingleView`,
  `CollectionView`, …), not loose arrays.
- **View context providers:** classes attached to view names or patterns that
  add data (for example, a nav menu for `parts/header`). Not called
  "composers", to avoid the Laravel term.

### Components
- A class (props through constructor promotion, typed) plus a template in
  `views/components/{name}.php`.
- Used in templates as `<?= $this->component('entry-terms', entry: $entry, taxonomy: 'category') ?>`
  or `new EntryTerms(...)`.
- **In content:** Markdown directives map to the same components, so authors
  and templates share one component library:
  ```
  ::: gallery columns=3
  ![](a.jpg) ![](b.jpg)
  :::
  ```
- Components are registered through the enum + registry pattern. A site can
  override a theme component by using the same key.

### Design tokens
- Themes declare tokens: color palettes, font families and sizes, spacing,
  radii, and breakpoints.
- Blush generates CSS custom properties from them (`--color-primary`, …),
  injected via `Head` or written to a built CSS file.
- **Site config can override tokens** without touching theme CSS (e.g. brand
  colors).
- Tokens can have modes (light/dark), which become `@media`/`[data-theme]`
  blocks.
- These are the same tokens the admin customizer would edit later.

### Settings
- Themes declare a settings schema using the same field types as content
  schemas. Site values live in `config/theme.php`, and the admin can edit
  them later.
- Examples: show reading time, archive layout (grid or list), social links.

### Assets
- Themes declare entrypoints in the manifest. `Asset` values (adapted from the
  x3p0-asset concept) resolve URL, path, version (build manifest or mtime),
  and dependencies.
- Per-template enqueueing: a template or component requests assets, and `Head`
  prints them once.
- The build tool is the theme's choice. Blush only reads `dist/manifest.json`
  (Vite style) or falls back to mtime versioning.
- On publish, theme `dist/` is copied or symlinked into `public/themes/{slug}`.

### Navigation and regions
- Menus are user data (`user/data/menus.yaml`) and are validated against the
  menus the theme declares.
- Regions (header, footer, sidebar) are declared by the theme and filled by
  components configured in site config. This is lightweight; no block editor.

### Distribution
- Composer package type `blush-theme`, installed to `themes/` or read from
  `vendor/`. Plus plain folders in `themes/`.
- Activation: `config/theme.php` → `new ThemeConfig(active: 'nova', …)`.
- CLI: `theme:list`, `theme:activate`, `theme:new` (scaffold), `theme:publish`
  (assets).

### Default theme
- The framework ships a minimal, accessible default theme that doubles as the
  reference implementation and test fixture.

## Open questions

- Should `theme.php` return a manifest object, or should we use `theme.json`
  for tooling friendliness (or support both)?
- Token format: our own, or the W3C Design Tokens format?
- Should themes be allowed to register content types? (Leaning no: content
  belongs to the site. Themes can declare `supports` and ship suggested type
  config instead.)
- Should child themes be one level only, or unlimited depth?
- Are Markdown directives the right component syntax, or should we use
  shortcodes or HTML custom elements?
- Is there a custom template engine later? If so, how do plain PHP and
  compiled templates coexist?
- Should themes be able to preview or switch themes per request (admin
  preview, `?theme=` in dev only)?
