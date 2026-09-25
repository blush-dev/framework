# Architecture

This is the design for the Blush 2 subsystems. Decisions it relies on are in
`decisions.md`. Theming and the CLI have their own docs.

## Principles

- **Flat files are the default source of truth** (D-003). Storage sits behind
  interfaces.
- **No global state.** Constructor injection everywhere. The only global
  functions are template escaping helpers.
- **Render anywhere.** Only `RequestFactory::fromGlobals()` touches
  superglobals. `Kernel::handle(Request): Response` serves the web, CLI, tests,
  static export (D-011), and admin preview.
- **Immutable values, lazy work.** Readonly value objects, native lazy objects,
  and nothing parsed or rendered until it's used.
- **Zero cost when unused.** Admin, publishing webhooks, search, and export are
  wired only when enabled.
- **In-house first** (D-006). Temporary third-party code sits behind Blush
  interfaces.

---

## Core

- **Container** (`Blush\Container`): copied from x3p0-framework and upgraded
  to 8.5. It covers autowiring, attribute injection (`#[Get]`, `#[Make]`,
  `#[Defer]`, `#[Tagged]`, `#[Param]`, `#[Singleton]`, …), contextual
  bindings, tagging, and `resolving()`/`decorate()` hooks. It implements
  `Psr\Container\ContainerInterface`. `make()`/`build()` verify the result's
  type (D-053). Consider native lazy objects to back `#[Defer]`.
- **Resolution plans** (`Blush\Container\Plan`, D-052): classes are built
  from plain-data plans, not per-build reflection. `ReflectionPlanner` in
  development; `CompiledPlanner` over `storage/cache/container.php`
  elsewhere, falling back to reflection.
- **Application** (`Blush\Core\Application`): the x3p0 application with a
  single register-then-boot pass (D-054). The framework providers (events,
  clock, log, errors) always register first. `boot()` is idempotent and
  dispatches `ApplicationBooted`.
- **`Bootstrap`** (`Blush\Core\Bootstrap`): builds a site's application from
  its root. It loads `.env` and config (compiled or from files, with
  defaults), picks the planner, and binds `Paths`, `Env`, and every config
  object. It also discovers and autoloads extensions, then registers providers
  in source order. `compile()`/`clearCompiled()` manage the
  `storage/cache/*.php` files (D-060).
- **Runners** (D-064, D-068): `Core\Runner` is the shared start of every
  entry point. It registers a bare error handler, builds and boots the
  application, then hands over to the configured `ErrorHandler`.
  `Http\HttpRunner` and `Console\ConsoleRunner` extend it.
- **Service providers** keep the declarative constants from x3p0
  (`SINGLETONS`, `TRANSIENTS`, `ALIASES`, `TAGS`, `BOOTABLE`).
- **Provider sources**, in order:
  1. Framework defaults
  2. Enabled extensions (D-041), from Composer packages and from
     `user/extensions`, discovered and cached
  3. The active theme chain's providers
  4. The site's providers from config
- **`Paths`:** a readonly value object for root, config, user, content, media,
  data, themes, extensions, public, resources, storage, cache, index, logs,
  sessions, export, and vendor. Any path can be overridden (D-046), and
  `join()` confines a relative path to its base.
- **`Env`** (`Blush\Env`, D-056): an in-house `.env` loader (read-only, no
  `putenv`; the process environment wins) with typed accessors: `string()`,
  `bool()`, `int()`, `float()`, `list()`, `enum()`.
- **Config** (`Blush\Config`, D-017, D-057): `config/*.php` returns typed
  immutable objects (one or a list), with `$env` and `$paths` in scope, e.g.
  `return new AppConfig(name: '…', url: $env->string('APP_URL'), timezone: 'America/Chicago');`
  Every config class implements `fromArray()`/`toArray()`. The merged config
  is compiled to `storage/cache/config.php`. Config objects are bound in the
  container by class.
- **Errors** (`Blush\Error`, D-059):
  - Every exception implements `Blush\Core\BlushException` (D-055).
  - `ErrorHandler` converts warnings to exceptions, logs deprecations, and
    catches fatal errors at shutdown with 8.5's fatal backtrace.
  - Renderers: HTML (generic, or detailed with `debug`) and plain text for the
    CLI. A themed page from `user/content/_errors/{status}.md` comes with
    views (M5).
