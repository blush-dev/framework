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
| M7 | **Static export.** `build` plus incremental mode. Removed in D-476 (a plugin's job now). | jtcom exports and serves from static files |
| M8 | **Port jtcom.** jtcom theme, config, `user/` layout, a URL-parity crawl against the live site, and a redirect map. | Every old URL returns 200 or 301; deployed (dynamically, D-142) |
| M9 | **Admin stage 2:** operations dashboard. | Publish, clear, and reindex from a browser |
| M10 | **Admin stage 3:** editor and media library. | Create and edit entries in a browser |
| Later | The data layer for every storage area (D-606), with SQLite, MySQL/MariaDB, and PostgreSQL drivers, and Redis and Memcached cache drivers (D-640); plugin views in the view chain (D-174; on hold, D-380; a plugin's components render themselves since D-382); search (FTS5 on the SQLite driver); in-house YAML and Markdown parsers; theme distribution; custom template engine; Vite dev-server integration | — |

---

## M8 (Port jtcom): in progress

Approach (D-142): jtcom runs dynamically; its theme keeps SCSS; the port
lives on jtcom's `2.x` branch. First, a trial port on a test branch of
`blush-dev/blush` against jtcom's real content, to find framework gaps
before jtcom changes. Skeleton fixes done first (D-143).

The trial branch is `jtcom-trial` in `../blush`. Its `user/` holds all
of jtcom's content, uncommitted (hidden by the local
`.git/info/exclude`): every entry and media file (about 1,200 entries,
4,260 media files, 380 MB; the full copy replaced the first subset of
55 posts on 2026-10-02, keeping the trial's own edits to files it
already had). The skeleton's own sample files show as deleted there;
don't commit them.

The trial covers everything jtcom 1.x does (`app/`, `config/`,
`public/views/`, `resources/scss/`): its seven types, the archive pages,
its controllers and `EntryTerms` block (as built-in routing and a
component), head meta, Markdown setup, every view, and the SCSS build.

### Trial progress

Done on `jtcom-trial` (a test bed; its site files are never committed,
D-156): `config/content.php` (the seven types, typed objects,
`home: 'post'`), `config/media.php` (`/user/media`), `config/markdown.php`
(jtcom's extensions and options). The theme is
`extensions/justintadlock/jtcom` (D-167, D-418), with the `Jtcom\View\PostArchives` component (the year, month,
and full archive lists) and its `Jtcom\ThemeProvider`, built with Vite
(D-155; `npm run build` in the theme's folder), with views for every page kind:
singles (post, literature, page), the homepage and listings, date
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

## The data layer (D-606): plan

Groundwork done: every storage area resolves through its driver, and
the data area is a `DataStore` (D-642). Settled: the names (D-643),
schemas and moving between drivers (D-644), and sessions and jobs
keeping narrow contracts (D-645). The steps, each reviewed before it's
built:

1. **Settle what blocks code** (done: D-643 to D-646).
2. **The record layer, on files only** (built, D-647). Roles are its
   first table.
3. **Content onto records** (planned below): writes keyed by id in place of
   `ContentWriter`'s paths, the index owned by the filesystem driver,
   content's `Query` wrapping `RecordQuery`, relation filters over
   D-585's links. The biggest step; it changes the admin's write paths.
4. ~~The filesystem driver's SQLite index~~ (dropped, D-661: SQLite
   is a driver a site chooses, never a second index beside files).
5. **The SQLite driver** (D-640, built: D-662 to D-667), its schema
   from types (D-644), and `storage:sync` and `storage:copy`, built on `Blush\Storage\Sql`
   (D-659's 4a, kept). The driver for large sites (D-661); an admin
   warning suggesting it is planned.
6. **Data and accounts onto records** (planned below, D-668): accounts
   and roles read through their repositories, links to accounts by id,
   and the data area as a table per kind. Sessions and jobs keep their
   narrow stores (D-645). Composer drivers, the admin's copy tool,
   publishing a database site, and reshaping the `entries` table are
   later steps, each planned on its own.
   Where it starts (after step 5, 2026-10-09):
   - Roles are on records (D-646). Data and accounts have record stores
     only as plain keyed tables for the SQLite driver
     (`RecordDataStore`, `RecordAccountStore`, D-665); on files they're
     still `FileDataStore` and `FileAccountStore`, with no repositories.
     Sessions and jobs keep narrow stores, with database versions
     (`RecordSessionStore`, `RecordJobStore`, D-665).
   - Raised by the author for later (`open-questions.md`, "The `entries`
     table's shape, later"): translations and `parent_id` as refs, and
     `fields` keeping only registered fields; also database-enforced
     uniqueness and D-649's composite ref indexes.
   - Also open: duplicating answering alike on every driver; the SQLite
     driver at scale (lists of every entry, folded sorting, term
     counts); per-field indexes from types (`storage:sync`, D-666);
     `storage:copy` from SQLite back to files.

### Step 2: the record layer, on files only (built, D-647)

**Goal:** the generic layer every later step builds on, proven by a
conformance suite and one real adopter, with nothing a site or the
admin can see changing.

**2a. The model** (`Blush\Storage\Record`):
- `Record` (final, readonly): `id`, `values` (by key), `body` (nullable).
  `with()` and `without()` return copies.
- `Table` (final, readonly): a table's definition as a driver needs
  it: `name`, the `StorageArea` it belongs to, its key (below), and the
  values it declares (for indexes later, D-644). Tables are registered
  (`TableRegistry`), by core and by plugins in `boot()`, as the other
  registries are.
- `RecordStore` (interface): `find(Table, id)`, `findMany(Table, ids)`,
  `save(Table, Record)`, `delete(Table, id)`, `select(Table,
  RecordQuery)` (a `RecordResult`: records, and the total before limit
  and offset), `count`, `aggregate`, and `transaction(Closure)`, as
  `DataStore`'s works.
- Exceptions: `RecordException` (base), `RecordNotFound`,
  `InvalidRecordQuery`.

**2b. The query** (`RecordQuery`, immutable, each call a copy):
- Conditions: `where(key, Operator, value)` with `Operator` an enum
  (`=`, `!=`, `<`, `<=`, `>`, `>=`, `in`, `not in`, `like`, `null`,
  `not null`, `between`), on values, `id`, or `body`; dotted keys reach
  into nested values. `whereAny()` and `whereAll()` take closures for
  nested or-and-and groups, kept as a condition tree (`Condition`,
  `ConditionGroup`).
- `orderBy()` (several keys, each with `Order`), `limit()`, `offset()`,
  `paginate()`.
- Aggregates: `count()`, `countBy(key)` (counts per value), `min`,
  `max`, `sum`, `avg`.
- Relation filters and eager loading (`whereRelated()`, `with()`) are
  designed into the condition tree now and built in step 3, where
  D-585's links become records.
- Values compare by type: no SQL-style coercion, so every driver can
  match (the conformance suite pins each case: nulls, missing keys,
  numbers against numeric strings, dates as ISO 8601 strings,
  case-sensitive `=` and case-insensitive `like`).

**2c. The filesystem driver** (`FileRecordStore`):
- A table is kept one of two ways, chosen per table: a **folder** in
  its area's root (data: `user/data/{table}/`), one file per record,
  named by its key; or **one file** holding every record by key, for
  small tables kept that way today (`storage/roles.json`,
  `user/data/health/ignored.json`, `user/data/redirects.json`). Writes
  are atomic. Its format
  is a codec the driver picks per table: JSON now (`JsonCodec`), front
  matter and Markdown in step 3. The record model never sees a format.
- Queries load a table's records once per request and run them through
  `ArrayEvaluator`: the condition tree, order, and aggregates over
  arrays, compiled to closures. Fine for data-sized tables; content
  keeps its own index until steps 3 and 4.
- Transactions: the same put-back on throw as `FileDataStore` (D-642),
  shared, not copied.
- `FilesystemStorage` binds `RecordStore` for the areas it covers.

**2d. The conformance suite** (`tests/Storage/Conformance`): an
abstract test case with fixture tables and records and the expected
answer to every operator, group, order, page, and aggregate, plus
transactions. `FileRecordStore` runs it, and so does
`ArrayRecordStore` (in memory, for tests). Every later driver extends
it; a driver that fails a case isn't done.

**2e. One adopter, to prove it:** roles (D-646). Tests
for the adopter pass unchanged, and `composer bench` shows no
regression.

**Done when:** the conformance suite passes on both stores, the
adopter runs on `RecordStore` with its file in the same place and shape
(an `id` added to each record, nothing else), `composer
check` passes, and `docs/` documents tables for plugin authors (what's
built only).

**Settled (D-646):** every record has a UUID `id`, and a table may
declare a unique key value (`name`, `username`) that lookups use and
the filesystem driver names files by. The first adopter is **roles**
(`storage/roles.json`, a one-file table keyed by role name; each role
gains an `id` on its next save). Accounts follow in step 6.

### Step 3: content onto records (built, D-651 to D-657)

**Goal:** content reads and writes through the record layer, keyed by
id, so a database driver (step 5) can keep it, while a flat-file site
keeps every file convention it has (D-078) and sees no change. The
biggest step, so it's built in six parts (3e split in two, D-655), each reviewed and built on
its own, with `composer check` and the benchmarks passing after each.

**What the code does today** (mapped 2026-10-08):
- **Identity comes from paths.** Type (the nearest type folder), key
  (tree folders plus slug; `_` segments), slug (file or bundle name
  after its last `.`), landing page (`index` in the type's folder),
  bundle, tree parent (`dirname` of the key), language and locale (the
  `.fr` suffix), translation group (the path without the suffix, unless
  `translation_of`), hidden (`_` names and folders), draft (`_drafts`),
  error pages (`_errors/`), `updated`'s fallback (mtime), and which of
  two files claiming a key wins. A database must store each as a value.
- **Writes are keyed by path.** Every `ContentWriter` method takes and
  returns paths; callers find an entry by id, then pass its path
  (EntryController, Referrers, RelationChanges, ProfilesController,
  Accounts, MissingTerms, TypedTargets, CreateContent). Revisions are
  the file's hash; front matter is edited as text (`DocumentEditor`,
  `YamlMap`) to keep comments, order, and aliases.
- **About 20 classes read the index snapshot directly**, not through
  the repository: relations (RelationChanges, Referrers,
  RelationLimits, EntryRelations), maintenance (EntryIds, FileNames,
  EntryFolders, EntryRefs, MissingTerms, TypedTargets, Linter),
  ReferencesController, ContentVersion, and FilesystemWriter.
- **Queries the record layer can't express yet:** effective status
  ("scheduled" depends on now), terms (any of several slugs, falling
  back to slugged front matter), 1.x `meta_key`/`meta_value` (compared
  as slugs), `date()` by parts (an hour without a day isn't a range),
  the fallback language's dedup (drop a fallback entry whose group has
  one in the query's language), and content's sort rules (natural,
  case-insensitive text; nulls first ascending; ties by id in the
  query's direction; position before title).
- **Relations** are computed: `LinkBuilder` resolves front matter and
  `refs` into `Link` rows (source id, relation, target id, position)
  over the whole snapshot; written forms that drift are "stale" and
  filed back (D-596). A database keeps only the rows.

**3a. The record layer grows what content needs** (built, D-651;
generic, in the conformance suite):
- **Versions** (D-648): a store-given `version` on each record read,
  and an optional expected version on `save()` and `delete()` that
  fails with a conflict when the record changed (a file's hash; a
  database's version column).
- **Subqueries:** `in` and `not in` take a `RecordQuery` over a key of
  the same table (SQL's `IN (SELECT …)`), for the fallback language's
  dedup and "children of".
- **`intersects`:** a list value holds any of several values, for terms.
- **Refs** (D-649): `whereRelated()` (a record that refers, through a
  relation, to given ids or to a subquery's records, or is referred
  to) and `with()` (each record's refs, loaded in one go), over the
  `refs` table.
- **Renames** (D-649): `Record`'s `values` and `body` become `fields`
  and `content`; text sorts without regard to case.

**3b. The entry record:** the `entries` and `refs` tables (D-649).
Built (D-652), with these left for later: `Link` and the graph stay
inside the index until 3d, and queries are slower until 3c (see
below).
- **Explicit columns** for everything paths imply today: `type`,
  `language`, `parent_id`, `slug`, `original_id`, `status`,
  `visibility`, `published`, `updated`, `title`, `position`; the rest
  in `fields` (`locale` and undeclared keys included), the Markdown in
  `content`. No stored key: addresses walk parents; URLs walk up,
  remembered. Landing pages, translation groups, "scheduled", and
  `created` are derived.
- **Refs** replace links: `Link` becomes `Ref` (`source_id`,
  `relation`, `target_id`, `position`), parents and translations
  moving to `entries` columns.
- **Slugged copies** of the values 1.x queries compare as slugs
  (`meta_key`/`meta_value`), computed by core on every save
  (`EntryValues`), the same for every driver, while those query
  arguments are kept (D-078).
- **`Entry`** follows the names: `$entry->content()` in place of
  `body()`, themes (the trial site's included) updated.
- **Entry ↔ record mapping** (`EntryRecords`), and the hydrator reading
  records instead of `IndexRecord`s; bodies stay lazy and cached.
- **Content's `Query` compiles to `RecordQuery`:** status to
  `status`/`published` against now; terms to `whereRelated()` over
  refs (`intersects` on slugged values for entries without ids); dates
  to ranges on `published`; language fallback to a subquery. Content sorts by the record layer's rules (D-648); query
  tests whose order changes are updated, the rest keep their answers.

**3c. The filesystem driver's content table** (built, D-653, speed
mostly won back; the rest is noted there): `FileRecordStore` hands
the entries table to a content backend: today's indexer, `RecordBuilder`
(every 1.x convention), and `PhpIndex`, now the driver's own index (D-606),
making each file's path-derived values explicit in its record.
- **Reads** run `RecordQuery` over the index (`ArrayEvaluator` over
  index arrays, not objects, so a query over 1,200 entries stays as
  fast as now), with the snapshot's lookups (keys, children,
  translations, the graph) as its fast paths.
- **Writes** compare the record with the stored one: changed values
  become a front matter edit through `DocumentEditor` (comments and
  aliases kept); a changed slug, parent, language, or type becomes the
  file move it is today (name and folder patterns, bundles promoted,
  `moved` reported). Stale written forms keep being filed (D-596), as
  the driver's business.
- **Files without an id** stay readable, with a steady id from their
  path (`Uuid::fromName()`), and can't be edited until `content:ids`
  gives them one, as now (D-481).

**3d. Writes by id, and callers moved** (built, D-654, with these left
open: keys a record can't rebuild, so entries still hydrate from the
index; `content:lint`'s split into entry and file checks; `Link` beside
`Ref`; see `open-questions.md`): `Entries` (the repository,
D-643) gains id-keyed writes: create (by type, or under a parent),
change fields and content, rename (a new slug), move (a new parent),
duplicate, trash, restore, and delete, each with a revision, returning
the entry. `ContentWriter`'s path API goes; `ContentRepository` becomes
`Entries`. Every caller moves to ids, and the snapshot readers move to
`Entries` and `RecordQuery` (relations, references, the content
version's next scheduled time). What only files have, path tools,
becomes the filesystem driver's own, offered only on it: `content:ids`,
file names and folders (`FileNames`, `EntryFolders`), filing refs, the
format check, and the parts of `content:lint` about files.

**3e. Parity and docs** (built, D-655, scoped to the record level): a
content conformance suite (`tests/Content/Conformance`: the same
entries and compiled queries on the filesystem driver and on
`ArrayRecordStore`, so a database driver has its target), with
`RecordLocations` answering folders and keys from records and
`QueryCompiler` resolving every query; the benchmarks compared with
before 3a; `docs/` corrected.

**3f. `Entries` on records** (built, D-657; its keys question settled
by D-656: files without ids aren't entries, and a tool writes missing
parents rather than records keeping keys, with an `archive` value for a
collection's archive pages): `Entries` finds and
hydrates entries from records, not paths (collections keyed by id,
bodies from `content`, `find`, `children`, `neighbors`, and
`termCounts` as record queries), so the conformance suite runs
`Entries` itself on both stores.

**Done when** (after 3f): no code outside the filesystem driver reads a content
path to find, query, or write an entry; content's queries answer as
before on files; the content conformance suite passes on both stores;
`composer check` passes; the benchmarks show no slowdown. Met but for
the last: building entries from records is slower than from the
index's rows (D-657's figures), left for step 4. Paths outside the
driver remain only in its own tools (`EntryFiles`, the linter, Site
Health's file checks) and for showing.

**Prerequisite (done, D-650):** `composer bench` runs again, and its
results before step 3 are stored locally as `before_step3`; compare
each part with `vendor/bin/phpbench run --report=aggregate
--ref=before_step3`.

**Settled (D-648):** one `entries` table, `type` a column, fields in
`values`, a database indexing core columns and only queried fields;
content sorts by the record layer's rules; `version` for the
edit-conflict check.

**Shape (D-649):** the `entries` and `refs` tables above; records'
`fields` and `content`; text sorted without regard to case.

### Step 4: dropped (D-661)

Planned as a derived SQLite copy of the filesystem driver's index
(D-659, D-660). Dropped: SQLite is a storage driver a site chooses
(step 5), and the filesystem driver keeps `PhpIndex` alone. What was
built of it that's generic stays for step 5: SQL for record queries
(`Blush\Storage\Sql`: `SqlDialect`, `SqliteDialect`, `SqlCompiler`,
`SqliteConnection`, `SqliteRecordStore`), passing the record
conformance suite and `SqlParityTest`.

### Step 5: the SQLite driver (built, D-662 to D-667)

**Goal:** `STORAGE_DRIVER=sqlite` (or `driver: 'sqlite'` in
`config/storage.php`) keeps every area's records in one database,
`user/site.sqlite` by default (`path` overrides it). Media files,
config, caches, and logs stay files (D-486). The driver for large
sites (D-661).

**What exists:** `SqliteRecordStore` (D-659's 4a) passes the record
conformance suite. `FilesystemStorage::bindings()` lists what each area
needs: content `ContentWriter` and `EntryLocations`, data `DataStore`,
accounts `AccountStore`, roles `RecordRoleStore` (on records already),
sessions `SessionStore`, jobs `JobStore`. `RecordLocations` answers
keys from records alone.

**What's missing:** a content writer over records (only the filesystem
driver has one); terms read from `refs` (entries read them from front
matter today; D-649: a database keeps only the rows); `ContentVersion`
moving on after a write (it follows the index today); `MediaUsage`
reading records (it reads content files).

**5a. The driver and content reads** (built, D-663): `SqliteStorage` (`sqlite`) gives
`RecordStore` (on the site's database) and `EntryLocations`
(`RecordLocations`); it checks for `pdo_sqlite` with SQLite's JSON
functions at boot and fails plainly without them. Entries' terms come
from `refs` for every driver. The content conformance suite's reads
pass on SQLite.

**5b. Content writes on records** (built, D-664): `RecordContentWriter` (every
database driver's) implements `ContentWriter` over `RecordStore`: an
entry and its `refs` rows saved in one transaction; core's derived
values (`slugs`, `published` on new entries, slugs unique among
siblings) and relation values resolved to `refs`; `EditableEntry` from
`fields` and `content`; conflicts by `version`. A rename or move
changes one record. Writes move `ContentVersion` on. The content
conformance suite, writes included, passes on SQLite.

**5c. The other areas** (built, D-665): data and accounts as plain keyed tables
(`DataStore` by name, `AccountStore` by username; reshaped in step 6),
and sessions and jobs on `RecordStore` inside (D-645). Each contract's
tests run on SQLite.

**5d. Schema and copying** (built, D-666; per-field indexes wait): `storage:sync` makes tables and columns, and
indexes the fields types sort or filter by (D-644, D-648).
`storage:copy --from=filesystem --to=sqlite` copies every area, ids
kept (SQLite to files later).

**5e. What only files have, docs, benchmarks** (built, D-667): on SQLite,
`content:index`, `publish`'s reindex, and `autoIndex` have nothing to do
and say so; the linter, format, and file checks in Site Health and
`WrittenDates` step aside; `MediaUsage` queries records. Requirements
and Site Health name the driver. `docs/` (installing on SQLite, moving
a site). Benchmarks: the jtcom-sized site on both drivers, and
`../ten-thousand` on SQLite within 128 MB.

**Done when:** the record and content conformance suites pass on
SQLite; a site copied to SQLite renders and edits in the admin as on
files; `composer check` passes; the large site serves within 128 MB.

**Left for later:** the admin's large-site warning (D-661), full-text
search, publishing a database site, SQLite to files, MySQL and
PostgreSQL, and step 6's repositories for data and accounts.

### Step 6: data and accounts onto records (planned, D-668)

**Goal:** everything the data and accounts areas keep is a table of
records, read and written through its repository on every driver, so
`DataStore`, `AccountStore`, and `RoleStore` go (D-606: the per-area
stores become drivers' internals or go). Files keep their places and
shapes, each record gaining an `id`. Sessions and jobs keep their
narrow stores (D-645).

**Where it starts** (mapped 2026-10-09):
- **Accounts:** `FileAccountStore` (`storage/accounts/{username}.json`,
  no ids) on files, `RecordAccountStore` (keyed by username) on
  SQLite, with `Accounts` over either. What links to an account holds
  its username: sessions, jobs' `account`, media `owner`, and ignored
  problems' `by`. An account links to its profile by the profile's
  slug (`author`). Usernames can't be changed.
- **Roles:** on records already (`RecordRoleStore`, D-646), with `Roles`
  over `RoleStore`.
- **Data:** `FileDataStore` on files, one generic `data` table keyed by
  a `name` with `/` on SQLite (D-665). Ten readers share it: settings,
  types, relations, field sets, menus, regions, redirects, theme data,
  ignored problems, and media metadata (which has ids, D-487, but is
  found by path).
- `docs/extending.md` already points plugins at tables; `DataStore`
  isn't public.

**6a. Accounts on records, linked by id** (built, D-669; a slug with no profile makes a draft, and a linked profile can be renamed):
- One `accounts` table on every driver, keyed by `username`, kept on
  files as a folder (`storage/accounts/{username}.json`, in place).
  `Account` gains its `id`, written on the account's next save, steady
  until then (from the username, as one-file tables give records
  without ids theirs), so a link written before the save still finds it.
- `Accounts` reads the table through `RecordStores`; `AccountStore`,
  `FileAccountStore`, and `RecordAccountStore` go. `Roles` does the
  same over the `roles` table; `RoleStore` and `RecordRoleStore` go.
- **Links to an account hold its id:** sessions, jobs' `account`, media
  `owner`, and who ignored a problem. **An account links to its
  profile by the profile entry's id** (`author`, a slug, becomes
  `profile`), so renaming a profile can't break it. The CLI and the
  admin still take and show usernames and display names, looking up the
  id; a link to a removed account says so.
- No fallback for the old values (no 2.x sites): sessions sign in
  again, and the trial site's files are rewritten once.
- **Roles stay linked by name:** config and code name them (`owner`,
  `administrator`), and a role's name never changes.
- **Proof:** the account and role tests on files, SQLite, and in memory;
  `AdminOnSqliteTest`.

**6b. Types and relations as tables** (D-671; built, D-672):
- **`types` and `relations`:** folder tables keyed by `name`, in
  `user/data/types` and `relations`, files in place, each gaining an
  `id`. A file's `$schema` stays first on files and is never part of a
  record (D-491).
- Messages and reports that say where a record is kept ask the store
  (a file's path, or "in the database").
- **On hold** (D-671): field sets, whose shape waits for the Fields API
  (D-348; their own table then), and redirects (likely user settings;
  no site uses them yet). Both keep `DataStore` until they're picked up.
- **Proof:** each reader's tests on files, SQLite, and in memory.

**6c. Settings as groups** (D-670; built, D-673: extensions' groups are always on demand, since the boot groups are read before plugins load; `feed.content` is saved as `feed.fullContent`):
- **A `settings` table, a record per group:** `app`, `feed`, `theme`,
  …, each extension's (`acme/gallery`), each theme's own, and the
  site's (`site`, field sets' settings). The table's key is `group`;
  a record's other fields are the group's settings, so `id`,
  `content`, and `group` can't be a setting's key.
- **Names:** code names a group as it is (`acme/gallery`); stored, its
  `/` is `__` (`acme__gallery`), which no extension name contains, so
  the name is a table key. On files, a group is
  `user/data/settings/{group}.json` (`settings.json` goes); on
  SQLite, a row.
- **Loading:** whoever registers a group says whether it's needed at
  boot. Boot groups (core's `app`, `theme`, `plugins`, `icons`) are
  read together by the bootstrap, before the container, through a
  record store built for it (D-642's early read); every other group is
  read the first time something asks for it and kept for the request.
  Core's groups are laid over their config objects as now, a lazy
  group when its config object is first fetched. No autoload flag on
  records.
- **Extensions' settings:** each plugin reads and writes its own group
  by its name; a theme's settings are its own group, so switching
  themes keeps each one's (from `user/data/theme.json`'s `settings`);
  deleting an extension can offer to delete its group. Values are
  checked by whoever owns the group (core by `Setting`, an extension by
  its own code) until the Fields API is picked up again.
- **Ignored problems** are a group of Site Health's (`health`, on
  demand), in place of `user/data/health/ignored.json`.
- **Proof:** settings read back by the bootstrap on both drivers; a
  request reading only the boot groups; groups' tests on files, SQLite,
  and in memory.

**6d. Media metadata as a table** (built, D-674, D-675: renditions in place of sizes, the description the record's content, `owner` declared, `{ext}` gone from upload paths; on files `MediaFiles` keeps the table, with file times for the index):
- `media`, keyed by its id (in every record already, D-487), with
  `path` (the original's under `user/media`) a declared field the store
  keeps unique. On files, the records stay
  `user/data/media/{path}.json`: a folder layout that names files by a
  field and reads nested folders. Moving a file renames its record's.
- Its own table, not a content type (the author): media need no URLs,
  kinds, or editor.
- How the media index and `content:lint` see a changed record (today
  file times; versions, likely) is settled when 6d is reviewed.

**6e. Menus, regions, and theme data:** wait for a discussion
(`open-questions.md`, "Menus, regions, and theme data in the data
layer"): regions may become written content (entries), menus are to be
looked at, and `user/data/theme.json`'s location maps depend on both
(its settings move in 6c). Planned once that's settled.

**6f. Retiring the data store, docs, proof** (after field sets and
redirects, D-671): `DataStore`,
`FileDataStore`, `RecordDataStore`, and SQLite's `data` table go;
`storage:copy` copies every registered table, with nothing per area
but content's; `docs/` (accounts, redirects, settings, ignored
problems, plugin tables); `composer bench` shows no regression.

**Done when:** every table the data and accounts areas keep is read
through a repository on files, SQLite, and in memory; stored links to
accounts are ids; a site copied to SQLite renders and edits in the
admin as on files; `composer check` passes.

**Not in step 6:** Composer drivers, the admin's copy tool, publishing
a database site, SQLite to files, and the `entries` table's later shape
(`open-questions.md`).

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
  `video`, and `file` (D-179); `abbr`, `kbd`, and `time` (D-180), then
  `badge`, `cite`, `dfn`, `ins`, `samp`, `small`, and `var`, and
  `[text]{.class}` spans (D-305); `toc`
  (D-183); `icon`, with a 131-icon Lucide subset (D-187); `progress` and
  `meter` (D-188); and `button` (D-189). Media props resolved against the
  entry's bundle (D-179; removed by D-294), and media and link props render as full URLs
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
a list per content type (D-234), New entry (the editor itself since
D-336), and the editor with forms
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
images from the media picker as plain Markdown (D-267). Then every
block as an object on the Component tab, with classes and an id
(attributes on by default), images edited as Markdown with the theme's
image variants, the list of components as a state, the media menu and
uploads (`POST media`), and Title Case for names (D-268). Then alt
text and captions in the media library, in `user/data/media/`, edited
on a file's screen and filled in on insert (D-269); an image without
alt text is decorative (D-272).
Next: the Markdown editing experience (D-252; begun with styled
Markdown and editor addresses by handle, D-253; a 640px Fira Code
editor, site addresses in tables, and row menus, D-254; Fira Code
throughout and pinned index pages, D-255), then the rest of media
metadata (D-238: fields defined like schemas, the media index, and
embedded metadata), a reference picker,
editing types and accounts (done: D-311, D-312), and the remaining stubbed screens
(Appearance, Extensions, Settings). Live preview waits (D-252; unsettled, see `open-questions.md`): inline
image and embed previews first, then a full preview, above all of
components. Changing one's own password on Your profile is done (D-273), the
editor's side of the index page (D-274), Duplicate (D-275), Preview
for a trashed entry (D-276), renaming from the editor (D-277), and
type labels (D-278). Then the updated direction's editor (D-280): every
element an object with one resolver, the Outline and Content groups,
the breadcrumb footer, lists (with a List Type) and definition lists,
Enter carrying markers, the sectioned ⋮ menu, the rail never
navigating, drawn selects, and the Publish group's Status menu and
calendar. Still from that direction: the rest of the document panel
(authors as people, a taxonomy tree and token field, a parent tree, a
featured image), which waits for the reference picker. The reference
picker is in (D-281): `GET references/{type}`, one picker for trees,
tokens, people, and single values, and the document panel's groups with
Visibility, Featured Image, Authors, taxonomies, and Summary. The panel
now matches the prototype, offers only the type's taxonomies (D-283),
and the Markdown editing experience has begun (D-284: formatting keys,
links, nesting lists with Tab, and files dropped or pasted in), then
heading levels and moving lines from the keyboard (D-285). Pasting HTML
as Markdown is on hold (D-286). Media metadata's fields are in (D-287:
built-in and a site's or extension's fields by kind, the API, and the
Details form), then the media index (D-288: separate and incremental,
`media:index`, publishing, and the library's search and Missing alt
text filter), then embedded image metadata (D-289: XMP, IPTC, and EXIF
read in-house, cached in the index, From the File in the admin, the
location never shown), a title for every file (D-290), and sound and
video metadata (D-291: tags, durations, and a video's size, read
in-house; artwork noted), and `content:lint` checks metadata files:
orphaned, unreadable, hidden, or with values that don't fit (D-293).
Page bundles' media is removed: media lives only in `user/media`
(D-294, which also takes back D-292's bundle files in the library).
Stripping a photo's location on upload is out of scope (D-297).
The entry lists gained the design's filter row (author, taxonomies,
Updated, `/` to search), column sorting, and a page size (D-300), then bulk selection and the
bulk bar (D-301). Then the stubbed Config screens, read-only first
(writing `config/` from the admin is a later decision): Appearance
(D-306: installed themes and the active chain; theme settings wait for
a proper API, D-307), then Extensions (D-308: every installed
extension, on or off, and what each adds), then Settings (D-309: the
site-wide settings that exist, by group, with defaults marked). Every
screen in the navigation is built now.
With `trailingSlash` on, the admin, the publish webhook, and preview
links are no longer redirected (D-310: `exact` routes). Then editing
content types (D-311): types in `user/data/types` are edited on their
screen, created with the three-step wizard, and deleted; config,
extension, and built-in types stay read-only.
Then editing accounts and roles (D-312): New Account with a one-time
password link to copy (Blush sends no email), roles ticked on an
account's screen, its author, password links for forgotten passwords,
suspending and removing; New Role and Duplicate, custom roles edited and
deleted, the built-ins' capabilities changed and reset, kept in
`storage/roles.json`; never more than you have, never your own account,
and someone always able to manage accounts.
Then the split design direction's editor (D-313): the toolbar in three
parts with no back button (the top bar's trail is the way out), bold
and italic as toggles, a link form on ⌘K, moving the top-level element
(⌥↑, ⌥↓, ▴▾), bleed with classes the theme names (`theme.json`'s
`bleed`), the code block as one box, the third backtick writing the
block, the drawer opening on the element, leaving the text dropping the
selection, and the Markdown elements as inserter tiles. Then the rest
of it (D-314): moving the element the caret is in among its siblings,
Backspace taking markers off, text kept out of directive tags and
attribute blocks, media fields and props with a kind (a locked
picker), and a component's `only` (the gallery holds images); then Tab
in quotes and over several lines of code (D-315), and the highlight
cached per line with the body read once per keystroke (D-316). Left
from the direction: with a visual editor, the node list and its
round-trip check.
Then, from the direction's shell and theming (D-317): a rail button
toggles its panel, the top bar's collapse button is gone, the trail
starts at the section (`Content / Posts / Editing`), and the Editorial
admin theme ships as a per-account choice beside the color scheme.
Accounts have a name (D-322): what the admin calls the person,
everywhere it shows an account, with the author page's title and then
the username as fallbacks; set on New Account, an account's screen,
Your profile, and the CLI; the dashboard greets by it, and roles show
their labels, not keys (D-323).
Settings became editable (D-324): the owner's settings (name, language,
time zone, homepage, trailing slash, feeds, sitemap) are saved in
`user/data/settings.json` over `config/`; then four Settings screens
(General, Reading, Addresses and Search, System) in a Config panel of
Structure, Settings, Customize, and People, and the file in sections
named for the config files (D-325). People became its own rail section,
between Content and Config (D-326), with Your Profile first; Appearance
is named Themes (D-327).
Then every content type's settings from the admin (D-349): a
collection or taxonomy from `config/content.php` or an extension is
edited on its screen, with only what differs from the code saved in
`user/data/types/{name}`, and **Reset** deleting it; the pages and
authors types stay as their code has them. And every URL path of a type
(D-350): an Addresses panel with each route key's path, checked for the
placeholders it needs. Still to consider for routes: a Routes screen
listing every route, editing redirects, and routes defined in data.
Extracting embedded artwork waited (D-295); it's built as library
artwork (D-581).
Then extensions as a type system (D-378, D-379): plugins (today's
extensions, renamed), themes (known by `vendor/name`), and icon packs
(new, in `user/icons`), each manifest with a `name`, `label`, and
declared `namespace` checked across kinds, and Customize as Themes,
Plugins, and Icon Packs with install placeholders.
Then the Themes screen from the themes sketch (D-381,
`admin-design/blush-themes-screen.html`): cards with a preview drawn
from each theme's declared palette (`theme.json`'s `preview`),
**Activate** saving `theme.active` in `user/data/settings.json`, and
**Delete** for `user/themes` folders the site doesn't use; and its
theme details screen (D-383).
Then the Plugins and Icon Packs screens from the extensions sketch
(D-385, `admin-design/blush-extensions.html`): switches saved in
`user/data/settings.json` (`plugins.disabled`, `icons.disabled`), with
plugins' `requires` enforced at boot (other plugins by `vendor/name`)
and packs that can be turned off (`config/icons.php`); **Delete** for
folder plugins that are off and folder packs; and a details screen for
each (a plugin's requirements, a pack's icons).
Then toasts from the toast sketch (D-387, `admin-design/toast-sketch.html`):
kinds, a countdown bar that holds on hover and focus, stacking, and Undo
where the reverse is exact.
Then the Home sketch (`admin-design/meridian-home.html`, D-537), in
stages: (1) the dashboard of what needs you first and Tools with
actions and the log (D-538 to D-541, built); (2) Site Health in place
of Content Health, with real checks only and a Requirements tab (D-543,
built); (3)
editable Shortcuts, kept with the account's preferences (D-547, built).
Undo on moving to the trash is built (D-525). Smaller admin items
waiting: the admin theme choice (a second
account preference, D-235), objects in forms, autosave, and Pages
and hierarchical terms as a tree (see D-233 to D-237's and D-257's open
items). Later: the admin's dates in the site's date and time
formats where they read as dates, not in compact columns or
pickers (D-446).

Testing the admin on the jtcom trial: create a throwaway administrator
account file in `../blush/storage/accounts/` (an Argon2id hash), drive
the admin with Playwright and Chrome, and delete the account, its
sessions, and anything it created afterwards. ddev syncs files with
Mutagen, so an edit made on the host can reach the container late: test
write conflicts through the API, not by editing files on disk.

### Running goal: a smaller admin (D-505 to D-510)

The built admin under **500 KB of JavaScript** (`public/admin/js/admin.js`)
and **100 KB of CSS** (`public/admin/css/admin.css`), unzipped, as a
standing goal met over time, and maybe lowered later (D-510). On
2026-10-05 they were 748 KB and 165 KB. Every change to the admin
should leave them no larger without a reason, and work in an area is a
chance to share what repeats there (D-509) and drop what's unused.
Measured then: Vue and the router are about 100 KB of the JavaScript,
and about 226 KB is prose (help, notes, messages), for a review pass
once the editor screens land; 116 KB of the CSS is components' scoped
styles (about 20 KB of it the `[data-v-…]` attributes). Loading screens
on demand, for what's loaded at once, is in `open-questions.md`.

### Still to scope

- **Background jobs and the scheduler (D-621; built, D-622):**
  `Blush\Job` (jobs by key, records in the `jobs` area, chunks,
  retries, the schedule), cron's `schedule:run` and the runners
  (worker, admin, after visits), the `jobs:*` and `schedule:list`
  commands, Tools → Jobs with `site.jobs`, Site Health's cron check,
  and Publish and Reindex in the admin as jobs. `EntriesWentLive`
  and the `RecordingQueue` for tests followed (D-623), Site
  Health's fixes as a chunked job (D-624), and Check Again too
  (D-625). The media indexer and the media details check followed
  (D-626). The admin's
  and the webhook's publish read a batch of media too (D-627). Still
  whole, by choice: a full rebuild of the content index, in whatever
  request finds it stale (see `open-questions.md`). The first of the layers AI plugins need
  (D-397), with the HTTP client (D-620).
- **APIs, agents, and headless (discussed 2026-10-03):** a versioned
  content API, API tokens, an MCP server, a headless mode, revisions
  and an activity log, outgoing webhooks, and an image pipeline; see
  `open-questions.md`. Markdown pages and `llms.txt` are done (D-395,
  D-396), and the AI Settings screen with AI crawler rules and a site
  description (D-398, D-399).
  Every content file has a UUIDv7 `id`, with a fix tool in the admin
  and the CLI (built: D-477, D-478, D-480: `content:ids`, Content
  health, `find()` by id and `findPath()` by path), and named by id at
  every boundary: the admin API, previews, and the trash (D-481, D-482).
  Trash is a status, its files left where they are (D-484).
  Decided, next: a
  read-only, opt-in content API at `/api/v1` beside the admin's
  (D-479); its answer shape, bodies, path, and caching are still open.
- **Translations and multilingual sites (D-451):** translation domains
  by `vendor/name` and overrides in `user/lang/{locale}/` that win (done,
  D-454), catalog metadata (D-452) checked by `theme:check`,
  `plugin:check`, and `icon-pack:check` (done, D-454); multilingual
  sites (D-036) as a 2.0.0 goal: translations as suffixed sibling files
  (`about.fr.md`) at `/fr/` URLs (D-455), with the core built (D-456:
  `languages` config, the index, queries, routes, URLs, rendering, and
  export); translated folder names (D-457), components following the
  page's language in templates and Markdown (D-458, D-459), plain files
  and bundles linking as translations (D-460), and `hreflang`
  alternates (D-461) are done, and so are component text, date
  archive titles, menus, and template routes in the page's language
  (D-462 to D-464), and the `time`, `progress`, `meter`, `file`, and
  `video` components' formatting (D-466), and untranslated content
  (D-467 to D-469), and `dir` on a page's `<html>` (D-470, D-471).
  Paused there by the author (2026-10-04). When it's picked up again, in the author's order (D-465): sitemaps and
  then feeds and `llms.txt` per language; translations in the admin
  (uploads, a strings editor) once there's a design; a language
  switcher is on hold.
- **Front-end search:** a JSON index; see `open-questions.md`.
- **Extension kinds, what's left (D-378, D-379):** admin themes as a
  fourth kind on the shared pieces (joining `AdminTheme`, D-317);
  installing from the admin, data-only kinds (icon packs) first, where
  the Install buttons are placeholders now.
  (`plugin:list`, `plugin:check`, and broken plugin manifests listed
  instead of failing discovery are done, D-394, and `plugin:new`,
  D-416. Every local extension lives in `extensions/{vendor}/{name}`,
  with manifests in Composer's shape and `autoload.files`, D-418.) See
  `open-questions.md`.
- **Fields API (D-337; paused, D-348: a baseline, with more design
  work to do before building further; phases 1 to 3 done, D-338 to
  D-340; phase 4: media, D-341, and site settings, D-343, done; theme
  settings, D-342, and accounts, D-344, on hold; set presentation,
  D-345 to D-348):** reusable field sets attached to targets, content types
  first. Phases: (1, done) `Content\Schema` moves
  to `Blush\Field`, field types describe themselves, the `Control`
  vocabulary and `control` key, both definition shapes, the config
  registry fix, and `GET fields/types` driving the admin's definition
  editor and `FieldControl`; (2, done) `FieldSet`, sources, `FieldTarget`,
  sets in type schemas (load checks, cache, index, lint, editor
  groups, JSON Schema, `docs/`); (3, done) Structure → Fields screens
  and writer; (4) media (done, D-341), theme settings (on hold, D-342), site
  settings (done, D-343), and accounts (on hold with the authors work,
  D-344) as later consumers.
- **Profiles (D-351; replaces D-329 to D-333):** accounts,
  profiles, and bylines as three nouns, from the author's profiles
  sketch. (1, done, D-352) Content and routing: the `profiles` kind with a canonical
  `{base}/{slug}`, people fields per type (plural, each with its own
  archive word), per-field lists with `_{field}` intro pages, per-profile
  index pages that fall back to the profile's body, feeds, sitemap,
  export, lint, and the jtcom trial. (2, done, D-353) The admin: Accounts and
  Profiles as two lists under People, the profile screen (where it
  appears), the account's Public profile panel, and the type editor's
  People panel. (3, done, D-369, D-370) The revised sketch: Your Account
  is the account screen on your own row, its cleanups to Accounts, New
  Account, Profiles, and a profile's screen, drawn as the sketch is;
  accounts keep a display name and need an email address. Later:
  profile capabilities, the revised sketch's open list
  (`open-questions.md`), and schema.org `Person` with structured data
  in general.
- **Role capabilities (done, D-359 to D-364):** per-type capabilities
  (`content.{type}.{action}`, `content.*.…` for every type, additive)
  and a role's screen as capability sections, from the author's sketch;
  the Roles list's readouts; seven capabilities for managing accounts
  and roles. Stopped here (2026-10-02): the author is bringing new
  Users and Accounts screens next. Later: the open questions under
  Role capabilities in `open-questions.md`.
- **Home's screens (D-368):** what belongs under Home besides the
  Dashboard and Content health. (1, D-368) The **Calendar**, a month
  of dated entries, was built and then removed: it's for a plugin
  later (D-550). Next maybe: **Publishing** (the cache and a button
  to clear it; the author is interested, not decided). Activity (who
  changed what) is extension territory, not core.
- **Relationships (D-585, planned; refines D-242):** one relation
  model for every link between entries, modeled as database records:
  (1) relations compiled from taxonomies, people fields, and reference
  fields, one index, and the read and query API (built and wired
  in, D-590 to D-592), and the taxonomy kind retired for collections
  and relations, with a migration tool and the admin's Relationships
  section (D-591, D-593, D-594); (2) limits and
  required, creating as typed, reverse archives, rewriting referrers
  (built, D-596);
  (3) the admin's controls and a type's Relationships section (built,
  D-599 to D-601), and people fields made credit relations with one
  archive mechanism (built, D-602); (4) data
  on links and targets of more than one type. Open items in
  `open-questions.md` → Relationships.

Other starting points the author may pick up (none decided):


- **Required directive registration (D-266's direction):** every
  directive is registered (D-533, from PHP); registering one from JSON
  with translations is still open, as are patterns (D-532).
- **Later for components:** captioned quotes and tables, a `<button>`
  component, rich script embeds and an embed refresh command, extension
  views (D-174), more icons and brand logos (see `open-questions.md`).

- Creating a site: `composer create-project` (once the skeleton is on
  Packagist, running `init` afterward; D-218).
- **A global installer (D-165):** a separately installed `blush` command
  (via `composer global require`) that creates
  sites (`blush new mysite`) and, inside a site, runs that site's
  `bin/blush`. Until then, `docs/installation.md` shows the small
  launcher script that finds the nearest `bin/blush` (D-421). Its shape
  (`blush-dev/cli`) and open points are in `open-questions.md`.
- Setup notices beyond storage (D-218): whether a web server rewrite
  check is possible (the welcome page's next steps and setup notes are
  D-419).
- The first look: the welcome page, the skeleton's sample content (its
  `blog/` isn't a content type, so the sample post is a plain page), and
  the default theme.
- Local development: `serve`, DDEV, and theme builds (Vite, D-155).
- The docs' installation guide (`docs/installation.md`) as the script
  for all of it.

---

## M7 (Static export): done

**Removed** 2026-10-04 (D-476): `Blush\Export`, `build`, and
`serve --static` are gone; the URL sources stay as `Routing\UrlSource`
and `SiteUrls` for a plugin to build on. What follows is the record.

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
  the static files, the homepage and `/page/2`, singles, year and month
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
limiting for the webhook (done, D-414), and
`CacheCleared` (done, D-415). To M7: static export can reuse the content version for
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
  (by name, JSON wins, reports shadowed files) (D-032). Since D-631,
  data is JSON only: `YamlParser` is front matter's alone, and the rest
  is gone.
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
  the homepage and home alias, and the page catch-all (`PageRoutes`,
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
  `/user/media` paths, and page bundle files, since removed by D-294),
  `MediaController` and the
  `media` route, `media:publish [--copy]`, and `Response::file()` Range
  support over `LimitedStream`.
- 1.x Markdown rendering: media URLs and image dimensions, absolute
  root-relative links, and lone images as `<figure>`s (D-100).
- PHPBench (`composer bench`) with the generated jtcom-sized site
  (D-101).

Checked against jtcom's real content (served through `Kernel::handle()`
with its 1.x type config and `MediaConfig(url: '/user/media')`): the
homepage and `/page/N`, `/archives/2008` and `/archives/2008/04`,
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

