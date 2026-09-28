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
- **Theme providers** register between extensions' and the site's
  (D-116).
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
    CLI. HTTP errors render as themed pages first (`Http\ErrorPages`,
    `View\ThemedErrorPages`, D-108), with the generic page as the fallback.
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
- **Core events** (the ones marked with a milestone exist; the rest are
  planned):
  - `ApplicationBooted` (M1), `RequestReceived` (M2), `RouteMatched` (M3), `ControllerResolved`
  - `MarkdownEnvironmentBuilding` (M4a), `EntryParsed`, `ViewRendering`, `ResponseReady` (M2)
  - `ContentIndexed` (M4b; the content version listens, M6a), `ContentWritten`, `ContentPublished`, `CacheCleared`
  - `ExportStarted`, `ExportFinished` (M7a)

## Data files

- **Split (D-022):** developer config is typed PHP objects (D-017).
  User-editable data (theme settings, menus, redirects, authors,
  anything the admin writes) is data files under `user/data/`, and theme
  manifests are data files too.
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

Implemented in M5a for the `blush` and `theme` domains (D-107).

- **`Translator`:** CMS-wide, in-house, using ICU MessageFormat via `ext-intl`
  (`MessageFormatter`) for plurals, select, and number/date arguments.
- **Catalogs:** per domain (`blush`, extension slugs, `theme`, `site`), per
  locale, stored as data files (`lang/{locale}.json`). Resolved through the
  same chains as views (site → theme chain → extension → framework).
- **Locale fallback:** `en_US` → `en` → the default locale.
- **Formatting services:** `DateFormatter` and `NumberFormatter` wrappers
  (`IntlDateFormatter`, `NumberFormatter`) use the site locale and timezone.
- **Available in:** views (`$template->t()`), components, controllers, the CLI,
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
  `html()`, `xml()`, `json()`, `text()`, `redirect()`, `file()` (with
  single byte ranges over a `LimitedStream`: 206, or 416 past the end,
  D-099), `notModified()`. `Status` is an enum of the registered codes.
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
  `HandleErrors`, `ConditionalGet`, and `PageCache` exist so far. The
  kernel always runs `HandleErrors` outermost, and it maps `HttpError`s
  to their status. It asks `ErrorPages` (the themed error pages) for the
  response first (D-108).
- **`Kernel`** implements `RequestHandlerInterface`. It runs `HandleErrors`,
  then the middleware tagged `Kernel::MIDDLEWARE` (`ConditionalGet`, then
  `PageCache`, D-129), then the global middleware, then its handler (the
  `Router`), and dispatches `RequestReceived` and `ResponseReady`.
- **`Emitter`** sends status, headers, and body through a `Sapi`; skips the
  body for `HEAD` and 1xx/204/304; and calls `fastcgi_finish_request()`
  for deferred work.
- The front controller in `public/index.php` is three lines:
  `new HttpRunner($root)->run()`.
- **Stray output (D-163):** `HttpRunner::run()` buffers anything printed
  while the kernel handles the request (a `dump()`, an `echo`) and
  `StrayOutput::insert()` puts it just inside the response's `<body>`
  (or before a non-HTML body), so it can't send headers early. If the
  kernel throws, the buffer is printed before the exception goes on.
  `Kernel::handle()` itself doesn't buffer.

## Routing

Implemented in M3 (D-073 to D-077).

- **`Route`:** methods, pattern (`/archives/{year:\d{4}}/{month}`), handler (an
  invokable class, `[Class, 'method']`, or a PSR-15 handler), name, defaults,
  constraints, and middleware. `Route::group()` shares a prefix, name
  prefix, and middleware. Paths are written without a trailing slash.
- **Sources** (`RouteSource`), in precedence order (`RoutePriority`):
  1. System routes (feeds, sitemap, robots, webhook, admin)
  2. Routes generated from content types (`ContentRoutes`, D-093)
  3. Controllers with `#[Get]`, `#[Post]`, … attributes, listed in
     `RouteConfig::$controllers` or tagged `ControllerRoutes::TAG` by site or
     extension providers. Themes can't add routes (D-020).
  4. `config/routes.php` (`RouteConfig::$routes`)
  5. Fallbacks (`PageRoutes`): the home page at `/` (and `/page/{page}`
     with a home type) and the page catch-all. Fallbacks are soft: one
     that finds nothing doesn't hide other methods' 405, `Allow` lists
     other routes' methods first, and a trailing-slash redirect to a
     fallback happens only if it finds something (D-095).
  The media route (`{media url}/{path}`) is a system route (D-099).
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
  Extra params become the query string. Entry, collection, term, and
  date-archive URLs come from `ContentUrls`, which builds them from type
  routing without the route table (D-096).