- **Log** (`Blush\Log`): in-house PSR-3 implementation with file, stderr, and
  null writers (`LogDriver`), configured by `LogConfig`.
- **Clock** (`Blush\Clock`): in-house PSR-20 implementation with system and
  frozen clocks. Scheduled content and TTLs depend on it, which keeps them
  testable.

## Events

- `Blush\Event` is copied from x3p0-event: dispatcher, listener
  registry/provider, subscribers, attribute-declared listeners, priorities,
  once-listeners, stoppable events, named events, and **`BroadcastableEvent`**
  (D-007). `BroadcastsToHooks` is dropped.
- It implements `Psr\EventDispatcher\*`. `EventServiceProvider` binds one
  shared `ListenerRegistry` (listeners registered by class name are built
  through the container) and the `EventDispatcher`.
- **Broadcast targets** (implementations of `broadcast()`) could include the
  log, a queue for async work, or webhooks out to other services.
- **Core events:**
  - `ApplicationBooted`, `RequestReceived`, `RouteMatched`, `ControllerResolved`
  - `MarkdownEnvironmentBuilding` (M4a), `EntryParsed`, `ViewRendering`, `ResponseReady`
  - `ContentIndexed`, `ContentWritten`, `ContentPublished`, `CacheCleared`
  - `ExportStarted`, `ExportFinished`

## Data files

- **Split (D-022):** developer config is typed PHP objects (D-017).
  User-editable data (theme settings and tokens, menus, redirects, authors,
  anything the admin writes) is data files under `user/data/`, and theme
  manifests and tokens are data files too.
- **`DataLoader`** (`Blush\Data`, M4a, D-085): reads a data file by name
  without its extension, through `DataParserRegistry` (keyed by extension,
  enum + registry, D-019). JSON, YAML, and YML are built in (D-032). If
  several exist, JSON wins and `shadowed()` lists the others for `doctor`.
  `loadAll()` reads a whole directory. The admin only writes JSON.
- **`YamlParser`:** the Symfony adapter returns plain data only, with
  timestamps as strings (D-080).
- **Schema validation:** data files have schemas (the same field-type system as
  content). JSON Schemas are published for editor autocomplete.

## Translation (D-028)

- **`Translator`:** CMS-wide, in-house, using ICU MessageFormat via `ext-intl`
  (`MessageFormatter`) for plurals, select, and number/date arguments.
- **Catalogs:** per domain (`blush`, extension slugs, `theme`, `site`), per
  locale, stored as data files (`lang/{locale}.json`). Resolved through the
  same chains as views (site → theme chain → extension → framework).
- **Locale fallback:** `en_US` → `en` → the default locale.
- **Formatting services:** `DateFormatter` and `NumberFormatter` wrappers
  (`IntlDateFormatter`, `NumberFormatter`) use the site locale and timezone.
- **Available in:** views (`$this->t()`), components, controllers, the CLI,
  and later the admin.
- **Multilingual content** (the same entry in several languages) is separate
  from UI translation. It is architected for but not built yet (D-036): entries
  carry a `locale`, IDs include it, and routes accept an optional locale
  segment.

## HTTP (custom, D-005)

Implemented in M2 (D-067).

- **`Uri`** implements `Psr\Http\Message\UriInterface`. It parses with 8.5's
  `Uri\Rfc3986\Uri` and applies the PSR-7 rules itself.
- **`Request`** implements `ServerRequestInterface`. It is immutable, and its
  `with*()` methods are built on `clone($this, [...])`.
  `RequestFactory::fromGlobals()` and `Request::create('/path')` are the
  constructors.
- **`Response`** implements `ResponseInterface`. Named constructors:
  `html()`, `xml()`, `json()`, `text()`, `redirect()`, `file()` (Range
  support comes with media in M4), `notModified()`. `Status` is an enum of
  the registered codes.
- **Streams:** one `Stream` class over a resource, with `fromString()`
  (`php://temp`) and `fromFile()`.
- **Factories:** `HttpFactory` implements all of PSR-17 and is bound under
  each interface.
