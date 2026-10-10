# Open questions

Move each item to `decisions.md` once it's answered.

## Needs the author's call

- **"Views" or "templates" for a theme's folder** (discussed
  2026-10-08; kept open by the author). The folder is `views/` and the
  runtime `Blush\View`, but writers and theme authors meet "template":
  front matter `template` (`view` an alias), the template hierarchy,
  `$template`, and `template()` on components and directives.
  CMS theming systems mostly say `templates/`; MVC frameworks say
  views; Symfony and CakePHP moved to `templates/`. The question is
  what "template" means:
  - **Any file a theme draws with:** rename to `templates/`, keep
    `Blush\View` as the runtime.
  - **Only the hierarchy's page-level files** (the recommendation):
    keep `views/` as the umbrella, a file at its root a template and
    subfolders (`layouts/`, `partials/`, `components/`, `directives/`)
    the pieces templates draw with. A split into five top-level
    folders was weighed and judged too crowded.
  - Either way: plugins put their markup in `views/directives/` and
    `views/components/` as themes do (the docs now show `views/`,
    `resources/directives/`, and `resources/components/`, and
    `resources/` is build sources in a theme); the stale "site's
    views" comment in `Component.php` and the "`template()` returns
    another view" wording get fixed.

- **Site Health's check pages** (D-612; the sketch's open points;
  saved for later, the author, 2026-10-08):
  - **Undo** for a fix: what reverses one (ids written, files created,
    renames), and how long it lasts.
  - **Whether fixes are recorded** anywhere beyond the files (a log
    entry, a history screen).
  - **"Did you mean"** for a term or profile name with no file: a
    similarity search from the API, as the pickers' `closest` has.
  - **Fixes for lint problems** by kind (quote a value, use the file
    name's date, remove a key nothing reads, change an older field name
    to the one it's read as), which need a front matter writer for each.
  - **Whether Check Again runs only its check.** It checks every
    content and media file now.

- **A full content index rebuild in a request** (raised 2026-10-08,
  after D-627; not built, by the author's call). A full rebuild (no
  index, or a fingerprint change from editing a content type or the
  timezone or locale) still parses every entry in whatever request finds
  it: the admin's type and settings saves (`TypeEditController`,
  `SettingsEditController`), Reindex, Publish, or the first page
  request. Measured on the jtcom trial: 1,248 entries in about 0.5
  seconds (about 0.4 ms each), so a 30-second limit is reached only
  around 50,000 to 70,000 entries. It can't be chunked as media was
  (D-626): an entry not read yet would be missing from the live site,
  and the old index can't stand in, since a rebuild means its records
  may name types that changed. Doing it safely means building the new
  index on the side and switching when it's complete, which changes
  `ContentIndex` (every storage driver would need to stage one, D-485).
  Leaning: design it with the data layer (D-606), when the index's
  storage is reworked anyway; until then, a very large site rebuilds
  with `content:index --full` on the command line.
