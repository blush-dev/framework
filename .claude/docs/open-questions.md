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
- **Rich (script) embeds** (D-184): providers such as X, Instagram,
  TikTok, and Mastodon answer oEmbed with HTML that needs their own
  `<script>`. The planned path: a provider opts in with
  `allowsScripts()`; its `EmbedData::$html` is output as given only for
  such providers (after checking that any script comes from the
  provider's own hosts); the script tag is deduplicated per page, which
  a Markdown component can't do today since its `Head` additions are
  dropped (D-112), so either directive components get a way to add page
  assets or the script stays inline. Also: a site's Content Security
  Policy, privacy (these scripts track visitors; a click-to-load
  placeholder with the thumbnail may be the default), and whether static
  export should snapshot them. Until then they render as links named by
  their title.
- **More icons** (D-187): bundle all of Lucide (about 2,100) rather than
  the front-end subset, and add brand logos (a social menu will need
  basics such as GitHub, Mastodon, and RSS; Simple Icons, CC0, with each
  brand's usage rules) in their own namespace.
- **Refreshing embeds**: `storage/cache/store/embeds` is only emptied by
  hand; a `cache:clear --embeds` or `embed:refresh` command may help.
- **Component namespace clashes** (D-171): a theme's namespace is its
  slug and an extension's is its vendor, so the two could collide.
  Decide whether Blush checks or reserves namespaces.
- **Require a class for every component?** (D-195): template-only
  components remain, with a generic `TemplateComponent` read through
  `prop()` (untyped, no autocomplete). Requiring a class would make every
  template typed and let the admin's inserter read props from
  constructors, at the cost of PHP for the simplest component.
- **Where themes declare component variants** (D-191): the rest of the
  variant plan is set (one per use, `component-{name}--{variant}`, a
  registry with registrants and translatable labels, fallback to the
  default). Working assumption: themes list them in `theme.json`
  (`"variants": {"blush/button": ["ghost"]}`), and extensions and the
  site register them in PHP; the author isn't sure yet.
- **A `<button>` component** (D-189): a real `<button>` for actions that
  need a script (toggles, dialogs), alongside the link-based `button`;
  what it runs, and how, is open.
- **An icon registry** (D-175): SVG icons registered by the framework,
  themes, and extensions, used by an `icon` component and templates
  (`inline()` already inlines a theme's SVGs, D-151).
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
- **Captioned quotes and tables** (D-175, deferred by the author):
  improve the existing blockquote (a source URL and a credited speaker),
  or add a general figure wrapper that captions a quote, table, or code
  block?
- **Product name** (D-038): the author will decide.
- **1.x's "Powered by" lines** (noted 2026-09-27): 1.x's footer picked a
  random line ("Powered by coffee.", "Powered by an old mixtape and
  memories of lost love.", …; `Template/Tag/PoweredBy.php` on `master`).
  The default theme says "Powered by Blush". Maybe bring the lines back
  in the default theme or the welcome page, for personality.
- **Repo strategy after 2.x stabilizes:** one package, or a split monorepo?
- **Multilingual file convention** (D-036): decided when the feature is built.
- **Theming:** see the open questions in `theming.md`.