- **Middleware:** PSR-15 `MiddlewareInterface` plus a `Pipeline`. Global
  middleware comes from `HttpConfig::$middleware`; per-group and per-route
  middleware come with the router. Built-ins:
  - `HandleErrors`, `TrustProxies`, `CanonicalUrl` (scheme, host, trailing slash)
  - `ConditionalGet` (ETag, Last-Modified → 304), `PageCache`, `SecurityHeaders`
  - `StartSession`, `VerifyCsrf`, `Authenticate`, `RateLimit` (admin and
    webhooks only)
  Only `HandleErrors` exists so far. The kernel always runs it outermost,
  and it maps `HttpError`s to their status.
- **`Kernel`** implements `RequestHandlerInterface`. It runs `HandleErrors`,
  then the global middleware, then its handler (the `Router`), and
  dispatches `RequestReceived` and `ResponseReady`.
- **`Emitter`** sends status, headers, and body through a `Sapi`; skips the
  body for `HEAD` and 1xx/204/304; and calls `fastcgi_finish_request()`
  for deferred work.
- The front controller in `public/index.php` is three lines:
  `new HttpRunner($root)->run()`.

## Routing

Implemented in M3 (D-073 to D-077).

- **`Route`:** methods, pattern (`/archives/{year:\d{4}}/{month}`), handler (an
  invokable class, `[Class, 'method']`, or a PSR-15 handler), name, defaults,
  constraints, and middleware. `Route::group()` shares a prefix, name
  prefix, and middleware. Paths are written without a trailing slash.
- **Sources** (`RouteSource`), in precedence order (`RoutePriority`):
  1. System routes (feeds, sitemap, robots, webhook, admin)
  2. Routes generated from content types (M4)
  3. Controllers with `#[Get]`, `#[Post]`, … attributes, listed in
     `RouteConfig::$controllers` or tagged `ControllerRoutes::TAG` by site or
     extension providers. Themes can't add routes (D-020).
  4. `config/routes.php` (`RouteConfig::$routes`)
  5. Fallbacks: the welcome page at `/` and, later, the page catch-all
  Static paths always match before patterns. When two routes claim the same
  method and pattern, the higher priority wins and the other is reported as
  shadowed.
- **Compiler and table:** `RouteCompiler` resolves each handler, validates it
  and its middleware, records how to fill its parameters (casts for `int`,
  `float`, `bool`, and backed enums, which also constrain the segment, plus
  which parameters take the request), and builds a `RouteTable`: a static
  hash map, then per-method combined regexes (branch reset plus `(*MARK)`,
  32 per chunk). `RouteCache` stores it in `storage/cache/routes.php`
  outside development.
