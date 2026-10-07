# Architecture

This is the design for the Blush 2 subsystems. Decisions it relies on are in
`decisions.md`. Theming and the CLI have their own docs.

## Principles

- **Flat files are the default source of truth** (D-003). Storage sits behind
  interfaces.
- **Storage is configured per area** (D-486): `Blush\Storage\StorageConfig`
  (`config/storage.php` or `STORAGE_DRIVER`) names a driver for content,
  data, accounts, and sessions, so a site can run on flat files or,
  later, a database. Only `filesystem` exists; content reads it (D-485).
  Media files are always files. Build new stored data behind an
  interface its area's driver can replace.
- **No global state.** Constructor injection everywhere. The only global
  functions are template escaping helpers.
- **Render anywhere.** Only `RequestFactory::fromGlobals()` touches
  superglobals. `Kernel::handle(Request): Response` serves the web, CLI, tests,
  admin preview, and plugins (such as a static exporter, D-476).
- **Immutable values, lazy work.** Readonly value objects, native lazy objects,
  and nothing parsed or rendered until it's used.
- **Zero cost when unused.** Admin, publishing webhooks, and search are
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
  object. It also discovers plugins, themes, and icon packs (settling their
  namespaces, D-378), autoloads the local ones, then registers providers
  in source order. `compile()`/`clearCompiled()` manage the
  `storage/cache/*.php` files (D-060).
- **Runners** (D-064, D-068): `Core\Runner` is the shared start of every
  entry point. It registers a bare error handler, builds and boots the
  application, then hands over to the configured `ErrorHandler`.
  `Http\HttpRunner` and `Console\ConsoleRunner` extend it.
- **Theme providers** register between plugins' and the site's
  (D-116).
- **Service providers** keep the declarative constants from x3p0
  (`SINGLETONS`, `TRANSIENTS`, `ALIASES`, `TAGS`, `BOOTABLE`).
- **Provider sources**, in order:
  1. Framework defaults
  2. Enabled plugins (D-041, D-378), from Composer packages and from
     `extensions/` (D-418), discovered and cached
  3. The active theme chain's providers
  4. The site's providers from config
