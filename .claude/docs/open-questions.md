# Open questions

Move each item to `decisions.md` once it's answered.

## Needs the author's call

- **Skeleton license** (D-070): confirm MIT for `blush-dev/blush` `2.x`.

## Later milestones
- **Where site code and themes live** (D-144, provisional): with `user/`
  as a separate content repo (jtcom), a site's theme sits in
  `resources/themes/` for now. Revisit the split between the site repo
  and `user/` (themes, extensions, `data/`), and whether `theme:new`
  should offer a location.
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
