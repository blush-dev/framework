# Open questions

Move each item to `decisions.md` once it's answered.

## Needs the author's call

- **Skeleton license** (D-070): confirm MIT for `blush-dev/blush` `2.x`.
- **Where jtcom's content types live** (D-166, D-169): `config/content.php`
  today. Options: data types in `user/data/types/` (travel with the
  content repo; a checked sketch matches the config exactly), or an
  extension in `user/extensions/` (WordPress-style, with site PHP such as
  a future blog extension).

## Later milestones
- **Extension component templates** (D-173): an extension can register
  a component in its vendor namespace, but the view chain has no
  extension directories, so the theme or site must supply its template.
  Options: add each enabled extension's `views/` to the chain (below the
  site and themes, above the default theme), or let a component class
  point at a file. Needed before extensions are a real component source.
- **Component namespace clashes** (D-171): a theme's namespace is its
  slug and an extension's is its vendor, so the two could collide.
  Decide whether Blush checks or reserves namespaces.
- **The core component set** (D-171): the author wants core components
  to cover the full range of HTML plus layout helpers. The inventory is
  open, including whether `gallery` becomes general layout components
  (`blush/grid`, `blush/flex`). Candidates to weigh: layout (grid, flex
  or stack, columns, group), text (callout, details, quote with cite,
  code with a file name), media (figure, audio, video, embed), and
  actions (button). Settle it before content depends on the names.
- **Browser-friendly feeds and sitemaps** (D-125): XSL stylesheets won't
  work in major browsers for much longer, so 1.x's approach (jtcom's
  `xsl/feed.xsl`) can't carry over. Options: an HTML "about this feed" page
  at a sibling URL, a CSS-only stylesheet (`<?xml-stylesheet
  type="text/css"?>`, limited), or content negotiation that serves HTML
  to browsers (`Accept: text/html`) and XML to feed readers.
- **Subdirectory installs** (D-071): a site at `example.com/site/` needs a
  base path for routing and URL generation. Derive it from `AppConfig::$url`?
  The M3 router and `UrlGenerator` assume the site is at the host's root,
  and so does static export: `build --base-url` takes only an origin
  (D-135).
- **Extension requirements** (D-058): what constraint syntax to support for
  `extension:check`, likely a Composer semver subset (`^`, `~`, comparison
  operators, `||`).

- **CLI publishing and opcache** (found while writing `docs/`, D-141):
  `publish` from the CLI rewrites the index and compiled caches, but its
  `opcache_invalidate()` can't reach the web server's opcache. With
  default settings the site lags by `opcache.revalidate_freq` (about 2 s,
  observed); with `opcache.validate_timestamps=0` it never sees the change
  until PHP restarts. The webhook is unaffected (it runs in the web
  server). Now relevant to M8, since jtcom runs dynamically (D-142).
  Options: document it (done for now), have `publish` ping the
  site to invalidate, or version the index file names.

- **Vite dev-server integration** (D-155, deferred by the author): live
  reload needs asset URLs pointed at Vite's dev server while it runs,
  typically through a "hot" file the dev server writes (Laravel's
  approach) that `ThemeAssets` checks in development. `vite build
  --watch` covers it until then.

- **Design tokens as an add-on** (D-160): the M5b token system (DTCG
  tokens, modes, site and entry overrides, `theme:check` contrast) was
  removed so themes can design however they like. If it comes back,
  probably as an extension, and opt-in per theme. Notes on the old
  design and what it taught are in `theming.md` → Design.

## Tooling
- **Shared path-encoding helper** (noted 2026-09-27):
  `implode('/', array_map(rawurlencode(...), explode('/', $path)))`
  appears in `MediaResolver`, `RoutePattern`, `ExportAssets`,
  `NetlifyFiles`, and `Exporter` (and the standalone
  `resources/static-server.php`, which can't share it). Extract it to
  one helper (such as a `Support` URL-path method).
- **Benchmark regressions in CI** (D-044, D-101): CI machines differ from
  the author's, so absolute baselines don't transfer. Options: compare
  against a baseline measured in the same CI run (the base branch), or
  gate on ratios between subjects.
- **PHPCS property-hook support** (D-048): when PHPCS ships it, remove the
  `phpcs:disable` comments around hooked properties and update the style
  skill.
- **PHPCompatibility 10 stable** (D-049): drop the `@alpha` flag once it's
  released.

## Later
- **Product name** (D-038): the author will decide.
- **1.x's "Powered by" lines** (noted 2026-09-27): 1.x's footer picked a
  random line ("Powered by coffee.", "Powered by an old mixtape and
  memories of lost love.", …; `Template/Tag/PoweredBy.php` on `master`).
  The default theme says "Powered by Blush". Maybe bring the lines back
  in the default theme or the welcome page, for personality.
- **Repo strategy after 2.x stabilizes:** one package, or a split monorepo?
- **Multilingual file convention** (D-036): decided when the feature is built.
- **Theming:** see the open questions in `theming.md`.