- **`Paths`:** a readonly value object for root, config, user, content, media,
  data, public, resources, storage, cache, index, logs,
  sessions, accounts, extensions, and vendor. Any path can be overridden (D-046), and
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
- **Settings** (`Blush\Settings`, D-324, D-325): the few settings the
  admin can change (`Setting`, `{section}.{key}` such as `feed.limit`)
  are saved in `user/data/settings.json` (`SettingsFile`), in sections
  named for the config files, and laid over the config on every build
  (`Settings::apply()`, through each object's `toArray()`/`fromArray()`),
  so a saved value wins. Compiling leaves them out. Each `Setting` is
  also a field on its screen (`Setting::field()`, `SettingsScreen`,
  D-343): general, reading, search, and ai (D-398), and the editable
  screens are field set targets (`settings:{screen}`, `SettingsTargets`;
  plugins' AI settings go on `settings:ai`, D-397); a set's settings are saved
  raw in the file's `site` section and read through their fields by
  `SiteSettings` (`$template->site()`).
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
  - `EntryParsed`, `ViewRendering`, `ResponseReady` (M2)
  - `ContentIndexed` (M4b; the content version listens, M6a), `ContentWritten`, `ContentPublished`, `CacheCleared` (D-415)

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
- **Editor JSON Schemas (D-206):** `Blush\JsonSchema\JsonSchemas` builds
  `resources/schemas/{theme,plugin,icons,menu,region}.schema.json`
  (`composer schemas`; a test fails when they're stale). Field definitions
  come from each built-in type's `Field::definitionSchema()`, checked with
  `if`/`then` on `type`; menu items and region items from each kind's
  static `MenuLink::itemSchema()` and `RegionItem::itemSchema()` (D-207).
  Manifests and items are open; menu and region files are closed, like
  their loaders. `entry.schema.json` is the built-in front matter, from
  `Field::valueSchema()` and `Schema::jsonSchema()` (D-211). Sites reach
  them through `vendor/` (a `$schema` key or
  YAML comment, or the skeleton's `.vscode/settings.json`). Other data
  files come later.

## Menus and regions (D-199 to D-204)

- `Blush\Menu`: `MenuLoader` reads `user/data/menus/{name}.*`; `Menus`
  resolves a location's menu for a chain and locale into immutable
  `Menu`/`MenuItem` objects, kept per process (the page cache keeps
  pages). Link kinds (`entry`, `term`, `collection`, `route`, `url`)
  are `Link\MenuLinkType` + registry + factory + registrar.
  `Menu::forPath()` marks the current item from `ViewContext::$path`.
- `Blush\Region`: `RegionLoader` reads `user/data/regions/{name}.*`;
  `Regions` renders a location's items (site file, else the theme's
  defaults). Item kinds (`directive`, `component`, `markdown`, `entry`,
  `view`) are `Item\RegionItemType` + registry + factory + registrar.
  Markdown renders through the body cache; directives and components
  render per request.
- Unresolved links and items that fail are left out and logged;
  `check()` on each feeds `menu:list` and `theme:check`.
- Locations come from the theme manifest; same-name matching, with an
  optional map in `user/data/theme.json`.
- Text values in these files may be locale maps, resolved by the page's
  locale with catalog-style fallback (D-202).

## Translation (D-028)

Implemented in M5a (D-107); domains by `vendor/name` and `user/lang`
overrides in D-451, catalog metadata in D-452, `en` last in D-453.

- **`Translator`:** CMS-wide, in-house, using ICU MessageFormat via `ext-intl`
  (`MessageFormatter`) for plurals, select, and number/date arguments.
- **Catalogs:** per domain (`blush`, `app`, and each extension's
  `vendor/name`), per locale, stored as data files (`lang/{locale}.json`),
  starting with `@@locale` and `@@domain` (D-452). `Translator::domainOf()`
  maps an extension namespace to its domain, for directive and icon
  labels.
- **Overrides:** `user/lang/{locale}/blush.json`, `app.json`, and
  `extensions/{vendor}/{name}.json`: each domain's first layer, winning
  key by key within a locale; a group in one replaces the package's
  group (`group()`), while across a list of domains groups add up.
- **Lists of domains:** `translate()`, `has()`, and `group()` take one
  domain or a list, searched in order within each locale.
  `DomainTranslator` binds a list: `Views::$messages` is the theme
  chain's, child first (`$template->t()`, `tGroup()`); a directive or
  component gets the chain's and then its own extension's (`Renderable::t()`).
- **Locale fallback:** `en_US` → `en` → the default locale and its
  language → `en` (D-453).
- **Formatting services:** `DateFormatter` and `NumberFormatter` wrappers
  (`IntlDateFormatter`, `NumberFormatter`) use the site locale and timezone.
- **Available in:** views (`$template->t()`), directives, components, controllers, the CLI,
  and later the admin.
- **Multilingual content** (the same entry in several languages) is separate
  from UI translation (D-036, D-455, D-456). `config/app.php`'s
  `languages` lists the languages besides the site locale's
  (`Core\Languages`). A translation is a sibling file with the code
  before the extension (`about.fr.md`); records carry a `language` code
  and the unsuffixed `original` path (or names its original's id in
  `translation_of`, which links it whatever its name, D-511), the index keys entries by
  language and links translations (a translation's folders and parent
  take their translations' keys when the snapshot is built,
  `TranslatedKeys`, D-457), queries find the default language
  unless given another (`language()`, `anyLanguage()`), and each other
  language's routes are registered again under `/{code}` (named
  `{code}:{name}`, `language` parameter). A page's locale follows its
  language, so `$template->t()`, dates, `$site->lang`, and `$site->dir`
  (`Locale::isRightToLeft()`, D-471) do too, and
  its directives and components get a `LocalizedRepository` in its language (D-458,
  `ViewContext::$language`), in a translation's Markdown too: the
  parsed document carries the language to its directives (D-459).
  `untranslated` (`Core\Untranslated`, D-467 to D-469) says what a
  language does without an entry's translation: a 404, a 302 to the
  original (`ContentController::untranslated()`), or that plus lists
  with the originals (a query's `fallback` language, set by the
  repository; `ArraySelector` keeps one record per translation group).

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
  `HandleErrors`, `ConditionalGet`, and `PageCache` exist so far, plus
  `StartSession`, `VerifyCsrf`, and `Authenticate` (D-219); login
  throttling is `LoginThrottle`, not a middleware. The
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
     plugin providers. Themes can't add routes (D-020).
  4. `config/routes.php` (`RouteConfig::$routes`)
  5. Fallbacks (`PageRoutes`): the homepage at `/` (and `/page/{page}`
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
- **`Router`** (the kernel's handler): canonical trailing-slash redirect
  (skipped for `exact` routes, D-310),
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
- **Site URLs** (D-476): `SiteUrls::all()` lists every concrete URL
  from tagged `UrlSource`s (content, feeds, sitemaps, `llms.txt` and
  Markdown pages, literal redirects, and plugins'), each path once, a
  listing with `SiteUrl::page()` for its later pages. Blush doesn't
  visit them; it's for plugins (a static exporter, a cache warmer, a
  link checker). Sitemaps don't use it; they list URLs from content
  (D-123).
- **Languages (D-456):** content routes and the page catch-all are
  registered again for each language besides the default under
  `/{code}`, ahead of the default's (`ContentRoutes::localized()`).
- **Not yet:** a base path for subdirectory installs (open question).

## Fields (D-337 to D-348)
The value layer is `Blush\Field` (see Content → Types and schemas). Built
(D-338): field types describe themselves (`typeLabel()`,
`typeDescription()`, `controls()`); a field's `control`, checked by
`canUse()`, with `editedWith()` the one used; `toForm()` for the admin's
forms; `GET fields/types`, the catalog; definitions as a list or a map
(`FieldFactory::definitions()`); and config types and media fields in
array form built with the container's registry. Field sets on content
types are built (D-339): `FieldSet`, `FieldTarget`, `ContentTypeTarget`,
`FieldSetLoader` (extensions, `config/fields.php`, `user/data/fields`),
`FieldSets`, sets in `ContentTypes::schema()` and its compiled array,
`FieldSetCheck` in `content:lint`, and a group per set in the editor.
Structure → Fields is built too (D-340): `DataFieldSetWriter`, the
`fields/sets` API, and the list, set, and New Field Set screens. Targets
are generic (D-341): each consumer tags a `FieldTargetSource`
(`FieldTargets` collects them), a target gives its own schema, and
`FieldSets::schemaFor()` adds its sets' fields; content types
(`type:{name}`), media kinds (`media:{kind}`), and the Settings screens
(`settings:{screen}`, D-343) are the consumers. A source can also report
conflicts across its targets (`conflicts()`; settings share one store),
and `FieldTargets::problems()` gathers every target's clashes and every
source's conflicts for lint and the set writer. A set's targets are
all one kind (D-347), and its `slot` names one of the slots that kind
declares (`FieldTargetSource::slots()`, `FieldSlot`; every kind offers
only `details` for now, D-348), falling back to the kind's default
(`FieldTargets::slotFor()`). Each admin screen maps slots to places; the
entry editor keeps every set in its document panel, out of the writing
area. The Fields API is paused (D-348): this is a baseline.
The design, with content types the only consumer until the API is
right:

- **`FieldSet`:** a named, labeled, ordered list of fields with
  `targets` (`type:post`, all of one kind, D-347), attached from the
  set's side. From extension
  `FieldSetSource`s, `config/fields.php`, and `user/data/fields/*`, a
  later set replacing an earlier one with its name. A type's own inline
  `fields` are its own set.
- **`FieldTarget`:** a place fields attach to. It says which fields it
  accepts (all, by default), so field types stay unaware of where
  they're used. A missing target is a notice. Content types' target is
  in `Content\Type`.
- **A type's schema:** entry fields, its own fields, then its sets'
  fields by set name; a name used twice is a load error.
- **`Control`:** the admin's fixed control vocabulary. Each field type
  lists the controls it can use (the first is the default); a
  definition picks one with `control`. No custom controls (yet).
- **The catalog:** field classes describe themselves (label,
  description, controls, definition schema), served as
  `GET fields/types` for the admin's definition editor.
- **The admin:** Structure → Fields lists and edits sets (data sets
  only), a type's screen lists its sets, and the editor shows each set
  as a document panel group.

## Content

Every content convention 1.x supports keeps working (D-078); the inventory
is in that decision.

### Conventions (under `user/content/`)
- Each folder is a collection of the type mapped to it. `index.md` is the
  collection's landing page. A file belongs to the type whose path is the
  nearest folder above it, or else to `page` (D-083).
- A tree's pages and a taxonomy's terms have a `position` field
  (D-412): siblings sort by it, then title, those without one last.
- Everything before the last `.` in a file name is organizational
  (`01.intro.md`, `2003-04-15.welcome.md`): it isn't part of the slug, and
  it sets the default order. Only collections and taxonomies take these
  prefixes; on a tree's or profiles type's file or folder they still
  read, but lint reports an error (D-409).
- A `_` prefix on a file name, or on a folder between the type's folder
  and the file, means hidden (D-088).
- A `_drafts/` folder or `status: draft` marks unpublished entries;
  `status: trash` (with `trashed`) marks entries in the trash (D-484).
- **Folder entries:** `slug/index.md` is the entry `slug`, listed in the
  folder above. `index` directly in a type's folder is the landing page
  instead. Media is never kept beside an entry (D-294): only
  `user/media` is media.
- **Entries are `.md` files only** (D-501); other user data (menus,
  redirects) lives in `user/data/`.
- `_errors/404.md` and `_errors/500.md` are error pages (1.x's `_error/`
  folder works too, D-108).

### Types and schemas
Implemented in M4a (D-083, D-084); kinds and option names from D-157.

- **`ContentType`** (`Blush\Content\Type`): an abstract base with the
  final kinds `Collection`, `Taxonomy`, `Tree` (D-386), and `Profiles` (`TypeKind` names
  them in data). Shared: name, `folder` (`_{name}` by default, D-258;
  the URL prefix drops each folder name's leading `_`), `labels`
  (`TypeLabels`, D-278), `description`, and `icon` (D-256), `public`, `urls` (`TypeUrls`:
  prefix plus per-key paths over 1.x's defaults, with `single` and
  `collection` shortcuts, or `false`), `listing` (`Listing`: typed `type`,
  `orderBy`, `order`, `perPage`, plus 1.x `query` arguments), `feed`
  (`TypeFeed`: `categories` taxonomy and a `listing`), `sitemap`, and its
  own `Schema` (`fields`, `closed`). `Collection` adds `dateArchives`
  (`DateArchives`); `Taxonomy` adds `types`, `field`, `aliases`,
  `termListing`, and `hierarchical` (a `parent` reference to its own
  terms, D-257); `Tree` has no URLs, listing, or feed, and its folder
  is the content root for `page` only (`atRoot()`; others are served
  under `pagePath()`, the folder without its `_`, and have an index
  page, D-386). `parentKey()`
  says where an entry nests: a tree's entries by folder, hierarchical terms by
  `parent`, nothing else. `fromArray()`
  dispatches on `kind` (or 1.x's `taxonomy: true`) and accepts the 1.x
  option names.
- **Sources, one model** (D-042, D-083): built-ins, extension
  `ContentTypeSource`s, `ContentConfig` (`config/content.php`), and
  data types (`user/data/types/*.json|yaml`, edited in the admin, D-311).
  A data file named for a code collection or taxonomy overrides it
  instead (D-349: `ContentType::overriddenBy()`, each option it sets
  replacing the code's; the type keeps its origin, and
  `ContentTypes::isOverridden()` says so); the code's root tree (pages) and profiles
  types can't be. `ContentTypeLoader::codeTypes()` returns the types
  before data, which `DataTypeWriter` writes overrides against.
  `TypeRouteKeys` lists a type's route keys and what each path holds
  (D-350), for the admin's Addresses panel and its checks.
  `ContentTypeLoader` merges and checks them into `ContentTypes`, which
  finds types by name, folder, or file, and builds each type's full schema.
  `ContentConfig` also holds the home alias, the data-type policy,
  `disabled` built-ins, and `autoIndex`. The resolved types compile to
  `storage/cache/content-types.php` outside development (D-092).
- **Built-in types:** `page` (a `Tree`, the catch-all, folder `''`) and
  `profile` (D-043, D-351, D-352: `Profiles`, the fourth kind, folder
  `profiles`, routed only at `single` (`/profiles/{name}`) and its paged
  and feed keys, not served as pages, with an `avatar` media field).
  Both can be redefined, and `profile` can be disabled. A site has at
  most one `Profiles` type (`ContentTypes::profiles()`). Other types
  credit profiles through their **people fields** (`PeopleField`,
  `ContentType::$people`, keyed by front matter field; collections get
  `authors` reading `author`, pages and taxonomies none), each a
  `ReferenceField` to the profiles type in the schema. A people field
  reading a key a taxonomy reads is dropped at load
  (`ContentType::withoutPeopleReading()`), so 1.x `author` taxonomies
  keep working. `ContentTypes::termTypes()` is the taxonomies plus the
  profiles type: the types the index keeps terms (and virtual terms)
  for.
- **`Schema`** (`Blush\Field`, D-338): field types `text`, `markdown`,
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
  **typed entry fields**, and **admin forms** (D-233). Field sets,
  controls, and the field type catalog are planned (D-337; see Fields).

### Entry
Implemented in M4b (D-088).

- `Content\Entry\Entry`, a readonly value object: `path` (the source
  path, or `virtual:{type}/{slug}`), `id` (the entry's UUIDv7 from its
  `id` front matter, `null` without a valid one; D-477, D-480), `type`, `slug`, `key` (the slug with any folders below the type's),
  `title`, `status` (`Published | Draft | Scheduled | Trash`; D-484:
  `Status::selectable()` is what status controls offer, and
  `Status::active()`, every one but `Trash`, is what `Query::any()`
  finds), `visibility`
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

- **`DocumentParser`** (`Blush\Content\Parser`): one parser, no
  registry (D-501). A content document is YAML front matter and a
  Markdown body, whatever stores it; it returns a `Document` (front
  matter, unrendered body). The filesystem driver keeps each document as
  a `.md` file (`FilesystemStorage::EXTENSION`), and `FilesystemSource`
  and `FilesystemWriter` read and write only those; `FormatCheck` has
  `content:lint` report files in the formats read before D-501.
- **Front matter:** YAML behind the `YamlParser` interface, split off by
  `FrontMatter` (1.x's `---` rules). It starts with a temporary adapter
  (symfony/yaml, objects refused, timestamps as strings); a small in-house
  YAML-subset parser is the long-term plan (D-006).
- **Markdown:** behind the `MarkdownParser` interface (`Blush\Markdown`).
  It starts with a CommonMark adapter (`CommonMarkParser`), with an
  in-house parser as the long-term goal. league/commonmark is never part
  of the public API (D-492): the dialect is fixed (CommonMark, autolinks,
  struck and highlighted text, tables, task lists, footnotes, definition
  lists, attributes, directives), and `MarkdownConfig` says only how it
  renders, by Blush names (`mentions`, `smartPunctuation`,
  `headingAnchors`, `lineBreaks`, `html` as `RawHtml`, `figures`, and
  `HeadingAnchorOptions` and `FootnoteOptions`). New syntax is a
  directive; replacing the parser is binding `MarkdownParser` (and
  `MarkupFinder`). Mentions (`@slug`) link through `MentionResolver`,
  bound to `Content\ProfileMentions` (D-493).
  1.x's rendering is built in (D-100): local media links point at the
  media URL and images get their dimensions, root-relative links become
  absolute, and a lone image becomes a `<figure>` with its title as the
  caption. Media references resolve from the site root, never against
  the entry's folder (D-294), so `toHtml($markdown)` takes no base.
- **Directives:** generic directives (`:::name`, `::name`,
  `:name[text]`, D-026) parsed by an in-house CommonMark extension and
  rendered through `Directive\DirectiveRenderer` as registered directives
  (D-112, D-532). The seam the parser uses (`DirectiveKind`,
  `ParsedDirective`, `DirectiveRenderer`, `DirectiveRules`) is the
  Directive subsystem's (D-535), so Markdown depends on it one way; with
  no renderer bound, directives are plain text. See `theming.md`.
- Raw HTML in Markdown: on a page, `MarkdownConfig::$html` (allowed by
  default, D-494); in the admin, the `html.allowed` and `html.unfiltered`
  capabilities, checked on save by `Admin\HtmlGuard` against
  `Markdown\Html\HtmlRules` for what the save adds (D-495). Files on
  disk are trusted.

### Taxonomies and relations
- Terms are entries (`user/content/topics/art.md`). A term that is referenced
  but has no file gets a virtual term, titled as first written (D-090).
- The index stores each entry's terms (forward) and the entries per term
  (reverse); `termCounts()` counts listed entries. Other reference fields
  are forward-only for now.
- **Profiles** (D-351, D-352) are entries of the profiles type, indexed
  like terms (forward, reverse, virtual). An entry's credits are kept
  twice in its record's `terms`: per people field
  (`PeopleField::termKey()`, `profile.cooks`) and together under the
  profiles type's name. The repository reads 1.x's `author` query
  argument, `whereAuthor()`, and `orderby: author` as the profiles type
  (`Query::withTaxonomyRenamed()`) unless a type is named `author`.
  Each profile's page (`ProfileController`, `PageKind::Profile`) lists
  every crediting type's entries. Each people field with archives
  (`ContentType::archivedPeople()`, `ContentUrls::hasArchive()`) has
  `{type}.{field}.collection` (`PeopleController`, the profiles
  `PeopleArchives::credited()` finds, with the type's hidden
  `_{field}` page) and `{type}.{field}.single` (`PersonController`,
  paged, with feeds) under its prefix, at the field's archive word.
  A person's archive is introduced by `_{field}/{slug}` in the type's
  folder when it's published, else the profile. `PeopleArchives::
  profiles()` lists every profile with a page, for the sitemap and
  the site's URLs.

## Source → Index → Repository

Implemented in M4b (D-087, D-090).

- **`ContentSource`** (`Content\Source`) reads raw documents: `files()`,
  `stat()`, `read()`. The default is `FilesystemSource` (content files by
  parser extension, dotfiles skipped, paths confined). Git, S3, or a
  database could follow later.
- **`ContentStorage`** (`Content\Storage`, D-485) pairs a source class with
  a writer class. `StorageConfig`'s driver for `StorageArea::Content`
  (D-486) names one from `StorageDriverRegistry`; the only built-in is
  `filesystem` (`FilesystemSource` + `FilesystemWriter`). An extension
  registers a driver, or binds `ContentSource` / `ContentWriter` itself.
- **`ContentIndex`** (`Content\Index`) is the queryable metadata store.
  - `PhpIndex` (default): `storage/index/content.php`, a `var_export`'d
    `IndexSnapshot` kept in opcache shared memory. Records stay arrays;
    queries are array filters (`ArraySelector`).
  - `SqliteIndex` (optional, later): for large sites and FTS5 search.
- **`RecordBuilder`** turns a file into an `IndexRecord` (the 1.x file
  conventions, D-088) and its schema violations. It reads `id` before
  the schema (`EntryFields::ID`, a reserved key no field may claim), and
  a missing or malformed id is a violation (D-477, D-480).
- **Ids** (D-477, D-480): `IndexRecord::$path` is the source path and
  `$id` the UUID; the snapshot (index v6) keeps `ids` (id to path, the
  first by path) and `duplicates`. `Support\Uuid` makes v7 UUIDs and
  checks any version. `Content\EntryIds` finds files missing a valid id
  or sharing one, and fixes them through `ContentWriter::assignIds()`,
  for `content:ids` and Content health.
- **`Indexer`:**
  - A full scan, or an incremental one that skips files whose mtime and
    size match and keeps records whose hash matches. Paths a writer just
    wrote (`written`) are read whatever their stat says (D-480). A changed
    fingerprint (content types, timezone, locale, languages) forces a
    full scan.
  - The index is built on first use if missing; in development each
    request's first use refreshes it (`ContentConfig::$autoIndex`). In
    production, reindexing is triggered by CLI, webhook, or admin save.
  - Emits `ContentIndexed` with the `IndexReport` when it writes.
- **`ContentRepository`** is the facade: `query()`, `find(id)` (by the
  UUID, D-480), `findPath(path)`, `named(type, key)`, `term()`, `termCounts()`, `parent()` and
  `children()` (from records' `parent` keys and the snapshot's reverse
  `children` map, D-257), `parentKey()` (for a hierarchical term's
  nested URL, which `ContentUrls::termPath()` builds, D-260), plus `get()`,
  `paginate()`, and `count()` for queries, plus `redirects()` for
  `redirect_from`. URLs are resolved by the router's content routes, not
  the repository. A stale index (another fingerprint) is rebuilt on first
  use in any environment (D-098).
- **`Linter`** checks every file for `content:lint` (D-091; dates not
  on the calendar, read from the key's line since YAML has already
  rolled them, D-449), then the
  media metadata files (`Media\MediaMetadataCheck`, D-293: unreadable,
  out-of-schema, hidden, and orphaned ones, by their path from the site
  root).
- **`ContentWriter`** (D-228; `Blush\Content\Writer`, `FilesystemWriter`
  by default): `load` (raw front matter, body, and a revision hash),
  `create`, `createAt` (a fixed key), `createUnder` (a tree's page in
  its parent's folder, a parent kept as `about.md` first becoming
  `about/index.md`; `WriteResult::$moved`; D-408), `move` (a tree's
  page and those under it to another parent, D-410), `update`
  (`EntryChanges`: set, remove, body), `rename` (a
  new slug; date prefixes kept, bundles move their folder), `duplicate`
  (a copy beside it under the first free name, `-2` and on; a new date
  prefix; a bundle's folder copied; D-275), and
  `trash` (`status: trash` and `trashed`, the file left in place),
  `restore` (to `status: draft`, `trashed` removed), and `delete` (the
  file, and a bundle's folder once empty, for good; D-484). Writes are atomic, serialized
  by a lock file, checked against the caller's revision (`WriteConflict`),
  confined to the content root and content formats, and followed by an
  incremental reindex and a content version bump. `DocumentEditor`
  edits Markdown and HTML front matter and YAML entries key by key
  (`YamlMap`, keeping formatting and aliases), rewrites JSON entries,
  and parses every result to confirm only the intended values changed.
  Entries are named by path. Every new entry (a copy too) gets a new
  `id`, written last; `update` adds one to a file without it and never
  changes or removes one; a new key goes before an existing `id`; and
  `assignIds` gives files new ones in one reindex (D-477, D-480).
  Outside the writer, entries are named by id (D-481): the admin API,
  preview links, and the editor's addresses.

## Query

Implemented in M4b (D-089).

- An immutable fluent builder built on `clone()` with properties and marked
  `#[\NoDiscard]`:
  `$content->query()->type('post')->whereTerm('category', 'art')->orderBy('published', Order::Desc)->paginate(perPage: 10, page: $page)`
- `Query::fromArray()` reads 1.x query arguments (a type's `collection`,
  a page's `collection` front matter) plus `status`, `visibility`,
  `terms`, `locale`, and `language`.
- A query finds the default language's entries unless given a code
  (`language('fr')`) or `anyLanguage()` (D-456). In another language,
  `withOriginals()` (or the site's `untranslated: include`) adds the
  default language's entries without a translation in it (D-469).
- `search()` matches the title or source path in any case, and
  `either()` takes alternatives (each built from `Query::condition()`,
  which matches everything) of which an entry must match one: the OR
  that `Permissions::restrict()` needs (D-230).
- Returns an `EntryCollection` or a `Paginator`. Hydration is lazy, so
  listings never render bodies.
- `Paginator::links($url, endSize, midSize, adjacent)` builds numbered
  pagination as `PageLink`s (kind, number, URL; D-161);
  `ContentPage::pageLinks()` passes the page's URL builder.
- Compiled per index: array filters for `PhpIndex` (`ArraySelector`,
  with a `RecordMatcher` per query and per alternative), SQL for
  `SqliteIndex`. `PhpIndex` scans every record for each query, so a
  query's cost grows with the site (about 2 ms per 1,200 entries,
  D-230); `SqliteIndex` is the answer for much larger sites.

## Media

Implemented in M4c (D-099), apart from image derivatives.

- Originals live in `user/media` only (D-294: no page bundle media).
- **`MediaConfig`:** the media URL (`/media` by default; jtcom uses
  `/user/media`) and the MIME allowlist (1.x's images, audio, and video).
- **`MediaResolver`:** turns front matter and Markdown references into
  `MediaFile`s (path, URL, MIME, size, dimensions): media URL paths and
  1.x `/user/media/...` paths into `user/media`; a relative path is read
  from the site root (D-190). `fromKey()` resolves a media key (its path
  under `user/media`), as the index and metadata files name files.
- **Serving** (web root is `public/`): `media:publish` links `public{url}`
  to `user/media` (or copies the allowed files with `--copy`), and
  writes the served folder's `.htaccess` (`PublishMedia::HTACCESS`,
  D-499: no scripts by any extension, `nosniff`, sandboxed SVG), leaving
  one without its `MARKER` alone. The
  `MediaController` streams anything unpublished, with ranges, `nosniff`,
  and sandboxed SVGs.
- **Uploads refuse** `MediaUploads::REFUSED` (SVG, markup, scripts, Office
  files with macros; D-497, D-499) whatever the site allows, and
  `safeName()` turns dots before the extension into hyphens.
- **Metadata (D-238, D-269, D-287):** fields for media, defined with
  content types' field types but without a body, status, or URLs, by
  kind (`MediaKind`: image, video, audio, document, file; documents are
  `MediaKind::DOCUMENT_TYPES`, D-406). `MediaSchemas` builds a
  kind's schema from the built-in fields (`alt` for images, then
  `title`, `caption`, `credit`, `description` for all), then the field
  sets aimed at the kind (`media:{kind}`, D-341). Values are
  stored in `user/data/media/`, mirroring the media paths (`{path}.yml`),
  never next to the file, as
  `MediaMetadata` (values by key), read and written by
  `MediaMetadataStore` (only the keys changed, under the name or alias
  in use; YAML edited key by key with `YamlMap`, JSON kept JSON, an
  empty file removed). `GET media/{path}` answers the fields, values,
  other keys, and violations; `PATCH media/{path}` sets and removes
  fields, checked by their fields. Pages use the library's alt text
  where an image has none (D-270); captions fill in on insert only.
  **The media index (D-288)** is separate from the content index:
  `Media\Index\MediaIndexer` records every library file
  (type, size, dimensions, metadata values) in `storage/index/media.php`
  (`MediaIndex`), incrementally by stat and metadata file time, with
  orphaned metadata files (one walk, `MediaMetadataStore::files()`,
  shared with `content:lint`); `MediaLibrary` keeps it fresh (on first use,
  in development each request, after admin writes; else `media:index`
  and `publish`) and answers `MediaQuery`s (search, kind, missing
  alt text), which `GET media` lists. **Embedded metadata
  (D-289, D-291, D-552)**: `Media\Embedded` readers (XMP, IPTC, EXIF for
  images; ID3, MP4, Ogg, RIFF, and Matroska for sound and video, over
  `BinaryFile`; PDF for documents; Type enum,
  registry, factory, registrar) merged by `EmbeddedMetadataReader` into
  one set of keys, cached in each file's record and reread only when
  the file changes; the location is kept apart and never answered.
  Readers that are also `ArtworkReader`s (ID3, MP4, Ogg) give the
  picture a file carries, read on request (`GET media-artwork/{path}`),
  never cached.
  Still planned: when rendering, a value set where the media is used
  wins, then the metadata file, then embedded metadata.
- **Ids (D-487):** every original has a UUIDv7 `id`, last in its
  metadata file (`MediaMetadata::ID`, not a field, like `owner`; a
  field set can't claim either). Uploads write it with the owner;
  `MediaIds` (report, `assignMissing`, `keep`, as `EntryIds`) backs
  `media:ids` and Content health's `health/media-ids[/keep]`;
  `MediaMetadataCheck` reports missing, malformed, and shared ids, by
  the media file's path when it has no metadata file. `MediaRecord::id()`.
  The indexer takes `written:` keys to reread after a same-second write.
- **Sizes (D-239, D-488):** resized copies imported from WordPress
  (`photo-300x200.jpg`) are sizes of their original. An image's
  metadata file lists them (`sizes:`, key → `{width, height}`,
  `MediaMetadata::$sizes`, not a field, written one to a line before
  the `id`); `Index\MediaVariants` takes listed sizes first (first
  image by key wins; an image that's a listed size can't have sizes),
  then D-239's rule for the rest (a `-{w}x{h}` name, an original beside
  it with the same extension that isn't a size, real dimensions that
  match and are no larger, and no id of its own), as
  `MediaRecord::$original` (snapshot v4). `MediaSizes` (report:
  `unrecorded`, `stale`; `record()` writes each image's list whole)
  backs `media:sizes` and Content health's `POST health/media-sizes`.
  `MediaLibrary::query()` lists originals only; `sizes($key)` gives an
  image's, smallest first. The admin API: `sizeCount` on items, `sizes`
  and `original` on a file; a size goes by its image's details (PATCH
  422), deleting an image deletes its sizes, deleting a size takes it
  off the list, and `usedIn` counts sizes' uses (`MediaUsage::entries()`
  takes more paths). Sizes get no id; lint warns of a size's metadata
  file and of stale listings. **Planned:** rules as a registry, and
  `-scaled`, `-rotated`, and edited `-e{time}` files (open-questions.md).
- **Image derivatives:** in-house GD/Imagick adapter, always from the
  original, never from a variant (D-239); a focal point field guides
  crops. Sizes are named and declared by the theme (sites can add or
  change them, in config first and the admin later), generated on demand
  and cached in `public/_media/`, never in `user/media`.
  Output includes `srcset`/`sizes` helpers.

## Views

Plain PHP templates (D-009). **The full theming design is in `theming.md`**
(themes are presentation only, with a data-first manifest, parent chains,
directives and components (D-532), and per-entry presentation fields; no design
token system, D-160). The
view layer was implemented in M5 (D-103 to D-125).

- **`Views`** (`Blush\View`): renders templates for one theme chain.
  A PHP template runs in a static closure with its `Template` as `$template`
  and no object or class scope, so it reaches only the template API
  (D-158). Layouts (which may nest), sections, and partials (shared data plus their own). Failures
  close their output buffers and become `ViewException`s.
- **View engines** (`Blush\View\Engine`, D-502): `Views` renders each
  template file through the engine its extension names, `PhpEngine`
  built in; a plugin registers more (`ViewEngineRegistry`). `Template`
  is every engine's API, with `#[ReturnsHtml]` and `SafeHtml` marking
  rendered HTML for engines that escape on their own.
- **`ViewFinder`:** view names (`single-post`, `layouts/base`) resolve
  through `resources/views/themes/{active}`, `resources/views`, then the
  theme chain, in each engine's extension (the first registered wins
  within a folder). `ViewFactory` builds one `Views` per chain and the per-page
  `ViewContext` (the `Head`, sections, shared `$site`, body classes, and
  the front matter `layout`).
- **`Escaper`:** `e()`, `attr()`, `url()`, `js()`, `css()`, and `raw()` are the
  only global functions (D-106).
- **`Head` manager:** collects title, meta, OpenGraph, canonical, alternates,
  stylesheets, and scripts, each once, and renders them in the base layout
  (D-109), with root-relative `href`s and `src`s as full URLs on the
  site's origin (D-193). `ThemedPageRenderer` adds the page number to the title on later
  pages of a listing (D-162). It takes registered assets by handle
  (`enqueue()`) and footer scripts, and is held during `Views::render()`
  and filled once the page has rendered (D-570).
- **Assets** (`Blush\Asset`, D-569 to D-573): `AssetRegistry` (handles,
  seeded by `AssetRegistrar` with `blush/player`), `AssetUrls` (core,
  plugin, theme, or URL; `?v={crc32}`), `Assets` (prints handles into a
  head, requirements first), `AssetCollector` (render scopes, so a
  directive's or component's `assets()` reach the page, kept with cached
  bodies and fragments), and the `core.asset` and `plugin.asset` routes
  (`AssetController`). Core's site files build from `resources/site`
  into `public/site` (`npm run site:build`).
- **Directives and components** render through `Views` (`directive()`,
  `hasDirective()`, `directives()`; `component()`, `hasComponent()`,
  `components()`), by namespaced name (D-171). Each is its own subsystem
  (see Directives and components, D-532).
- **`Hierarchy`:** the candidate view names for a content page or error,
  with front matter `template:` first (D-104).
- **Renderers:** `ThemedPageRenderer` (the `PageRenderer`) and
  `ThemedErrorPages` (the `ErrorPages`) pick the chain per request
  (`ThemeResolver`, `?theme=` in development), fill in the head, and
  dispatch `View\Events\PageRendering` before the templates (D-571).
- **Context providers** (`ContextProviders`, D-114) add data to views by
  name or pattern.
- **Themes** (`Blush\Theme`, D-105, D-115 to D-121): `ThemeDiscovery`
  (framework, Composer `blush-theme`, and `extensions/` (D-166, D-418), before the
  container; cached in `storage/cache/themes.php`), `Themes`,
  `ThemeChain` (with its providers, registered at boot), `ThemeConfig`,
  `ThemeResolver`, `ThemeAssets` (build manifests or mtime), settings
  (`SettingsResolver`, `SiteThemeData`), `ThemeChecker`, and the
  `theme.asset` route.

## Directives and components (D-532)

Two subsystems that share a base: `View\Renderable` (props, content,
the root element's `classes()`/`attributes()`/`html()`, `t()`,
`locale()`, `contentOr()`) and `View\RenderableFactory` (builds a class
through the container, casting string props). `Views` renders both and
picks the chain's template first, then the class's `render()`
(`renderOwn()`).

### Directives

`Blush\Directive` (was `Blush\Component` before D-532), bound by
`DirectiveServiceProvider`: what content says.

- **Directives:** `Directive` (abstract; `KIND`, `CONTENT`, `VARIANTS`,
  `HOLDS`, `attach()`, `variant`, `render(): string|DirectiveView|null`;
  every directive is a class that declares its `KIND`, D-534); `DirectiveName`
  (short names only for core, views `directives/…`), `DirectiveDefinition`,
  `DirectiveContent`, `DirectiveCategory`, `DirectiveType` (the core
  ones); `DirectiveRegistry` (every directive registered; refuses a
  theme's namespace, D-532), `DirectiveRegistrar`, `DirectiveListing`,
  `DirectiveVariants` and `Events\DirectiveVariantsCollecting` (D-266),
  and `PendingDirective` (`$template->directive()`).
- **Built-ins:** `Callout` (D-195), `Embed`; the layouts in
  `Directive\Layout`
  (`Group`, `Grid`, `Row`, `Stack`, `CssLength`, D-177, D-318); `Directive\Media`
  (`Audio`, `Video`, `File`, `Figure`, `Gallery`, `MediaPreload`, D-179); `Directive\Inline`
  (`Abbr`, `Kbd`, `Time`, D-180; `Badge`, `Cite`, `Dfn`, `Ins`, `Samp`,
  `Small`, `Variable` for `var`, D-305); `Toc` (D-183, fed the outline by the Markdown
  layer's `CollectOutline`); `Menu`; `Icon`; `Progress` and `Meter` (D-188); and `Button`
  (D-189).
- **Props:** `MediaProp` marks media props, which are resolved like an
  image's; `LinkProp` marks link props (D-190).
- **Rendering itself (D-382):** every core directive's `render()`
  returns its file in `resources/directives/`, and
  `DirectiveListing::rendersItself()` reads `render()`'s return type, so
  a class that can't return `null` is never "missing a template".
- **The seam (D-535):** `DirectiveKind`, `ParsedDirective`,
  `DirectiveRenderer`, and `DirectiveRules` live here; the Markdown
  parser imports them, and `Directive` takes only `MarkdownConfig` from
  Markdown.
- **Markdown:** `MarkdownDirectives` (the default `DirectiveRenderer` and
  `DirectiveRules`) renders a parsed directive (`ParsedDirective`)
  as the registered directive of its name, or `null` (plain content)
  for one no one registered.
- **Admin:** `GET directives` (`Admin\DirectivesController`); the admin
  calls them blocks.

### Components

`Blush\Component`, bound by `ComponentServiceProvider`: a template's
reusable pieces, from themes, the site, and plugins.

- `Component` (abstract; `attach()` with `Slots`, `render(): string|ComponentView|null`),
  `TemplateComponent`, `ComponentName` (always namespaced, view
  `components/{namespace}-{name}`), `ComponentRegistry` (name → class
  only; a template alone is a component), `ComponentListing`, and
  `PendingComponent` (`$template->component()`, with `slot()`).
- `Views::components()` lists registered classes and every
  `components/*.php` named for one; `component:list` and `theme:check`
  read it, leaving out other themes' components.

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
  everything outside production, and asks the AI crawlers of each
  `SitemapConfig::$blockAi` group, `AiCrawlerGroup`, to stay away,
  D-398).
- **Markdown pages and `llms.txt`** (`Blush\Llms`, D-395): every
  published entry with a URL has its body as written, under front
  matter (title, URL, dates, summary), at its URL with `.md` (the
  homepage at `/index.md`), served as `text/markdown` with a canonical
  `Link` header. `MarkdownLinks` gives a Markdown body's (and
  summary's) links full URLs as the HTML has them (D-396): inline and
  reference destinations, and directives' media and link props by the
  directive's definition, skipping code and HTML found line by line.
  `MarkdownPages` finds the entry by building each
  entry's Markdown path, so a lookup scans the site's entries (the page
  cache keeps the answer). `/llms.txt` lists public entries of the
  types whose `llms` option is on (by default collections and trees,
  D-401), under the site's description
  (`AppConfig::$description`, D-398); with `LlmsConfig::$full`,
  `/llms-full.txt` has every listed page's copy in one file (D-402).
  `LlmsRoutes` is a system route registered last among the framework's
  (so the admin's and media's own `.md` paths win, and content routes'
  `{name}` never takes `hello.md`); themed pages link the version with
  `<link rel="alternate" type="text/markdown">`. Both are site URLs.
- **Search:** optional; needs `SqliteIndex` (under discussion: a JSON index; see `open-questions.md`).

## Caching

Implemented in M6a (D-127 to D-130), apart from publishing (M6b).

| Layer | Key / invalidation |
|---|---|
| Config, routes, plugins, themes, icon packs, content types, container plans | Compiled PHP files; cleared by `cache:clear` or deploy |
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

## Embeds (D-184)

- **Providers:** `EmbedProvider` (name, label, oembed.com-style schemes,
  HTTPS endpoint; `request()`, `frame()`, `allowsScripts()`). Built in:
  `YouTube` and `Vimeo` (enum + registry + factory + registrar); sites
  add `OEmbedProvider`s in `config/embed.php` or classes in
  `ProviderRegistry`. `EmbedProviders` matches a URL to the first
  provider (configured ones first). Unmatched URLs are never framed.
- **Lookups:** `Embeds::lookup()` asks the provider through `Fetcher`
  (`StreamFetcher` by default) on first render and keeps the answer
  (`EmbedData`) in the persistent `embeds` store: 30 days, failures an
  hour. `EmbedConfig` sets the providers, `fetch`, timeout, and TTLs.
  `cache:clear --embeds` empties the store, with the cache store (D-448).
- **Rendering:** the `embed` directive frames `provider->frame()` with
  the answer's size (as `--embed-ratio`) and title; the theme owns the
  markup. Script-based rich embeds render as links for now.

## Icons (D-187)

- `IconName` (`{namespace}/{name}`, short names are core), `Icons` (finds
  a name's SVG for a theme chain: site, themes, `IconRegistry` folders
  (installed icon packs', D-378, and plugins'), then the framework's
  Lucide subset in `resources/icons/blush`), and
  the `icon` directive (inline SVG, `1em`, `currentColor`, decorative
  or labeled). Labels are catalog text (`icons.{name}.label`).

## Setup (D-218)

- `Blush\Setup\SetupChecks` checks PHP and its extensions, `.env`,
  production risks (`APP_DEBUG` on, a local `APP_URL`), `public/`, and
  that every storage path is writable (or can be created). Results are
  `CheckResult`s (`CheckStatus`: ok, warning, failure; a hint says what
  to do).
- `init` creates `.env` (from `.env.example`, through `Env\EnvFile`,
  which rewrites single lines and keeps the rest), optionally adds a
  `PUBLISH_SECRET`, and creates the storage folders. `doctor` prints
  every check.
- `HttpRunner::handle()` runs the storage checks before loading the
  application; a failure answers every request with `SetupPage` (a
  plain, self-contained 503) instead of a stack trace.

## Publishing and admin (D-013)

- **Stage 1: no UI (ships with core; M6b, D-131 to D-133)**
  - `Publisher` (`Blush\Publish`): an optional `git pull` in `user/`
    (`Puller`, `GitPuller`), the compiled content types and route table
    rewritten if present, an incremental reindex, the cache store
    cleared, a new content version, and `ContentPublished`. One at a
    time (a lock file).
  - A signed webhook, `POST /_blush/publish` (only with
    `PublishConfig::$secret`): HMAC-SHA256 over timestamp and body,
    a time window, seen signatures kept in the persistent `webhooks`
    store, and an address locked out (429) after `maxAttempts` failed
    signatures within `lockout` seconds (`WebhookThrottle`, D-414).
  - `publish` on the CLI does the same over SSH; `schedule:run` is the
    optional cron entry.
- **Stage 2: operations dashboard**
  - Auth: several accounts, each linked to an `author` entry, with roles
    that hold capabilities (D-216); passkeys later. Sessions, CSRF, and
    rate limiting.
  - The admin is a JavaScript single-page application (D-215).
  - Actions: clear caches, reindex, publish (git).
  - Content health (lint), drafts and scheduled lists, and signed preview
    URLs (built: D-225, D-226; `Blush\Preview`, signed with `APP_SECRET`).
  - A calendar of dated entries by month (built: D-368; removed, to
    be a plugin later, D-550).
- **Stage 3: editor**
  - The editing API (built, D-229): load, create, change (with the
    status shortcut and renames), duplicate (D-275), and delete entries through
    `ContentWriter`, with permissions judged on the change.
  - Forms generated from schemas.
  - A Markdown editor with live preview through `Kernel::handle()`.
  - A block inserter for dropping directives into content: only
    registered directives with a class, always written by full name
    (D-171, D-172, D-214; built, D-243: `GET directives` since D-532,
    `DirectivePanel`, and `/` at the start of a line).
  - A media library, and git-backed revisions.
- **Admin constraints:** it lives in an `/admin` route group (path
  configurable) behind its own provider and is off by default.
- **Built so far (D-219):** the auth groundwork, with no UI.
  - `Blush\Session`: server-side sessions (`FileSessionStore`, files
    named by a hash of the id), started only by `StartSession` on the
    routes that need them; a new session is saved only once something is
    stored in it; `__Host-` cookie over HTTPS.
  - `Blush\Auth`: accounts (`FileAccountStore`, `storage/accounts`),
    `Accounts` (create and change, with checks), `Passwords` (Argon2id),
    `Roles` (built-ins, then the admin's `RoleStore` in
    `storage/roles.json`, then `AuthConfig::$roles`, each with its
    `RoleOrigin`; `RoleEditor` changes the stored ones and reloads them,
    D-312), `Capabilities` (the
    registry), `Permissions` (roles, ownership through the author link,
    and the live-entry rule, as statuses per own/others' entries that
    both `can()` and the query filter `restrict()` use; and media, D-407:
    `mayUpload()` by kind, `mayChangeMedia()` by a file's `owner`, and
    `usesMedia()`), `Authenticator` (throttled sign-in, session
    login with a new id, a CSRF token, and a password fingerprint), and
    the `VerifyCsrf` and `Authenticate` middleware.
  - `Blush\Admin`: `AdminConfig` and the JSON API under
    `{path}/api`: `GET session`, `POST login`, `POST logout`.
  - CLI: `account:add|list|password|roles|name|email|author|suspend|reinstate|remove`;
    `init` offers the first account, the owner (D-500).
  - The admin edits accounts and roles (D-312): `AccountEditController`
    (new accounts with a one-time `PasswordLink`, roles, author,
    suspension, removal), `RoleEditController`, and the public
    `SetPasswordController`, within `PeopleRules` (never more than you
    have, never your own account, someone always able to manage
    accounts); `PeopleJson` describes both for the screens.
  - Accounts carry `Preferences` (the admin's color scheme, D-235), set
    through `PATCH {path}/api/preferences`; `SessionReader` lets the
    admin's shell read the session without starting one.
  - An account changes its own password through `POST
    {path}/api/password` (`PasswordController`, D-273):
    `Authenticator::confirm()` checks the current one, throttled like a
    sign-in, and `refresh()` keeps the session signed in with a new id.
    `Authenticator::account()` forgets a stale sign-in (and its CSRF
    token), and `VerifyCsrf` doesn't refuse a session that's signed out.
  - An account may have a `name` (D-322), what the admin calls the
    person: one line, up to 100 characters (`Account::tidyName()`,
    `isValidName()`). `Accounts::displayName()` is the name, else the
    profile's title, else the username (D-370); `GET session`,
    `PeopleJson` (accounts and each role's holders) send it as
    `displayName`, and the dashboard greets by it (D-323). Set on New
    Account, an account's screen (`PATCH accounts/{username}`), Your
    Account (`PATCH {path}/api/profile`, `ProfileController`), and
    `account:add --name` / `account:name`.
  - Every account has an `email` (D-370): `Accounts::create()` and
    `invite()` require one, and `checkEmail()` refuses one that's
    missing, invalid (`Account::isValidEmail()`), or another account's
    in any case. It's `null` only for an account saved before emails,
    which the admin flags. Set with `account:add --email` (asked for
    when left out), `init`, `account:email`, `POST`/`PATCH accounts`,
    and `PATCH profile`. Blush sends no email. The
    session's `roles` carry each role's label, so the admin never shows a
    role's key outside Roles, and the session carries the account's
    `profile` and `created`, so Your Account (`/accounts/{you}`, D-371) is the account screen
    on your own row without `accounts.view`.
- **The admin app (D-220 to D-224):** a Vue 3 SPA (`resources/admin`,
  built to `public/admin` with plain file names) over the private JSON
  API. `ShellController` serves one page at `{path}` and every screen
  under it (strict CSP, no caching or framing) with the Vite entry's
  URLs versioned `?v={crc32}` (`AdminApp::url()`) and a JSON start-up
  block; `AssetController` serves the build's files at
  `{path}/assets/{file}` (immutable with `?v=`, `no-cache` without). `AdminConfig::$app` swaps
  in another build. Screens so far: sign-in, the dashboard (entry
  counts and actions, or a setup path on an empty site), a list per
  content type (D-234; no list of every type, D-240), and the
  editor (D-233; a new entry opens in it unwritten, from
  `GET entries/new`, and its first save creates it, D-336; forms from schema fields via `fields.ts`, saves that
  send only what changed; D-240: unsaved changes kept in the browser
  (`kept.ts`), saves that wait for a connection, failed saves with Try
  again, conflicts with Keep theirs, Compare (`diff.ts`), and Keep mine,
  and required fields checked before publishing), and content health
  (D-225); lists, the dashboard, and the editor show skeletons while
  loading (D-240); drafts and scheduled entries are
  tabs on each list (D-236), as is the trash (D-237, a status since
  D-484: restore as a draft, delete permanently, empty), and a taxonomy's list counts each term's uses. The body is
  edited in `MarkdownEditor` (D-241: a text area over a highlighted copy
  from `markdown.ts`, which finds directives by the server's rules, and
  styles emphasis, strong text, headings, quotes, and list markers with
  muted marks, D-253, in Fira Code, the admin's only mono, D-254,
  D-255), with
the block inserter (D-243, D-532: `directives.ts` loads `GET directives`
once, groups core directives by `DirectiveType::category()` and the rest
by source, keeps recents, and writes the directive text). The
  navigation is a section rail (Home, Content, Config) with a panel for
  the active section (D-244: `meta.area` on each route), with taxonomies
  nested under the one type they group, and screens not built yet are
  `PlannedView` stubs (D-241). The editor fills the work area
  (`meta.bleed`) as a writing surface (D-245): a centered column, a
  settings drawer with Document and element tabs (`DirectiveOptions`,
  whose changes `markdown.ts` writes into the directive's head as minimal
  edits), and focus mode (`focusMode` in `screen.ts`). Its inserters
  (D-247): `DirectivePanel` (a pushing panel, also opened by `/`),
  `IconPicker` (a popover over `GET icons`, `site-icons.ts`), and
  `MediaPicker` (a `<dialog>` over `GET media`, also behind **Choose** on
  media fields); `grid.ts` moves through their grids. What screens do
  alike is shared in modules, as how they look is in `admin.css` (D-505):
  `action.ts` (`useAction`, `latest`, `debounced`), `popover.ts`,
  `dialog.ts`, `drop.ts`, `query.ts`, `month.ts`, `guardLeave()` in
  `confirm.ts`, `copyText()` in `toast.ts`, and `errorMessage()`,
  `saveSettings()`, `patchEntry()`, and `trashEntry()` in `api.ts`. Toasts (`toast.ts`,
  `toast(message, { kind, undo, life })`, drawn by `ToastHost` as the
  toast sketch has them, D-387) and the command palette
  (`CommandPalette`, with screens adding commands through
  `useCommands`) are in the layout (D-248). Roles and
  Accounts are list and detail screens over `GET roles` and
  `GET accounts` (D-249), edited since D-312 (`RoleChecks`,
  `CapabilityChecks`, `ProfilePicker` (D-356), New Account, New Role, and the
  public Set Password screen), as are Content types over `GET types` and
  `GET types/{name}` (D-250) and Media over `GET media` and
  `GET media/{path}` (D-251). The editor's address is the entry's
  type and id, `content/{type}/{id}` (D-483; handles, D-253, are only
  shown now). Entry tables show each entry's site address, not its file,
  and end in a row menu (`MenuButton`, `floating`; D-254); a
  collection's or taxonomy's landing page is its **index page**, pinned
  in a `tbody` of its own above the rest and answered apart from them
  as `index` in `GET entries` (D-255), as Pages' root page is, with
  the homepage marked by a house and a **Homepage** tag (D-420). The element tab follows the
  caret (D-268, D-280): `elements.ts` resolves the most specific of the
  directives, images, and blocks (`blocks()`, with lists and definition
  lists) and builds the outline and breadcrumb from their spans; a
  directive gets `DirectiveOptions`, a Markdown image `ImageOptions`
  (with `ImagePreview`), and a block `BlockOptions`, each writing
  minimal edits through `markdown.ts`, which also carries list, quote,
  and table markers on Enter (`continuation()`). Reference fields use
  `ReferencePicker` over `GET references/{type}`
  (`ReferencesController`, D-281); selects are `AdminSelect` and dates
  `DatePicker`; images' variants are
  the theme's `variants.image` classes (`DirectiveVariants::forImages()`,
  `GET directives`' `image`). `MediaPicker` has Library and Upload tabs;
  uploads go through `POST media` (`MediaUploadController`: hidden first,
  checked by contents with `MediaResolver::mimeOf()`, then named), by
  the upload rules (`MediaConfig::$uploads`, a `MediaUploads` of
  `MediaUploadRule`s by kind, D-406: on or off, the largest file, and
  the path pattern under `user/media`), which the Media settings screen
  saves as the one setting `media.uploads` (`UploadRules.vue`). The look follows `.claude/docs/admin-design/` (D-231):
  design tokens in `css/tokens.css` are the only literal values, and
  the shell is a rail, a top bar, and a scrolling work area. Extension pieces are described in PHP and drawn
  generically (D-222): `AdminAction`s (label, description, capability,
  optional confirmation, `run()` → `ActionResult`) in
  `AdminActionRegistry`, with `publish`, `reindex`, and `clear-caches`
  built in.

## Extensions (D-041, D-378)

*Extensions* is the umbrella for everything a site installs. Each is one
of a few **kinds** (`Extension\ExtensionKind`): **plugins**, **themes**,
and **icon packs**; **admin themes** are planned on the same pieces.

- **Shared by every kind** (`Blush\Extension`): a manifest named for its
  kind (`plugin.json`, `theme.json`, `icons.json`, or `.yaml`/`.yml`;
  `ManifestFile`, JSON wins), one folder for every kind,
  `extensions/{vendor}/{name}` (D-418; `LocalExtensions` finds a kind's
  folders, `LocalExtension` reads one, and a folder claiming two kinds
  is broken for each), a Composer package type per kind
  (`blush-plugin`, `blush-theme`, `blush-icons`), and whether it runs
  code (plugins and themes do; icon packs don't). A folder's kind is its
  manifest file or its `composer.json`'s `type`, either or both, which
  must agree (D-432, `LocalExtensions::claims()`); a manifest file is
  optional for every kind and source, Composer's too.
- **Identity:** every manifest has `name`, the key (`vendor/name`,
  Composer's rule, `ExtensionName`; a Composer package's own name), and
  `label`, the readable title (optional: without one, or with a blank
  one, the name is the title, `ExtensionName::label()`, D-423; so a
  Composer package needs no `extra.blush.label`), and `namespace`
  (optional too: without one, the name with hyphens for the `/` and any
  `.`, `ExtensionNamespace::fromName()`, D-424; an empty manifest, `{}`,
  is valid). A local extension's folder is its name: a
  manifest naming another is broken, saying where it belongs.
- **Composer's schema** (D-418): the keys a manifest shares with
  `composer.json` (`name`, `description`, `version`, `license`,
  `authors`, `autoload`, `require`) take Composer's names and shapes, and
  a manifest that leaves one out takes it from the `composer.json`
  beside it (`ComposerJson::fill()`); Blush's own keys come only from
  its `extra.blush` (D-432). Each key is read from the manifest file,
  then `extra.blush`, then `composer.json`'s top level (shared keys
  only); `ManifestFile::load()` reads a folder that way. `autoload` (`Extension\Autoload`) is `psr-4` plus `files`, every
  path inside the extension.
- **Namespace:** every manifest declares one (`ExtensionNamespace`):
  what its directives, components, and icons go by (its translation domain is its
  `vendor/name`, mapped from the namespace, D-451). Reserved:
  `blush`, `app`, `theme`, and `default` (the default theme's). No two
  installed extensions share one: two plugins doing so fail discovery;
  two themes, or two icon packs, are both broken; across kinds,
  `Bootstrap` lets installed plugins (even ones turned off) claim first,
  then themes, then icon packs, and records each loser as broken.
- **Plugins** (`Blush\Plugin`): a manifest plus, usually, a service
  provider; `provider` is optional (D-425), so a plugin may only load
  `autoload.files`, `require` others, or carry `lang/`, and one with
  nothing is allowed (`Plugins::providers()` skips those). A
  plugin can register content types, routes, CLI commands, directives, components,
  listeners, parsers, field types, cache drivers, and translations (its
  `lang/` is its domain, its `vendor/name`, D-451).
  - Composer plugins keep the rest of their manifest in `composer.json`
    `extra.blush` (`label`, `namespace`, `provider`, `require`, all
    optional, so `extra.blush` may be left out), read from Composer's
    `installed.json`. A local plugin may do the same, with no
    `plugin.json` (D-432).
  - Local plugins' `plugin.json` declares name, label, namespace,
    version, description, `autoload`, the provider, `require`, and
    optionally `authors` and `license` (any Composer key it leaves out
    from its `composer.json`, D-385, D-418). Blush registers the
    autoloader and loads `files` once (`Extension\LocalAutoloader`).
  - By default a Composer plugin is on, and a local one only when
    `PluginConfig`'s `enabled` (`config/plugins.php`) names it (D-390).
    Once the admin saves a list (`plugins.enabled` in
    `user/data/settings.json`, laid over `PluginConfig::$saved`), it
    names every plugin that's on, Composer's included, and nothing else
    is (D-391). Config never lists what's off. Naming one that isn't
    installed fails boot (naming a broken one doesn't). Discovery is
    compiled to `storage/cache/plugins.php` (D-058), every installed
    plugin, on or off, and the broken ones.
  - **Broken plugins** (D-394, `BrokenPlugin`): a manifest that doesn't
    parse or doesn't hold is kept, by where it was found (a Composer
    package's name, or its folder from the root) with the reason and
    its name when the manifest gives one, instead of failing discovery
    (`PluginDiscovery::discover()` returns `DiscoveredPlugins`). It
    never runs, turned on or not; `Plugins::broken()` lists them.
    Duplicate names and namespaces still fail discovery.
  - **Requirements are enforced** (D-385, `Extension\Requirements`): an
    enabled plugin runs only when its `require` is met: Blush as
    `blush-dev/framework` (D-418), `php`,
    `ext-{name}`, and other extensions of any kind by `vendor/name`
    (installed at a fitting version and running), as Composer's
    constraints, by Composer's rules, stability included
    (`Extension\VersionConstraint`, a port of `composer/semver`'s parser,
    D-429). `Plugins` holds the ones that run, every installed one, and
    what the rest don't meet; providers register a plugin's requirements
    first. Every kind is enforced the same way (D-431): see
    **Requirements across kinds** below.
  - CLI: `plugin:list` and `plugin:check` (D-394), and `plugin:new`
    (D-416).
- **Requirements across kinds** (D-431): plugins, themes, and icon packs
  share `ExtensionManifest` (`name`, `label`, `version`, `require`,
  `kind()`) and `ExtensionRequire` (reading `require`), and
  `ExtensionState::settle()` decides at boot which run, all kinds
  together, since any may require any other: the plugins config turns
  on, the packs that are on, and the active theme's chain, each only
  when its `require` is met by the site and by the others that run
  (`Requirements::settle()`, to a fixed point). A chain is a group: it
  runs whole or not at all, and one that can't run falls back to the
  default theme (`Themes::running()`, used by `ThemeResolver::active()`
  and the autoloader); a pack that can't run adds no icons
  (`IconPacks::enabled()`, with `on()` for what's turned on). A theme
  requirement is met by a theme in the running chain. Names are one
  extension's across kinds (`Bootstrap` leaves out a theme or pack whose
  name an extension before it has). `ExtensionState` is bound in the
  container: `check()` (as if on), `report()` (the admin's
  `requirements`, `blocked`, and `requiredBy`), `with()` (settling again
  with other choices, for 422s and what starts or stops), and
  `runningNotIn()`. A Composer package's `composer.json` `require` is
  Composer's to check: a Composer theme or pack doesn't take it
  (`ComposerJson::fill()`'s `skip`). Enforced in the admin (`PUT
  plugins`, `PUT icon-packs`, `PATCH settings` `theme.active`, rollback),
  `theme:activate`, the check commands (`plugin:check`, `theme:check`,
  `icon-pack:check`), and `doctor`.
- **Themes:** see `theming.md`. Known by name everywhere (`active`,
  `parent`, `?theme=`, `/themes/{vendor}/{name}/…`,
  `resources/views/themes/{vendor}/{name}`); the default theme is
  `blush/default`. Broken ones are listed by where they were found.
- **Icon packs** (`Icon\IconPack`): SVGs in the pack's folder (or its
  manifest's `folder`), each `{namespace}/{icon}`, labeled from its
  `lang/`. Which are on works as for plugins (D-390, D-391): Composer's
  and the ones `IconConfig` (`config/icons.php`, `enabled`) names, or,
  once saved, only the admin's list (`icons.enabled`, laid over
  `IconConfig::$saved`); discovery is lenient (broken packs are listed) and compiled to
  `storage/cache/icon-packs.php`. The folders of the packs that are on
  seed `IconRegistry`, so themes and the site can restyle them.
- **Admin:** Extensions lists Themes, Plugins, and Icon Packs. Themes
  are activated and deleted (D-381); plugins and icon packs are turned on
  and off and deleted (D-385), each saved in `user/data/settings.json`.
  **Install** takes a `.zip` (D-392).
- **Installing** (`Extension\Install`, D-388, D-392, D-418):
  `ExtensionInstaller` installs a `.zip` of a folder into
  `extensions/{vendor}/{name}`, at the name its manifest (or
  `composer.json`) gives, or replaces one with the same name. `ExtensionArchive`
  checks every entry first (no absolute paths, `..`, or links; at most
  5,000 files and 100 MB unpacked), unwraps a zip whose files sit in one
  folder, and writes entries itself. The archive is unpacked into a
  hidden `extensions/.install-…` (discovery skips hidden folders), read
  as discovery reads a folder, and checked: its kind (one, by manifest
  file or `composer.json` `type`, D-432), manifest,
  its name held by another kind, namespace (reserved, or claimed across
  kinds), a Composer install of its name, `composer.json` requirements
  beyond the platform, and PHP syntax for kinds that run code
  (`token_get_all(TOKEN_PARSE)`). Then it's renamed into
  `extensions/{vendor}/{name}`, or swapped for the installed folder (not
  a git checkout), the old one kept in `storage/backups/{vendor}/{name}`,
  one per extension (D-393): rolling
  back swaps it in and keeps the version it replaces, discarding removes
  it, and deleting the extension deletes it (`ExtensionBackupController`;
  `InstalledExtensions` finds a folder extension and whether it runs).
  `InstallClash` is an installed one
  with its name; `InstallException` anything else, naming the kind an
  archive of another kind holds. Nothing is turned on (D-390).
  `ExtensionInstallController` answers `POST themes`, `POST plugins`,
  and `POST icon-packs` (`install`, or `update` to replace), and each
  list's `upload` (the limit, 25 MB or PHP's, and any `problem`).

## Hosting (D-040)

- **Shared Apache hosting is a first-class target** (jtcom is on GoDaddy).
- **Whole-project installs work out of the box** (D-071): uploading the
  entire project into `public_html` needs no setup. A root `.htaccess`
  rewrites every request into `public/` (and denies everything without
  mod_rewrite).
- **Blush ships:**
  - `GET counts` (`CountsController`, D-371) gives the section panel's
    counts: each editable type's entries as its list counts them, and
    media files, accounts, roles, content types, field sets, themes, and
    extensions where the account may see them (D-372); the admin reloads it on every change of screen.
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
- **Precompiled** (opcache-friendly PHP files): config, routes, extension (plugin,
  theme, icon pack) and provider discovery, the content index, and container resolution plans.
- **Lazy:** services (deferred and lazy objects), entry bodies, and
  Markdown rendering.
- **Layers:** page cache (including files the web server can serve without
  starting PHP), and HTTP 304s.
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
- The webhook uses HMAC with a time window, replay protection, and a
  lockout after failed signatures, and exists only when a secret is
  configured.
- Admin uses CSRF protection and SameSite=Strict cookies.
- CSP and security headers, plus an upload allowlist.