- **The data layer** (D-606; steps 1 to 3 built, D-642 to D-657). Settled: three
  layers (drivers over records, a fluent query compiled per driver,
  repositories), data mapper, every area, id-keyed writes, the full
  query language, drivers from core and Composer, and the names
  (D-643: `Record` with `values`, tables, `RecordStore`, one
  `RecordQuery`, plural-noun repositories), and schemas and moving
  between drivers (D-644: drivers derive their schema from types, a
  JSON `values` column with generated indexed columns, no hand-written
  migrations, `storage:sync` and `storage:copy`), and sessions and jobs
  keeping narrow contracts (D-645). Step 6 planned (D-668). Open:
  - **The Redirects screen, what's left** (built in D-686, from
    `meridian-redirects.html`; the sketch's open questions):
    - **Short links that end:** an end date on a row (`/sale` for a
      while) would need the site to check a clock, and a compiled route
      table to be written again when one passes. Left out.
    - **Keeping a path on purpose:** a typed path that's a live page's
      address always becomes the page. Someone who wants the literal
      path (so the redirect stays put when the page moves) has no way
      to say so. Maybe a choice in the To box's list.
    - **Problems on a big site:** problems are worked out on each load
      (the author's choice), reading every live entry's address once a
      request (about half a second for the trial site's 1,253 entries).
      On a much larger site they may belong to Site Health, with the
      screen reading its last report.
    - **Importing:** rows have `via: import`, but nothing imports yet
      (CSV, `.htaccess`, another system's export; maybe with
      `Blush\Transfer`, D-685, or a plugin).
  - **Generating renditions** (the author, 2026-10-09, D-674): a
    feature for site owners to make an image's other renditions (sizes,
    WebP or AVIF copies) on demand or on upload, recorded in its
    `renditions`. Nothing built; to plan with media work. Options
    discussed 2026-10-09 (nothing decided):
    - **Engines:** GD (WebP almost everywhere, AVIF only when built
      with libavif; drops ICC profiles; about 100 MB to decode a 24 MP
      JPEG), Imagick (WebP, usually AVIF, keeps profiles; often missing
      on cheap hosts, capped by `policy.xml`), libvips through
      `php-vips` (fast, little memory, rare on shared hosts), command
      line tools (`cwebp`, `avifenc`, `vips`; needs `proc_open`), or a
      hosted service (imgproxy, a CDN's) as a plugin. Support varies by
      host (on the author's machine GD lacks AVIF, Imagick has AVIF,
      WebP, JXL, and HEIC), so: an engine interface on the Type enum +
      Registry pattern, GD and Imagick built in, others from plugins,
      each engine saying which formats it writes, and the admin offering
      only those.
    - **When:** during the upload request (simple, but AVIF encodes
      slowly; several sizes of a big JPEG take seconds); a background
      job after upload (D-621; the best fit); on first request (lets
      anyone make the server encode unless limited to named sizes or
      signed URLs; leaning no); and a command plus a Site Health check,
      in the style of `media:sizes`, for existing images, whichever is
      picked.
    - **What:** format copies of the original only, or every size in
      every format (5 sizes × 3 formats is 15 files an upload). Either
      way, a per-kind setting beside the upload rules on the Media
      settings screen (D-406): formats to also make, and a quality.
    - **Serving:** `<picture>` with AVIF and WebP `<source>`s built from
      `renditions`, the original as the fallback (works with the page
      cache and CDNs; leaning this), or content negotiation on `Accept`
      with `Vary: Accept` (poor with the page cache and many CDNs).
    - **Edge cases:** apply EXIF orientation before encoding; strip GPS
      from copies; keep color profiles; never upscale; don't keep a copy
      larger than its original (small or simple PNGs); animated GIFs
      and transparent PNGs; a cap on the image size an engine decodes;
      and, with Imagick, HEIC uploads (iPhone photos) turned into JPEGs.
    - **Suggested, not agreed:** the engine interface with GD and
      Imagick, copies made by a job after upload, formats per kind in
      Media settings, the command and Site Health check for older
      images, and `<picture>` on the site.
  - **Composer drivers:** how they're found before plugins load.
    Leaning: a key in the package's `composer.json` `extra`, read with
    the installed packages and cached, and a driver named in
    `config/storage.php` or `.env`.
  - **Writes that touch several records** (a rename filing referrers'
    ids, D-596): transactions on a database; on files, best effort with
    a journal, or a documented limit. Now concrete (D-653): the content
    store's transactions take the lock, but the writer's file writes
    (and the referrers it files) aren't put back when one fails.
  - **The filesystem driver's index:** settled by D-661 (it keeps
    `PhpIndex` alone; SQLite is a driver, and large sites use it).
  - **Indexing large flat-file sites** (measured 2026-10-08 on
    `../ten-thousand`, 10,384 entries, 43 MB of Markdown;
    `JtcomSizedSite::build()` at scale 9): a full index takes 4.3 s and
    395 MB at its peak, past PHP's default 128 MB (it fails in
    `RelationGraph`); `content.php` is 65 MB, and every page request
    fails at 128 MB loading it (works with 1 GB). D-661: sites that size
    use the SQLite driver, with an admin warning planned. Open: at what
    size the warning shows, and whether indexing should still be made
    to stream within 128 MB so such a site can be indexed once to copy
    it to SQLite (`storage:copy`).
  - **The `entries` table's shape, later** (the author, 2026-10-09,
    reading the tables laid out after D-665; to decide later, nothing
    built):
    - **Translations as refs:** the translation system may move onto
      entry relationships (refs), so `original_id` would no longer be
      needed as a column.
    - **`parent_id` as a relationship:** a parent is likely just another
      relationship, so `parent_id` may fold into refs too.
    - **Only registered fields stored:** `fields` would keep only what's
      registered (a type's declared fields), not any front matter key.
    - Also raised then, unanswered: uniqueness enforced by the database
      (a unique index on an entry's place, unique table keys) and
      D-649's composite indexes on refs, which are checked in PHP and
      single-column today.
  - **The SQLite driver at scale, what's left** (after D-667): lists
    of every entry at once (the sitemap, `llms.txt`, `content:list`)
    hydrate every entry and peak near or past 128 MB at 10,000 entries
    on either driver, so they need paging or a lighter read (`only()`);
    SQL ordering folds text through `blush_fold` (a PHP function) on
    every row, which costs at scale and isn't needed for dates; term
    counts read every ref (13 ms on the benchmark site, 4 on files).
    - **A post's Markdown copy loads something site-wide** (found
      2026-10-09, not looked into): `/archives/…/post-8134.md` on
      `../ten-thousand` takes 630 ms and 134 MB on SQLite (990 ms on
      files), past 128 MB, though it should need one entry. Look at
      what `MarkdownController` builds first.
    - **Measured on `../ten-thousand`, 2026-10-09** (a fresh PHP
      process per page, page cache off, CLI without opcache): ordinary
      pages on SQLite take 137 to 286 ms and 26 MB; on files 566 to 671
      ms and 432 MB (the 65 MB PHP index loaded each time; opcache would
      lower that under a web server), and files can't serve the home page
      at 128 MB. The sitemap (2,088 ms, 120 MB on SQLite), `llms.txt`
      (777 ms, 120 MB), and a post's Markdown copy run out of memory at
      128 MB on either driver.
  - **Duplicating should answer the same on every driver** (raised
    2026-10-09, after D-664; the author: make copying handle the same,
    decide later). Copying `spring` as `spring` gives `spring-2` on
    SQLite (`RecordContentWriter` checks slugs among siblings), but a
    second `spring` on files (`FilesystemWriter::duplicate()` checks only
    whether the new file name exists, and a dated pattern names a free
    one). To decide: which rule both follow (likely the free slug among
    siblings), and whether the conformance suite pins it.
  - **The skeleton's `2.x` branch** has a stale `config/markdown.php`
    (it names `MarkdownConfig::DEFAULT_EXTENSIONS`, gone since D-492),
    so a fresh install from it fails; the trial site's copy is current.
  - **Whether DDEV's PHP has `pdo_sqlite` with JSON** (`ddev exec php
    -m`), for trying the SQLite driver on the trial site; the CLI here
    does.
  - **Publishing** a database-backed site (D-131 pulls `user/` with
    git; D-486's open point).
  - **`content:lint` in two parts** (3d's plan, left from D-654): the
    linter reads files fresh, by design; which of its checks are about
    an entry (field values, limits, parents, addresses) and which about
    a file (parse errors, aliases, prefixes, formats, two files for one
    entry) is clearer with a second driver to check entries for. Until
    then it's one tool, and the editor asks it about an entry's file.
  - **`Link` and `Ref`:** D-649 renamed `Link` to `Ref`; the content
    side still uses `Link` (source and target types included) for what
    `EntryLinks` answers, and `LinkBuilder` and the graph stay inside
    the filesystem driver's index, where they build its refs. Leaning:
    keep `Link` as content's view of a ref with its types, and rename
    the driver's builder when the index is reworked (step 4).

- **One profile system** (the author, 2026-10-08, while building
  D-657: "I think I want to go to a single profile system down the
  road"). Nothing to build yet; to discuss: what "single" replaces
  (profiles as one type for every relation, accounts and profiles as
  one, or credit relations sharing one archive), and what it means for
  relation archive pages (D-602, D-657) and bylines (D-351).

- **A front-end interactivity API** (discussed 2026-10-07; nothing to
  build yet). Developers will need a way to build interactive sites.
  The direction discussed: no client framework (Preact or otherwise)
  as the public API; directives over server-rendered HTML, with
  signals inside.
  - **Why not components in JS:** a Preact (or Vue, Svelte, Solid)
    component is a second copy of a PHP template. Either it renders
    only on the client (content late or missing, and missing from the
    Markdown copies and `llms.txt`), or Blush needs Node at render time
    (against render anywhere), or two templates are kept in step by
    hand. Themes would also need a build step, where today a theme is a
    folder.
  - **Sketch:** a region names a store by `vendor/name` (D-378) and
    takes local context. Elements bind to it with attributes
    (`data-blush-on-click="actions.toggle"`,
    `data-blush-text="state.count"`, `data-blush-bind-hidden="!context.open"`).
    Stores are ES modules registered by plugins and themes. PHP seeds
    the starting state, which is written to the page as JSON with the
    head and footer (D-577, D-578).
  - **PHP runs the same directives at render time,** so the first
    paint is right with no flash. That means a directive's value is a
    small expression language (paths, `!`, perhaps named getters),
    never arbitrary JS. Both PHP and JS can evaluate it, and it's
    CSP-safe (no `eval`).
  - **Loading:** stores and the runtime are scripts by handle (D-569
    to D-573), asked for by directives, components, and templates, so
    a page loads them only when it uses them. Plain ES modules with an
    import map, no bundler for themes.
  - **Runtime internals:** `@preact/signals-core` (about 1.5 KB) with
    Blush's own directive walker (leaning), full Preact for its list
    diffing, or a hand-written signal. Kept out of the public API so it
    can be swapped.
  - **Alongside it:** custom elements for self-contained widgets (the
    audio and video players are natural ones); server round trips in
    the htmx or Datastar style for search, forms, and pagination,
    perhaps a later "server actions" layer.
  - **Not chosen:** Alpine (`eval`-style expressions, no server-side
    pass, another project's API); compiled frameworks (a build step
    for site developers). Vue stays in the admin.
  - **Open:** the attribute prefix and directive set; the expression
    language; the runtime; how stores are registered in PHP and JS;
    whether client navigation by region comes later (a player that
    keeps playing across pages is the likely first case).

- **View transitions for themes** (the author, 2026-10-07: "we should
  probably have native view transition support for themes," then: on
  hold, "but we'll definitely add"). Whether is settled; how isn't, and
  nothing is built until it's picked up. Cross-page
  view transitions are CSS only (`@view-transition { navigation: auto; }`)
  and need no client router, which pairs with the interactivity API
  above. To settle: whether a theme turns them on in `theme.json`
  (core adding the rule) or in its own CSS; a way to name elements
  that carry across pages (`view-transition-name`) from templates and
  components, such as an entry's title or image from an archive to the
  entry; honoring `prefers-reduced-motion` by default; and whether the
  default theme uses them.

- **Groups and relationships in the content model** (discussed
  2026-10-06; the author wants to dig deeper on the content model
  later). One operation under `termCounts()`, a proposed `termStats()`,
  `credited()`, and jtcom's post archives: take what a query finds,
  group it by something entries point to, and report on each group.
  - **What entries point to:** terms, people (credit relations, D-602), a parent,
    dates (year, month), references (reference fields, on hold with the
    Fields API, D-348), translations (`translation_of`), mentions,
    media used, and the type, language, or status.
  - **What a group reports:** how many, first and latest published,
    last updated, perhaps its newest entry, and the entry it's about
    (the term, profile, or parent) when there is one.
  - **Sketch:** `query()->type('post')->groups('category')`,
    `->groups('authors')` (who writes the site), `->groups('year')`
    (archives with counts), `->groups('parent')`. `termCounts()` stays
    as the counts of `groups($taxonomy)`; `credited('post')` would be
    `groups('authors')` with the profiles loaded. All from the index.
  - **The other direction:** what points at an entry (recipes using an
    ingredient, entries linking to a page, where media is used), a
    filter such as `query()->whereReferences($entry)`.
  - **Open:** the name (`groups()`, `tally()`, `facets()`); whether
    `credited()` exists as a shortcut; which groupings first (taxonomy,
    credit relation, parent, year/month proposed); references when the
    Fields API resumes.

- **Template naming and the hierarchy, as a whole** (the author,
  2026-10-06): to evaluate later, after `single-{kind}` and
  `collection-{kind}` (D-561) went in as they are.

- **Template lookup order** (the author, 2026-10-06): the site/app
  first, then the theme chain, then plugin views (not built yet), then
  a class's `render()`.

- **PHP APIs met building Second Proof** (discussed 2026-10-06). The
  principle (the author): no container used as a service locator and
  nothing done in global scope, while keeping templates (and the PHP
  behind them) pleasant to write. Providers are the one place that
  wires with the container; view objects and components get their
  services injected. Only the slice the theme used; not an audit.
  - **Providers:** registering is lookups
    (`$this->container->get(ComponentRegistry::class)->register(…)`).
    Declarative constants like `SINGLETONS` and `TAGS`
    (`COMPONENTS`, `DIRECTIVES`, `ICONS`), with providers knowing their
    extension (below), would leave most providers without `boot()`.
  - **Components:** props and services share the constructor with
    nothing marking which is which (the docs say props are public
    parameters, yet Second Proof's non-public `$taxonomy` works as one);
    mark props explicitly (an attribute or a props object). The page's
    context is attached after construction, so `ClosestMatch` computes
    lazily with a flag; give context at construction or a "compute
    once" hook. View paths: below.
  - **Content:** `terms` is three shapes (`$entry->terms` slugs by
    taxonomy, `$entry->terms('x')` slugs, `$template->terms($entry, 'x')`
    entries). `summary()` is Markdown or `null` and `excerpt()` HTML,
    which the names don't say. `isPublished()`, `isRoutable()`,
    `isListed()`: explained in their doc comments and
    `docs/extending.md` (D-560).
    `field()` returns `mixed` (typed access waits on the Fields API,
    D-348). `->get()->all()` for an array (`query()->type()` takes the
    type object now, D-557). `Paginator` mixes `->page` with
    `->pages()` and `->total()`.
  - **Routes and the head:** route names built as strings
    (`"{$type->name}.{$field}.single.feed"`) and `route()` throws, so
    themes need `try`; URL methods (`feedUrl($type)`) should cover what
    themes need. `head()->remove()` takes built string keys
    (`'style:' . $href`).
  - **Taxonomy queries** (from reviewing Second Proof's `src/`):
    top-level terms are `whereParent(null)` now (D-562); `termCounts()` doesn't say whether a parent counts
    its children's entries (it doesn't; documented, D-557); no newest entry per term (one query per
    term). One term-statistics call (count, latest date, children)
    would replace most of `Topics`.
  - **Profiles credited by a type:** "who writes the site" takes a
    `whereAuthor()` query per profile; a query for the profiles a
    type's entries credit would do it in one.
  - **`search()`** matches titles and file paths, not text; the name
    suggests full text. Documented (D-557); renaming it is still open.
  - **The component shape:** every class component fetches in its
    constructor, exposes a property, and has `shouldRender()` check it
    for empty. Blush could own the pattern (a typed data property it
    checks, or a short "render only if" declaration); with `render()`
    optional (below), most would shrink to a constructor. Second Proof
    stands in with its own abstract `ThemeComponent` (a `VIEW`
    constant and one `render()`).

- **Template API gaps found building Second Proof** (discussed
  2026-10-06, from D-556). What took extra code, by how much it would
  save:
  1. **Named content lookups in templates.** Six of the theme's classes
     only fetch content. Read-only, named calls for the common cases
     (open queries stay allowed; these are the easy path): `$template->previous($entry)` /
     `next($entry)` backed by a repository method (`Adjacent` loads
     every post of the type to find two), `recent('post', 3)`,
     `page('about')` (like `profile('jane')`), `profiles()`,
     `termCounts('category')`.
  2. **Translations with HTML in them:** built (D-559): `raw()` marks
     a value, and `t()` keeps it as HTML while escaping the rest.
  3. **Lists as sentences:** `$template->list($items)` ("A, B, and C"
     by `IntlListFormatter` in the page's locale), maybe with a form
     for linked people or terms.
  4. **Plain-text excerpts:** `html_entity_decode(trim(strip_tags(
     $entry->excerpt(36))))`, six times; `$entry->excerptText(36)`.
     Maybe subtitle-else-summary too.
  5. **Every term of an entry** (waits for the larger template work, the author, 2026-10-06): `$template->terms($entry)` with no
     taxonomy, instead of looping over `array_keys($entry->terms)`.
  6. **`<time>` tags:** `$template->timeTag($date)`, the datetime
     attribute and the site's format, instead of building it by hand.
  7. **Routes that may not exist:** the feed link needs `try`/`catch`.
     `$template->routeOr('home.feed')` returning `''`, or
     `$template->feedUrl()` for the site, a type, or a person.
  8. **The request path:** an error page can't see the address asked
     for (Second Proof's script fills it in); `$template->path()`.
  9. **`head()`:** built: `preload()` and `theme.json`'s `preload`
     (D-558), `inlineScript()` (D-560).

  Related entries: component view paths and providers that know their
  extension (below).

  **Views free of logic** (discussed 2026-10-06). The goal (the
  author): theme authors write HTML with `$template->…` calls, a few
  `if`s and loops, and no custom code. Second Proof's views have about
  150 lines of PHP before their HTML, in these groups:
  1. **HTML built in strings:** links, `<time>` tags,
     `str_replace('{names}', …)`, lists joined by a part. Wanted: a
     linked title, linked titles as a sentence, a byline that can leave
     someone out (`except:`), a time tag, translations with `raw()`
     params (gap 2 above).
  2. **Fallback chains:** title else slug; heading else the type's
     plural else the site's name; subtitle else summary; the landing
     page's title else the site's description. Wanted: `$title` never
     empty, a standfirst on the entry, a listing's heading and intro on
     the page.
  3. **Facts about the page:** counts, page number, first page or not,
     the person on a profile page and their feed, a type's listing URL,
     a page's siblings (which also ends the three `try`/`catch` blocks
     around routes).
  4. **Templates choosing templates** (the author, 2026-10-06: not by
     date; discuss templates chosen by the type's kind instead): `home.php` falls back to the
     page layout, and `single.php` does for undated entries. The
     hierarchy should choose (`home-page` / `home-collection`, a page
     template for undated types), so a view never includes another
     view and returns.
  5. **Part arguments and defaults** (`$meta ??= 'default'`): parts that
     take arguments become template components (`$component->prop()`),
     documented as the way.
  6. **Directive templates with logic** (the core callout naming its
     kind without a title: no, the author, 2026-10-06): callout maps its variant to a
     kind and falls back to a translated title; the class should give
     `kind()` and a `heading()` that falls back to the kind's name.
  7. **Menus:** built, `$item->ariaCurrent()` (D-560).
  8. **Head setup in the layout:** the font preload loop and the inline
     script; `head()` calls or a `fonts` / `preload` list in
     `theme.json`.
  9. **One-offs:** a featured image (alt text, left out when the body
     already shows it), the request path and the site's host, cache
     keys made from the entry.

  **Guidance for views, not a rule** (the author: don't disallow
  querying and such; make proper APIs and a good developer experience
  that discourage logic, and point to components or another method
  where it makes sense). The aim is that a view needs only HTML,
  `$view->…` calls, `if`, `foreach`, and printing values, because the
  APIs make that the easy path, not because anything else is refused.
  Docs show that style, and say where logic belongs instead: a
  component (with a class when it needs data), a directive, or the
  site's or theme's provider. Whether `theme:check --strict` should
  ever *note* (never fail) closures, `try`, or HTML built in strings in
  a view is open.

  **Where the calls live** (the author: the naming of every method on
  `$template` needs careful shaping, or more could be passed to views).
  Two directions:
  - **Everything on `$template`:** one place to look, but it grows
    into a large object whose names have to say what they're about
    (`$template->link($entry)`, `$template->byline($entry)`).
  - **Behavior on what it's about:** `$entry->link()`,
    `$entry->byline()`, `$page->heading`, `$page->total()`,
    `$item->ariaCurrent()`, with `$template` kept for rendering
    (`layout`, `include`, `component`, `t`, `asset`, `head`, `cache`).
    Shorter names that read as English. But `Entry` is content, and
    links, dates, and people need the router, the locale, and the
    repository; so views would get view objects wrapping the content
    (an entry view over `Entry`), keeping `Entry` free of rendering.
    Also: what a view object costs in a listing of hundreds.

  Either way, settle the names together as one API before building
  any of the gaps above.

  **One variable, typed by the kind of page** (the author prefers the
  second direction, with view objects, but without theme authors
  learning many variable names). Proposed: a view gets one variable,
  and everything is reached through it, so autocomplete shows the rest:
  `$view->entry->byline()`, `$view->entries`, `$view->page->pager()`,
  `$view->site`, `$view->menu('primary')`. The only names an author
  picks are their own loop variables. A view marks what it draws with
  one line, `/** @var Blush\View\Listing $view */`: the object is typed
  per kind of page (a single has `->entry`, a listing `->entries` and
  `->pager()`, an error page `->status` and `->path`; every page has
  `->site`, menus, `t()`, `asset()`), so nothing is "there but `null`
  here", and `theme:check` can compare a view's marked type against
  the hierarchy. Costs: longer lines (`$view->entry->title`; an author
  may still write `$entry = $view->entry;`); it decides `$template`
  versus `$view` (D-158, above); components and directives would reach
  theirs the same way (`$view->component`) or keep their own variable.

  **How others do it** (background, from memory, not checked against
  their current docs):
  - **Hugo:** one root, `.`, the current page (`.Title`, `.Content`,
    `.Pages`, `.Paginator`, `.Site`), with `.Kind` (home, section,
    page, taxonomy, term) and a hierarchy that picks by kind. The
    closest precedent; untyped, so authors learn it from docs.
  - **Kirby** (flat files, PHP templates): the same few variables
    everywhere (`$page`, `$site`, `$kirby`, `$pages`), behavior on
    objects with chained fields (`$page->text()->excerpt(50)`); a
    controller beside a template passes more, which brings back
    "which variables does this one have?"
  - **Ghost:** helpers that print finished HTML (`{{authors separator=", "}}`,
    `{{tags separator=" and "}}`, `{{excerpt words="36"}}`,
    `{{date format="…"}}`), context blocks (`{{#post}}`). Nearly every
    piece Second Proof built by hand is one helper.
  - **Shopify's Liquid:** globals plus an object per kind of template
    (`product`), with docs listing what each has; filters for
    formatting.
  - **Twig:** variables by name, a global `app`; since 3.13 a
    `{% types %}` tag declares the variables a template expects.
  - **Blade:** components declare inputs with defaults
    (`@props(['type' => 'info'])`); view composers attach data by view
    name.
  - **Craft, Statamic:** queries in templates
    (`craft.entries().section('blog').limit(3)`, `{{ collection:blog }}`).

  To take: Hugo's shape (one root typed by kind), Kirby's behavior on
  objects, Ghost's calls that print finished HTML, a one-line
  declaration of what a view draws (Twig's `types`, Blade's `@props`;
  our `@var`), and queries allowed (as Craft and Statamic do) with
  named lookups as the easier path.

- **Components' views** (discussed 2026-10-06): settled by D-563 more
  simply than first proposed. A component's template is looked for in
  the site's views, then the theme chain's, then its class's `render()`,
  which is now optional: themes' components need none, plugins' and
  restylable site components keep their default there. Still open: the
  same for directives from plugins and the site, and whether `view()`
  takes a path relative to something (plugins still write
  `__DIR__ . '/../views/…'`).

  **Provider constants** (`COMPONENTS`, `DIRECTIVES`, `ICONS`, in the
  style of `SINGLETONS` and `TAGS`): on hold (the author, 2026-10-06);
  providers are framework-level, and this work is CMS-level. Take up
  with providers that know their extension.

  **Providers that know their extension** (discussed 2026-10-06). A
  theme's or plugin's provider is registered by class name and gets
  only the container, so it types its own namespace
  (`$components->register('second-proof/letterhead', …)`), repeating
  `theme.json` with nothing checking the two agree. Proposed: Blush
  hands an extension's provider its extension (name, namespace,
  folder) when it registers it, so registration fills in the
  namespace:

  ```php
  $this->components([
  	'letterhead' => View\Letterhead::class,
  	'writers'    => View\Writers::class
  ]);
  ```

  Paths in providers (jtcom's `dirname(__DIR__) . '/resources/svg/icon'`
  for its icons) become relative to the extension's folder. And knowing
  which extension registered each component answers "how the owner is
  found" above. Open:
  - **Another extension's namespace:** jtcom-blade registers `jtcom/*`
    components. Allowed, or refused?
  - **Shape:** helpers on `ServiceProvider` (`components()`,
    `directives()`, `icons()`), or an `ExtensionProvider` base for
    themes and plugins.

- **A pull quote directive** (discussed 2026-10-06, from D-556). A
  pull quote and a quote are two different things (the author):
  - **Pull quote:** a line repeated from the page it sits on, set large
    as a visual hook. It never takes a `cite`, since its source is the
    page itself. It may take an optional attribution label (who said
    it, in an interview or a piece with several voices). It's hidden
    from screen readers (`aria-hidden="true"`, nothing focusable inside),
    since the line is already in the text, and isn't a `<blockquote>`.
  - **Quote:** words from somewhere else: `<blockquote>`, an optional
    `cite` URL, attribution in a `<figcaption>`; always exposed.

  Proposed: a core `pullquote` directive (content says what it is, so
  every theme draws it, D-532), e.g. `:::pullquote[Ines Okafor]`, its
  width from bleed. Open:
  - Whether Second Proof's `pull` figure variant stays as a large quote
    style (it's really a quote: `<figure>`, `<blockquote>`,
    `<figcaption>`) or goes once the directive exists. The design's
    "Every Element" page mixes the two.
  - Whether the editor warns when a pull quote's text isn't in the
    entry.
  - Separately, decorative quotation marks drawn with CSS `content`
    should have empty alternative text (`content: "\201C" / ""`) so
    screen readers don't announce them; Second Proof's is fixed.

- **Second Proof, what's left** (D-556). The design shows things
  Blush can't do yet; the theme leaves them out:
  - **Get It by Email** in the footer: no email subscriptions.
  - **The "where" line** ("Published most weeks from Chicago") and
    the footer's "in Chicago": no site setting for a place or a
    rhythm. Theme settings could hold them, but the Fields API is
    paused (D-348).
  - **The feed page:** the design's `/feed` is the RSS file styled for
    a browser, which needs an XSLT or CSS stylesheet on the feed.
  - **Follow a topic by feed:** terms have no feeds of their own.
  - **First names in lists** ("by Ines and Theo"): profiles have no
    short name.
  - **"Tell us which link"** on the 404 page: no contact address or
    page the theme can find.
  - **Shipping it:** move it into the framework (`resources/themes/`)
    or keep it as its own package. (`theme:check` on an inactive theme
    now runs its provider, D-557.)

- **Directives and components** (D-532, built in D-533). Settled: themes
  can't register directives, writers see "Blocks", and every directive is
  a registered class that declares its kind (D-534). What's left:
  - **Patterns:** a theme's (or plugin's) named arrangement of
    directives, inserted into content as plain Markdown. Proposed:
    copied in and forgotten, never a live reference, which would bring
    the theme dependency back.

- **`$view` in place of `$template`** (D-158, discussed 2026-10-06).
  Inside a file in `views/`, `$template` reads as a second concept:
  everything else a theme developer meets says "view" (the `views/`
  folder, view names, `Views`, `theme:why <view>`, `ViewException`).
  D-158 named it after the `Template` class. Leaning toward `$view`
  (Symfony's old PHP engine used it), keeping D-158's point: no `$this`,
  explicit, typed with `@var`. A data key or prop named `view` would be
  reserved, as `template` is now. Front matter's `template` and the
  template hierarchy are set aside for later. Undecided, the class names:
  - **Keep `Template`:** the least churn, but `@var Template $view`
    shows the mismatch.
  - **`View`:** the variable and class match, but it sits a letter away
    from `Views` (the chain's renderer).
  - **`View`, with `Views` renamed** (e.g. `ViewRenderer`): each name
    has one meaning; a wider rename.

  Either way it touches the default theme, the test views, `docs/`,
  `theming.md`, and the `jtcom-trial` theme, and supersedes D-158.

- **Global helper functions** (D-106, D-504): `e()`, `attr()`, `url()`,
  `js()`, `css()`, and `raw()` are global and unguarded, so Blush can't
  share a site with a library that defines its own, such as
  `illuminate/support` (and so `illuminate/view`, for a Blade adapter).
  The author doesn't want pluggable functions (`function_exists()`
  guards, tried as D-503) and wonders whether to avoid global functions
  altogether. The options:
  - Methods on `$template` (`$template->e($title)`): no global functions
    at all; matches how templates reach everything else; longer to type,
    and every template changes.
  - Namespaced functions (`Blush\View\e()`): short calls, but a
    `use function` line in every template.
  - Variables the PHP engine passes in (`$e($title)`): names like `$url`
    clash with template data.
  - Keep them global and unguarded (today): a Blade adapter would need a
    standalone compiler, such as BladeOne.

- **Profiles** (D-351 to D-353, D-369): who may edit their own profile
  and who may edit anyone's (capabilities the roles don't have yet; the
  revised sketch calls it the first capability that depends on the
  row's identity rather than its type); whether Profiles shows bylines
  per field; **Import from accounts** (profiles for accounts without
  one, in the first sketch, gone from the revised one), not built.
  Account fields (the Fields API's `account` target, D-344) still wait.
  The revised sketch's own open list, D-369:
  - Whether a profile is a taxonomy-kind type or a third kind: it's
    public prose, so it probably wants pending changes (moot while the
    admin has none, D-233).
  - What a byline shows for an entry whose author has no profile (an
    entry credits profiles, never accounts, so today it shows none,
    though the account has a display name, D-370).
- **Sign-ups and community sites** (discussed 2026-10-07, after D-605;
  nothing to build until the author says so). The author plans open
  sign-ups, and wants to think about community sites built on Blush,
  such as an open forum or a social site, where every member has a
  profile. Today only `accounts.edit` links or creates a profile, your
  own included (D-373), and nothing creates one on its own.
  - **Who gets a profile:** a draft profile for every new account
    suits a forum or social site, where every member is a public
    person, but not a blog with open sign-ups, where most accounts
    never write. Drafts would pile up in `user/content/profiles`, in
    the Profiles list, its counts, and Site Health, and an open
    sign-up form would let bots write content files. So it's a site
    setting, not core behavior. Leaning: **Create a profile for new
    accounts** on Settings → Accounts: *Never*, *For roles that write*
    (the default), or *Always* (what a forum or social site, or its
    plugin, sets). Perhaps also when an account is first credited on
    an entry.
  - **Draft or published:** a blog's writer can publish a draft when
    ready; a forum member's profile has to be live when they first
    post, or their name links nowhere. So perhaps the setting also
    names the new profile's status.
  - **Your own profile is yours** (the first step, needed by everything
    else): any account, the member included (D-365), can create its own
    profile from Your Account (one click, the name filled in, a draft),
    then edit and publish it, whatever its role. Overlaps the Profiles
    item above (who may edit their own profile).
  - **Slugs:** a profile made for a new account never takes, or links
    to, an existing guest profile's slug (`jane` taken means `jane-2`,
    or the sign-up asks). Locked profiles (D-605) are never claimable.
  - **An `AccountCreated` event,** so a plugin can make the profile,
    fill it from the sign-up form, or do more.
  - **The sign-up page** (see Signing up, under Later milestones) may
    offer an optional public name step that makes the profile.
  - **Community features** (threads, replies, feeds, follows,
    moderation) look like plugins, as the Calendar became (D-550):
    core supplies accounts, profiles, sign-ups, and events.
  - **Scale and spam:** thousands of members, each with a profile file
    and many posts, strain flat files; that's what storage drivers per
    area are for (D-485, D-486), so such a site can keep accounts and
    content in a database. Open sign-ups also need D-518's open list
    (email confirmation, approval, allowed domains, spam protection).
  - **Suggested order:** your own profile is yours; the new-account
    profile setting (when, and its status); the `AccountCreated`
    event; the sign-up page; community features as plugins.
  - Whether `/` belongs to list search: the entries list's search shows
    and answers it, and no document names it, beside ⌘K.
  - Whether a profile can be merged into another: two guest profiles
    for one person is the predictable mess, and there's no screen for
    it.
  - A fourth rail section: the direction's §6 names three; Users is the
    admin's fourth (D-326), so the diagram in §6 is one short.
- **Role capabilities** (D-359), from the sketch's own list and what
  building it raised:
  - Whether open sections persist per account (the sketch: on the
    account record, not browser storage); they don't now.
  - What an account's screen shows of its roles' capabilities (the
    Roles list's readouts, D-361, or a sentence).
  - Whether taxonomies take the same actions (a term has no pending
    changes, so "Publish anyone's" reads oddly for a tag); they do now.
  - Whether a section row wants a count beside its sentence.
  - A read-only `view` capability (the sketch's), which needs a
    read-only editor first.
- **Front-end search** (raised 2026-10-03; the author wants to pursue
  it): the plan in `architecture.md` (FTS5) needs the SQLite driver
  and leaves static copies of the site out. The idea instead: a
  JSON search index as a route (`/search.json`, built the way feeds
  are, and listed in the site's URLs as `FeedSiteUrls` lists feeds,
  D-476), searched in the
  browser by a theme's template and a small script, with `/search?q=`
  answered on the server from the same records when PHP serves the
  site; wired only when enabled. To settle:
  - Body text: the index keeps metadata, not bodies, and
    `Query::search()` matches only the title and source path. Index
    stripped body text (built at reindex or cached by content version),
    or only titles, excerpts, headings, and terms?
  - Size: one file (several MB with full bodies on a jtcom-sized site),
    one per type, or a chunked index (Pagefind's approach: a query
    loads only the chunks its words need)?
  - Pagefind itself (a third-party tool that indexes the exported HTML
    after export): it fits a static export (a plugin's job since D-476)
    but not a site PHP serves,
    and it's not in-house (D-006). Borrow its chunking idea, not the
    tool?
  - The browser-side matcher: in-house, or a library behind a Blush
    interface?
  - Only public, published entries, never drafts, private, or
    future-dated ones (`Query`'s visibility filters).
  - Whether FTS5 search is planned for sites on the SQLite driver
    (D-661: no SQLite index beside files).
  - Its records could be the content API's (below).
- **Data types: types without a body** (discussed 2026-10-06; leaning,
  not decided): types that are data more than prose (testimonials,
  price rows, events, links) don't need the writing surface. Today
  every entry is front matter and a Markdown body (D-501), the body
  may be empty, and the editor always opens on the writing area.
  `urls: false`, `public: false`, `sitemap`, `feed`, and `llms` already
  let a type exist without pages; `user/data` files are settings-style
  data, not entries (no id, status, list, or API), and shouldn't grow
  into a second model.
  - **A type option, not a kind:** kind is structure; whether entries
    have prose is its own axis, and any kind could go either way (a
    tree of reference data, a taxonomy without bodies). A type would
    have four: structure (`kind`), addressing (`urls`, `public`), prose
    (`body: false`, missing), and data (fields, paused, D-348).
  - **With `body: false`:**
    - **Storage:** one format still, a `.md` file of front matter only
      (D-501 allows it); a database driver (D-486) keeps a null body.
      `content:lint` warns of a body that turns up rather than removing
      it.
    - **Admin:** the editor opens as a form: the title and the type's
      field groups in the main column in place of the writing surface
      (D-348's "the writing area stays the text's" holds, since there's
      no text). A type's list could show chosen fields as columns.
    - **Elsewhere:** excerpts, word counts, Markdown copies (D-395),
      and search over body text skip these types.
    - **The content API:** the type's description says `body: false`,
      and its entries have no `body` key at all (not `null`), so
      clients know from the type, not from each entry.
  - **One entry shape across the content API:** `{ id, type, title,
    slug, status, published, updated, url?, fields: {…}, body? }`, with
    a site's fields under `fields`, not flattened beside the built-ins,
    so a site's `status` or `title` field never collides with Blush's;
    `url` only when the type has URLs, `body` only when it has one.
  - Still open:
    - **The name and values:** `body: false`, or `body:
      optional|required|none`.
    - **Titles:** data records often have no natural title. Required,
      or a type saying "make the title from a field" (the slug
      following it)?
    - **Order:** this leans on the Fields API (paused, D-348). Data
      types as the use case that restarts it, or `body: false` first
      for types needing only a title and a few front matter keys?
- **Regions as written content** (discussed 2026-10-06; the author's
  leanings, not decided; don't build yet; regions are removed for now,
  D-676, and this is the starting point when they return): a region becomes an editable
  area a theme registers, filled with Markdown the user writes, instead
  of a typed item list in `user/data/regions/` (D-201, D-204).
  - **Why:** since D-532, directives are what content says and
    components are a template's pieces, so of today's item kinds only
    `directive` and `markdown` are content; `component` and `view` are
    template work kept as site data, and `entry` (with its `_regions/`
    folder) exists only because inline text was too small. Markdown
    covers what's left (`::menu{name=social}`), and the admin's editor
    (blocks, media, mentions) becomes the region editor, in place of
    the separate one D-213 plans.
  - **Leanings:**
    - **Storage:** entries of a built-in type of their own, with no
      URLs (ties in with **Data types** above: a type that exists
      without pages), so regions get ids, translations
      (`translation_of`), status, the storage driver (D-486), and the
      body cache from content rather than rules of their own.
    - **Replace** the item list; one format, not two. Regions are new
      in 2.x, so D-078 doesn't hold them; a migration converts existing
      files (the default theme's `footer`, the jtcom trial's).
    - **Theme defaults** are Markdown. The user overrules them by
      attaching their own region entry to that location; no merging.
    - **Structured areas** (search, then recent posts) wait for a
      larger library of directives, or the theme draws them with
      components in its templates and offers no user input there.
  - **Still open:**
    - **The name.** Is "region" a word users understand? The user docs
      already explain regions as "the sidebar and footer areas themes
      offer" (`docs/README.md`). Taken or confusing: blocks
      (directives), sections (the admin's section rail and panel),
      slots (components, D-025, and the Fields API), zones (time
      zones), partials (template parts), and "widgets" reads as
      another CMS. "Areas" is plain English ("Footer area"), but
      storage areas (D-485) and the admin's rail areas use the word in
      config and code. "Snippets" suggests reusable text rather than a
      place on the page. The author likes **areas** or **slots**.
      Slots fits the component fold (a place in the theme's layout the
      user fills, as a caller fills a component's named slot), but the
      word already has two meanings: components' named slots (public,
      `docs/components.md`) and field set slots (D-347, in the admin
      API, paused with the Fields API), which would need renaming.
      Areas collides only in config and code.
    - **Attaching:** how an entry names its location: its slug, or a
      front matter key (`region: footer`), and whether one entry can
      fill several locations. The `user/data/theme.json` map
      (`"regions": {"aside": "sidebar"}`) may become unnecessary if the
      entry names the location.
    - **Where theme defaults live:** Markdown in `theme.json`, or files
      in the theme (`regions/footer.md`), translated how.
    - **Limits per area:** whether a theme can declare which blocks an
      area allows (the inserter's `only`).
    - **Per-page conditions** (a sidebar only on posts; see **Menus
      and regions, later**), possibly as the entry's front matter.
    - **Folding into components** (asked by the author): an area as a
      component slot the user fills. A component is drawn in many
      places with different props, while an area is one place with one
      piece of content, so a full fold needs a key per area, a region
      by another name, and makes components user-editable again,
      blurring D-532. A lighter fold: areas stay registered locations
      backed by entries, and draw through a built-in component, so a
      theme overrides their markup as it does any component's (D-382),
      and a theme's own component (a footer) can hold an area.
- **An outgoing HTTP client** (D-620; discussed 2026-10-08, leanings
  the author hasn't confirmed):
  - **The transport:** curl only, and optional. Without `ext-curl`
    (and no PSR-18 transport bound by the site) the client says it's
    unavailable, the features needing it (embeds, AI, webhooks,
    importers) are off and say why, Site Health names it, and plugins
    `require` `ext-curl`. Symfony's client and Guzzle prefer curl and
    fall back to streams; Kirby requires curl. One transport is one
    place to get the address checks right; the cost is embeds on a
    host without curl, which work through streams today.
  - **Security, fixed rules rather than a caller's say-so:** `http`
    and `https` only, HTTPS unless allowed; TLS always verified; every
    address a host resolves to checked, and the connection pinned to
    it (`CURLOPT_RESOLVE`); loopback, private, link-local (cloud
    metadata), CGNAT, `0.0.0.0`, multicast, reserved, and their IPv6
    and IPv4-mapped forms refused; redirects limited and each hop
    checked, HTTPS to HTTP refused, credentials dropped when a
    redirect leaves the host; size and time limits always. The one
    exception is an allowed list of origins (scheme, host, port),
    such as Ollama's `http://localhost:11434`, written only by `.env`
    and `config/`: never admin settings, plugins at runtime, or
    content. A provider's base URL from `.env` joins it.
  - **Caching:** opt-in per GET (`->cache()` by the server's headers,
    or a TTL), in its own cache namespace that `cache:clear` empties;
    POSTs never. Embeds keep caching their parsed answer a level up
    (D-448). Open: whether GETs cache by default instead.
  - **Shape, from the uses foreseen** (AI, webhooks, CDN purges,
    importers, embeds, extension downloads and update checks, link
    checking, webmentions, remote feeds, posting to social sites,
    ActivityPub, remote storage): bodies as streams, with downloads
    to a file and uploads from one; request middleware from the start
    (signing, auth, logging); many requests at once later (curl's
    multi interface), not ruled out by the API; HEAD and conditional
    GETs; retries opt-in, only on 429, 502 to 504, and dropped
    connections, honoring `Retry-After`, never a POST silently, and
    longer waits handed to jobs; streamed answers later.
  - **Also:** a fake transport in core for tests; debug logging of
    method, host, status, and time, with auth headers removed; an
    `HttpClientConfig` (timeouts, user agent, proxy from
    `HTTPS_PROXY`, the allowed list); `Blush\Http\Client` as its
    namespace.
- **APIs, agents, and headless** (discussed 2026-10-03; the author wants
  to explore or build most of these; nothing decided):
  - **The content API** (decided in D-479: two surfaces over one
    domain layer, read-only first, opt-in with anonymous reads; single
    entries by id, D-477). Still open: the answer's shape (leaning plain
    `{ items, total, page, pages }` with `next` and `prev` links over a
    `{ data, meta, links }` envelope), bodies (`?body=html` by default,
    `markdown`, `none`; types without a body and the entry shape are
    under "Data types" above), the path setting, and caching (an `ETag`,
    anonymous answers only), and a lookup by site URL (`?url=`) for
    headless routing. No file paths, ever (D-481). Settle before tokens and MCP, which build
    on it.
  - **API tokens:** the admin API is session and `X-CSRF-Token` only
    (D-220). Tokens tied to an account act with its roles and
    capabilities, can be revoked, and stop with a suspended account
    (D-312). An agent's account with `content.edit` and no
    `content.publish` writes drafts only. Every outside caller needs
    this first.
  - **An agent-readable site:** built (D-395): Markdown pages at `.md`,
    `llms.txt`, the AI screen (D-398, which settled listing only some
    types with each type's `llms` option), and `llms-full.txt` (D-402).
    Still open: answering `Accept: text/markdown` on the page's own URL
    (a cache would then vary by `Accept`, and a static copy can't);
    and directives rendered to plain Markdown rather than left as
    written (their URL props get full URLs, D-396), which needs each
    component's plain form, with today's as the fallback.
  - **An MCP server:** tools (search content, read an entry, list types
    and their JSON Schemas (D-206), create a draft, update an entry,
    upload media) over the content API, checked against the token's
    capabilities. For sites on a server; local agents can edit the
    files.
  - **A headless mode:** a setting where Blush serves the admin and the
    API (and perhaps feeds and the sitemap) but no themed pages, and a
    separate front end (Astro, Next.js, SvelteKit, an app) owns the
    pages. Content reaches it over HTTP at runtime, at build time (a
    rebuild on an outgoing webhook), or as the content API exported to
    a folder of JSON by a static export plugin (D-476), so a front end
    builds with no PHP running. Open within it:
    - Bodies: directives and components render through theme templates
      (D-382), so a front end can't render them alone. Rendered HTML
      first (core component templates), a structured tree (JSON nodes
      a front end maps to its own components, like Portable Text or
      MDX) as a later opt-in; raw Markdown isn't enough alone.
    - URLs: the API gives each entry's path, so links inside content
      and front-end routes agree.
    - Preview: Preview opens the front end's preview mode with a
      signed, expiring draft token (D-226's preview links, which
      exist).
    - How much the kernel assumes an active theme (not checked yet;
      look first).
    - Media: URLs, dimensions, alt text, and captions from the media
      index (D-287 to D-291).
  - **Revision history and an activity log:** what an agent or person
    changed and undoing it; git-backed revisions are already listed as
    later in `architecture.md`; an activity log (who or which token did
    what, when) makes tokens trustworthy.
  - **Outgoing webhooks:** signed posts to configured URLs on events
    such as a publish (social posts, deploys, chat, headless rebuilds).
    Blush only receives publishing webhooks today.
  - Shareable draft preview links already exist (signed, expiring
    links, D-226); headless preview would reuse them.
  - **An image pipeline:** resized images and modern formats (AVIF,
    WebP) on request; `srcset` helpers exist, but nothing
    makes the files. Options are under "Generating renditions" above.
  - **AI features (D-397):** from plugins, on a core `Blush\Ai`
    provider layer (not built). Ideas discussed: alt text for media
    (the library's Missing alt filter, D-269), summaries and meta
    descriptions, term suggestions from existing terms, transcripts and
    `.vtt` captions (D-291), editor help offered as changes to accept,
    translation drafts (once D-036 is settled), and embeddings for
    related posts or search. Always at authoring time, saved to files
    like any edit, never on a visitor's request; suggestions, not
    actions; off until a provider is configured. Leaning no: generating
    whole posts, public AI chat. Open: the provider interface's shape
    (text, images, audio), whether a capability per feature or one
    `ai.use`, and how plugins declare what they send.
  - Leaning no: GraphQL and real-time collaborative editing (costly,
    and a poor fit for flat files; plain JSON and MCP cover the needs).
  - A suggested order, not agreed: the content API's shape, then
    tokens, then MCP (Markdown pages and `llms.txt` came first, as the
    easiest, D-395).
- **A Tools screen for actions** (discussed 2026-10-03, for a redesign;
  **partly settled by D-540**: Tools is under Home, from the Home
  sketch, with Actions grouped by who registered them and Logs; Publish
  went with the other actions for now, and the site check becomes Site
  Health, stage 2 of D-537. Still open below: where Publish belongs,
  scheduled tasks, media and theme asset actions, backups, and routes).
  The dashboard's Actions panel (D-223) draws each
  `AdminAction` the account may run as a button, so a task the CLI can
  do can also run from the admin without a shell (shared hosting), and
  plugins can add their own (`docs/extending.md`). The built-ins are
  Publish (`site.publish`), Reindex content (`site.publish`), and Clear
  caches (`cache.clear`).
  - **Publish is part of writing, not maintenance.** If actions move,
    Publish shouldn't go with them; it belongs where writers are (a
    top-bar "3 unpublished changes · Publish", or the editor's save
    flow), with the dashboard keeping a status tile.
  - **The rest get a screen.** Reindex, Clear caches, and plugins'
    actions are fixes and chores, not the first thing an editor sees.
    Suggested name: **Tools**, under Config ("Maintenance" doesn't fit
    a plugin's sync-orders action; "System" sounds like read-only
    status).
  - **Possible contents**, each something the CLI can already do or
    data that already exists without an admin screen:
    - A site check: `doctor`'s `SetupChecks` as passes and failures,
      plus an HTTP exposure test (fetching `/.env`, `config/`, and a
      `storage/` sentinel from `APP_URL`; a 200 fails), which catches
      what `.htaccess` can't (`AllowOverride None`, dotfiles not
      uploaded, a replaced root `.htaccess`). That test was raised in
      the same discussion as an alternative to an `.htaccess` in each
      private folder, which would help only when the root file is
      replaced, and never for `.env`, a file at the root.
    - Reindex media (`media:index`) and republish media and theme
      assets (`media:publish`, `themes:publish`), for files uploaded
      over FTP.
    - Scheduled tasks (`schedule:run`): when it last ran, and Run now,
      for hosts without cron. Built as Tools → Jobs (D-622).
    - Logs: the latest lines in `storage/logs`, read-only.
    - Backups: `storage/backups` (the versions extension replacements
      keep, D-393) listed with restore and delete; later a "back up
      `user/`" action.
    - Routes (`routes:list`), read-only, for debugging a 404; aimed at
      developers.
  - **Content Health** (D-225) probably stays under Home: it's about
    content, for editors, while Tools is for whoever runs the site.
    Or Tools gets tabs for the site check and Content Health together.
  - **A possible shape:** Tools with tabs for Actions (built-ins and
    plugins' actions, grouped by who registered them), Status (the site
    check), and Logs; Backups once they exist (see below).
- **Site backups from the admin, for every driver** (discussed
  2026-10-09; nothing settled). Backing up a site's stored data, not
  only `user/` on files, from the admin, on SQLite, MySQL/MariaDB, and
  PostgreSQL (D-640) alike.
  - **Two ways:**
    - **Each database's own dump** (`mysqldump`, `pg_dump`, SQLite's
      backup): fast and exact, but MySQL's and PostgreSQL's need
      `exec()` and the tools on the server, which shared hosts rarely
      allow, and a path per database. SQLite alone can do it from PHP
      (`VACUUM INTO`).
    - **Through the record layer (leaning this way):** read every table
      through `RecordStores` into one portable archive (a manifest with
      the Blush and schema versions, the source driver, and the tables;
      a file per table), the way `storage:copy` already moves data
      between drivers with ids kept (D-644, D-662). Works on every
      driver, plugins' included, on what the conformance suites already
      test, and restoring is the copy in reverse, to *any* driver, so a
      backup is also a migration (SQLite to PostgreSQL). Native dumps
      could stay a CLI or plugin option for very large sites.
  - **The archive's format:** its own (JSON lines per table), or the
    filesystem driver's layout itself, so every site's backup is a
    readable, diffable `user/` tree and restoring it is also moving a
    site off its database. Open.
  - **Needed either way:**
    - **A consistent snapshot:** one read-only transaction (`BEGIN` on
      SQLite, `START TRANSACTION WITH CONSISTENT SNAPSHOT` on MySQL,
      `REPEATABLE READ READ ONLY` on PostgreSQL), so links between
      entries never point at records left out.
    - **As a job** (D-621, D-622): queued from the admin and followed
      chunk by chunk, so large sites don't time out; which also makes
      scheduled backups possible.
    - **Media originals** are files in `user/media` on any driver, so a
      full backup is the data plus those files. Whether extensions and
      config go in too is open.
    - **Sensitive:** backups hold password hashes, so they're kept in
      `storage/backups` (never served), made and downloaded only by an
      owner (D-500, or a new capability), and streamed.
    - **Restoring is the dangerous half:** it replaces accounts too, so
      it can lock you out. Owner only, asks first, and takes a backup
      just before. Restoring from an older schema needs a migration
      path; from a newer Blush, refused.
    - **Sessions aren't kept,** as `storage:copy` leaves them.
  - **For plugins:** off-site storage (S3 and the like) and keeping
    backups by age or count, on the scheduler. Whether core keeps the
    last N is open, with D-393's extension backups (below).
- **Unpublished changes and "the site is behind"** (from the Home
  sketch, D-537; left out by the author's choice): the sketch's
  dashboard bar ("2 entries have changed since it was published, 2
  hours ago", with Publish Site) and the entry **Changes** pill (a
  published entry with edits that aren't live) need a publish model
  Blush doesn't have: a save writes the file, and Publish reindexes
  and clears caches. Options when it's taken up: count content files
  newer than the last publish or reindex (cheap, but a git pull or an
  FTP upload counts too), or a real pending-changes model (drafts of
  live entries, the direction's §8 "Autosave and pending changes").
  Each action's **Last run** (the Tools sketch) needs the time stored
  per action too.
- **Translation overrides and management** (D-451; raised 2026-10-04):
  - Where uploads and management go in the admin: translations aren't
    extensions (no code, no manifest), but an **Extend** rail section
    (Plugins, Themes, Icon Packs, then Translations) could hold them;
    or a Languages screen tied to multilingual sites.
  - Multilingual sites in 2.0.0 (D-451, D-036): the site's languages,
    translated content, and translated strings likely belong on one
    screen, with what's missing per language.
  - The admin's own strings, which aren't translatable yet (D-278).
- **Turning languages on and off** (raised 2026-10-04, D-470): the
  author asked for a switch for multilingual as a whole. Proposed
  instead: `languages` is already the switch (a site is multilingual
  when it lists one), and turning it off while languages are listed
  would make `about.fr.md` a page at `/about.fr`; so a **`live` flag per
  language** (`'de' => ['locale' => 'de_DE', 'live' => false]`; the
  name is open, also `enabled` or `public`): its files are still read
  as translations, but it has no `/de/` routes, `hreflang` links,
  switcher or menu entries, or sitemap. That covers translating a whole
  language before launch and taking one offline without renaming
  files. Config only until the languages redesign (D-468). Leaning no
  (the proposal, not yet the author's call): choosing a language from
  the browser's `Accept-Language` at `/` (breaks the page cache, and
  search engines), and domains per language (set aside in D-455).
- **Multilingual, what's left** (D-455 to D-467): untranslated content
  is decided (D-467, on General, D-468). Still open: a bigger
  redesign of languages in the admin (the author, 2026-10-04); the
  proposal was a Languages settings screen with the site's language, a
  table of languages, and `untranslated`, warning when removing a
  language would turn its files into pages of their own; a
  language switcher, on hold (the author, 2026-10-04: there's no
  light/dark switcher yet either; the proposal was a helper listing the
  page in each language from `ContentPage::alternateUrl()`, D-461, and a
  core component the default theme draws in its footer); feeds,
  sitemaps, `llms.txt`, profiles, and people archives per language; a
  per-type list override for `untranslated` if a site needs one
  (D-467), with types whose entries are never translated (deferred,
  D-470); site settings per language (name, description, date format;
  deferred, D-470); fallback chains (`pt-br` → `pt`) and the default
  language under a prefix (later, D-470); and the admin's Translate action (translations aren't listed
  or editable in the admin yet).
- **Translations written inline, and collecting them** (discussed
  2026-10-05): write a message's English where it's used, and have a
  command add it to the catalog. Where the discussion got to:
  - **Explicit keys, not the source text** (the author's call): the
    key names the message, the English sits beside it in the code, and
    a catalog stays keyed as today (D-107, D-451).
  - **`text()`** (name open) beside keyed `t()`, which stays as it is:
    `$template->text('nav.go_to', 'Go to the {page} page', page: $title)`.
    Signature `text(string $key, string $text, ?string $context = null,
    mixed ...$params)`: named parameters as `t()` takes them (D-028),
    and context through `text()` (the author's call) as a named
    argument, so `key`, `text`, and `context` can't be placeholder
    names. The inline English is the fallback when no catalog has the
    key, so English needs no catalog entry. Also on `DomainTranslator`
    and `Component`, for plugins, services, and component classes.
  - **Context is for translators**, not for telling messages apart
    (the key does that: `entry.type_post` and `actions.post` are both
    "Post"): where the text shows, what a placeholder holds, or a
    length limit. Stored the way ARB does (the format `@@locale` comes
    from, D-452): `"post": "Post", "@post": {"context": "…"}`, so the
    translator skips single-`@` keys as well as `@@` ones.
  - **Use cases shown:** plain text; one or several placeholders;
    context, alone and with placeholders; ICU plurals, `select`, and
    `number`/`date` arguments; attributes (`aria-label`); ICU quoting
    (`'{'name'}'`; a lone apostrophe is literal); component classes and
    plugin services.
  - **HTML in a message** (needs the author's call): `Written by
    {author}` with a link. Escaping the parameters by hand and printing
    the result raw lets a translation add raw HTML; a stricter
    `textHtml()` (name open) could escape the message and leave only
    the parameters raw.
  - **`lang:extract [vendor/name] [--prune] [--dry-run]`**: reads an
    extension's PHP (templates and `src/`; also the framework's `blush`
    domain) with PHP's tokenizer for `text()`
    calls; checks each message is valid ICU; adds new keys with their
    English and `@key` context to `lang/en.json`, keeping what's there;
    marks a key whose English changed, so translators recheck it;
    reports keys no longer used, removing them only with `--prune`.
    Warns about a key or text that isn't a literal string
    (`"status.{$status}"`, to be written out or made a `select`) and
    about one key used with two texts. `lang:missing` (`cli.md`) then
    lists what each locale lacks.
  - **Set aside:** keys generated from the text and context, as
    gettext does (they break whenever either is reworded); the Vue
    admin's strings (D-278), which need their own collector; and moving
    the default theme and framework to `text()`, which can go bit by
    bit (error and component metadata messages stay keyed, since code
    looks them up by convention).
- **Leaving a fragment out of the page cache** (raised 2026-10-04): a
  component, piece of text, or template part that's drawn fresh on
  every request while the rest of the page stays cached. Today the page
  cache (D-129) is all or nothing per response: it skips responses with
  a cookie or `Cache-Control` of `private`, `no-store`, or `no-cache`,
  but only code building its own `Response` (a plugin's route) can set
  that. Themed pages come from `ThemedPageRenderer::render()` with no
  way for a template, component, or front matter to opt out, so not
  even a whole content page can. `$template->cache()` (D-152) is
  opt-in, and Markdown components are kept with the body (D-130), so
  both end up inside the cached page anyway. Options:
  - **Page-level opt-out:** `cache: false` in front matter, or a
    template call that marks the response `no-store`. Simplest; gives
    up caching for the whole page.
  - **A placeholder swapped on a hit:** a template marks a hole
    (`$template->dynamic('name', fn)`, say), the page cache stores the
    page with a placeholder and runs only that callback on each hit.
    Cheap, since PHP already runs for a hit, but no help to a static
    export plugin (D-476) or anything the web server serves without
    PHP, and the callback can't touch the `<head>`.
  - **Loading it in the browser:** the page stays cached and a small
    script fetches the fragment from an uncached endpoint. The only one
    that works for static copies and CDNs, but it needs JavaScript and
    an endpoint.
  The proposal was the page-level opt-out first, then browser-loaded
  fragments for real holes. What's wanted depends on the use: content
  that changes over time (a year, a random quote) or per visitor.
- **Skeleton license** (D-070): confirm MIT for `blush-dev/blush` `2.x`.
- **The skeleton's content model** (noted 2026-10-07, after D-602; not
  needed until the skeleton is worked on): its `2.x` branch has no
  content types or relations, so `user/content/blog/` and `blog/tags/`
  are pages of the page tree, and its post still uses 1.x keys
  (`author`, `tag`, `date`, which keep working, D-078). Bring it up to
  a 2.x site: a `post` collection in `blog/` (index page, feed), a
  `tag` collection in `blog/tags/` with its classify relation,
  `user/data/relations/authors.json` crediting posts (nothing credits
  until a relation says so, D-602), and a profile for the post's
  author. In `user/data`, so the admin can edit them. Done in its own
  worktree of `2.x`, since `../blush` is on `jtcom-trial`.
- **Live preview** (D-252): the author is leaning toward a more visual
  editor in the admin, with live preview on the front end (the site
  itself) rather than a rendered preview inside the editor. Not settled.

- **Fields API details** (D-337), still open after phase 3 (D-340):
  - The Fields API's long-term architecture: the author has larger
    concerns about how it's built for the future, not yet spelled out,
    and has paused it (D-348). D-337 to D-348 are a baseline; revisit
    them as a whole when those concerns are raised.
  - Order: may sets be ordered other than by name? (Where they show is
    a screen's call from their slot, D-347.)
  - More slots: which a content type should offer beyond `details`
    (fields in the writing area were tried and taken back, D-348), and
    whether extensions may add slots to a kind they don't own.
  - Broader targets: every type (`type:*`), a kind
    (`kind:collection`), or a type and its children?
  - Conditional fields (shown when another field has a value), and
    editing `object` fields and lists of objects in forms.
  - Should the type wizard offer existing sets, and should a type's
    screen attach and detach sets (which writes the set's file)?
  - When settings and accounts become targets, what each one refuses.
  - Should a data set be able to replace a config set of the same name
    (it can, D-339), or should config sets be locked as config types
    are? And should a locked set's screen offer to copy it into
    `user/data/fields` to customize it?

- **Autosave** (asked 2026-10-02, D-374; on hold by the author's call,
  D-375): saving the editor's changes
  on their own. D-233 held it back because the writer had nowhere to
  keep a pending draft: an autosave into the file would publish
  half-written edits to a live entry. It needs a place for pending
  changes first (a sidecar or `storage/` copy per entry, read by the
  editor and the preview, merged on Publish), which is also the
  direction's "pending changes" and the profiles sketch's open question.
  Autosaving drafts alone is possible today.

- **The global `blush` command** (D-165, discussed 2026-10-03): its
  shape so far, in place of D-421's bash launcher.
  - **Name:** `blush-dev/cli` over `blush-dev/installer`. It also hands
    off to a site's CLI, which is its everyday use. Peers that do both
    say `cli` (`statamic/cli`, `getkirby/cli`); `installer` is for tools
    that only create projects. The risk is
    reading it as where the commands live; its README says it finds the
    site and runs its `bin/blush`.
  - **Contents:** its own repository, no dependencies (global packages
    share one dependency tree), `"bin": ["bin/blush"]`, about 150 lines:
    `Application` (dispatch), `SiteLocator` (walks up to the nearest
    `bin/blush`), `Process` (`proc_open()` with an argument list and the
    real `STDIN`/`STDOUT`/`STDERR`, so prompts, colors, and `serve`
    work; returns the exit code), and `NewSite` (`blush new <dir>` runs
    `composer create-project blush-dev/blush <dir>`; the skeleton's
    `post-create-project-cmd` runs `init`). It never loads the
    framework: the site's `bin/blush` runs as its own process. Written
    in PHP, so it works on Windows without WSL, unlike the launcher.
  - **Global commands shadow the site's:** keep them to `new` and
    something like `--global-version`. Should `--version` and `help` go
    to the site when inside one and answer globally only outside?
  - **Which PHP:** it runs the site with `PHP_BINARY`, the PHP running
    the global tool. If that's older than the site needs, fail with a
    message naming it.
  - **Ctrl+C during `serve`:** both processes get the signal. Use
    `pcntl_exec()` (replacing the process) where available, with
    `proc_open()` as the fallback (Windows, builds without `pcntl`)?
  - **Namespace:** `Blush\Cli` sits close to the framework's
    `Blush\Console` (no clash, since they never share a process);
    `BlushDev\Cli` is the alternative.
  - **Publishing:** it and the skeleton on Packagist. `create-project`
    needs tagged releases of the skeleton and framework 2.x; until
    then, `blush new --dev` passes `--stability=dev`.

- **Podcasts** (discussed 2026-10-06). Nothing is podcast-specific
  yet, but most of the pieces exist: a type in `user/data/types` with
  its own feeds and limit (D-122, D-311), and a feed per term (a season
  or show taxonomy); audio served with byte ranges; duration, bit rate,
  and cover art read from files (D-291, D-552); `:::audio` and the
  audio player (D-553). What podcast apps need that's missing:
  - **Enclosures:** `FeedItem` carries no media and `feed-rss.php`
    writes no `<enclosure>`, so apps see no episodes. Core could give
    items an enclosure from an `audio` (or `video`) media key, with
    size, MIME type, and duration from the media index. Which key, and
    is it core or the plugin's?
  - **Feed extension points:** a way for a plugin to add namespaces
    and tags to the channel and its items (an event, or slots in the
    feed templates), so it needn't replace them. Today the only way is
    a theme's `feed-rss-{type}` reading `$item->entry`.
  - **`guid`:** it's the URL (`isPermaLink="true"`), so a moved entry
    is a new episode to every app. Use the entry's `id` (D-477), for
    every feed or only podcasts?
  - **The podcast plugin:** a show's settings per type (artwork, owner
    and email, category, explicit, language, `itunes:type`); the
    `itunes:` and `podcast:` tags (duration, episode and season,
    `podcast:guid`, transcripts, chapters); episode fields (waiting on
    the Fields API, D-348); and the site's player (core's, D-573, or
    the plugin's own over its handle). Leaning: core does enclosures, the
    extension points, and `guid`; the rest is a plugin.
- **Syntax highlighting, and plugins in content rendering** (discussed
  2026-10-09; nothing decided or built). The author wants highlighted
  code blocks rendered in PHP and cached with the body, with no script on
  the page, and themes deciding whether to style them. Leaning: a
  plugin on `tempest/highlight` (pure PHP, no dependencies, classes not
  inline colors; the author is "ok with tempest"), tried first as an
  extension on the jtcom trial to find what core is missing. Phiki
  (Shiki's grammars, more exact, but inline colors from a VS Code
  theme) and `scrivo/highlight.php` (highlight.js's languages and
  `hljs-*` classes, slower updates) were the alternatives. Output ideas:
  a small set of Blush token classes (keyword, string, comment, number,
  function, type, variable, operator, punctuation, tag, attribute,
  inserted, deleted) the engine's are mapped to, so themes style one
  set; `data-lang`; marked lines in the fence line (```` ```php {3,5-7} ````);
  line numbers by CSS counters; a file name through `:::figure`
  (D-267); a copy button as an optional script; an optional stylesheet
  by handle (D-569) a theme loads or doesn't.
  - **Problems a plugin meets today, all solvable:**
    - **No way into fenced code.** D-492 took plugins off
      league/commonmark (`MarkdownEnvironmentBuilding` is gone), so a
      plugin binds its own `MarkdownParser` around core's and
      re-highlights `<pre><code class="language-…">` in the HTML: two
      parses, and one plugin at a time. See the options below.
    - **The body cache doesn't know about plugins.**
      `RenderedBodies::fingerprint()` hashes the framework version, the
      URL, and the Markdown, media, and embed config, so turning a
      plugin on or off, or updating it, keeps serving cached bodies.
      Extensions need to add to the fingerprint, or it holds the active
      extensions and their versions. True of any plugin that changes
      rendered output.
    - **Assets only where they're used.** Pages, templates, directives,
      and components ask for assets, kept with cached bodies (D-570);
      a fenced block is none of those, so a plugin's stylesheet loads on
      every page unless a render can ask for an asset.
    - **A plugin's Composer packages.** Whether a local extension's
      `require` (D-418) is installed for it, or the site's own
      `composer.json` must name `tempest/highlight`.
  - **Options for plugins working on content as it renders**, not
    exclusive:
    - **A. After rendering:** an event with the body's HTML (as a
      `Dom\HTMLDocument`) for plugins to change. Works with any parser,
      and survives a parser of Blush's own. But it parses twice, has
      lost the Markdown (what isn't kept in attributes, as the fence
      line), and needs an order between plugins.
    - **B. Renderers per node kind, Blush-named:** core's renderer for
      a code block, image, link, heading, and so on, in a registry
      (the Type enum + Registry pattern), a plugin's replacing or
      wrapping it by priority. Needs Blush's own read-only node classes,
      mapped from league's, so plugins never see league (D-492).
    - **C. Changes to the parsed tree:** an event with Blush's document
      tree before it renders, for plugins to walk and change (ids,
      links, collected data). Core already does this inside
      (`ResolveLinks`, `DescriptionAttributes` on league's
      `DocumentParsedEvent`). Needs the same node classes as B.
    - **D. New syntax stays directives** (D-171, D-532): one dialect on
      every site (D-492), which the editor reads and writes, so no
      plugin grammar for blocks or inline text.
    - **E. Before parsing,** changing the Markdown text: fragile, and
      the editor's copy would differ. Leaning no.
    - **F. Replacing the parser** (binding `MarkdownParser`): there
      today, kept as an escape hatch, one winner.
    - Leaning: B and C on Blush's nodes, A for what they don't cover, F
      as the escape hatch. Whatever's chosen has to: add to the body
      cache fingerprint, ask for assets, render the same for every
      request (no per-request data in a cached body), and say where it
      applies (bodies, excerpts, feeds; Markdown copies come from the
      source, D-395).
  - **A Markdown parser of Blush's own** (the author: "eventually").
    For: Blush's nodes are the public API with no adapter; source
    positions for the editor; one dialect owned end to end (the admin's
    TypeScript editor and PHP read it apart today, so they can drift);
    speed and memory on large sites. Against: the CommonMark spec's
    edge cases (emphasis delimiters, lazy continuation, link reference
    definitions, HTML blocks), raw HTML safety (D-495), and upkeep. A
    path: build B and C's node API over league first, so plugins target
    Blush's nodes, then swap the parser under them, checked by the
    CommonMark and GFM spec examples as a conformance suite (as the
    storage suites, D-655). Open: whether the PHP and TypeScript sides
    could share one grammar or test set.

## Later milestones
- **Signing up** (D-518 has only the settings): the form and route
  (on the site, the admin's sign-in screen, or both, and themable?),
  email confirmation before the account can sign in, approval by
  someone who can add accounts, allowed or refused email domains, spam
  protection, and whether a sign-up gets a profile (see Sign-ups and
  community sites, under Needs the author's call).
- **Uploading a Markdown entry** (raised 2026-10-05, for the future):
  an upload in the admin for a `.md` entry file written elsewhere, added
  to a content type. To settle: where it lives (the entries list, New
  entry), checks before it's written (its type's schema, a slug in use,
  a missing or duplicate `id` given a new one, D-477), whether it lands
  as a draft, and several files or a `.zip` at once.
- **Hierarchy** (D-257):
  - Should a hierarchical term's page also list its child terms'
    entries, as WordPress's category archives do? An option on the
    taxonomy (or `termListing`), or always?
  - Tree types (D-386): sibling order is `position` (D-412). Does the
    template API get previous/next through the tree and a table of
    contents? And dragging rows in the Pages tree to set positions?
  - **Books in a tree** (discussed 2026-10-05; leaning, not decided):
    a book (parts, chapters, prologue, interludes, epilogue) is a tree,
    not a Book kind. A `book` tree in `_books` works today: each book a
    folder whose `index.md` is its cover or landing page, parts as
    folders, chapters as files. What it lacks:
    - **Reading order:** previous/next through the whole tree, page by
      page, and a table of contents (the question above). Perhaps a
      tree option (`sequential: true`, or `reading: book`) that turns
      them on, which docs and manuals would want too.
    - **A role per entry** in front matter (`part`, `chapter`,
      `prologue`, `interlude`, `epilogue`, `appendix`), with numbering
      ("Chapter 3", "Part II") worked out from role and `position`:
      chapters counted, a prologue or interlude skipped. A field once
      the Fields API is picked up again (D-348); a plain front matter
      key until then.
    - **No pinning by file name:** `prologue.md` and the like get no
      special treatment (D-516; a database has no file names).
      `position` puts a prologue first and an epilogue last; `index.md`
      stays the one pinned name, since it means "this folder's page,"
      not an order.
  - **Plugin kinds:** should `TypeKind` be open to plugins, as other
    registries are? The leaning is closed until a case appears that a
    tree or collection with options can't handle; books, forums, and
    shops (see "Forums and shops") aren't one.
  - The site's own pages (error pages, pinned on Pages for now, D-411):
    a tab on Pages, or a System screen, once there are more of them?
    Longer term (the author): an internal **system** content type for
    the site's system and error pages, managed from the admin. Today it
    would hold only the error pages (`_errors/{status}.md`; the site
    raises 404, 405, and 500, and a 500 isn't themed with debug on); a
    maintenance page, an editable welcome page, or search's intro could
    join later. Index pages and people pages stay with their types. Its
    folder must keep `_errors/` and 1.x's `_error/` working (D-078), and
    error pages would leave the root tree, so Pages needs no pinning.
  - Nested URLs for pages already follow folders; should a collection's
    single route ever take a hierarchical term's path (`{category}` as
    `web/css`)? Today it's the first term's slug.
- **Type labels** (D-278): how labels are translated, once the admin
  itself is.
- **File names** (D-511):
  - The admin's Translate action, when it's built, writes
    `translation_of` on the translation it creates.
- **Relationships** (D-242, D-585; discussed 2026-10-07):
  - **A relationship object** (raised 2026-10-05, on hold by the
    author): tying several entries together as a record of its own,
    stored in neither entry (a series with its parts in order).
    Ordered membership may instead be stored on the group.
  - Data on a link (the role an actor played): references inside object
    fields (`cast: [{actor: tom-hanks, role: Forrest}]`), indexed too?
    D-585's fourth stage; it needs fields.
  - ~~A type that lists what references it~~: answered in D-596 (by
    the inverse `archive`: the target's page, a word, or a template's
    `referencedBy()`).
  - **Cases to account for** (listed 2026-10-07; target states,
    sources and targets, hierarchy, and defaults answered in D-586;
    languages, integrity, and when defaults apply in D-587):
    - ~~Target states~~: answered and built in D-598 (links kept
      for drafts and the trash, warnings, unlinking on delete, pickers
      offer drafts too); which statuses count as live waits for the
      status API (D-588).
    - **Media as a content type** (D-586): once the full relation API
      works (D-588); it reopens D-238.
    - ~~Two extensions defining one relation (or type) name~~: fail
      softly, keeping the first (D-597). Field sets still fail to load
      (Fields API paused, D-348).
    - ~~Relationship schemas for editors~~: `relation.schema.json` (D-601).
    - **Filtered targets** (only a product in Shoes, only a term under
      a parent): later (D-587).
    - **Output** (later, D-601): structured data (`author`, `about`, `isPartOf`),
      feeds, `llms.txt`, and the read-only content API (D-479)
      including related entries (`?include=actors`).
    - **Bulk and maintenance** (later, D-601): adding a term to many entries from a
      list, merging two terms (rewriting referrers), and the reverse
      side's order (series parts by position).
    - ~~Changing a relation~~: D-600 (refused retargets and several to
      one while used, keys kept as aliases or rewritten, stripping on
      unfiling and removing, warnings for tighter limits, `content:relation`).
      Renaming a relation's name stays a hand edit.
    - **Duplicating an entry** copies its relations, but not a
      one-per-target one?
    - ~~Templates for a reverse archive~~: `related-*` and
      `related-list-*` (D-596).
    - **Conditional requirements:** required only for some statuses or
      when another value is set.
- **Media metadata** (D-238, D-239):
  - Edited WordPress images (`photo-e1234567890.jpg`, D-239): a variant
    of the original, or an image of its own, since the edit (a crop or
    rotation) is often what the author meant to show?
  - Removing unused WordPress variants (D-239): a command that lists
    variants no content references, and optionally deletes them?
  - Adopting WordPress variants as Blush sizes (D-239): when a generated
    size matches a variant's dimensions, serve the existing file instead
    of generating one, or always generate?
- **Media as records: sizes, grouping, and importers** (discussed
  2026-10-05; ids on media are D-487):
  - **Recorded sizes** are built (D-488). Still open: image sizes Blush
    makes, cached by id and size name (`_media/{id}/{size}.jpg`), so
    they survive moves and renames; and WordPress's other variants
    (`-scaled`, `-rotated`, edited `-e{time}`, D-239's open questions).
  - **Media isn't a content type** (D-238 stands, narrowed). A content
    type adds a body, routes (attachment pages), status, and taxonomies;
    everything else (an id, fields, an owner, dates, the index and its
    queries, the content API) media can have as its own kind of record,
    stored as `data` (D-486). What would flip it: wanting attachment
    pages, or public term archives of media.
  - **Grouping media** (the author may want it): it depends on who it's
    for. For the library (organizing, filtering), terms are a field in
    the file's data file, mapped by the media index and filtered by
    `MediaQuery`, as kind is; the media's own terms or a site's
    taxonomies is a later choice. For the site, two shapes: tags on
    media with public archives (routes, the one thing that pulls media
    toward a content type), or albums and galleries, where membership
    and order live on the group: an ordinary entry (a "galleries"
    collection, or `:::gallery`) that references media by id, so moves
    don't break it. Either way, groups reference ids, never paths. Taxonomy
    fields on media sit on the Fields API, paused (D-348).
  - **Follow-ups to D-487 and D-488** (listed 2026-10-05; the author
    saved them for later):
    - **The picker can't insert a size.** It lists originals only, so
      an author can't choose a smaller copy. Let insert choose a size,
      or wait for Blush-made sizes with `srcset`, which makes it moot?
    - **Searching a size's file name finds nothing**, since sizes aren't
      items; match sizes' names and answer their original?
    - **The admin API addresses media by path** (`media/{path}`), while
      entries moved to ids at every boundary (D-481, D-482). `media/{id}`
      for consistency? Sizes have no id, so their screens would still
      need a path.
    - **A file moved or renamed by hand loses its id**: its metadata
      file is orphaned and the moved file has none (lint reports both).
      A move or rename action in the admin would carry the id and the
      `sizes` list with the file.
    - **When related work comes:** media by id in the content API
      (D-479); Blush-made sizes cached by id; importers writing ids,
      `sizes`, and source keys; `MediaMetadataStore` behind the `data`
      area's interface (D-486).
  - **Importers** (and exporters): now their own entry, **Importers and
    exporters (`Blush\Transfer`)**.
- **Importers and exporters (`Blush\Transfer`)** (discussed 2026-10-09;
  the namespace is D-685, the rest are leanings the author hasn't
  confirmed). The goal: a site's owner can always take their content
  wherever they want, and bring it in from elsewhere, WordPress (WXR)
  the largest of both.
  - **N formats, one read path, one write path.** Importers and
    exporters never touch storage. Each format converts to and from a
    small neutral set of **portable items** (entry, term, person as an
    account or profile, media, menu, redirect), each with a source key,
    its fields, and its references as source keys. Core alone writes
    items into Blush (`ImportWriter`: ids, reference resolution, order
    prefixes and folder patterns, `published` dates, media `sizes`) and
    reads Blush out into them (`SiteReader`), through `Entries` and the
    repositories, so every format works on every storage driver (D-485).
  - **Interfaces**, by Type enum + Registry + Factory + Registrar, so
    plugins add formats (Ghost, Jekyll, Hugo):
    ```php
    interface Importer {
        public function inspect(ImportSource $source): SourceSummary;          // types, counts, authors, for mapping
        public function read(ImportSource $source, ImportMap $map): iterable;  // generator of PortableItem
    }

    interface Exporter {
        public function write(iterable $items, ExportTarget $target): ExportReport;
    }
    ```
  - **A native Blush archive in core** is the guarantee: a `.zip` of
    items as JSON plus media originals, ids kept, lossless both ways.
    Copying files works only on the files driver; a SQLite site needs
    this, and `storage:copy` only moves a site between its own drivers.
    Every other exporter can start from what the archive holds.
  - **Source keys in a mapping table**, not front matter: an `imports`
    table, `(source, source_id, kind) → id`, so a second run updates
    instead of duplicating and an exporter can map ids back, without a
    field on the settled entry shape or in imported files. Replaces the
    earlier `imported: { from, id }` sketch (see **Media as records**);
    the author to decide.
  - **Two passes:** the first creates every item and maps its id; the
    second resolves references (parents, terms, bylines, featured
    images, internal links rewritten to ids). WordPress parents often
    come after their children, so one ordered pass won't do.
  - **Run as a job** (D-621): WXR files reach hundreds of MB, so stream
    with `XMLReader`, write in batches, resume, and show progress in
    Tools → Jobs. CLI first, with `--dry-run` printing the plan; an
    admin screen later.
  - **Mapping is explicit:** post types to Blush types, taxonomies to
    classify relations and term collections (D-591), users to accounts
    or profiles (bylines, D-351). `inspect()` proposes defaults; the
    user changes them (CLI options, later a screen).
  - **Bodies through their own extension point:** a registry of block
    converters (`core/image` to Markdown images, `core/gallery` to a
    gallery, embeds to the embed directive), which plugins add to for
    their blocks. Anything unmapped is kept as raw HTML within D-495's
    allowed list, and listed in the report. Exporting to WXR goes the
    other way: rendered HTML with block comments for common elements.
  - **What Blush doesn't have** (comments, postmeta while the Fields
    API is paused, D-348, shortcodes) is reported, never dropped
    silently; optionally kept in the archive.
  - **Security:** XML parsed without network access or external
    entities; zip paths checked (no zip slip); SVG refused (D-497);
    no password hashes in any export; capabilities for importing and
    exporting (names open).
  - **Media:** downloading attachments needs the outgoing HTTP client
    (D-620, planned only). Without it, an import reads a local uploads
    folder (`--uploads=path`), the better path for large sites anyway.
    WordPress's variants are D-239's open questions.
  - **Core or plugin:** leaning toward core holding the interfaces,
    portable items, writer, reader, and native archive (the guarantee),
    and WXR import and export as a first-party plugin (it brings an
    HTML-to-Markdown dependency and WordPress's rules). Public text
    names the format, WXR, never the product (D-489).
  - **Still open:** comments (drop, archive, or wait for a comments
    plugin); the capability names; whether `Blush\Transfer` holds
    `Import\` and `Export\` subnamespaces or flat classes; what of
    settings, themes, and extensions an archive carries.
- **Rich (script) embeds** (D-184): providers such as X, Instagram,
  TikTok, and Mastodon answer oEmbed with HTML that needs their own
  `<script>`. The planned path: a provider opts in with
  `allowsScripts()`; its `EmbedData::$html` is output as given only for
  such providers (after checking that any script comes from the
  provider's own hosts); the script tag is deduplicated per page, which
  directives can now do: an embed's `assets()` can ask for a
  provider's registered asset, kept with the body (D-572). Also: a site's Content Security
  Policy, privacy (these scripts track visitors; a click-to-load
  placeholder with the thumbnail may be the default). Until then they render as links named by
  their title.
- **More embed providers** (discussed 2026-10-07, after D-584). Step 1
  is built (D-633): TED and CodePen built in; Dailymotion, Loom,
  Wistia, Speaker Deck, and Kickstarter dropped; and a switch per provider on
  Settings → Writing. Step 2 is built (D-634): Spotify and SoundCloud
  at fixed heights, Mixcloud left out. Step 3 is built (D-635): Flickr
  photos, Twitch, and TikTok. The rest is as discussed, from memory (each
  endpoint and response to be checked live before relying on it):
  - **Frame-answering oEmbed, no code:** these answer with an
    `<iframe>`, so a plain `OEmbedProvider` in `config/embed.php` works
    today: Dailymotion (`https://www.dailymotion.com/services/oembed`),
    TED (`https://www.ted.com/services/v1/oembed.json`), Loom
    (`https://www.loom.com/v1/oembed`), Wistia
    (`https://fast.wistia.com/oembed`), Spotify
    (`https://open.spotify.com/oembed`), SoundCloud
    (`https://soundcloud.com/oembed`), Mixcloud
    (`https://app.mixcloud.com/oembed/`), CodePen
    (`https://codepen.io/api/oembed`), Speaker Deck
    (`https://speakerdeck.com/oembed.json`), and Kickstarter
    (`https://www.kickstarter.com/services/oembed`).
  - **Fixed-height players:** Spotify, SoundCloud, and Mixcloud players
    have a set height, and SoundCloud answers `width: "100%"`, which
    `EmbedData` drops (sizes must be positive integers), so the frame
    falls back to 16:9, far too tall for a ~166px player. Supporting
    them well needs a height-based frame beside the ratio-based one
    (D-185), from the provider's answer or the provider class.
  - **Provider classes, no script:** Twitch has no oEmbed; a class
    could build the player URL from the link (as `YouTube` does), with
    the `parent=` parameter Twitch requires set to the site's host.
    TikTok's oEmbed needs its script, but its iframe player
    (`tiktok.com/player/v1/{id}`) could be framed instead, the way
    YouTube always frames `youtube-nocookie.com`; it's portrait, so
    D-186's cap applies.
  - **Waiting on rich embeds (above):** X, Instagram, Facebook, Threads,
    Bluesky, Reddit, Tumblr, Imgur, and newer Mastodon answer with a
    blockquote and a script. Instagram, Facebook, and Threads also
    need a Meta app access token on every request (a setting for a
    provider's token, which nothing has yet). Mastodon is one server
    per site, so no fixed schemes cover it; a site would list the
    servers it uses. Bluesky's and Mastodon's post pages can be
    framed without the script, but a post's height depends on its
    text, and without the provider's resize script a framed post is
    cut off or padded, which is why social posts need the rich-embed
    design rather than plain frames.
  - **The order, as it went:** steps 1 to 3 are built (D-633 to
    D-635). Step 4, click-to-load, is below, saved for later. Rich
    embeds for social posts come after it, as their own design
    discussion.
- **Click-to-load embeds** (step 4 of the embed providers discussion,
  2026-10-08; the author wants these options kept for later; nothing
  decided, nothing built):
  - **The idea:** an embed renders first as a placeholder (thumbnail,
    title, provider, a button such as "Play on YouTube"), and a click
    swaps in the frame, so the provider gets no request and sets no
    cookies until a reader asks. Spotify, TikTok, Twitch, and CodePen
    all track once framed; only YouTube's no-cookie host doesn't. It
    also keeps pages with several embeds light, and is what makes
    script (rich) embeds acceptable, behind a click.
  - **How it would work:** the placeholder is in the `embed` template:
    a real `<button>` named for what it loads, and without JavaScript a
    link to the embed's page. A small core site script swaps the frame
    in, registered and asked for as the audio and video players are
    (D-569 to D-573), only on pages with embeds. The placeholder keeps
    the frame's ratio or fixed height, so nothing moves on load.
    Providers never asked (CodePen, Twitch) have no thumbnail: a plain
    panel with the provider and label. Themes style it.
  - **Thumbnails** load from the provider (`i.ytimg.com/…`), which
    gives back part of the privacy. They could be kept on the site
    instead: downloaded on first render, served from the site, with a
    size limit and the address checks the HTTP client plans (D-620).
  - **Options to settle, with the leanings offered:**
    1. The default: load right away (as now) or click to load, chosen
       on Settings → Writing → Embeds. Leaning: click to load.
    2. Thumbnails: the provider's, or copies kept on the site.
       Leaning: copies, so the default is private.
    3. Remembering a reader's choice: "Always load from YouTube" on
       the placeholder, kept in that browser's local storage (no
       cookies), or asked every time. Leaning: offered, per provider.
    4. Scope: one site setting, or one per provider (TikTok behind a
       click, YouTube right away). Leaning: one for now.
    5. Photos (Flickr's images also load from Flickr): direct, as now,
       or behind the same click. Leaning: direct; one request, no
       script.
- **More icons** (D-187): bundle all of Lucide (about 2,100) rather than
  the front-end subset. Brand logos are the theme's (D-203).
- **Extensions the framework ships** (discussed 2026-10-03, after D-418;
  nothing decided, nothing built): the author expects to ship several
  defaults over the years (themes, icon packs, plugins), so they'd
  live in the framework in its own `extensions/{vendor}/{name}`, the
  site's layout, under the vendor Blush publishes as:
  `extensions/blush-dev/default-theme` in place of
  `resources/themes/default` (`blush/default`). They ship and update
  with `blush-dev/framework`; nothing is copied into a site.
  - **Renaming the default theme** to `blush-dev/default-theme`:
    `Themes::DEFAULT`, `ThemeConfig`, the theme's `theme.json` (and its
    `$schema` path), the reserved-name checks, the JSON Schema, two
    admin views, about 50 test references, and the docs. Its
    namespace, `default`, stays reserved. A site whose config names
    `blush/default` would need it changed (the jtcom trial names
    `justintadlock/jtcom`).
  - **Discovery:** the framework's `extensions/` read with the same
    `LocalExtensions` (folder is the name, one kind per folder), as the
    framework source (`ThemeSource::Framework`); plugins and icon packs
    would need a framework source too. Nothing in a site's
    `extensions/` or Composer may take a shipped extension's name.
  - **Turned on or off by default:** whether shipped plugins and icon
    packs are on (as Composer's are) or off until named (as local ones
    are, D-390).
  - **Which default is the fallback:** with several shipped themes,
    which one ends every chain, and whether a site can choose it.
  - **Asset URLs:** the public path changes from
    `/themes/blush/default/…` to `/themes/blush-dev/default-theme/…`
    (and `theme:publish`'s folders).
  - **Packaging:** a top-level `extensions/` ships in Composer's archives
    unless `.gitattributes` excludes it (it shouldn't).
- **Extension kinds, still open** (D-378, D-379; references, assets,
  icon pack manifests, and Composer manifests are settled in D-379):
  what an admin theme's manifest holds and how it joins `AdminTheme`
  (D-317). (Every local extension lives in `extensions/{vendor}/{name}`
  since D-418; `plugin:list`, `plugin:check`, and `plugin:new` are
  built, and broken plugin manifests are listed, D-394, D-416.) Still
  open from D-394: whether two plugins sharing a name or
  a namespace should be broken too, as themes and packs are, instead of
  failing discovery.
- **Installing and updating extensions** (D-388 decides the admin
  installs them, into `extensions/` since D-418; installing and
  replacing from a zip are built, D-392):
  - Installing from a URL, and from the CLI (`plugin:install` and the
    like), on the same `ExtensionInstaller`.
  - Revisiting backups (D-393) if Blush gets a scheduler: expiring
    kept versions by age, or capping `storage/backups/`.
  - Where updates come from for a local extension: an update source
    declared in its manifest (as WordPress's `Update URI`), or inferred
    from its `vendor/name` (risking a stranger's package of the same
    name). D-418 makes the name the folder, so an update API can find an
    installed extension by name alone; a registry (Packagist first) is
    what makes a vendor name someone's.
  - Discovery: Packagist by package type (`blush-theme`, and so on;
    its p2 metadata carries `extra.blush`) is the likeliest first
    source, GitHub only as a host for files; whether to show everything
    or an allowlist until a first-party catalog exists; integrity, since
    Packagist's GitHub zipballs usually carry no checksum.
  - Installing a missing requirement (the author's idea, for later): a
    `require` naming an extension that isn't installed (kind `missing`,
    D-431) offers **Install from Packagist** where it's listed. Open:
    whether a name always resolves to a Packagist package of a Blush
    type (`blush-plugin`, `blush-theme`, `blush-icons`), or a manifest
    may name the source (a stranger's package of the same name is the
    risk); picking the version the constraint allows, and that
    version's own requirements; whether it unpacks into `extensions/` as
    a zip install does or goes through Composer (since D-438 a zip that
    needs a library installs, but can't run until Composer installs
    the library; a `missing` name may be a library or an extension);
    and whether one install may
    bring others, and how the admin asks first.
- **Array and map props in directives** (D-112, D-205): the
  `key=value` attribute syntax stays, not JSON. Today every attribute is
  a string, cast to the prop's scalar or enum type. When a component needs an `array` prop (a
  breadcrumbs component with `icons` and `taxonomies` maps, for
  example), two additive changes are the likely path:
  - **Dotted keys** nest: `icons.home=house icons.date=calendar` becomes
    `icons: {home, date}`, and perhaps `items[]=a items[]=b` for lists.
    Keys already allow `.`, so this is a parser change only. Leaf and
    inline directives have no body, so they need nesting on the
    attribute line itself.
  - **Multi-line attributes**: `{…}` may span lines (today `SYNTAX`
    stops at a newline), so a long option list reads like config:
    ```md
    ::breadcrumbs{
      showIcons=all
      taxonomies.product=product-categories
      taxonomies.post=tags
    }
    ```
  The attribute scan should also become quote-aware, so a `}` inside a
  quoted value doesn't end the braces. Heavier options, if ever needed:
  comma lists cast by an `array` prop type, a JSON value for an `array`
  prop, a YAML options block at the top of a container (MyST-style),
  child directives as list items, or a prop naming a data file.
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
  The M3 router and `UrlGenerator` assume the site is at the host's root.
- **CLI publishing and opcache** (found while writing `docs/`, D-141):
  `publish` from the CLI rewrites the index and compiled caches, but its
  `opcache_invalidate()` can't reach the web server's opcache. With
  default settings the site lags by `opcache.revalidate_freq` (about 2 s,
  observed); with `opcache.validate_timestamps=0` it never sees the change
  until PHP restarts. The webhook is unaffected (it runs in the web
  server). Now relevant to M8, since jtcom runs dynamically (D-142).
  Options: document it (done for now), have `publish` ping the
  site to invalidate, or version the index file names.
  Discussed 2026-10-06 and left for now (the author's call). If taken
  up, the lean was versioned names for the content index only, with a
  plain-text pointer (a PHP pointer would be cached too) and delayed
  cleanup of old versions.

- **Vite dev-server integration** (D-155, deferred by the author): live
  reload needs asset URLs pointed at Vite's dev server while it runs,
  typically through a "hot" file the dev server writes that `ThemeAssets` checks in development. `vite build
  --watch` covers it until then.

- **Design tokens as an add-on** (D-160): the M5b token system (DTCG
  tokens, modes, site and entry overrides, `theme:check` contrast) was
  removed so themes can design however they like. If it comes back,
  probably as an extension, and opt-in per theme. Notes on the old
  design and what it taught are in `theming.md` → Design.

## Tooling
- **Review every CLI command** (raised 2026-10-08, D-617, D-618): the
  author wants all commands reviewed against the scaled-back site
  layer (code in extensions, templates in themes, types from plugins
  or the admin). `theme:new`'s stylesheet for child themes is done
  (D-618); look for other commands that assume site views, the `app`
  namespace, or types in config, and for ones that no longer earn
  their place.
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
- **How the admin's scripts load, once every screen is in** (D-687 to
  D-690, discussed 2026-10-09; revisit when the admin's screens are
  built). Today every screen but the dashboard, sign-in, and not-found
  loads on demand and the rest are prefetched in the background. The
  call was a small win now that grows as screens are added, bought with
  build complexity, so the author wants it looked at again with the full
  set of screens. Claude's assessment then:
  - **For:** the first load stays flat as screens are added (one file
    grows with every screen: 2.6 s against 1.3 s on the slow profile
    already); once prefetching is done, moving between screens and
    reloading match the one-file build.
  - **Against:** on a fast connection the gain is small today (70 to 90
    ms opening the admin) and opening an entry from a link is about 100
    ms slower; a screen clicked before prefetching reaches it waits on
    its files (the editor, about 0.9 s slow); the build carries moving
    parts one file doesn't (`versionChunks()`, the patch to Vite's
    preloader's `.css` check, which fails the build if Vite changes it,
    the reload on a newer build, and 116 committed files).
  - **Not measured:** hosts serving HTTP/1.1, where about six
    connections a site make many small files queue; the dev site is
    HTTP/2, and the editor's 13 files would feel it most. Measure this
    first when revisiting.
  - **Paths to compare then:** keep it as it is; group screens by the
    rail's areas (Home, Content, Users, Config, Extend) into a handful of
    files, most of the scaling with far fewer files and less exposure to
    HTTP/1.1; or go back to one file if the screens stay few and small.
    Rerun the timing in D-690 (the harness drove Chrome with Playwright
    over the dev site, cold and warm, fast and throttled) on each, with
    the totals from D-689.
- **Core directive templates under `resources/views`** (D-632, noted
  2026-10-08). The core directives' templates are in
  `resources/directives`, and each directive's `render()` names its own
  file. They could move to `resources/views/directives`, the floor of
  view lookup, so `theme:why directives/callout` and the theme's
  `directives/` folder resolve the same way as every other view. Left
  as it is for now.
- **Example plugins, and the gaps they show** (discussed 2026-10-08;
  ideas only, nothing decided). Plugins to build as samples and tests
  of the extension points:
  - **Buildable now:** static export (D-476; a command walking every
    URL through `Kernel::handle()`, an admin action, incremental on
    `ContentPublished` and `CacheCleared`); a CDN purge (`CacheCleared`'s
    `namespaces`, `ContentPublished`, and a Purge CDN action; the
    smallest sample); a front-end search index (JSON written on
    `ContentIndexed`, a script by handle, a directive or component;
    see **Front-end search**); reading time and a table of contents;
    more iframe or oEmbed embed providers; related posts from the
    relations index (D-590 to D-592); Twig or Blade views (D-502);
    a redirects file in `user/data` with a CSV or `.htaccess` import
    command (no screen to edit them); more media metadata readers; and
    syntax highlighting, with workarounds (see **Syntax highlighting,
    and plugins in content rendering**).
  - **Waiting on APIs:** the Calendar (D-550) needs plugins to add
    admin screens and rail items, the largest gap, which also blocks
    a redirects screen, a forms inbox, and anything a plugin shows in
    the admin; podcasts need feed extension points, enclosures, and an
    id `guid` (see **Podcasts**), with episode fields on the Fields API
    (paused, D-348); a WXR importer needs the `Importer` registry and
    source keys (see **Importers and exporters**); outgoing webhooks want
    signed delivery, retries, and a log (see **APIs, agents, and
    headless**); AI helpers (alt text, summaries, translation drafts)
    need `Blush\Ai` (D-397), an `ai.use` capability, and extension
    points in the editor, which has none; comments, forms, and
    community features need `AccountCreated`, sign-ups, spam
    protection, and likely a database driver (D-485, D-486), plus the
    front-end interactivity API; script embeds wait on D-184's open
    list.
  - **Plugin settings screens:** nearly every plugin above wants
    settings, which live only in config today; theme settings (D-342)
    are on hold and the Fields API is paused. The second largest gap.
  - **Suggested order:** CDN purge, then static export, then the
    Calendar, which would force the admin screens API.
- **A components screen with options** (raised 2026-10-06, after
  D-532): components (not directives) declare options a site owner
  sets on their own admin screen, such as a card's featured image and
  excerpt length or pagination's style, saved in `user/data/` and
  given to the component as defaults for its props. Needs field types
  for the controls, so it waits on the Fields API (paused, D-348).
- **Captioned quotes and tables** (D-175, deferred by the author):
  improve the existing blockquote (a source URL and a credited speaker),
  or add a general figure wrapper that captions a quote, table, or code
  block?
- **Indented page source** (raised 2026-10-05; tried and set aside,
  "might be better as plugin territory"): indent each front-end page's
  whole HTML source by its nesting, as `View\Head` does the head
  (D-472), so "view source" reads as if written by hand. It's a site
  owner's choice, not a theme's. What a trial showed:
  - **The shape that worked:** one pass over the rendered page
    (themed pages and error pages), before the page cache stores it.
    A string scanner, not a `Dom\HTMLDocument` round trip, since
    serializing again rewrites entities (`&hellip;` to `…`), `/>`,
    boolean attributes, and the line after the doctype.
  - **Whitespace only, never added:** reshape only runs already
    there. A run with a line break, or with a tab (a line break a
    template's `<?php endif ?>` took), becomes a break and a tab per
    level, as does a run beside a block-level tag. Leave single spaces
    between inline tags alone, and add nothing where tags touch. Keep
    one blank line at most between siblings, never just inside an
    element, and drop trailing whitespace. `<pre>`, `<textarea>`,
    `<script>`, `<style>`, `<title>`, comments, and tag text stay
    verbatim. `<html>`'s children aren't indented. Close omitted end
    tags (`li`, `p`, `td`, …) as a parser would, and return a page
    whose tags don't balance unchanged. The only rendering risk is
    CSS `white-space: pre`/`pre-line` on ordinary elements.
  - **Results:** about 1 ms for a 40 KB page; the jtcom trial's pages
    came out cleanly, the Markdown body and nested menus included.
    Touching tags from component and Markdown output
    (`</figure></aside>`) stay as they are; those renderers would need
    to print their own line breaks.
  - **Undecided:** core or a plugin (a plugin needs a way to change a
    rendered HTML response before the page cache, such as middleware
    or an event); if core, a setting, and on which screen.
- **Inline code or data on a registered asset** (raised 2026-10-07):
  data and inline code are tied to a handle at runtime (D-580:
  `data(for:)`, `inlineScript(after:)`). Open: whether `Asset` itself
  can carry them, for code or settings that are the same on every
  page, and how a theme's `theme.json` `assets` would write them.
- **A late page event** (raised 2026-10-07, D-578): an event dispatched
  from `PageMarkup::fill()`, once the page has rendered, for reacting
  to what it asked for (a preconnect only when the player loaded, or
  checking the final HTML). Left until something needs it; head and
  foot events from themes were turned down in D-578.
- **Scripts and styles at the end of the page** (raised 2026-10-05):
  `View\Head` (D-109, D-472) prints only inside `<head>`, so there's no
  way to print an asset before `</body>`. Scripts in the head are
  already deferred (or modules), so the gaps are styles that aren't
  render-critical and inline scripts (`Head` has no inline-script
  method at all). Today a theme can print tags in its footer part with
  `$template->asset()`, but those aren't printed once by key, and
  plugins and components can't add to them. The likely shape needs
  two pieces:
  - **A placement flag** on `Head`'s `script()`, `style()`, and
    `inlineStyle()` (and a new inline-script method), keeping one
    collection and one key space, so each asset still prints once
    wherever it's asked for, and `has()` and `remove()` work as now.
    A separate body-end object would mean two collections and
    printing once across both.
  - **A second print point**, such as `$template->foot()`, that base
    layouts print before `</body>`. (Built for scripts: D-570, D-577,
    D-578: `PageMarkup` with `Head` and `Foot`; `Foot` has no styles
    yet.)
  - **Undecided:** the flag's name and form; whether `Head` is renamed,
    since it would no longer hold only the head; which placement wins
    when an asset is asked for in both (probably the head, since
    earlier is always safe); and what happens when a layout never
    prints the late point (default layouts always print it, or a theme
    check warns).
- **Product name** (D-038): the author will decide.
- **Versioning manifest and schema shapes** (raised 2026-10-04): the
  author wants a version on JSON manifests (extension and theme
  manifests, D-418) and on every schema-like file (the editor JSON
  Schemas, D-206; `user/data/` files such as `settings.json` and
  `types`), so a future change of shape can be detected and migrated
  rather than misread. Undecided: the key (a `version`/`schemaVersion`
  integer, or a versioned `$schema` URL), what a missing version means
  (version 1), whether loaders migrate old shapes or refuse them, and
  how this fits Composer-shaped manifests that fall back to
  `composer.json`, which has no such key.
- **Schemas for content types and settings** (raised 2026-10-05, after
  D-491; the author: "wait on this for later"): editor JSON Schemas
  (D-206) for `user/data/types/*` and `user/data/settings.json`, so
  their `$schema` keys have something to point at. The other data files
  already have one. Undecided: whether the types schema can describe a
  type's fields (an extension's field types are only known per site,
  as with entries) and whether the settings schema comes from the
  `Setting` enum, as the others come from their PHP definitions. It
  may fold into the versioning question below.
- **Network (multisite) support** (raised 2026-10-05; early
  exploration, probably not 2.x): many sites run from one install. The
  author agreed with this direction: plan for a network, but build it
  in two layers.
  - **Layer 1, many sites on one codebase:** one `vendor/` and
    `extensions/`, and each site with its own `config/`, `user/`, and
    `storage/` (e.g. `sites/{name}/`). The front controller picks the
    site from the request (host or path, through a network config such
    as `config/network.php`) and builds its `Paths` before the runner
    boots. This mostly works already: there's no global state, and
    every location is a `Paths` value with overrides (D-046).
  - **Layer 2, a network:** shared accounts and sessions in
    network-level storage, with roles per site; network capabilities
    (creating sites, installing extensions, `extensions.{kind}.install`,
    D-389) apart from per-site ones (switching an extension on stays
    in each site's `settings.json`, D-390, D-391); a Sites screen, a
    site switcher in the shell, and network Users.
  - Ruled out: several sites inside one `user/`. It breaks one site's
    `user/` as one repo (D-131, D-166) and adds a site filter to every
    query.
  - Undecided: which URL shapes are supported (separate domains,
    subdomains, subdirectories); signing in across domains (a cookie
    covers subdomains only, so separate domains need a handoff, perhaps
    a signed one-time token as preview links are, D-226); whether
    accounts are shared or kept per site with network admins above
    them; how storage areas (D-486) gain a network scope (accounts and
    sessions are the candidates); the CLI's site option (`--site`, a
    `BLUSH_SITE` variable, `--all-sites` for commands such as
    `content:ids` and `cache:clear`); publishing a whole network
    (D-131); and whether subdirectory installs work at all today (a
    base path in URLs, the admin, feeds, and `llms.txt`), which matters
    without a network too. Out of scope for now: shared media, references
    between sites, syndication.
  - Keep in mind meanwhile: reach `user/` and `storage/` through
    `Paths` or the storage areas, never from `root`; don't hard-code
    the cookie domain or path or assume the admin sits at the root's
    `/admin`; and allow for accounts and sessions living at network
    level when those areas get their storage interfaces.
- **Responses: comments, reviews, webmentions** (discussed 2026-10-05;
  wanted for v2.0.0, not built yet): visitors responding to entries,
  turned on per content type. Leaning so far:
  - **One model, a response with a `kind`:** `comment` (a form, from a
    guest or a signed-in account; `parent` for threads), `review` (the
    same form, with a `rating`), `webmention` (verified against its
    source; a subtype such as reply, like, repost, bookmark, or mention
    read from the source's microformats). Shared fields: a UUIDv7 `id`
    (as D-477), `entry` (the entry's id, not its path, so renames and
    moves keep it), `author` (name, url, optional email, or an
    account), `body`, `status`, `created`. Statuses `pending`,
    `approved`, `spam`, `trash` (trash as a status, as D-484).
  - **Storage under `storage/`** (the author's call): runtime data that
    visitors write, never overwritten by a git deploy. A fifth storage
    area (`responses`, D-486) behind a `ResponseStore` interface, the
    filesystem driver first (one file per response, a folder per entry
    id, so concurrent posts never write the same file), a database
    driver for busy sites.
  - **Settings per type, overridden per entry:** on the content type,
    e.g. `responses: {accept: [comment, webmention], moderation:
    first-time, closeAfter: 30, threads: 3, rating: 5}`; front matter
    `responses: closed` or `open` on an entry. Site defaults and spam
    rules on a Settings screen.
  - **Dynamic sites only** (the author's call): no responses on static
    sites. A new approved response clears its entry's cached page and
    changes its ETag (or responses render outside the body cache; see
    "Leaving a fragment out of the page cache").
  - **Pingback and trackback:** not in core (the author agreed);
    receive-only in a plugin at most.
  - **Identity:** guests give a name, an optional email and URL, with a
    remember-me cookie; signed-in accounts respond as their profile
    (D-351) with their byline; IndieAuth sign-in later as a plugin (the
    author agreed).
  - **Capabilities** (the author agreed), as D-359:
    `responses.{type}.moderate` and `responses.*.moderate`, and whether
    an account's responses skip moderation.
  - Still open:
    - **Rendering bodies** (the author: needs real honing): the Markdown
      API (D-492) with a locked-down profile: no raw HTML whatever
      D-495's capability says, links `rel="nofollow ugc"`, mentions
      (D-493) perhaps only for signed-in accounts; what else is allowed
      (headings, images, code).
    - **Privacy** (the author: to consider): emails and IPs are personal
      data; a retention period for IPs; exporting and erasing every
      response by email, in the CLI and the admin.
    - **The first public write path:** CSRF, rate limits, a honeypot and
      time-trap, size limits, and a spam-check hook for plugins (an
      Akismet-style service as a plugin).
    - **Webmention:** in core or a first-party plugin on the same model.
      Receiving returns `202`, then verifies the source; with no job
      queue, a pending-verification state and a cron-run CLI command
      (`responses:verify`). Sending hangs off `ContentPublished`
      (discover the endpoints of an entry's links and notify them).
    - **Reviews:** visitor reviews only, or also an author reviewing
      something with visitor responses under it; an average and count
      per entry, cached, for templates and `AggregateRating`.
    - **Moderation in the admin:** a Responses screen under Content
      (status tabs as the entry lists have), tabs inside each entry, or
      both.
    - **Core or plugin overall:** the leaning is the model, storage
      area, per-type setting, moderation, comments, and reviews in core.
- **Forums and shops** (discussed 2026-10-05; leaning, not decided):
  neither is a new kind. What sets them apart is who writes the data:
  visitors write it at runtime, so it belongs in `storage/` behind a
  storage area (D-486), never in `content/`, where a git deploy would
  overwrite it.
  - **Forums** (forum → thread → reply): forums are a taxonomy or small
    tree the site's author writes; replies are close to Responses'
    `comment` with `parent` for threading. Threads are the open part:
    a response kind (`thread`) on a forum entry, with replies under it,
    which reuses Responses; or visitor-created entries in a
    storage-backed type, a much larger change. Ties in with open
    membership and Responses' database driver (busy forums). Leaning: a
    plugin built on responses and accounts.
  - **Shops:** products are a collection and product categories a
    taxonomy. Variants and prices are fields, so they wait on the
    Fields API (D-348). Cart, orders, stock levels, and payments are
    runtime data in a storage area of their own: a plugin.
- **Repo strategy after 2.x stabilizes:** one package, or a split monorepo?
- **Theming:** see the open questions in `theming.md`.
- **Menus and regions, later** (D-199 to D-204): entries adding
  themselves to menus from front matter (`menu:`, `weight:`); mega-menu
  `panel` entries; per-page region conditions (a sidebar only on posts).
  Regions may become written content (see **Regions as written
  content**), which would change the region items below.
  Smaller follow-ups from building them:
  - A region command (`region:list`/`region:show`); `theme:check` only
    checks item shapes, since it doesn't render items, so a missing
    component or view in a region is caught only in the log.
  - The default theme ships no script for submenu toggles (they stay
    `hidden`, and submenus stay open); decide whether it should.
  - The core `resources/components/menu.php`'s list (D-382) leaves template whitespace
    inside each link; tighten it if it causes spacing issues.
  - A `menu` in an entry body (`::menu`) renders once for every page, so
    nothing is marked current there.
  - Resolved menus are kept per process only (no cache namespace);
    revisit if `composer bench` (not run for D-204) shows menus cost
    much per request.
  - `LocaleMap` treats a map as a locale map when every key looks like
    a locale and every value is a string, so a view's data such as
    `{id: "x", to: "y"}` would be read as one.
  - A location's label is both the admin's name for it and the
    `<nav>`'s accessible name; they may need to be separate.
