# Roadmap

| # | Milestone | Done when |
|---|---|---|
| M0 | **Setup.** Clear 1.x `src/` on `2.x`. New `composer.json` (MIT, PHP 8.5, PSR interface packages, dev tools). `.phpcs.xml` (modeled on x3p0-breadcrumbs, no WordPress rules, 8.5). PHPStan (max). PHPUnit. CI. A local PHP 8.5 toolchain. | The empty project passes lint, analysis, and tests on 8.5 |
| M1 | **Core.** Copy and adapt the x3p0-framework container and application, x3p0-event, and x3p0-class-registry/attributes, with their tests. Add `Paths`, `Env`, config objects, errors, log, and clock. Add a cached container resolution plan (D-044) and extension discovery (D-041). | The copied tests pass under `Blush\` on 8.5 |
| M2 | **HTTP + Console.** PSR-7/17/15 implementations, `Kernel`, `Emitter`, the console framework, and `serve`. | "Hello" is served in the browser, through `Kernel::handle()` in tests, and via `bin/blush` |
| M3 | **Routing.** Compiler, matcher, URL generator, attribute discovery, redirects, and `routes:list`. | 404, 405, and redirects are tested; the route cache works |
| M4 | **Content.** Source, parsers (behind interfaces), schemas, indexer, repository, query, content types from PHP and data (D-042), the built-in `author` type (D-043), `content:*` commands, media, and the PHPBench baseline. | jtcom's ~1,200 entries index and lint cleanly; query benchmarks are recorded |
| M5 | **Views + theming.** Engine, hierarchy, components, `Head`, tokens, theme loader, default theme, built-in controllers, feeds, and sitemaps. | The default theme renders every route type |
| M6 | **Caching + publishing.** Cache layers, content version, `PageCache`, webhook, and `publish`. | One-command publish and cache clear |
| M7 | **Static export.** `build` plus incremental mode. | jtcom exports and serves from static files |
| M8 | **Port jtcom.** jtcom theme, config, `user/` layout, a URL-parity crawl against the live site, and a redirect map. | Every old URL returns 200 or 301; deployed (dynamically, D-142) |
| M9 | **Admin stage 2:** operations dashboard. | Publish, clear, reindex, and export from a browser |
| M10 | **Admin stage 3:** editor and media library. | Create and edit entries in a browser |
| Later | Extension views in the view chain (D-174); `SqliteIndex` + search; in-house YAML and Markdown parsers; theme distribution; custom template engine; Vite dev-server integration | — |

---

## M8 (Port jtcom): in progress

Approach (D-142): jtcom runs dynamically; its theme keeps SCSS; the port
lives on jtcom's `2.x` branch. First, a trial port on a test branch of
`blush-dev/blush` against jtcom's real content, to find framework gaps
before jtcom changes. Skeleton fixes done first (D-143).

The trial branch is `jtcom-trial` in `../blush`. Its `user/` holds an
uncommitted subset of jtcom's content (hidden by the local
`.git/info/exclude`): all non-post content, the newest 55 posts, and
the 289 media files they reference (87 MB). The skeleton's own sample
files show as deleted there; don't commit them.

The trial covers everything jtcom 1.x does (`app/`, `config/`,
`public/views/`, `resources/scss/`): its seven types, the archive pages,
its controllers and `EntryTerms` block (as built-in routing and a
component), head meta, Markdown setup, every view, and the SCSS build.

### Trial progress

Done on `jtcom-trial` (a test bed; its site files are never committed,
D-156): `config/content.php` (the seven types, typed objects,
`home: 'post'`), `config/media.php` (`/user/media`), `config/markdown.php`
(jtcom's extensions and options). The theme is `user/themes/jtcom`
(D-167), with the `Jtcom\View\PostArchives` component (the year, month,
and full archive lists) and its `Jtcom\ThemeProvider`, built with Vite
(D-155; `npm run build` in the theme's folder), with views for every page kind:
singles (post, literature, page), the home page and listings, date
archives, taxonomy lists, the art/drawing/painting image grids, the three
archive pages, errors, 1.x's numbered pagination markup, head meta
(description, OpenGraph, Twitter, theme color, icons, font preloads,
print styles), and an `entry-terms` component. `theme:check` passes, and
`build` crawls 421 pages with no failures; its 54 broken links are posts
outside the subset, jtcom's old `/warehouse` files, and relative links.

Findings, and what was done:

- **Fixed:** partials see the page's data (D-146); `collection-taxonomy`
  covers taxonomy listings (D-147); `inheritTokens: false` drops the
  default theme's tokens (D-148); the head gets the entry's description
  and `og:image` (D-149); `excerpt()` takes an HTML `$more` (D-150);
  `readingTime()`, `wordCount()`, and `inline()` for theme SVGs (D-151);
  site themes (D-144, since removed by D-167) and web app manifests (D-145). The jtcom theme
  uses all of them.
- **Also fixed:** the archive lists are cached per content version and
  theme (first with the new `$this->cache()` fragment helper, D-152;
  now as `::post-archives{by=…}` directives in the three pages' content,
  cached with the rendered body, so the theme needs no `single-page-*`
  views for them), and
  titles use `$this->widont()`, 1.x's `runt()` (D-153).
- **Custom 1.x views:** `template-canvas` (/plugindevbook, with its
  `style` sheet mapped from `/public/...` to the theme) and the
  standalone React tic-tac-toe page (`Head::remove()` drops the theme's
  styles, D-154).
- **Theme build (D-155):** Vite builds the jtcom theme. Sources are in
  the theme's `resources/` (SCSS migrated from `@import` to `@use` with
  `sass-migrator`; the compiled CSS matched the old output byte for byte),
  and the built, hashed files and manifest are in its `public/`
  (committed). `vite.config.js` and `package.json` are at the site root.
  The feed and sitemap SCSS are kept but not built (the sitemap file
  didn't compile in 1.x either).
- **Not ported:** `MarkdownCite` (unused in jtcom's content, and its
  `:tag[...]` syntax collides with inline components). jtcom's own
  `style` front matter is handled in its theme.
- **Content issues** (for the redirect map): 7 media references that
  don't exist in jtcom either.

Carried from M7: the 114 dead links in old posts feed the redirect map.

### Remaining for M8 (on hold, D-156)

- **The trial is a test bed, not a commit.** The author tests on
  `jtcom-trial`; its site files stay uncommitted. Framework changes found
  through it are committed in this repo as usual.
- **jtcom's `2.x` branch** (the skeleton plus the trial's site files,
  with jtcom's full content): waits until the author says jtcom can
  change. Content changes to make then: `__drafts/` files move to
  `_posts/` as `status: draft` (D-227).
- **URL parity and the redirect map** (every old URL answers 200 or
  301; the 114 dead links and 7 missing media references), and
  **production and deploy** (`APP_ENV=production`, the page cache,
  `publish` and the webhook on the host, the CLI-publish opcache
  question): wait until the author is ready to go live.

## Next: setup DX/UX (D-156)

The current focus: the experience of setting up a Blush site.

### Done

- **Defining content types (D-157):** kinds as classes (`Collection`,
  `Taxonomy`, `Pages`; `kind:` in data), clearer option names (`folder`,
  `urls`, `listing`/`termListing`, `types`, `aliases`, `dateArchives`,
  feed `categories`), and a typed `Listing`. 1.x names still read. The
  `jtcom-trial` config uses the new classes; it builds the same 421
  pages.
- **Removed the design token system (D-160):** themes style themselves
  with plain CSS; the default theme's palette is custom properties in
  `style.css` with `light-dark()`. Tokens may return as an add-on.
- **1.x features restored:** numbered pagination as `PageLink`s
  (D-161), page numbers in paged titles (D-162), and `dump()`/`dd()`
  with stray output kept in the page (D-163).
- **Component discovery (D-164):** the four core components declared in
  `ComponentType`, `component:list`, and a `theme:check` warning for
  components with no template.
- **Component names and metadata (D-171 to D-173):** namespaced names
  (short names only for core), `{namespace}-{name}.php` templates,
  registered definitions with props from constructors, translatable
  text by namespace (the new `app` and extension vendor domains), labels
  in `component:list`, and `theme:check` checks. The trial's components
  are `jtcom/post-archives` and `jtcom/entry-terms`; it builds the same
  421 pages.
- **Component docs and classes:** `docs/components.md` (D-170);
  component classes are `component-{name}` BEM blocks (D-182); checking
  a theme leaves out other themes' components (D-178).
- **The core component set (D-175):** definition lists and highlighting
  in Markdown (D-176); `group`, `grid`, and `row` (D-177); `audio`,
  `video`, and `file` (D-179); `abbr`, `kbd`, and `time` (D-180); `toc`
  (D-183); `icon`, with a 131-icon Lucide subset (D-187); `progress` and
  `meter` (D-188); and `button` (D-189). Media props resolve against the
  entry's bundle (D-179), and media and link props render as full URLs
  for feeds (D-190).
- **Embeds (D-181, D-184 to D-186):** start times and accessible names;
  oEmbed providers (YouTube, Vimeo, config, and classes) with cached
  lookups, real sizes and titles; frames sized with `aspect-ratio`,
  with a height cap for portrait video.
- **Component templates get one `$component` (D-195):** typed props as
  properties, logic in methods, `attributes()` for the root element,
  and a class for every core component (template-only ones get a
  `TemplateComponent`). Content and slots are on it too, with methods
  named for their role (`caption()`, `text()`; D-196).
- **The jtcom trial's theme** styles every new component in its
  hand-drawn look (not committed; D-156).
- **Menus and regions (D-199 to D-204):** site data in
  `user/data/menus/` and `user/data/regions/` filling theme locations;
  entry, term, collection, route, and URL links; rich items and
  theme-declared item fields; locale maps for text; the core `menu`
  component, `$template->menu()`, `region()`, and `hasRegion()`;
  `menu:list`, `menu:show`, and `theme:check` reports. The default theme
  shows a `primary` menu and a `footer` region; the jtcom trial's
  primary and social menus are data now, and it builds the same 421
  pages. User guide: `docs/menus.md`. More design work on how menus and
  regions relate is still to come (see `open-questions.md`).

- **First-run setup (D-218):** `init` (creates `.env`, asking for the
  basics; an opt-in webhook secret; the storage folders), `doctor` (every
  setup check, with hints), and a plain setup page instead of a stack
  trace while storage isn't writable. Docs: `docs/installation.md`.
  Still to do on the skeleton's `2.x`: run `init` from
  `post-create-project-cmd`.

- **Accounts and auth, no UI (D-219):** server-side sessions, CSRF,
  accounts in `storage/accounts` with `account:*` commands (and `init`
  offering the first), roles and capabilities (`config/auth.php`),
  permissions with ownership through the author link, throttled sign-in,
  and the admin's JSON sign-in API (`AdminConfig`, off by default). Docs:
  `docs/accounts.md`. The jtcom trial has the admin on with one account.

### The admin (M9, D-215, D-220 to D-223): in progress

Done: the Vue app's shell, sign-in, and dashboard (entry counts, and
the `publish`, `reindex`, and `clear-caches` actions, which extensions
extend in PHP); drafts and scheduled entries (a tab on each list since
D-236), the trash (a tab too, with restore as a draft, D-237), and content health
(D-225); signed preview links (D-226); the paged, filterable entry
list API (D-230); design tokens and the rail-and-top-bar shell from
the admin design direction (D-231), with each account's light/dark
preference on Your profile (D-232, D-235). Docs: `docs/admin.md`.

M10 has started: writing content back to files (`ContentWriter`,
D-228), the editing API (D-229), and the first editor screens (D-233):
a list per content type (D-234), New entry, and the editor with forms
from content schemas and a plain-text Markdown body; then the design
direction's loading, offline, failed-save, conflict, validation, and
first-run patterns (D-240); then, from the author's clickable prototype
(`admin-design/blush-admin.html`), the full navigation with stubs for
the screens not built yet (Media, Content types, Appearance,
Extensions, Accounts, Roles, Settings) and a Markdown source editor
that highlights directives and knows the one under the caret (D-241);
then the component inserter, with `/` to open it at the caret and
`GET components` behind it (D-243); then, from the updated design, the
section rail (Home, Content, Config) with a panel per section (D-244),
and the editor as a writing surface: one centered column, a settings
drawer with Document and Component tabs, component options written back
into their directives, and focus mode (D-245); then the header's two
halves and three inserters: components in a panel from the left, icons
in a popover, and media in a modal picker that's also **Choose** beside
media fields (D-247), over `GET icons` and `GET media` (D-246).
Then toasts and the ⌘K command palette (D-248), and read-only list and
detail screens for Roles and Accounts (D-249), Content types (D-250),
and Media (D-251). Then content-model gaps the admin exposed: type
descriptions and icons (D-256), pages nesting by folder and hierarchical
taxonomies by `parent` (D-257), `_{name}` type folders (D-258), and
authors as the public side of accounts (D-259).
Then the updated design (D-265): the space scale, flat surfaces, and a
compact toggle for lists; four inserters (block components, media,
icons, and an inline menu), icons as a library modal grouped by
category (`GET icons`' `category` and `source`), a wider media picker,
the drawer's tabs and never-disabled Component tab, and the source
marked as the design's table says. Then component variants (D-266):
Default plus named variants from a component, a theme's `theme.json`, or
the `ComponentVariantsCollecting` event, with the callout's tones and
the button's secondary style as core variants, and a Variant select in
the editor. Then `:::figure` as a container for anything captioned, and
images from the media picker as plain Markdown (D-267).
Next: the Markdown editing experience (D-252; begun with styled
Markdown and editor addresses by handle, D-253; a 640px Fira Code
editor, site addresses in tables, and row menus, D-254; Fira Code
throughout and pinned index pages, D-255), then uploads and media
metadata, a reference picker,
editing types and accounts, and the remaining stubbed screens
(Appearance, Extensions, Settings). Live preview waits (D-252): inline
image and embed previews first, then a full preview, above all of
components. Smaller admin items waiting: the admin theme choice (a second
account preference, D-235), changing one's own password on Your profile,
renaming an entry from the editor, objects in forms, autosave, and Pages
and hierarchical terms as a tree (see D-233 to D-237's and D-257's open
items).

Testing the admin on the jtcom trial: create a throwaway administrator
account file in `../blush/storage/accounts/` (an Argon2id hash), drive
the admin with Playwright and Chrome, and delete the account, its
sessions, and anything it created afterwards. ddev syncs files with
Mutagen, so an edit made on the host can reach the container late: test
write conflicts through the API, not by editing files on disk.
Export as a background-safe action is deferred (jtcom runs
dynamically).

### Still to scope

- **Relationships (D-242, planned):** a reverse index for every
  reference field, keyed by field, with a template API and "Used by" in
  the admin; then "lists what references it" as a setting for any type,
  with taxonomies as a preset; then one picker in the admin.

Other starting points the author may pick up (none decided):


- **Required component registration (D-266's direction):** every
  component registered to render (PHP, or JSON with translations).
- **Later for components:** captioned quotes and tables, a `<button>`
  component, rich script embeds and an embed refresh command, extension
  views (D-174), more icons and brand logos (see `open-questions.md`).

- Creating a site: `composer create-project` (once the skeleton is on
  Packagist, running `init` afterward; D-218).
- **A global installer (D-165):** a separately installed `blush` command
  (like `laravel/installer`, via `composer global require`) that creates
  sites (`blush new mysite`) and, inside a site, runs that site's
  `bin/blush`. Until then, the docs can show the small launcher script
  that finds the nearest `bin/blush` (the author uses one in
  `~/.local/bin/blush`).
- Setup notices beyond storage (D-218): a friendly page for "no
  content yet", and whether a web server rewrite check is possible.
- The first look: the welcome page, the skeleton's sample content (its
  `blog/` isn't a content type, so the sample post is a plain page), and
  the default theme.
- Local development: `serve`, DDEV, and theme builds (Vite, D-155).
- The docs' installation guide (`docs/installation.md`) as the script
  for all of it.

---

## M7 (Static export): done

Started and finished 2026-09-26, in two slices (D-134). Exit criterion,
**jtcom exports and serves from static files:** done (M7b, on Apache).

### M7a: export (done)

Implemented 2026-09-26. See D-135 to D-138. Delivered and tested (703
tests):

- `Blush\Export`: `Exporter` (reindex, export application, public files,
  crawl, 404 page, theme and media files, prune, manifest, events, lock,
  output-folder safety), `ExportSite` (the production export
  application, D-135), `Crawler` (sources, paging by asking, link
  crawling, broken links), `UrlSource` + `ExportUrl`, `ExportLayout`,
  `ExportWriter`, `ExportAssets`, `ExportManifest`, `ExportReport`,
  `ExportConfig` (`config/export.php`), and `ExportStarted`/`ExportFinished`.
- URL sources: `ContentExportUrls`, `FeedExportUrls`, `SitemapExportUrls`.
- `Bootstrap::withConfig()` and `withPaths()`.
- `build [--base-url] [--no-crawl]`, `serve --static` with
  `resources/static-server.php`, and `Filesystem::files()`.

Checked:

- https://blush.ddev.site's `../blush` site builds (13 pages) and
  `serve --static` serves every page, sitemap, `robots.txt`, media,
  the theme stylesheet, and the 404 page with the right statuses and
  content types.
- **jtcom's real content** (a scratch site with its 1.x type config,
  `home` `post`, and `MediaConfig(url: '/user/media')`, as in M4c):
  2,795 pages and 4,261 media files in 12 s, no failures; served from
  the static files, the home page and `/page/2`, singles, year and month
  archives, terms, `/writing` and its forms, pages, the RSS, Atom, and
  JSON feeds, sitemaps, `robots.txt`, `/user/media/…`, and the 404 page
  all answer as expected. The crawl reports 114 broken links, all real
  dead links in old posts (input for M8's redirect map), and 4
  misdated-link redirects.
- The generated jtcom-sized site: 2,920 pages in about 9 s, 34 MB peak;
  a second run leaves every file unchanged.

### M7b: incremental mode and hosts (done)

Implemented 2026-09-26. See D-139 and D-140. Delivered and tested (708
tests):

- `build --incremental` with `ExportFingerprint` and the content version
  in the manifest.
- Redirects: `Routing\RedirectExportUrls` (the table's literal redirects,
  confirmed by rendering), `ExportRedirect`, pattern redirects, and
  redirect pages (`ExportConfig::$redirectPages`).
- Host files (`Export\Host`): `HostFormat`, `HostFiles`, the registry,
  factory, and registrar, `HostContext`, `HostOutput`, `ApacheFiles`
  (`.htaccess`), and `NetlifyFiles` (`_redirects`, `_headers`);
  `ExportConfig::$hosts`.
- `serve --static` applies `_redirects` and hides the host files.

**Exit criterion, checked on Apache** (a private XAMPP 2.4.53 instance,
`AllowOverride All`, the jtcom export at its root): all 2,798 rendered
URLs answer 200 (301 at redirected paths); the 4 redirects the crawl met
and trailing slashes answer 301; `/feed`, `/feed/atom`, `/feed/json`,
`/sitemap`, and `robots.txt` carry their content types; media are
served; unknown paths get the themed 404; `.htaccess` and `_redirects`
are 403s. A rebuild wrote only the changed `.htaccess`, and an
incremental build with nothing changed takes about 1.3 s (a full one
about 10 s).

### M7 carried forward

To M8: the 114 broken links the crawl found in jtcom's old posts feed
the redirect map, and the URL-parity crawl can run against a static
export too. Later: image derivatives in the export (with `image()`),
testing the Netlify files on Netlify and Cloudflare Pages, and a
subdirectory base path (open question).

---

## M6 (Caching + publishing): done

Started and finished 2026-09-25, in two slices (D-126). Exit criterion,
**one-command publish and cache clear:** done. `bin/blush publish`
(or a signed webhook request) pulls, reindexes, recompiles what depends
on site data, clears the store, and moves the content version on;
`bin/blush cache:clear` clears everything.

### M6a: caching (done)

Implemented 2026-09-25. See D-127 to D-130. Delivered and tested (677
tests):

- `Blush\Cache`: the PSR-16 `Store` base with the `file`, `php`,
  `apcu`, `array`, and `null` drivers (enum + registry + factory +
  registrar), namespaces, `CacheConfig` (`config/cache.php`; on outside
  development), and `Caches`.
- `ContentVersion` (`storage/cache/content-version.json`): bumped when
  the index is stored and on `cache:clear`/`cache:compile`, and moved on
  by itself at the next scheduled go-live time.
- `PageCache` and `Http\Middleware\ConditionalGet` (ETag, 304s), run by
  the kernel through the new `Kernel::MIDDLEWARE` tag.
- Rendered bodies, summaries, and excerpts (`BodyCache`,
  `RenderedBodies`; bodies read their file only on a miss) and compiled
  token CSS, per content version and theme (the M5 carry-overs).
- `cache:clear` clears the store and bumps the version (`--store` for
  only that); `cache:compile` does too.

Checked on https://blush.ddev.site (after `composer update
blush-dev/framework` in `../blush` for `psr/simple-cache`): pages, the
theme stylesheet, and the sitemap → 200, `/nowhere` → 404, a matching
`If-None-Match` → 304, and with caching turned on, `X-Page-Cache: miss`
then `hit`. `bin/blush cache:clear` clears the store.

**Benchmarks** (`CacheBench`, new; `ContentBench` now runs with caching
off, so its numbers stay comparable):

| Subject | What it measures | Time |
|---|---|---|
| `benchRequestHome` | `/`, caching off | 8.5 ms |
| `benchRequestSingle` | A term archive, caching off | 8.3 ms |
| `benchRequestHomeCachedBodies` | `/`, page cache off, bodies and tokens warm | 1.7 ms |
| `benchRequestSingleCachedBodies` | The term archive, the same | 2.5 ms |
| `benchRequestHomeCachedPage` | `/`, a page cache hit | 0.048 ms |
| `benchRequestSingleCachedPage` | The term archive, a page cache hit | 0.047 ms |

Carried into M6b: publishing (D-126).

### M6b: publishing (done)

Implemented 2026-09-25. See D-131 to D-133. Delivered and tested (688
tests):

- `Blush\Publish`: `Publisher` (optional pull, compiled content types
  and routes, reindex, store clear and prune, new content version, one
  at a time), `PublishReport`, `PublishConfig` (`config/publish.php` or
  `PUBLISH_*`), the `Puller` seam with `GitPuller`, and the
  `ContentPublished` event.
- The webhook: `POST /_blush/publish` (only with a secret),
  `WebhookSignature` (HMAC-SHA256 over timestamp and body), replay
  protection in the persistent `webhooks` namespace, and JSON answers.
- `publish [--pull] [--no-pull]` and `schedule:run`.

Checked on https://blush.ddev.site: `bin/blush publish -v` and
`bin/blush schedule:run` work, and with a secret set temporarily, a
signed webhook request → 200 with the report, the same request again →
409, and an unsigned one → 401. A real `git pull` is covered by
`PublishTest` (a bare origin, an author clone, and `user/` as a clone).

### M6 carried forward

Page-cache files the web server serves without PHP (`try_files`), a
template `cache()` helper for fragments (done in the M8 trial, D-152),
tagged invalidation, rate
limiting for the webhook (with the admin's middleware, M9), and
`CacheCleared`. To M7: static export can reuse the content version for
incremental builds.

---

## M5 (Views + theming): done

Started and finished 2026-09-25, in three slices (D-102). Exit criterion,
**the default theme renders every route type:** done, checked by
`DefaultThemeTest` (D-124), which fails when a route type has no sample.

### M5a: view engine, themes, default theme (done)

Implemented 2026-09-25. See D-103 to D-110. Delivered and tested (594
tests):

- `Blush\View`: `Views` (isolated-scope PHP templates, layouts, sections,
  partials), `Template` (the `$this` API), `ViewContext`, `ViewFinder`
  (site overrides, then the theme chain), `ViewFactory`, `Hierarchy`,
  `Head`, `Site`, `Escaper` and the global `e()`/`attr()`/`url()`/`js()`/
  `css()`/`raw()` helpers.
- `ThemedPageRenderer` replaces `BasicPageRenderer`; `ThemedErrorPages`
  renders HTTP errors through `Http\ErrorPages` (from `_errors/` or 1.x's
  `_error/` entries), falling back to the generic page. The `welcome`
  view replaces `WelcomeHandler` (resolves the empty-state question).
- `Blush\Theme`: `ThemeManifest` (`theme.json|yaml`), `Themes`
  (`user/themes` plus the framework `default`), `ThemeChain` (parents,
  loop detection), `ThemeConfig` (`config/theme.php`), `ThemeResolver`
  (`?theme=` in development), and the `theme.asset` route.
- `Blush\Translation\Translator`: ICU messages, per-domain catalogs with
  key-by-key overrides, locale fallback (`blush` and `theme` domains).
- The framework default theme, `resources/themes/default`: base layout
  with landmarks and a skip link, `single`, `collection`, `error`,
  `welcome`, parts, a light/dark stylesheet, and `lang/en.json`.
- `layout` and `class` front matter; `template` first in every hierarchy.

Checked on https://blush.ddev.site (after `composer update
blush-dev/framework` in `../blush` to pick up the helpers' `files`
autoload): `/`, `/about`, `/blog`, and `/themes/default/style.css` → 200;
`/nowhere` → 404 with the site's `_errors/404.md`.

**Benchmarks:** the request subjects now render themed pages. Listings
show excerpts, which renders each listed entry's Markdown body
(about 0.7 ms per 3 KB body with CommonMark), so `benchRequestHome` is
8.3 ms (was 0.71 ms with the title-only stand-in) and `benchRequestSingle`
8.2 ms (was 1.7 ms). The rendered-body cache (M6) is the fix; the page
cache hides it entirely.

Carried into M5b/M5c: see D-102. Also: compiling theme manifests for
production, site and extension translation domains, and `image()`.

### M5b: components, tokens, settings, assets, CLI (done)

Implemented 2026-09-25. See D-111 to D-121. Delivered and tested (630
tests):

- Components (`Component`): template-only and class-backed, slots,
  `$this->component()`, the registry/factory/registrar, and `Embed`.
- Markdown directives (an in-house CommonMark extension) rendered as
  components with the request's theme, and the core content components
  in the default theme: `callout`, `gallery`, `figure`, `embed`.
- Context providers.
- Theme discovery before boot (framework, Composer `blush-theme`, local),
  broken manifests recorded instead of fatal, the compiled theme cache,
  and theme providers with PSR-4 autoloading.
- Settings (content field types, `user/data/theme.json`,
  `$this->setting()`; the default theme's `excerpts`).
- DTCG tokens with aliases, modes (light/dark), site and per-entry
  overrides, sanitizing, inline CSS, and `$this->token()`; the default
  theme's palette and scale are tokens.
- `ThemeAssets` (Vite-style manifests or mtime), `stylesheet` front
  matter, and `theme:publish`.
- `theme:list`, `theme:activate`, `theme:new`, `theme:check` (contrast,
  landmarks, skip link, and more), `theme:why`, and `theme:publish`.

Checked on https://blush.ddev.site: pages render with the compiled tokens,
and `bin/blush theme:check` passes the default theme (0 errors, 0
warnings). Request benchmarks: `benchRequestHome` 8.7 ms and
`benchRequestSingle` 8.3 ms (M5a: 8.3 and 8.2), the difference being the
token CSS.

Carried forward: `image()` and derivatives, menus and regions, hierarchy
candidates added by theme providers, `requires` enforcement (with
`extension:check`), and caching compiled tokens and rendered bodies per
content version (M6; the body cache must key on the theme, D-112).

### M5c: feeds, sitemaps, robots.txt (done)

Implemented 2026-09-25. See D-122 to D-124. Delivered and tested (643
tests):

- `Blush\Feed`: RSS, Atom, and JSON Feed per collection, home, and
  taxonomy term, with 1.x's route names and paths plus `.feed.json`;
  `FeedConfig`; `<link rel="alternate">` on pages; default theme
  templates `feed-rss`, `feed-atom`, and `feed-json`.
- `Blush\Sitemap`: `/sitemap` (and `/sitemap.xml`), `/sitemap/{type}`,
  and `/robots.txt`, with `SitemapConfig`; templates `sitemap-index` and
  `sitemap`.
- `View\DocumentRenderer` for themed non-HTML documents.
- The exit-criterion test (D-124).

Checked on https://blush.ddev.site: `/sitemap` and `/sitemap/page` serve
XML, and `/robots.txt` disallows everything (it's development). Request
benchmarks are unchanged (8.5 ms and 8.2 ms).

Carried forward: splitting sitemaps past 50,000 URLs, sitemap image
entries, and a way to make feeds and sitemaps readable in a browser.
jtcom's 1.x feeds used an XSL stylesheet, but major browsers are dropping
XSLT, so that isn't the solution (D-125; see `open-questions.md`). Feed and sitemap URLs are
checked against jtcom's live site in the M8 URL-parity crawl.

### M5 carried forward

To M6: caching compiled tokens and rendered bodies per content version
(the body cache must key on the theme, D-112), and the page cache for
themed pages. Later: `image()` and derivatives, menus and regions,
hierarchy candidates from theme providers, `requires` enforcement,
and site and extension translation domains.

---

## M4 (Content): done

Started and finished 2026-09-25, in three slices (D-079). Everything 1.x
supports carries over (D-078); jtcom's files and front matter don't
change.

Exit criteria:

- **jtcom's ~1,200 entries index and lint cleanly:** done in M4b (all
  1,183 files; 0 errors, 0 warnings).
- **Query benchmarks are recorded:** done in M4c (below).

### M4a: data, parsers, types, schemas (done)

Implemented 2026-09-25. See D-078 to D-086. Everything below is delivered
and tested (478 tests). A smoke run over all 1,183 jtcom content files
(parse, type, and schema resolution against jtcom's unchanged 1.x type
config) reports no errors and no warnings, in about 200 ms uncached.
Notices are the expected ones: 1.x aliases (`date`, `author`, `excerpt`,
`view`) and undeclared keys (`format`, `tag`, `amazon`, …).

Carried into M4b: caching the resolved content types for production, and
YAML extension manifests (D-058), now that the data loader exists.


- `symfony/yaml` ^8.1 and `league/commonmark` ^2.10 behind Blush
  interfaces (D-080).
- `Blush\Data`: `YamlParser` (+ Symfony adapter), `DataParser` with JSON and
  YAML parsers, `DataFormat` enum, registry and registrar, and `DataLoader`
  (by name, JSON wins, reports shadowed files) (D-032).
- `Blush\Markdown`: `MarkdownParser` (+ CommonMark adapter), `MarkdownConfig`
  (options, extensions, inline parsers), and the
  `MarkdownEnvironmentBuilding` event.
- `Blush\Content\Schema`: `Field` base, the built-in field types (`text`,
  `markdown`, `date`, `bool`, `number`, `enum`, `list`, `reference`,
  `media`, `slug`, `object`), `FieldType` enum, registry, factory, and
  registrar. `Schema` resolves names and aliases (the canonical name wins),
  coerces scalars into lists, keeps undeclared keys (D-081), and reports
  violations.
- `Blush\Content\Type`: `ContentType` (1.x options accepted, D-078),
  `ContentConfig` (`config/content.php`: types, home alias, data-type
  policy, disabled built-ins), the built-in `page` and `author` types
  (D-043), data-defined types from `user/data/types` (D-042), and the
  resolved `ContentTypes` (type by name, by path, and for a file).
- `Blush\Content\Parser`: front matter splitting and document parsers by
  extension (Markdown, HTML, JSON, YAML).

### M4b: source, index, query, commands (done)

Implemented 2026-09-25. See D-087 to D-092. Delivered and tested (525
tests):

- `Content\Source`: `ContentSource`, `FilesystemSource`, `SourceFile`.
- `Content\Index`: `ContentIndex`, `PhpIndex` (`storage/index/content.php`),
  `IndexSnapshot` (records plus derived keys, term relations, labels,
  conflicts, the next scheduled time, and a fingerprint), `IndexRecord`,
  `RecordBuilder` (1.x file conventions, D-088), `Indexer` (full and
  incremental by mtime/size, then hash), `IndexReport`, `ArraySelector`,
  and the `ContentIndexed` event.
- `Content\Entry`: `Entry` (lazy `Body` ghost, `excerpt()`) and
  `EntryHydrator` (virtual terms included).
- `Content\Query`: `Query` (fluent and 1.x arguments, D-089), `Order`,
  `EntryCollection`, `Paginator`, `QueryRunner`, `Selection`.
- `ContentRepository` + `IndexedRepository` (index on first use, dev
  auto-index, `term()`, `termCounts()`, D-090).
- `Content\Lint`: `Linter` and `LintReport` (D-091).
- `content:index [--full]` (with `Console\ProgressBar`), `content:lint
  [--strict]`, `content:list [--type] [--status]`, and `content:new`.
- The M4a carry-overs: the compiled content-type cache
  (`storage/cache/content-types.php`, `cache:clear --types`) and YAML
  extension manifests (D-092).

Exit criterion, **jtcom's ~1,200 entries index and lint cleanly:** done.
Against jtcom's unchanged content and 1.x type config, `content:index`
indexes all 1,183 files in about 200 ms (a no-op incremental run takes
about 20 ms), and `content:lint` reports 0 errors and 0 warnings. With
`--strict` there are 3,459 notices, all expected: 1.x aliases (`date`,
`author`, `excerpt`, `view`), undeclared keys (`format`, `tag`, …), and
virtual terms (jtcom has no author files). The index file is about
2 MB. The `../blush` dev site indexes and lints too.

Carried into M4c: entry URLs (they need the content routes), and
reverse relations for non-taxonomy reference fields if a feature needs
them.

### M4c: routes, media, benchmarks (done)

Implemented 2026-09-25. See D-093 to D-101. Delivered and tested (554
tests):

- Content routes with 1.x's names and URL parameters (`ContentRoutes`),
  the home page and home alias, and the page catch-all (`PageRoutes`,
  fallback priority, so a site's `/` wins). `FallbackRoutes` is gone; the
  home controller shows the welcome page on an empty site.
- Content controllers (home, collection, date archive, single, term,
  page) over a `PageRenderer` seam, with `BasicPageRenderer` standing in
  until M5. Canonical redirects for `/page/1` and misdated singles.
- `ContentUrls` (entry, collection, term, and date-archive URLs from type
  routing), and `AppConfig::origin()`/`absoluteUrl()`.
- Redirects from `user/data/redirects.*` and `redirect_from`, after the
  config's, kept current in a compiled route table by
  `RefreshRouteCache`.
- The router treats fallback routes as soft (D-095), and `int` route
  casts accept leading zeros.
- A stale index (other types, timezone, or locale) is rebuilt on first
  use in any environment (`IndexFingerprint`, D-098).
- `Blush\Media`: `MediaConfig`, `MediaResolver` (user media, 1.x
  `/user/media` paths, and page bundle files), `MediaController` and the
  `media` route, `media:publish [--copy]`, and `Response::file()` Range
  support over `LimitedStream`.
- 1.x Markdown rendering: media URLs and image dimensions, absolute
  root-relative links, and lone images as `<figure>`s (D-100).
- PHPBench (`composer bench`) with the generated jtcom-sized site
  (D-101).

Checked against jtcom's real content (served through `Kernel::handle()`
with its 1.x type config and `MediaConfig(url: '/user/media')`): the home
page and `/page/N`, `/archives/2008` and `/archives/2008/04`,
`/archives/2003/04/15/welcome-to-my-site` (and a misdated URL → 301),
`/topics`, `/topics/art`, `/writing`, `/writing/forms/essay`, `/about`,
`/about/biography`, `/archives/years`, `/authors/justintadlock`, and
media with ranges all answer as expected; `/_error/404`, `/__drafts/...`,
and unknown paths are 404s. The `../blush` dev site serves its pages on
DDEV.

**Baselines** (2026-09-25; the author's Mac, PHP 8.5.10, opcache on in
the CLI; mode of 5 iterations; the generated 1,183-file site):

| Subject | What it measures | Time |
|---|---|---|
| `benchIndexFull` | Parse and index every file | 112 ms |
| `benchIndexUnchanged` | Incremental run with nothing changed | 5.9 ms |
| `benchLoadIndex` | Load the index file (from opcache) | 0.020 ms |
| `benchHomePage` | 940 posts by published date, page 1 of 10 | 2.1 ms |
| `benchDeepPage` | The same, page 80 | 2.0 ms |
| `benchDateArchive` | Posts in one month | 0.32 ms |
| `benchTermArchive` | One category's posts, page 1 | 1.3 ms |
| `benchNamedLookup` | One entry by type and key | 0.004 ms |
| `benchTermCounts` | Listed entries per category | 0.48 ms |
| `benchRequestHome` | `/` through the kernel (home type) | 0.71 ms |
| `benchRequestSingle` | A term archive through the kernel, bodies rendered | 1.7 ms |

Carried forward: feed and sitemap routes (M5), themed rendering
replacing `BasicPageRenderer` (M5), reverse relations for non-taxonomy
reference fields if a feature needs them, data-file redirects in a
compiled table refreshing on publish (M6), and gating CI on benchmark
regressions (open question).

---

## M3 (Routing): done

Implemented 2026-09-25. See D-073 to D-077. Delivered:

- `Blush\Routing`: `Route`, `RoutePattern`, `Redirect`, `RouteConfig`
  (`config/routes.php`), the routing attributes (`Route`, `Get`, `Post`,
  `Put`, `Patch`, `Delete`, `Group`), `RouteSource`/`RedirectSource` with
  `RoutePriority`, the config, controller, and fallback sources,
  `RouteCompiler`, `RouteTable`, `RouteCache`, `Router`,
  `ControllerHandler`, `UrlGenerator`, and the `RouteMatched` event.
- `Http\HttpError`, `NotFound`, and `MethodNotAllowed`, mapped to their
  statuses by `HandleErrors`.
- Trailing-slash canonicalization, redirects before 404, and `/public/...`
  redirects (the D-071 follow-up).
- `routes:list`, `cache:clear --routes`, and route compilation in
  `cache:compile`.

Exit criteria:

- **404, 405, and redirects are tested:** `RouterTest` (plus
  `RouteCompilerTest`, `RoutePatternTest`, `UrlGeneratorTest`,
  `RouteConfigTest`). Done.
- **The route cache works:** `RouteCacheTest` (production serves the cached
  table until it's cleared; development ignores it; `compile()` writes
  it). Done.
- Checked on https://blush.ddev.site: `/` → 200, `/nope` → 404,
  `POST /` → 405 with `Allow: GET, HEAD`, `/public/x` → 301 `/x`.

Carried forward: content-type routes, the page catch-all, `redirect_from`,
and data-file redirects (M4); route enumeration for export and sitemaps
(M5/M7); the locale segment (D-036); and subdirectory base paths (open
question).

---

## M2 (HTTP + Console): done

Implemented 2026-09-25. See D-063 to D-070. Delivered:

- `Blush\Http`: PSR-7 messages (`Request`, `Response`, `Uri`, `Stream`,
  `UploadedFile`), `Status`, `HttpFactory` (PSR-17), `RequestFactory`,
  the PSR-15 `Pipeline`, `HandleErrors`, `Kernel`, `Emitter`, `HttpConfig`,
  the `RequestReceived`/`ResponseReady` events, and `WelcomeHandler`.
- `Blush\Console`: commands declared by attribute and `__invoke()`
  parameters, argv parsing and binding, `Output`, `Prompt`, the registry
  and registrar, `CommandTester`, and `list`, `help`, `serve`,
  `cache:clear`, and `cache:compile`.
- `Core\Runner`, `HttpRunner`, and `ConsoleRunner`, with two-stage error
  handling (D-064).
- Plan warm-up for everything the container knows (D-066).
- `../blush` `2.x` branch with DDEV at PHP 8.5 (D-063).

Exit criteria:

- **Tests:** "Hello" through `Kernel::handle()` (`KernelTest`,
  `RunnerTest`). Done.
- **`bin/blush`:** `serve` returns the Hello page (checked with curl), and
  `cache:*` works. Done.
- **Browser (DDEV):** https://blush.ddev.site serves the Hello page on
  PHP 8.5, after the 1.x leftovers were removed from `../blush`. Done.

Deferred from M2: progress bars (M4), `Response::file()` Range support
(M4), and the other built-in middleware (with their features).

---

## M1 (core): done

Completed 2026-09-25. See D-051 to D-061. Delivered:

- Container (plan-based, compiled plans), `Application`, and `ServiceProvider`,
  with the copied x3p0 tests passing under `Blush\`.
- Events (PSR-14), `Support\Registry`, and `Support\Attributes`.
- `Paths`, `Environment`, `Env`, the config system (`AppConfig`,
  `LogConfig`, `ExtensionConfig`), error handling, the PSR-3 logger, and the
  PSR-20 clock.
- Extension discovery (Composer and local), with a local autoloader and cache.
- `Core\Bootstrap` with `compile()`/`clearCompiled()`, tested against
  `tests/Fixtures/site`.

Carried into M2: the compile and cache-clear commands, and a plan warm-up
strategy for request-time classes. Registering the error handler belongs
in the front controller and `bin/blush`.

---

## M0 checklist (setup): done

Completed 2026-09-25. See D-048 to D-050.

Work on the `2.x` branch. **Never commit** (the user reviews and commits).

1. **PHP 8.5 toolchain** (D-047). Herd's global PHP is 8.5.10, so plain `php`
   and `composer` work. Verify `php -v` before starting.
   **Don't switch jtcom's DDEV to 8.5: PHP 8.5 breaks the current (1.x)
   jtcom site.** jtcom stays on its current PHP until the M8 port. The framework is a
   library with no site of its own, so M0 and M1 only need the PHP 8.5 **CLI**
   plus Composer. A browser-facing dev site (on DDEV) comes in M2; see
   `open-questions.md`.
2. **Clear out 1.x.** Caution: jtcom's `vendor/blush-dev/framework` is a
   symlink to this repo's working tree, so jtcom always runs whatever branch
   is checked out here. Clearing 1.x on `2.x` breaks the local jtcom site until
   M8. If the user still needs jtcom running locally, set up a 1.x checkout
   first (e.g. `git worktree add ../blush-framework-1x master`), point jtcom's
   Composer path repository and `.ddev/docker-compose.blush.yaml` mount at it,
   and confirm with the user before deleting anything. Then delete `src/`, `composer.lock`, and `vendor/`, and rewrite
   `readme.md`. Keep `.claude/`, `AGENTS.md`, `CLAUDE.md`, and
   `.editorconfig`.
3. **License** (D-014): replace `license.md` with an MIT `LICENSE.md`
   (Copyright (c) 2026 Justin Tadlock).
4. **`composer.json`:**
   - Name `blush-dev/framework`. Description without "Foundation" (D-008).
   - License `MIT`, `"php": ">=8.5"`, `minimum-stability: stable`.
   - PSR-4: `Blush\` → `src/`, `Blush\Tests\` → `tests/`.
   - `require`: only what M0 needs. Add PSR interface packages as the
     subsystems that implement them land (D-006).
   - `require-dev`: `phpunit/phpunit` ^12, `phpstan/phpstan`,
     `squizlabs/php_codesniffer`, `phpcompatibility/php-compatibility`, and
     `dealerdirect/phpcodesniffer-composer-installer`.
   - Scripts: `lint`, `fix`, `analyse`, `test`, and `check` (all three).
5. **`.phpcs.xml`:** based on x3p0-breadcrumbs' ruleset (see `paths.md`)
   *without* the WordPress rules. Keep PSR-12 with its exclusions, tabs,
   `RequireStrictTypes`, and short arrays. Use `PHPCompatibility` with
   `testVersion` `8.5-`. Remove `/.phpcs.xml` and `/phpcs.xml` from
   `.gitignore`.
6. **Verify 8.5 syntax support** (see `open-questions.md`): write a scratch
   file using `|>`, `clone($x, [...])`, property hooks, and asymmetric
   visibility, then run PHPCS, PHPStan, and PHPUnit over it. Record the
   outcome in `decisions.md`, and put any restrictions in the
   `blush-code-style-php` skill.
7. **`phpstan.neon`:** level `max`, with paths `src` and `tests`.
8. **`phpunit.xml`** plus one smoke test under `tests/`.
9. **`.gitattributes`:** export-ignore dev files (`.claude`, `tests`, and the
   tool configs).
10. **CI:** `.github/workflows/ci.yml` on PHP 8.5 running
   `composer validate`, lint, analyse, and test.
11. **Docs:** fill in the `AGENTS.md` "Commands" section. Update the "Status"
    line to M1 when done.

**Done when:** `composer check` passes on PHP 8.5 locally (and in CI once
pushed).

