# Open questions

Move each item to `decisions.md` once it's answered.

## Needs the author's call

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
  it): the plan in `architecture.md` ("needs `SqliteIndex`", FTS5) only
  works when PHP serves the site, not on a static export (D-011). The
  idea instead: a JSON search index as a route (`/search.json`, built
  and exported the way feeds are, `FeedExportUrls`), searched in the
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
    after export): it fits a static export but not a site PHP serves,
    and it's not in-house (D-006). Borrow its chunking idea, not the
    tool?
  - The browser-side matcher: in-house, or a library behind a Blush
    interface?
  - Only public, published entries, never drafts, private, or
    future-dated ones (`Query`'s visibility filters).
  - Whether `SqliteIndex` and FTS5 stay planned, for large sites PHP
    serves.
  - Its records could be the content API's (below).
- **APIs, agents, and headless** (discussed 2026-10-03; the author wants
  to explore or build most of these; nothing decided):
  - **One content API** over two: a versioned content API
    (`/api/v1/…`: entries, types, terms, media), REST-style JSON
    answered by who's asking (anonymous: published, public entries
    only; a session or token: more, and writes, by capability, through
    `Permissions::restrict()`, as WordPress's REST API does), used by
    front ends, exports, agents, MCP, and the admin's content screens;
    plus admin-only endpoints for screens (counts, the calendar, lint,
    form definitions), unversioned. Separation was discussed for
    stability (a public API is a contract; the admin's changes with the
    admin), shape (D-229's answers carry `violations`, `can`,
    `revision`, `extra`, field definitions), caching (cache only
    anonymous answers), and ids (source paths in the admin, URL paths
    for front ends). Moving today's admin content endpoints into the
    versioned layer is the cost. Settle before tokens and MCP, which
    build on it.
  - **API tokens:** the admin API is session and `X-CSRF-Token` only
    (D-220). Tokens tied to an account act with its roles and
    capabilities, can be revoked, and stop with a suspended account
    (D-312). An agent's account with `content.edit` and no
    `content.publish` writes drafts only. Every outside caller needs
    this first.
  - **An agent-readable site:** a Markdown version of every page (`.md`
    on the URL, or by `Accept`), served from the source Blush already
    has, and `llms.txt` (a Markdown map of the site, built like the
    sitemap). Both plain routes, so they export as feeds do. Cheap and
    distinctive.
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
    a folder of JSON by the static exporter, so a front end builds with
    no PHP running. Open within it:
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
    WebP) at export or on request; `srcset` helpers exist, but nothing
    makes the files.
  - Leaning no: GraphQL and real-time collaborative editing (costly,
    and a poor fit for flat files; plain JSON and MCP cover the needs).
  - A suggested order, not agreed: the content API's shape, then
    tokens, then Markdown pages and `llms.txt`, then MCP.
- **Skeleton license** (D-070): confirm MIT for `blush-dev/blush` `2.x`.
- **Where jtcom's content types live** (D-166, D-169): `config/content.php`
  today. Options: data types in `user/data/types/` (travel with the
  content repo; a checked sketch matches the config exactly), or an
  extension in `user/extensions/` (WordPress-style, with site PHP such as
  a future blog extension).
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

## Later milestones
- **Hierarchy** (D-257):
  - Should a hierarchical term's page also list its child terms'
    entries, as WordPress's category archives do? An option on the
    taxonomy (or `termListing`), or always?
  - Tree types (D-386): do sibling entries take a manual order (an
    `order` field, title as the fallback), and does the template API
    get previous/next through the tree and a table of contents? In the
    first slice, or later?
  - Nested URLs for pages already follow folders; should a collection's
    single route ever take a hierarchical term's path (`{category}` as
    `web/css`)? Today it's the first term's slug.
- **Type labels** (D-278): how labels are translated, once the admin
  itself is.
- **Relationships** (D-242):
  - Data on a link (the role an actor played): references inside object
    fields (`cast: [{actor: tom-hanks, role: Forrest}]`), indexed too? A
    bigger step; nothing else in D-242 needs it.
  - Should the reverse side ever be editable (adding a movie from an
    actor's screen writes the movie's file)? Leaning no, at least at
    first.
  - A type that lists what references it: paged like a term page (with
    feeds), or a plain list on the entry's page? Reusing term paging gives
    both.
- **Media metadata** (D-238, D-239):
  - Edited WordPress images (`photo-e1234567890.jpg`, D-239): a variant
    of the original, or an image of its own, since the edit (a crop or
    rotation) is often what the author meant to show?
  - Removing unused WordPress variants (D-239): a command that lists
    variants no content references, and optionally deletes them?
  - Adopting WordPress variants as Blush sizes (D-239): when a generated
    size matches a variant's dimensions, serve the existing file instead
    of generating one, or always generate?
  - Extracting embedded artwork (D-291 notes it only; on hold, D-295):
    a cached image the library and themes can show, as a sound's
    thumbnail?
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
  the front-end subset. Brand logos are the theme's (D-203).
- **Refreshing embeds**: `storage/cache/store/embeds` is only emptied by
  hand; a `cache:clear --embeds` or `embed:refresh` command may help.
- **Extension kinds, still open** (D-378, D-379; references, assets,
  icon pack manifests, and Composer manifests are settled in D-379):
  what an admin theme's manifest holds and how it joins `AdminTheme`
  (D-317); whether `user/extensions/{kind}/` should be allowed too;
  and `plugin:new` (planned since D-041; `plugin:list` and
  `plugin:check` are built, and broken plugin manifests are listed,
  D-394). Still open from D-394: whether two plugins sharing a name or
  a namespace should be broken too, as themes and packs are, instead of
  failing discovery.
- **Installing and updating extensions** (D-388 decides the admin
  installs them into `user/`; installing and replacing from a zip are
  built, D-392):
  - Installing from a URL, and from the CLI (`plugin:install` and the
    like), on the same `ExtensionInstaller`.
  - Revisiting backups (D-393) if Blush gets a scheduler: expiring
    kept versions by age, or capping `storage/backups/`.
  - Where updates come from for a local extension: an update source
    declared in its manifest (as WordPress's `Update URI`), or inferred
    from its `vendor/name` (risking a stranger's package of the same
    name).
  - Discovery: Packagist by package type (`blush-theme`, and so on;
    its p2 metadata carries `extra.blush`) is the likeliest first
    source, GitHub only as a host for files; whether to show everything
    or an allowlist until a first-party catalog exists; integrity, since
    Packagist's GitHub zipballs usually carry no checksum.
- **Requiring components to be registered** (D-266's direction): how
  a template-only component registers without PHP (a JSON file beside
  the template, with its text in the catalog?), and what happens to
  today's components found only by their file name.
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
  The M3 router and `UrlGenerator` assume the site is at the host's root,
  and so does static export: `build --base-url` takes only an origin
  (D-135).
- **Theme and icon pack requirements**: plugins' `requires` are
  enforced with Composer-style constraints since D-385
  (`VersionConstraint`); whether a theme's `requires` (only a notice in
  `theme:check` now) should block activating it, and whether packs get
  `requires` at all.

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
- **Lint zero months and days** (D-227, held by the author for later):
  placeholder dates such as `2019-00-00` roll back to a real date
  (`2018-11-30`) without a `content:lint` warning.
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
- **Menus and regions, later** (D-199 to D-204): entries adding
  themselves to menus from front matter (`menu:`, `weight:`); mega-menu
  `panel` entries; per-page region conditions (a sidebar only on posts).
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
