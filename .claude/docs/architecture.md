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
  `Psr\Container\ContainerInterface`. Consider native lazy objects to back
  `#[Defer]`.
- **Application** (`Blush\Core\Application`): the x3p0 application with a
  single register-then-boot pass (the WordPress `begin()` phases are removed).
  Providers are declared with typed class constants
  (`protected const array PROVIDERS`).
- **Service providers** keep the declarative constants from x3p0
  (`SINGLETONS`, `TRANSIENTS`, `ALIASES`, `TAGS`, `BOOTABLE`).
- **Provider sources**, in order:
  1. Framework defaults
  2. Composer packages via `extra.blush.providers` (discovered automatically
     and cached)
  3. The active theme's provider
  4. The site's providers from config
- **`Paths`:** a readonly value object for root, config, user, content, media,
  data, themes, public, storage, cache, and so on.
- **`Env`:** an in-house `.env` loader (read-only, no `putenv`) with typed
  accessors: `string()`, `bool()`, `int()`, `list()`.
- **Config** (D-017): `config/*.php` returns typed immutable objects, e.g.
  `return new SiteConfig(title: '…', url: $env->string('APP_URL'), timezone: 'America/Chicago');`
  Every config class also has `fromArray()`. The merged config is compiled to
  `storage/cache/config.php`.
- **Errors:**
  - A `BlushException` hierarchy.
  - A global handler that converts errors to exceptions.
  - Renderers: a detailed page in dev; in production, a themed page from
    `user/content/_errors/{status}.md`.
  - 8.5 fatal-error backtraces are logged.
- **Log:** in-house PSR-3 implementation with file, stderr, and null writers.
- **Clock:** in-house PSR-20 implementation with system and frozen clocks.
  Scheduled content and TTLs depend on it, which keeps them testable.

## Events

- `Blush\Event` is copied from x3p0-event: dispatcher, listener
  registry/provider, subscribers, attribute-declared listeners, priorities,
  once-listeners, stoppable events, named events, and **`BroadcastableEvent`**
  (D-007). `BroadcastsToHooks` is dropped.
- It implements `Psr\EventDispatcher\*`.
- **Broadcast targets** (implementations of `broadcast()`) could include the
  log, a queue for async work, or webhooks out to other services.
- **Core events:**
  - `ApplicationBooted`, `RequestReceived`, `RouteMatched`, `ControllerResolved`
  - `MarkdownEnvironmentBuilding`, `EntryParsed`, `ViewRendering`, `ResponseReady`
  - `ContentIndexed`, `ContentWritten`, `ContentPublished`, `CacheCleared`
  - `ExportStarted`, `ExportFinished`

## HTTP (custom, D-005)

- **`Uri`** implements `Psr\Http\Message\UriInterface` on top of 8.5's
  `Uri\Rfc3986\Uri`.
- **`Request`** implements `ServerRequestInterface`. It is immutable, and its
  `with*()` methods are built on `clone($this, [...])`.
  `RequestFactory::fromGlobals()` and `Request::create('/path')` are the
  constructors.
- **`Response`** implements `ResponseInterface`. Named constructors:
  `html()`, `xml()`, `json()`, `text()`, `redirect()`, `file()` (with Range
  support for media), `notModified()`.
- **Streams:** in-house string, file, and temp streams (PSR-7 requires them).
- **Factories:** in-house PSR-17.
- **Middleware:** PSR-15 `MiddlewareInterface` plus a `Pipeline`, attachable
  globally, per route group, or per route. Built-ins:
  - `HandleErrors`, `TrustProxies`, `CanonicalUrl` (scheme, host, trailing slash)
  - `ConditionalGet` (ETag, Last-Modified → 304), `PageCache`, `SecurityHeaders`
  - `StartSession`, `VerifyCsrf`, `Authenticate`, `RateLimit` (admin and
    webhooks only)
- **`Kernel`** builds the pipeline, dispatches to the router, and returns a
  `Response`.
- **`Emitter`** sends status, headers, and body; handles `HEAD`; and calls
  `fastcgi_finish_request()` for deferred work.
- The front controller in `public/index.php` is about five lines.