- **`Router`** (the kernel's handler): canonical trailing-slash redirect,
  match (`HEAD` falls back to `GET`; wrong method → 405 with `Allow`;
  `OPTIONS` → 204), then the route's middleware and `ControllerHandler`. The
  match and parameters become request attributes, and `RouteMatched` is
  dispatched.
- **Errors:** `Http\HttpError`, `NotFound`, and `MethodNotAllowed` become
  status responses in `HandleErrors` (D-075).
- **`UrlGenerator`:** turns a name plus params into relative or absolute URLs.
  Extra params become the query string. Entry and type URLs go through it.
- **Redirects:** `RouteConfig::$redirects` and tagged `RedirectSource`s
  (`redirect_from` front matter and `user/data/redirects.*` in M4), with
  pattern placeholders. They're checked only before a 404, including when a
  handler throws `NotFound`. `/public/...` URLs redirect to the canonical
  path (D-076).
- **Route enumeration:** the router can list every concrete URL, which static
  export and sitemaps need (M5/M7, with content).
- **Not yet:** the optional locale segment (D-036) and a base path for
  subdirectory installs (open question).

## Content

Every content convention 1.x supports keeps working (D-078); the inventory
is in that decision.

### Conventions (under `user/content/`)
- Each folder is a collection of the type mapped to it. `index.md` is the
  collection's landing page. A file belongs to the type whose path is the
  nearest folder above it, or else to `page` (D-083).
- Everything before the last `.` in a file name is organizational
  (`01.about.md`, `2003-04-15.welcome.md`): it isn't part of the slug, and
  it sets the default order.
- A `_` prefix on a file name means hidden.
- `_drafts/` or `status: draft` marks unpublished entries.
- **Page bundles:** `slug/index.md` sits next to its own media, which resolves
  relative to the entry.
- **Data-only entries** (`.yaml`, `.json`) and other user data (menus,
  authors, redirects) live in `user/data/`.
- `_errors/404.md` and `_errors/500.md` are error pages.

### Types and schemas
Implemented in M4a (D-083, D-084).

- **`ContentType`** (`Blush\Content\Type`): name, path, `public`, routing
  (`TypeRouting`: prefix plus per-key paths over 1.x's defaults, or
  `false`), collection query, taxonomy flag and term field, `collect`,
  `termCollect` and `termCollection`, feed (`TypeFeed`), sitemap, archive
  granularity (`ArchiveGranularity`), and its own `Schema`. `fromArray()`
  accepts the 1.x option names.
- **Sources, one model** (D-042, D-083): built-ins, extension
  `ContentTypeSource`s, `ContentConfig` (`config/content.php`, locked), and
  data types (`user/data/types/*.json|yaml`, editable later).
  `ContentTypeLoader` merges and checks them into `ContentTypes`, which
  finds types by name, path, or file, and builds each type's full schema.
  `ContentConfig` also holds the home alias, the data-type policy, and
  `disabled` built-ins.
- **Built-in types:** `page` (the catch-all, path `''`) and `author`
  (D-043, path `authors`, term field `authors` with alias `author`). Both
  can be redefined, and `author` can be disabled.
- **`Schema`** (`Blush\Content\Schema`): field types `text`, `markdown`,
  `date`, `bool`, `number`, `enum`, `list`, `reference`, `media`, `slug`,
  and `object` (`FieldType` enum, `FieldRegistry`, `FieldFactory`,
  `FieldRegistrar`, D-019), so extensions can add more. Fields normalize
  raw values for the index and hydrate them for entries. Names win over
  aliases, empty values count as missing, undeclared keys are kept
  (D-081), and problems are `Violation`s with a `Severity`, never
  exceptions.
- **Built-in entry fields** (`EntryFields`): `title`, `subtitle`, `slug`,
  `published` (alias `date`), `updated`, `status`, `visibility`, `summary`
  (alias `excerpt`), `image`, `locale`, `template` (alias `view`),
  `layout`, `stylesheet`, `class`, `tokens`, `redirect_from`, and
  `collection`, plus each taxonomy's term field.
- Schemas drive **validation/casting** (at index time and in `content:lint`),
  **typed entry fields**, and **admin form generation** later.

### Entry
- A readonly value object: `id` (type plus relative path), `slug`, `type`,
  `status` (enum `Published | Draft | Scheduled`), `visibility` (enum
  `Public | Unlisted | Hidden`, D-082),
  `published`/`updated` (`DateTimeImmutable`, site timezone), `title`,
  `summary`, `fields`, `terms`, `media`, `template`, and `source` (path, mtime,
  hash).
- The body is a **lazy ghost**: the file is parsed and rendered only when the
  body is read.
- **Scheduling:** a future `published` date means `Scheduled`. The index
  records the next go-live time so cache invalidation happens automatically.

### Parsers
Implemented in M4a (D-080, D-085, D-086).

- **`DocumentParsers`** (`Blush\Content\Parser`): a registry keyed by
  extension. Built in: Markdown (`.md`, `.markdown`), HTML, and data
  entries (`.json`, `.yaml`, `.yml`, whose `body` key is Markdown). Each
  parser returns a `Document` (front matter, unrendered body,
  `BodyFormat`).
- **Front matter:** YAML behind the `YamlParser` interface, split off by
  `FrontMatter` (1.x's `---` rules). It starts with a temporary adapter
  (symfony/yaml, objects refused, timestamps as strings); a small in-house
  YAML-subset parser is the long-term plan (D-006).
- **Markdown:** behind the `MarkdownParser` interface (`Blush\Markdown`).
  It starts with a CommonMark adapter configured by `MarkdownConfig`, with
  an in-house parser as the long-term goal. The
  `MarkdownEnvironmentBuilding` event lets extensions add syntax.
- **Content components:** a Markdown directive syntax (for example
  `::: gallery`) rendered by theme or site components. See `theming.md`.
- Raw HTML in Markdown is controlled by config (trusted authors by default).

### Taxonomies and relations
- Terms are entries (`user/content/topics/art.md`). A term that is referenced
  but has no file gets a virtual term.
- The index stores forward and reverse relations and term counts.
- **Authors** (D-043) are entries of the built-in `author` type, referenced
  through the `authors` field. They get archives and feeds like terms, and
  structured data (`Person`).

## Source → Index → Repository

- **`ContentSource`** reads raw documents: `list()`, `read()`, `stat()`. The
  default is `FilesystemSource`. Git, S3, or a database could follow later.
- **`ContentIndex`** is the queryable metadata store.
  - `PhpIndex` (default): a `var_export`'d array kept in opcache shared memory,
    with no per-request parsing.
  - `SqliteIndex` (optional): for large sites and FTS5 search.
- **`Indexer`:**
  - A full scan, or an incremental scan that compares **per-file** mtime, size,
    and hash.
  - In dev, a throttled auto-check. In production, reindexing is triggered by
    CLI, webhook, or admin save.
  - Emits `ContentIndexed` with the changed IDs.
- **`ContentRepository`** is the facade: `find()`, `byUrl()`, `query()`.
- **`ContentWriter`:** `create`, `update`, `move`, `delete`. Writes are atomic
  (temp file + rename), use file locks, are confined to the content root, and
  trigger incremental reindexing.

## Query

- An immutable fluent builder built on `clone()` with properties and marked
  `#[\NoDiscard]`:
  `$content->query()->type('post')->whereTerm('category', 'art')->orderBy('published', Order::Desc)->paginate(perPage: 10, page: $page)`
- Returns an `EntryCollection` or a `Paginator`. Hydration is lazy, so
  listings never render bodies.
- Compiled per index: array filters for `PhpIndex`, SQL for `SqliteIndex`.

## Media

- Originals live in `user/media` and in page bundles.
- **Serving** (web root is `public/`): the CLI command `media:publish` creates
  a symlink (or copies files on hosts that can't symlink) to `public/media`. A
  streaming `MediaController` is the fallback.
- **Image derivatives:** in-house GD/Imagick adapter. Sizes are declared by the
  theme, generated on demand or at export, and cached in `public/_media/`.
  Output includes `srcset`/`sizes` helpers.

## Views

Plain PHP templates (D-009). **The full theming design is in `theming.md`**
(themes are presentation only, with a data-first manifest, parent chains,
DTCG tokens, components with slots, and per-entry presentation fields). The
summary below is the view core those features sit on.

- **Rendering:** isolated scope (a static closure include). Layouts and
  sections, partials, and components (a class plus a template).
- **`Escaper`:** `e()`, `attr()`, `url()`, `js()`, `css()`, and `raw()` are the
  only global functions.
- **`Head` manager:** collects title, meta, OpenGraph, canonical, alternates,
  and preloads, and renders them once.
- **Template hierarchy:** a value object that front matter `template:` can
  override.

## Built-in controllers and outputs

- **Pages:** single, collection (paged), term (paged), date archives, home
  (alias to a collection or a page), page catch-all, and errors.
- **Feeds:** RSS 2.0, Atom, and JSON Feed, per collection and per term, written
  with `XMLWriter`.
- **Sitemaps:** a sitemap index plus one per type, and `robots.txt`.
- **Search:** optional; needs `SqliteIndex`.

## Caching

| Layer | Key / invalidation |
|---|---|
| Config, routes, extensions, container plans | Compiled PHP files; cleared by `cache:clear` or deploy |
| Content index | Per-file mtime/size/hash, incremental |
| Rendered bodies | Content hash + renderer version |
| Fragments | `$cache->remember($key, $ttl, fn() => …)` |
| Full pages | `PageCache` middleware; the key includes the **content version** |
| HTTP | ETag, Last-Modified, Cache-Control |

- **Content version:** a single stored value that goes up on publish, reindex,
  or the next scheduled go-live time. Clearing the whole cache is one write;
  stale files are garbage-collected later.
- **Store:** in-house PSR-16 with PhpFile, File, APCu, Array, and Null drivers
  (enum + registry). Tagged invalidation comes later.
- **Optional:** page-cache files written so nginx/Apache `try_files` can serve
  them without starting PHP.

## Static export (D-011)

- `build` enumerates every URL (routes × entries × pagination × feeds ×
  sitemaps), then calls `Kernel::handle()` for each one.
- Output goes to `storage/export/` (configurable), along with assets, media,
  and image derivatives.
- Options: base URL rewrite, pretty URLs (`/about/index.html`), incremental
  export based on the content version, and a `_redirects` file for hosts that
  support one.

## Publishing and admin (D-013)

- **Stage 1: no UI (ships with core)**
  - A signed webhook: `POST /_blush/publish` with HMAC + timestamp.
  - It optionally runs `git pull` in `user/`, reindexes incrementally, and
    bumps the content version.
  - `publish` on the CLI does the same over SSH.
- **Stage 2: operations dashboard**
  - Auth (password hashes in env or config; passkeys later), sessions, CSRF,
    and rate limiting.
  - Actions: clear caches, reindex, publish (git), export.
  - Content health (lint), drafts and scheduled lists, and signed preview URLs.
- **Stage 3: editor**
  - Forms generated from schemas.
  - A Markdown editor with live preview through `Kernel::handle()`.
  - A media library, and git-backed revisions.
- **Admin constraints:** it lives in an `/admin` route group (path
  configurable) behind its own provider and is off by default.

## Extensions (D-041)

- **What an extension is:** a manifest plus a service provider. It can
  register content types, routes, CLI commands, components, listeners,
  parsers, field types, cache drivers, and translations.
- **Composer extensions** (package type `blush-extension`): the manifest lives
  in `composer.json` `extra.blush`. They're discovered from
  `vendor/composer/installed.json`.
- **Local extensions** live in `user/extensions/{slug}/`. Their
  `extension.json|yaml` manifest declares name, version, a PSR-4 namespace and
  path, the provider, and requirements (Blush version, PHP extensions, other
  extensions). Blush registers the autoloader.
- **Enabling:** every discovered extension is enabled unless
  `ExtensionConfig` narrows it (`enabled` allow-list, `disabled`). Discovery
  results are compiled to `storage/cache/extensions.php` (D-058).
- **CLI:** `extension:list`, `extension:new`, `extension:check`.

## Hosting (D-040)

- **Shared Apache hosting is a first-class target** (jtcom is on GoDaddy).
- **Whole-project installs work out of the box** (D-071): uploading the
  entire project into `public_html` needs no setup. A root `.htaccess`
  rewrites every request into `public/` (and denies everything without
  mod_rewrite).
- **Blush ships:**
  - A root `.htaccess` (forward into `public/`) and `public/.htaccess`
    (front controller, only `index.php` runs as PHP; cache headers for
    assets and page-cache files later).
  - `nginx.conf.example` with `root` at `public/` (D-072). nginx can't use
    the whole-project-in-web-root layout, because it ignores `.htaccess`.
- **Relocatable web root** (D-046): only `index.php` (plus `.htaccess` and
  published assets) must be in the web root, e.g. cPanel's fixed
  `public_html`. `index.php` holds a single path to the project bootstrap.
  The public path, public URL, and asset and media publish targets all come
  from config.
- **Nothing needs a long-running process.** Scheduled go-live is handled by
  checking at request time against the content version, with an optional cron
  hitting `blush schedule:run`.
- **Publishing without shell access:** upload by SFTP, then use the signed
  webhook or the admin to reindex and bust caches. `git pull` is an optional
  step for hosts with git.
- **Media without symlinks:** `media:publish --copy`.

## Performance (D-044)

- **Goal:** as fast as possible.
- **Precompiled** (opcache-friendly PHP files): config, routes, extension and
  provider discovery, the content index, container resolution plans, and
  compiled design tokens.
- **Lazy:** services (deferred and lazy objects), entry bodies, and
  Markdown rendering.
- **Layers:** page cache (including files the web server can serve without
  starting PHP), HTTP 304s, and static export.
- **Measured:** a PHPBench suite (dev only) against a jtcom-sized fixture
  runs in CI, with regression thresholds. Baselines are recorded in M4.

## Security baseline

- The web root is `public/` only (configurable name, D-040).
- Nothing executable is ever written into `user/` by the admin, the writer, or
  uploads (D-039).
- Every user, view, and media path is resolved and checked to stay inside its
  root.
- YAML is parsed without objects, and secrets live only in env.
- The webhook uses HMAC with replay protection.
- Admin uses CSRF protection and SameSite=Strict cookies.
- CSP and security headers, plus an upload allowlist.