- **Redirects:** `RouteConfig::$redirects`, then `user/data/redirects.*`,
  then `redirect_from` front matter (D-097), and any tagged
  `RedirectSource`s, with pattern placeholders. They're checked only before a 404, including when a
  handler throws `NotFound`. `/public/...` URLs redirect to the canonical
  path (D-076).
- **Route enumeration:** static export (M7) needs every concrete URL.
  Sitemaps don't use it; they list URLs from content (D-123).
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
- A `_` prefix on a file name, or on a folder between the type's folder
  and the file, means hidden (D-088).
- A `_drafts/` folder or `status: draft` marks unpublished entries.
- **Page bundles:** `slug/index.md` is the entry `slug`, listed in the
  folder above, next to its own media, which resolves relative to the
  entry. `index` directly in a type's folder is the landing page instead.
- **Data-only entries** (`.yaml`, `.json`) and other user data (menus,
  authors, redirects) live in `user/data/`.
- `_errors/404.md` and `_errors/500.md` are error pages (1.x's `_error/`
  folder works too, D-108).

### Types and schemas
Implemented in M4a (D-083, D-084); kinds and option names from D-157.

- **`ContentType`** (`Blush\Content\Type`): an abstract base with the
  final kinds `Collection`, `Taxonomy`, and `Pages` (`TypeKind` names
  them in data). Shared: name, `folder`, `public`, `urls` (`TypeUrls`:
  prefix plus per-key paths over 1.x's defaults, with `single` and
  `collection` shortcuts, or `false`), `listing` (`Listing`: typed `type`,
  `orderBy`, `order`, `perPage`, plus 1.x `query` arguments), `feed`
  (`TypeFeed`: `categories` taxonomy and a `listing`), `sitemap`, and its
  own `Schema` (`fields`, `closed`). `Collection` adds `dateArchives`
  (`DateArchives`); `Taxonomy` adds `types`, `field`, `aliases`, and
  `termListing`; `Pages` has no URLs, listing, or feed. `fromArray()`
  dispatches on `kind` (or 1.x's `taxonomy: true`) and accepts the 1.x
  option names.
- **Sources, one model** (D-042, D-083): built-ins, extension
  `ContentTypeSource`s, `ContentConfig` (`config/content.php`, locked), and
  data types (`user/data/types/*.json|yaml`, editable later).
  `ContentTypeLoader` merges and checks them into `ContentTypes`, which
  finds types by name, folder, or file, and builds each type's full schema.
  `ContentConfig` also holds the home alias, the data-type policy,
  `disabled` built-ins, and `autoIndex`. The resolved types compile to
  `storage/cache/content-types.php` outside development (D-092).
- **Built-in types:** `page` (`Pages`, the catch-all, folder `''`) and
  `author` (D-043, a `Taxonomy`, folder `authors`, term field `authors`
  with alias `author`). Both
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
  `layout`, `stylesheet`, `class`, `redirect_from`, and
  `collection`, plus each taxonomy's term field.
- Schemas drive **validation/casting** (at index time and in `content:lint`),
  **typed entry fields**, and **admin form generation** later.

### Entry
Implemented in M4b (D-088).

- `Content\Entry\Entry`, a readonly value object: `id` (the source path),
  `type`, `slug`, `key` (the slug with any folders below the type's),
  `title`, `status` (`Published | Draft | Scheduled`), `visibility`
  (`Public | Unlisted | Hidden`, D-082), `published`/`updated`
  (`DateTimeImmutable`, site timezone), `locale`, `fields` (typed by the
  schema), `extra` (undeclared keys), `terms`, `landing`, and `source`
  (path, mtime, size). Helpers: `field()`, `summary()`, `excerpt()`,
  `templates()`, `terms()`, `hasTerm()`, `isListed()`, `isRoutable()`.
- The `Body`'s source is a **lazy ghost** (`EntryHydrator`): the file is
  read, parsed, and rendered only when `body()` is called, and not at all
  when the `BodyCache` has the rendering (D-130).
- **Virtual entries** stand in for referenced terms with no file.
- **Scheduling:** a future `published` date means `Scheduled`, decided
  against the clock at read time. The index records the next go-live time,
  and the content version moves on by itself when it passes (D-128).
- Entry URLs come from `ContentUrls` (D-096).

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
  1.x's rendering is built in (D-100): local media links point at the
  media URL and images get their dimensions, root-relative links become
  absolute, and a lone image becomes a `<figure>` with its title as the
  caption. `toHtml($markdown, $base)` resolves bundle media against the
  entry's folder.
- **Content components:** generic directives (`:::name`, `::name`,
  `:name[text]`, D-026) parsed by an in-house CommonMark extension and
  rendered through `DirectiveRenderer` as theme or site components
  (D-112). See `theming.md`.
- Raw HTML in Markdown is controlled by config (trusted authors by default).

### Taxonomies and relations
- Terms are entries (`user/content/topics/art.md`). A term that is referenced
  but has no file gets a virtual term, titled as first written (D-090).
- The index stores each entry's terms (forward) and the entries per term
  (reverse); `termCounts()` counts listed entries. Other reference fields
  are forward-only for now.
- **Authors** (D-043) are entries of the built-in `author` type, referenced
  through the `authors` field. They get archives and feeds like terms, and
  structured data (`Person`).

## Source → Index → Repository

Implemented in M4b (D-087, D-090).

- **`ContentSource`** (`Content\Source`) reads raw documents: `files()`,
  `stat()`, `read()`. The default is `FilesystemSource` (content files by
  parser extension, dotfiles skipped, paths confined). Git, S3, or a
  database could follow later.
- **`ContentIndex`** (`Content\Index`) is the queryable metadata store.
  - `PhpIndex` (default): `storage/index/content.php`, a `var_export`'d
    `IndexSnapshot` kept in opcache shared memory. Records stay arrays;
    queries are array filters (`ArraySelector`).
  - `SqliteIndex` (optional, later): for large sites and FTS5 search.
- **`RecordBuilder`** turns a file into an `IndexRecord` (the 1.x file
  conventions, D-088) and its schema violations.
- **`Indexer`:**
  - A full scan, or an incremental one that skips files whose mtime and
    size match and keeps records whose hash matches. A changed
    fingerprint (content types, timezone, locale) forces a full scan.
  - The index is built on first use if missing; in development each
    request's first use refreshes it (`ContentConfig::$autoIndex`). In
    production, reindexing is triggered by CLI, webhook, or admin save.
  - Emits `ContentIndexed` with the `IndexReport` when it writes.
- **`ContentRepository`** is the facade: `query()`, `find(id)`,
  `named(type, key)`, `term()`, `termCounts()`, plus `get()`,
  `paginate()`, and `count()` for queries, plus `redirects()` for
  `redirect_from`. URLs are resolved by the router's content routes, not
  the repository. A stale index (another fingerprint) is rebuilt on first
  use in any environment (D-098).
- **`Linter`** checks every file for `content:lint` (D-091).
- **`ContentWriter`:** `create`, `update`, `move`, `delete`. Writes are atomic
  (temp file + rename), use file locks, are confined to the content root, and
  trigger incremental reindexing.

## Query

Implemented in M4b (D-089).

- An immutable fluent builder built on `clone()` with properties and marked
  `#[\NoDiscard]`:
  `$content->query()->type('post')->whereTerm('category', 'art')->orderBy('published', Order::Desc)->paginate(perPage: 10, page: $page)`
- `Query::fromArray()` reads 1.x query arguments (a type's `collection`,
  a page's `collection` front matter) plus `status`, `visibility`,
  `terms`, and `locale`.
- Returns an `EntryCollection` or a `Paginator`. Hydration is lazy, so
  listings never render bodies.
- `Paginator::links($url, endSize, midSize, adjacent)` builds numbered
  pagination as `PageLink`s (kind, number, URL; D-161);
  `ContentPage::pageLinks()` passes the page's URL builder.
- Compiled per index: array filters for `PhpIndex`, SQL for `SqliteIndex`.

## Media

Implemented in M4c (D-099), apart from image derivatives.

- Originals live in `user/media` and in page bundles.
- **`MediaConfig`:** the media URL (`/media` by default; jtcom uses
  `/user/media`) and the MIME allowlist (1.x's images, audio, and video).
- **`MediaResolver`:** turns front matter and Markdown references into
  `MediaFile`s (path, URL, MIME, size, dimensions): media URL paths and
  1.x `/user/media/...` paths into `user/media`, and relative paths into
  the entry's page bundle (served at `{url}/_content/...`).
- **Serving** (web root is `public/`): `media:publish` links `public{url}`
  to `user/media` (or copies the allowed files with `--copy`). The
  `MediaController` streams anything unpublished, with ranges, `nosniff`,
  and sandboxed SVGs.
- **Image derivatives:** in-house GD/Imagick adapter. Sizes are declared by the
  theme, generated on demand or at export, and cached in `public/_media/`.
  Output includes `srcset`/`sizes` helpers.

## Views

Plain PHP templates (D-009). **The full theming design is in `theming.md`**
(themes are presentation only, with a data-first manifest, parent chains,
components with slots, and per-entry presentation fields; no design
token system, D-160). The
view layer was implemented in M5 (D-103 to D-125).

- **`Views`** (`Blush\View`): renders templates for one theme chain.
  A template runs in a static closure with its `Template` as `$template`
  and no object or class scope, so it reaches only the template API
  (D-158). Layouts (which may nest), sections, and partials (shared data plus their own). Failures
  close their output buffers and become `ViewException`s.
- **`ViewFinder`:** view names (`single-post`, `layouts/base`) resolve
  through `resources/views/themes/{active}`, `resources/views`, then the
  theme chain. `ViewFactory` builds one `Views` per chain and the per-page
  `ViewContext` (the `Head`, sections, shared `$site`, body classes, and
  the front matter `layout`).
- **`Escaper`:** `e()`, `attr()`, `url()`, `js()`, `css()`, and `raw()` are the
  only global functions (D-106).
- **`Head` manager:** collects title, meta, OpenGraph, canonical, alternates,
  stylesheets, and scripts, each once, and renders them in the base layout
  (D-109). `ThemedPageRenderer` adds the page number to the title on later
  pages of a listing (D-162).
- **Components:** `ComponentType` lists the core content components;
  `Views::components()` discovers every component a chain can render
  (D-164).
- **`Hierarchy`:** the candidate view names for a content page or error,
  with front matter `template:` first (D-104).
- **Renderers:** `ThemedPageRenderer` (the `PageRenderer`) and
  `ThemedErrorPages` (the `ErrorPages`) pick the chain per request
  (`ThemeResolver`, `?theme=` in development) and fill in the head.
- **Components** (`Blush\View\Component`, D-111): template-only or
  class-backed, with slots; the registry, factory, and registrar; the
  built-in `Embed`. `ComponentDirectives` renders Markdown directives as
  components (D-112). **Context providers** (`ContextProviders`, D-114)
  add data to views by name or pattern.
- **Themes** (`Blush\Theme`, D-105, D-115 to D-121): `ThemeDiscovery`
  (framework, Composer `blush-theme`, and `user/themes` (D-166), before the
  container; cached in `storage/cache/themes.php`), `Themes`,
  `ThemeChain` (with its providers, registered at boot), `ThemeConfig`,
  `ThemeResolver`, `ThemeAssets` (build manifests or mtime), settings
  (`SettingsResolver`, `SiteThemeData`), `ThemeChecker`, and the
  `theme.asset` route.

## Built-in controllers and outputs

- **Pages** (M4c, D-094): single, collection (paged), term (paged), date
  archives, home (a type's collection, `index.md`, or the welcome page),
  and the page catch-all. The controllers build a `ContentPage`; the
  `PageRenderer` (`ThemedPageRenderer` since M5a) renders it. Error pages
  are themed (D-108).
- **Feeds** (`Blush\Feed`, M5c, D-122): RSS 2.0, Atom, and JSON Feed, per
  collection, home, and term, built by `FeedBuilder` and rendered by theme
  templates (`feed-{format}`) through `View\DocumentRenderer`; pages link
  them with `<link rel="alternate">`.
- **Sitemaps** (`Blush\Sitemap`, M5c, D-123): `/sitemap` (an index), one
  per type at `/sitemap/{type}`, and `robots.txt` (which disallows
  everything outside production).
- **Search:** optional; needs `SqliteIndex`.

## Caching

Implemented in M6a (D-127 to D-130), apart from publishing (M6b).

| Layer | Key / invalidation |
|---|---|
| Config, routes, extensions, content types, container plans | Compiled PHP files; cleared by `cache:clear` or deploy |
| Content index | Per-file mtime/size/hash, incremental |
| Rendered bodies, summaries, excerpts | Content version + theme + rendering settings + content hash (`RenderedBodies`) |
| Fragments | `ContentCache::remember(namespace, key, fn)`, per content version; in templates, `$template->cache($key, fn)` (per active theme too, D-152) |
| Full pages | `PageCache` middleware; content version + path |
| HTTP | `ConditionalGet`: ETag or Last-Modified → 304; `Cache-Control` on cached pages |

- **Content version** (`ContentVersion`): a random value in
  `storage/cache/content-version.json` that changes when the index is
  stored, on `cache:clear`/`cache:compile`, on publish, and by itself at
  the next scheduled go-live time. Clearing every derived cache is one
  write; `cache:clear` (and publish) delete the stale entries.
- **Store** (`Store`, an in-house PSR-16 base): `file` (default), `php`,
  `apcu`, `array`, and `null` drivers (enum + registry + factory +
  registrar), one store per namespace (`pages`, `bodies`,
  `fragments`, or an extension's). Values are plain data. `Caches` hands
  out stores, null ones when caching is off (in development, by
  default). Tagged invalidation comes later.
- **`CacheConfig`** (`config/cache.php`): `enabled`, `driver`, `stores`,
  `pages`, `maxAge`.
- **Later:** page-cache files written so nginx/Apache `try_files` can
  serve them without starting PHP.

## Static export (D-011)

Implemented in M7 (D-135 to D-140).

- **`Exporter`** (`Blush\Export`): reindexes, boots the export
  application, copies `public/`'s files, renders every URL the crawler
  finds, writes the 404 page, copies theme assets and media, removes what
  the last export wrote and this one didn't, and records the manifest.
  One export at a time; the output folder (`Paths::$export`,
  `storage/export`) can't overlap the site's own folders.
- **`ExportSite`:** a second application booted from the site's
  `Bootstrap` (`withConfig()`, `withPaths()`): production, the export's
  origin as `AppConfig::$url`, in-memory caching without the page cache,
  and compiled caches in `storage/cache/export` (so always fresh).
- **`Crawler`:** tagged `UrlSource`s (content, feeds, sitemaps, and
  extensions'), `ExportConfig::$paths`, paging by asking for the next
  page until one isn't a 200, and link crawling (`ExportConfig::$crawl`)
  that also reports broken links.
- **`ExportLayout`:** `/about` → `about/index.html`, `/feed` →
  `feed/index.rss`, `/robots.txt` → `robots.txt`; index names give hosts
  the content type (`ExportLayout::INDEXES`).
- **`ExportWriter`** + **`ExportManifest`** (`storage/cache/export/manifest.json`):
  unchanged files are left alone, stale ones removed, others never
  touched.
- **Incremental** (`build --incremental`, D-139): nothing is rendered
  when the content version and `ExportFingerprint` (a stat of config,
  data, media, themes, extensions, `public/`, and code) match the
  manifest; the previous pages are kept and assets synced.
- **Redirects** (D-139): the table's literal redirects are export URLs
  (rendering confirms them); every redirect met is exported, with a
  page that redirects in the browser; patterns go to host files.
- **Host files** (`Export\Host`, D-140; enum + registry + factory +
  registrar): `apache` (`.htaccess`: indexes, types, redirects,
  extensionless URLs without `DirectorySlash`, the 404) and `netlify`
  (`_redirects`, `_headers`), chosen by `ExportConfig::$hosts`.
- **Preview:** `serve --static` with `resources/static-server.php`, which
  applies `_redirects`.
- **Later:** image derivatives in the export (with `image()`).

## Publishing and admin (D-013)

- **Stage 1: no UI (ships with core; M6b, D-131 to D-133)**
  - `Publisher` (`Blush\Publish`): an optional `git pull` in `user/`
    (`Puller`, `GitPuller`), the compiled content types and route table
    rewritten if present, an incremental reindex, the cache store
    cleared, a new content version, and `ContentPublished`. One at a
    time (a lock file).
  - A signed webhook, `POST /_blush/publish` (only with
    `PublishConfig::$secret`): HMAC-SHA256 over timestamp and body,
    a time window, and seen signatures kept in the persistent
    `webhooks` store.
  - `publish` on the CLI does the same over SSH; `schedule:run` is the
    optional cron entry.
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
  provider discovery, the content index, and container resolution plans.
- **Lazy:** services (deferred and lazy objects), entry bodies, and
  Markdown rendering.
- **Layers:** page cache (including files the web server can serve without
  starting PHP), HTTP 304s, and static export.
- **Measured:** a PHPBench suite (dev only, `composer bench`) against a
  generated jtcom-sized site. Baselines are recorded in `roadmap.md` (M4c,
  D-101); gating CI on regressions is an open question.

## Security baseline

- The web root is `public/` only (configurable name, D-040).
- Nothing executable is ever written into `user/` by the admin, the writer, or
  uploads (D-039).
- Every user, view, and media path is resolved and checked to stay inside its
  root.
- YAML is parsed without objects, and secrets live only in env.
- The webhook uses HMAC with a time window and replay protection, and
  exists only when a secret is configured.
- Admin uses CSRF protection and SameSite=Strict cookies.
- CSP and security headers, plus an upload allowlist.
