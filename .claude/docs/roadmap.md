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
| M8 | **Port jtcom.** jtcom theme, config, `user/` layout, a URL-parity crawl against the live site, and a redirect map. | Every old URL returns 200 or 301; deployed |
| M9 | **Admin stage 2:** operations dashboard. | Publish, clear, reindex, and export from a browser |
| M10 | **Admin stage 3:** editor and media library. | Create and edit entries in a browser |
| Later | `SqliteIndex` + search; in-house YAML and Markdown parsers; theme distribution; custom template engine | — |

---

## M5 (Views + theming): in progress

Started 2026-09-25, in three slices (D-102). Exit criterion: **the default
theme renders every route type.**

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

### M5b: components, tokens, settings, assets, CLI (next)

Components and slots (D-025), Markdown directives and the core content
components (D-026, D-033), context providers, theme providers and
autoloading, Composer-installed themes (D-034), settings from
`user/data/theme.json`, DTCG tokens compiled to CSS (D-023),
`stylesheet`/`tokens` front matter (D-027), `manifest.json` versioning and
`theme:publish` (D-031), and `theme:list`, `theme:activate`, `theme:new`,
`theme:check`, and `theme:why`.

### M5c: feeds and sitemaps

RSS, Atom, and JSON Feed per collection, term, and home (`.feed`,
`.feed.atom`, `home.feed`, D-029), sitemaps (index plus per type), and
`robots.txt`.

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