## Routing

- **`Route`:** methods, pattern (`/archives/{year:\d{4}}/{month}`), handler (an
  invokable class or `[Class, 'method']`), name, defaults, constraints, and
  middleware.
- **Sources**, in precedence order:
  1. System routes (feeds, sitemap, robots, webhook, admin)
  2. Routes generated from content types
  3. Controllers with `#[Get]`, `#[Post]`, … attributes in site, theme, or
     extensions
  4. `config/routes.php`
  5. The page catch-all
- **Compiler and matcher:** a static hash map, then per-method combined regexes.
  The compiled table is cached as a PHP file. Unmatched methods return 405 with
  an `Allow` header.
- **`UrlGenerator`:** turns a name plus params into relative or absolute URLs.
  Entry and type URLs go through it.
- **Redirects:** a map (config or `user/data/redirects.*`) plus `redirect_from:`
  in front matter, checked before a 404 is returned.
- **Route enumeration:** the router can list every concrete URL, which static
  export and sitemaps need.

## Content

### Conventions (under `user/content/`)
- Each folder is a collection of the type mapped to it. `index.md` is the
  collection's landing page.
- A `_` prefix means hidden, and an `NN.` prefix means manual order.
- `_drafts/` or `status: draft` marks unpublished entries.
- **Page bundles:** `slug/index.md` sits next to its own media, which resolves
  relative to the entry.
- **Data-only entries** (`.yaml`, `.json`) and other user data (menus,
  authors, redirects) live in `user/data/`.
- `_errors/404.md` and `_errors/500.md` are error pages.

### Types and schemas
- **`ContentType`:** name, path, routing (prefix and path patterns),
  collection query, feed, sitemap, archive granularity (enum), taxonomy flag,
  and the `collects` relation.
- **`Schema`:** typed fields per type (`Text`, `Markdown`, `Date`, `Bool`,
  `Number`, `Enum`, `ListOf`, `Reference(type)`, `Media`, `Slug`, `Object`).
  Field types use the enum + registry pattern (D-019), so extensions can add
  more.
- Schemas drive **validation/casting** (at index time and in `content:lint`),
  **typed entry fields**, and **admin form generation** later.

### Entry
- A readonly value object: `id` (type plus relative path), `slug`, `type`,
  `status` (enum `Published | Draft | Scheduled | Unlisted`),
  `published`/`updated` (`DateTimeImmutable`, site timezone), `title`,
  `summary`, `fields`, `terms`, `media`, `template`, and `source` (path, mtime,
  hash).
- The body is a **lazy ghost**: the file is parsed and rendered only when the
  body is read.
- **Scheduling:** a future `published` date means `Scheduled`. The index
  records the next go-live time so cache invalidation happens automatically.

### Parsers
- A registry keyed by extension. Built in: Markdown, HTML, and data (YAML,
  JSON).
- **Front matter:** YAML behind a `YamlParser` interface. It starts with a
  temporary adapter (symfony/yaml, object parsing disabled); a small in-house
  YAML-subset parser is the long-term plan (D-006).
- **Markdown:** behind a `MarkdownParser` interface. It starts with a
  CommonMark adapter, with an in-house parser as the long-term goal. An event
  lets extensions add syntax.
- **Content components:** a Markdown directive syntax (for example
  `::: gallery`) rendered by theme or site components. See `theming.md`.
- Raw HTML in Markdown is controlled by config (trusted authors by default).

### Taxonomies and relations
- Terms are entries (`user/content/topics/art.md`). A term that is referenced
  but has no file gets a virtual term.
- The index stores forward and reverse relations and term counts.

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

Plain PHP templates (D-009). The full theming design is in `theming.md`.

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
| Config, routes, providers | Compiled PHP files; cleared by `cache:clear` or deploy |
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

## Security baseline

- The web root is `public/` only.
- Every user, view, and media path is resolved and checked to stay inside its
  root.
- YAML is parsed without objects, and secrets live only in env.
- The webhook uses HMAC with replay protection.
- Admin uses CSRF protection and SameSite=Strict cookies.
- CSP and security headers, plus an upload allowlist.
