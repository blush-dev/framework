# Admin UI design direction

## Status: prototype

**This is a starting point, not a specification.** It was derived from a
clickable prototype and a design conversation, before the admin SPA had real
screens, real data, or a settled API. Nothing here was validated against a
running system.

Treat it as the current best guess at a coherent direction. Where the real
shape of the project disagrees with this document, **the project wins** — the
codebase, the API's actual responses, what the CMS genuinely does, and what an
operator actually needs on screen all outrank anything written here.

### How to use it

Follow it where it fits. Depart from it where it doesn't, and when you do:

1. Say what you changed and why, in the PR or the commit message.
2. Update this document in the same change, so it stays the description of the
   system rather than a record of an old opinion.

Do not contort a screen to satisfy a rule here. A rule that keeps needing
exceptions is a wrong rule; delete it.

### What is firm and what is provisional

Some of this is mechanical — break it and the interface visibly fails. Some is
taste, formed early and on thin evidence.

**Firm.** These hold until someone deliberately re-architects them:

- The theming contract in §3. Getting the cascade wrong produces an admin that
  is unreadable for anyone on the default system setting.
- Token discipline: no literal colors, fonts or radii in component CSS.
- Status is never conveyed by color alone (§5).
- Real `<button>` and `<a>` elements, and the ARIA attributes in §11.
- The token *names*. Changing values is theming; changing names breaks every
  third-party admin theme.

**Provisional.** Argue with these freely:

- The vocabulary table (§1). It reflects answers given in conversation, not
  the API's field names. If the API says `user`, do not translate it in code
  just to satisfy this table — reconcile the two and update whichever is wrong.
- Everything in §7 about specific components: sizes, which controls exist,
  what the dashboard shows. These came from a prototype with invented data.
- Sidebar grouping (§8). Deliberately an interim rule, with its replacement
  already named in that section.
- The patterns in §8. The behavior is decided; the presentation is not.
- Hide-vs-disable for permissions (§8). A guess, and a consequential one.
- The directory layout in §11. Match whatever the repo already does.

### Files

```
.claude/docs/admin-design/admin.md     this file
.claude/docs/admin-design/tokens.css   the original prototype tokens (reference only)
resources/admin/css/tokens.css         the tokens the admin builds from
```

`AGENTS.md` points here. The theming cascade, and that all color, type,
radius, spacing and density values come from `resources/admin/css/tokens.css`
as `var(--token)`, are not negotiable without a deliberate decision.

### Departures so far

Each is recorded in `.claude/docs/decisions.md`.

- **Type tokens added** (D-231): `--text-sm` (12px), `--text-xs` (11px) and
  `--h2` (14px), so no type size is a literal.
- **Shared pieces are global classes** in `admin.css` (buttons, panels, pills,
  tables, stat tiles, notices), not yet `Base*` components; components keep
  their own layout in scoped styles (D-231). The directory layout follows the
  repo: `resources/admin/{css,fonts,js/{components,views}}`.
- **Tables aren't sticky-headed** (D-231): a table that scrolls sideways is its
  own scroll container.
- **Only the neutral theme ships** until an account can choose a theme (D-231,
  D-235).
- **No autosave or pending changes** (D-233): the writer has nowhere to keep a
  pending draft, so the editor saves when asked and warns before leaving
  unsaved work.
- **Status tabs are links** with `aria-current`, since each is a URL (D-233).
- **Trash is a tab, not an index status** (D-237): trashed files leave the
  index, so the Trash tab lists them separately and "All" doesn't include them.
- **Term use counts are published entries only** (D-236), matching the site's
  term pages.
- **No reparenting on delete** (D-236, D-257): a hierarchical taxonomy's
  terms name their parent, and trashing a parent leaves its children
  pointing at a missing term, shown at the top level and reported by
  `content:lint`.
- **Trees are paged** (D-261, D-263): the All tab, unsearched, lists
  nesting types in tree order with the Hierarchy section's triangles and
  18px indent, but 20 rows a page rather than all at once, and a page
  that starts inside a branch repeats the rows above it, marked
  **Continued**. Tabs and searches flatten the tree (with the note bar)
  and show a row's parents before its title. There's no tree/flat switch
  and no column sorting yet.
- **Authors are under People** (D-259), not Content's shared taxonomies:
  they're the public side of accounts.
- **No list of every type together** (D-240): each content type has its own
  list, and there's no "All entries" screen. The dashboard's Drafts and
  Scheduled figures are plain numbers.
- **Unsaved changes are kept in the browser** (D-240): without autosave, the
  editor keeps a copy of unsaved changes in `localStorage` as they're made
  and offers them back when the entry is opened again. That's what makes the
  offline bar's "changes stay in this browser" true. A save made offline
  waits and goes ahead when the connection is back.
- **Conflicts say when, not who** (D-240): a file can change through git or a
  text editor, so the notice gives the time the file was written and "from
  the admin or by editing the file itself". **Keep mine** saves this
  editor's version of every field it shows over theirs; front matter the
  editor doesn't show stays as theirs.
- **Validation runs in the admin only** (D-240), from the schema's
  `required`; the API doesn't refuse to publish yet.
- **The setup path has only steps that do something** (D-240): the first
  entry of each page and collection type. "First content type", media, and
  inviting people join it when their screens exist.
- **A type's purpose comes from its kind** (D-240): types have no
  description yet, so an empty type's screen says what pages, collections,
  or taxonomies are for.
- **Content health keeps quiet text while checking** (D-240): its result is
  a summary, not rows, so there's no shape to sketch.
- **The full navigation** (D-241): screens that don't exist yet are listed
  and open a "comes next" page. A taxonomy moves when its `types` change
  in a config file, not in the admin, so there's no toast announcing the
  move (§8, Sidebar grouping); nav counts are left out until an API gives
  them cheaply.
- **The section rail** (D-244): no state dot on Content (without autosave
  there are no unpublished changes to live entries), no theme button in
  the top bar (the color scheme is an account preference on Your
  profile), and no site switcher; the site's mark links
  to the site. The account's menu is in the top bar. Home's panel has the
  Dashboard and Content health, then shortcuts.
- **The command palette** (D-248): the screen's own commands come first
  (the editor's), then going places and New {singular}, then entries;
  switching the color scheme saves it to the account.
- **The Markdown source editor** (D-241): spelling on, and no horizontal
  padding on inline code (it shifted the text).
- **The component inserter** (D-243, D-247): core components are grouped
  by category (Text, Media, Layout, Navigation, Data); a theme's, the
  site's, or an extension's by where they come from, and a theme's or
  extension's tiles say so. Components are written by full name
  (`blush/callout`, D-171). An inline component goes at the caret; a leaf
  or container on lines of its own, with a blank line either side;
  selected text becomes its label or body. Required props are written
  empty; defaults aren't written. In the search field, left and right move
  through the grid only while it's empty. Tab inserts, as Enter does,
  while typing after a slash, and Escape leaves the slash as text. The
  panel opened by a slash closes once a component replaces it.
- **The media picker** (D-246, D-247): the library comes from `user/media`
  newest first, with "Beside this entry" for a page bundle; there's no
  upload yet. It's also **Choose** beside every media field and option.
- **The writing surface** (D-245): no `Changes` pill and the save state
  reads "Unsaved changes" or "Saved 3:46 PM" (no autosave); nothing sits
  under the title (D-254); the ⋯ menu has Save draft or
  Switch to draft, View, Focus mode, and Move to trash (no Copy link or
  Duplicate); Tab in the body moves focus; the drawer isn't remembered;
  the footer has no line and column. Below 480px the save state is its
  dot, with its words read out.
- **Media** (D-251): no upload, alt text, captions, or "used in" yet;
  bundle files are in the editor's picker, not the library screen.
- **Content types** (D-250): read-only; no field editor, new-type wizard,
  or delete yet. "Show in the sidebar" and a hierarchy switch aren't type
  settings in Blush. Entries counts are what the account may edit.
- **Roles and accounts** (D-249): read-only, with a notice naming where
  each is changed; no Invite, role checkboxes, or danger zone yet; roles
  have no description, and the last sign-in stands in for "last active".
- **Component options** (D-245): an option set back to its default is
  removed from the directive; a required one left empty stays as
  `key=""`. Removing a container keeps its body. Option changes are
  applied to the text directly, so they aren't in the field's own undo.
- **Vocabulary follows Blush** where it differs: extensions, not addons, and
  whatever taxonomies a site defines (no built-in Topic).
- **Markdown reads as it looks** (D-253): emphasis italic, strong text
  and headings semibold, quotes muted and italic, list markers in the
  accent, and the marks (`*`, `#`, `>`, a link's address) in `--fg-3`.
  Headings stay the body's size: the highlighted copy has to line up
  with the text area, so nothing may change a character's width.
- **Fira Code is the mono** (D-254, D-255), not IBM Plex Mono: 400, 500,
  and a real 600 (so bold keeps its width), and a slanted italic (it has
  none). The writing column is 640px (`--measure`), with nothing between
  the title and the body.
- **Editor addresses by handle** (D-253): `/content/{type}/{key}`, under
  the type's list, not the file's path.
- **Paths stay out of the UI** (D-254): tables show an entry's address on
  the site (`/archives/…`), not its file or folder, and untitled entries
  are "Untitled". Files belong in an info box, later.
- **Row menus** (D-254): Edit, View and Copy link once live (View archive
  for terms), and Move to trash; the trash's are Restore as a draft and
  Delete permanently. No Duplicate yet (no API), and no Preview of a
  trashed entry. The floating list opens above the button when there's
  no room below.
- **The pinned index page** (D-255): a collection's or taxonomy's
  landing page. With no checkbox column (no bulk actions yet), the pin
  sits before the title. It's pinned on the list's first page only, not
  on every page as "survives … paging" says (D-264). In a tree list its
  title leaves the triangle's space like the rows below it. Only the
  tables so far: not the editor's side
  of the pattern, the type screen's switch, or a new type being born
  with one; the editor's menu still offers Move to trash.

---

## 1. Vocabulary

*Provisional — see Status. These came from a design conversation, not from the
API. Reconcile with the real field names before treating them as settled.*

The words in the interface are part of the design. Use them consistently in
code and in copy.

| Term | Means | Not |
|---|---|---|
| **Entry** | One piece of content of any type | Post, item, node, document |
| **Content type** | A user-definable type; taxonomies are a special kind | Collection, model |
| **Term** | An entry of a taxonomy type — a topic or a tag | Category object |
| **Page** | Core type, hierarchical | — |
| **Post** | Type provided by the Posts addon | — |
| **Topic** | The Posts addon's hierarchical taxonomy | Category |
| **Tag** | Flat taxonomy | Label, keyword |
| **Account** | A person who can sign in | User |
| **Role** | A named set of capabilities, many per account | Group |
| **Capability** | One permission | Permission, scope |
| **Component** | An author-inserted directive in Markdown | Directive, shortcode, block |
| **Addon** | A server-side extension | Plugin, module, extension |
| **Media** | The file library | Attachments, assets, uploads |
| **Index page** | The entry that is a type's archive | Archive settings, listing page |

Counting reads naturally: "12 entries", "3 accounts", "No pages match".
Type names take their own noun in context: "New page", "New post", "New
release" — derive the button label from the type, never hardcode "New entry".

## 2. Principles

1. **The table is the product.** Most admin time is spent scanning a list.
   Legibility and density beat decoration everywhere they conflict.
2. **State is visible without reading numbers.** Anything that needs attention
   carries a shape — a pill, a dot, an outline — not just a different figure.
3. **One component per job, varied by declaration.** Types differ in data, not
   in code paths. The entries list is one component for every content type;
   what changes is what the type declares.
4. **Chrome recedes.** One accent hue, achromatic surroundings. Color spent on
   status and selection, not on making things look designed.
5. **Nothing invented.** No metric appears unless the API can produce it. A
   panel that summarizes derived state says so.
6. **Destructive and irreversible actions look different from safe ones** and
   never sit adjacent to a common action.

## 3. Theming contract

Two attributes on `<html>`:

```html
<html data-admin-theme="neutral" data-color-scheme="dark">
```

- `data-admin-theme` — absent means `neutral`. Addons may ship more.
- `data-color-scheme` — `light`, `dark`, or absent to follow the OS.

**Both are per-account preferences, not site settings.** They belong to the
signed-in account, persist server-side, and follow that person to any device.
Two people working on the same site can be in different themes at once. They
are edited on **Your profile**, never under Appearance — Appearance is the
site's own theme, which is a different thing with a different audience.
Browser storage may cache the choice to avoid a flash on load, but the account
record is the source of truth.

Four states per admin theme. The cascade order in `tokens.css` matters:

```css
:root                                            /* all tokens, light */
@media (prefers-color-scheme: dark) {
  :root:not([data-color-scheme="light"]) { }     /* dark, system */
}
:root[data-color-scheme="dark"] { }              /* dark, explicit */
```

Rules that hold for every theme, bundled or third-party:

- Every token is first defined on bare `:root`. A token that exists only inside
  a media query or an attribute selector fails for the majority of viewers, who
  set nothing.
- `body` sets an explicit `background` from a token.
- Wherever the dark palette applies, also set `color-scheme: dark` so native
  controls and scrollbars follow.
- Component CSS reads tokens only. A literal color in a component is a bug,
  because it survives a theme switch.
- A theme redefines token **values**, never token **names**, and adds no
  selectors of its own beyond its `[data-admin-theme="id"]` blocks.

An admin theme may change color, type, radius and density. It may not change
layout structure, component markup, or which elements exist.

## 4. Type

Four roles, all tokenised:

| Token | Role |
|---|---|
| `--font-ui` | Everything by default: controls, labels, body |
| `--font-display` | Page titles, panel headings, stat figures, site name |
| `--font-title` | Entry titles in lists and rows |
| `--font-mono` | Machine data only |

`--font-display` and `--font-title` are separate on purpose: a theme may want a
serif for page headings but not for forty table rows.

**Mono is for machine data, and only that**: slugs, paths, timestamps, IDs,
counts in badges, file sizes, keyboard shortcuts. Never for prose or labels.

Scale, from `tokens.css`: `--base` 13px, `--h1` 22px, entry titles
`--title-size`. Everything else is a fixed step off those — 11px for uppercase
micro-labels (with `.04em`–`.07em` tracking), 11.5–12.5px for secondary text.
Do not introduce new sizes without adding a token.

Give headings `text-wrap: balance`. Give uppercase labels letter-spacing.
Anywhere digits stack in a column, `font-variant-numeric: tabular-nums`.

## 5. Color use

- **Accent** — primary buttons, links, focus rings, selected rows, the active
  nav marker, the active tab underline, chart marks. One hue. If something
  needs to stand out and is not one of those, the answer is weight or position,
  not another color.
- **Status** — `good` / `warn` / `danger` are reserved for state. Never reuse
  them decoratively or as a chart series. Each has three steps: `--good` for
  text, `--good-soft` for the fill behind it, `--good-dot` for the marker.
- **Status is never color alone.** Every status pill carries a dot *and* a
  word. This is a hard rule, not a preference.
- **Neutrals carry the interface.** Surfaces step `--bg` → `--surface` →
  `--surface-2` → `--surface-3`; ink steps `--fg` → `--fg-2` → `--fg-3`.

## 6. Layout

```
┌──────┬────────────┬──────────────────────────────┐
│ icon │ section    │ top bar              --bar   │
│ rail │ panel      ├──────────────────────────────┤
│ 66px │  --rail    │                              │
│      │            │ work area (scrolls)          │
│ Home │ Pages   16 │   .wrap  max --work-max      │
│ Cont.│ Posts  248 │                              │
│ Str. │  Topics 14 │                              │
│ Site │ Media      │                              │
└──────┴────────────┴──────────────────────────────┘
```

**Two levels, on purpose.** One sidebar cannot hold a site's content types,
their nested taxonomies and its settings without becoming a wall. The icon rail
carries three sections — **Home, Content, Config** — and the panel beside it
shows **only the active section**, so its length is bounded by the section
rather than by the whole admin.

Three sections, not more. Taxonomies are content, so they live under Content
with their types. *Defining* a content type is configuration, so the type
builder lives under Config alongside settings, addons and people. The test for
a new section is whether someone would go looking for it by name — not whether
the things in it are related.

Rules that keep it honest:

- **Rail items are labeled**, not icons alone. "Structure" has no guessable
  glyph, and a tooltip is not a label.
- **Switching sections does not navigate.** It changes what the panel offers;
  the screen stays put until something in the panel is clicked. The exception
  is Home, which is a single screen and so navigates directly.
- **The panel collapses to nothing**, leaving just the rail. That is the real
  space win, and it beats the old icon-only collapse, which was unreadable.
- **The cost is one click** to reach anything outside the current section. The
  command palette is the answer for anyone who feels that, which is why ⌘K
  stays prominent.
- A section may carry a **state dot** on its rail icon — Content shows one when
  live entries have unpublished changes — so attention survives the partition.

Remaining layout rules:

- The app is `height: 100%`, not `100vh`. The work area is the only scroll
  container; the rail, panel and top bar do not move.
- Below 860px the rail and panel slide in together as one off-canvas drawer
  with a scrim, and the collapse toggle is replaced by a menu button.
- Work area padding: 22px/24px desktop, 16px at phone width. At least a 16px
  side gutter at every width. The page body never scrolls horizontally; only
  the table does, inside its own `overflow-x: auto`.
- Space siblings with flex/grid `gap`, not per-element margins.

Breakpoints: **1100px** (stat row to 2-up, two-column panels stack),
**860px** (rail to drawer), **640px** (phone: tighter padding, search bar
collapses to an icon, breadcrumb root drops).

## 7. Components

### Buttons
Height 30px (`sm` 26px), `--r-1`, `--shadow-1`. Four kinds:
`default` (surface + border), `primary` (accent fill), `ghost` (transparent,
for toolbars), `danger` (danger ink, danger-soft on hover). Icon at 16px,
6px gap, label always present except in a labeled icon-button group.

### Status pills
20px tall, fully rounded, `<dot><label>`. Statuses: Published, Draft,
Scheduled, Trash. **Changes** is a second pill shown beside Published, in warn,
for a live entry with unpublished edits — it is a modifier, not a status.

### Panels
`--surface` on `--r-3`, `--shadow-1`, hairline border. A header row with an
`h2` at `--font-display`, an optional muted hint, and actions pushed right.
Content sits below with `--pad-x` horizontal padding.

### Stat tiles
Label with a 14px icon, figure at `--stat-v` in `--font-display`, one line of
context beneath. Context is a real comparison or breakdown, never filler. A
tile whose value demands action takes a `--warn-dot` border; at most one tile
per dashboard may do this.

### Tables
Sticky header, uppercase 11px column labels, hairline row separators, hover on
`--surface-2`, selected rows on `--accent-soft`. Row padding is `--pad-row` so
density follows the theme; a separate dense toggle halves it.

Core columns only, the same for every type: **Title, Status, Author, Updated**.
Custom fields never become columns. Title carries the slug beneath it in mono,
hidden in dense mode.

Row actions live in a `⋯` menu revealed on hover and always present to keyboard
focus. Bulk selection uses a checkbox column with a tri-state header
(`aria-checked="mixed"`).

### Tabs, toolbar, filters
Status tabs with counts sit directly under the page header. Filters live in one
row below them: search, then selects, then toggles, with view/density controls
pushed right. A **Clear filters** button appears only when a filter is active.

### Bulk bar
Floating pill, fixed to the bottom centre, appearing only with a selection.
Carries the count, the safe actions, a divider, then destructive actions, then
Clear. It respects the bottom safe-area inset.

### Menus, command palette, toasts
Menus are fixed-position, `--shadow-2`, flipping above the trigger when they
would overflow. The palette (`⌘K`) is modal over a scrim and searches commands
first, then entries. Toasts stack bottom-right, one at a time, auto-dismiss at
~2.6s, and state what happened in the past tense.

### Empty states
Icon, a heading that names what is missing, one sentence explaining why, and
the action that resolves it. An empty result from filters offers **Clear
filters**. Never a bare "No data".

## 8. Patterns

### Entry lifecycle
`draft → published`, plus `scheduled`, plus `trash`. No review step, no
revisions.

### Autosave and pending changes
Unpublished entries autosave continuously. A **published** entry autosaves to a
pending draft; the live version is untouched until **Update** is pressed. This
produces a state that must be visible everywhere the entry appears:

- the list: a `Changes` pill beside `Published`
- the toolbar: an **Unpublished changes** filter toggle
- the row menu: **Update — publish changes** and **Discard changes**
- bulk: **Discard changes**

Autosave shows its state as quiet text near the title — *Saving…* → *Saved
14:32* — never as a toast, never as a spinner that blocks typing.

### The editor is a writing surface
Every other screen in this admin is a tool. The editor is a place someone
writes for an hour, so it is designed against a different measure: how little
of it you notice.

- **One centered column.** The source sits in a `68ch` measure with the
  remaining width as margin. Full-width lines are unreadable, and a column that
  moves when a panel opens is worse than one that does not.
- **Settings push, never cover.** The drawer widens the layout aside rather
  than sliding over the text, so nothing is hidden behind a panel while it is
  open. Closed by default; ⌘/ or the header button opens it. Below 980px there
  is no room to push, so it overlays instead.
- **Two tabs in the drawer, both always visible**: *Document* for the entry,
  *Component* for whatever the caret is inside. Neither replaces the other's
  header and there is no back arrow — you can always see where you are and what
  the alternative is. The Component tab is disabled with no selection, and
  names the component and its kind when there is one.
- **The title is part of the document**, not a form field above it — display
  face, 30px, no box, wrapping to as many lines as it needs, and it scrolls
  away with the text. Enter moves to the body.
- **Chrome recedes while typing.** The header and footer fade to a third
  opacity as soon as keys move and come back on any pointer movement. Nothing
  disappears; it just stops competing.
- **Focus mode** (⌘⇧F, or the palette) drops the rail and top bar entirely,
  leaving the column. Escape returns.
- **The footer is the status line**, not a toolbar: words, reading time, and
  quiet shortcut hints. Counts belong here, out of the way, not above the text.
- **A selected component is named, not opened.** When the drawer is closed and
  the caret enters a directive, a chip appears in the footer — "Callout
  options" — and opens the drawer only if clicked. Interrupting writing to show
  a panel nobody asked for is the thing this design is against.
- **Unpublished changes are ordinary, not exceptional.** They get a small
  `Changes` pill beside the status and the primary button reading **Update** —
  no banner. Discard lives in the overflow menu. A bar across the top of the
  screen is for something that has gone wrong: a failed save, a newer version on
  the server, a required field blocking publication. Routine state does not earn
  one, and spending the banner on routine state means nobody reads the ones that
  matter.

- **The header has two halves, and the split is meaningful.** On the left, back,
  where you are, a hairline, then the three insert tools — things you do *to*
  the document. On the right, save state, status, settings, overflow, and the
  primary action — what the document *is* and what happens to it. Reading the
  toolbar should not require remembering where a given control was put. The
  insert button also sits directly above the panel it opens, so the panel reads
  as coming from the button rather than appearing beside the text.

What earns a permanent place in the header: back, where you are, the three
insert tools, save state, status, settings, overflow, and the primary action.
Everything else is in the overflow menu or a shortcut.

### The inserters
Three ways to put something in an entry, and the shape of each follows how much
of a decision it is.

**Components slide in from the left.** Choosing one is part of writing, often
with browsing involved, so the panel widens the layout aside and stays open —
it never covers the sentence you were writing, and it does not snap shut after
one pick. Inside: a search field, category pills, and a two-column grid of
tiles — an icon in a tinted square, the name beneath, the kind under that. A
strip at the foot of the panel describes whatever is highlighted, so the grid
stays uncluttered while the detail is still one glance away.

Keyboard maps to the grid: left and right step, up and down move a row, Enter
inserts, Escape closes. Hovering a tile highlights it, so pointer and keyboard
agree on what Enter would do.

**Typing `/` at the start of a line opens the same panel**, with what follows
filtering it. The query lives in the document while it is typed and is removed
when something is inserted, so an abandoned slash is just text. This is the
path most authors end up using; the toolbar button is the discoverable one that
teaches it.

**Icons are a popover** anchored to their button: a search field, a dense grid,
and a foot showing the directive the highlighted icon would produce. One
decision, no browsing, so it does not deserve to move the layout. Its button is
a shapes glyph — triangle, square, circle. Avoid a sparkle or a star here, or
anywhere that is not generation: a sparkle now reads as "AI" before it reads as
anything else, and an icon that promises the wrong thing is worse than a dull
one.

**Media is a modal** — the same picker used everywhere else in the admin, so a
file is chosen the same way wherever you are. What it inserts depends on what
was chosen: an image becomes a figure, a video becomes a video, anything else
becomes a download.

The rule: **a panel for choices you browse, a popover or modal for choices you
make once.** Anything that interrupts writing should be over in a single action.

### Hierarchy
Hierarchical types (Pages) default to a **tree**: disclosure triangles, 18px
indent per level, expansion state held client-side. Sorting a column or
applying any filter flattens the tree — when that happens, show a bar above the
table saying why and how to get the hierarchy back. Pagination is suppressed in
tree mode; the count reads "Showing all 14 pages as a tree".

Flat types get a sortable, paginated table. Both are the same component.

### List, then detail — everywhere
Every collection in the admin uses the same two-screen shape: a **full-width
list screen** and a **dedicated detail screen** reached by clicking the row's
name. This holds for entries, taxonomy terms, media, accounts, content types
and roles alike — a collection of five things gets the same treatment as a
collection of five hundred, because consistency is worth more than the space
saved on a short list.

No master/detail split panels. A two-column list-beside-editor layout halves
the width available to a form that needs it, hides the list on a phone, and
gives the same object two different appearances depending on how you arrived.

Each detail screen carries: the object's name as the page title, a subtitle of
its identifying facts, a **back button naming the collection** ("All types",
"All roles"), and its primary action. Creating follows the same rule — the type
wizard is its own screen, not a modal over the list.

A list screen earns tabs when its rows divide into meaningful states (entry
status, type kind) and a search box when the collection grows without bound.
Roles has neither: four fixed rows need no search, and inventing one is worse
than leaving it out.

### Sidebar grouping
A taxonomy attached to **exactly one** content type is nested under that type
in the sidebar — Topics under Posts, Product categories under Products. A
taxonomy attached to none, or to more than one, cannot nest without lying, so
it sits in **Structure** with a caption naming the types that use it: "Tags ·
Posts, Releases", collapsing to "3 types" when the list gets long.

Nesting is one level only. Content types never nest inside each other, and a
nested taxonomy never gains children of its own.

**The known cost, stated plainly:** where a taxonomy lives depends on data, so
attaching a second type moves it out of the nest and into Structure with no
action by the person watching. The rule "navigation should not rearrange
itself" is being traded away for a relationship that is worth showing. Two
things keep it honest — the move is announced with a toast that says why, and
**Content types** remains the flat index that lists every taxonomy whatever
group it is in.

*Provisional, and expected to change.* The relationship that actually binds a
group together is **provenance** — the addon that registered them. A Shop addon
brings Products, Product categories and Product brands at once; they arrive and
leave together, a type has exactly one source forever, and grouping by source
never duplicates and never rearranges. When addons register content types, the
grouping axis should move from attachment to source, and this section should be
rewritten rather than extended.

### The index page is an entry, pinned
A content type that has an archive has exactly one **index page** for it, and
that index page is an ordinary entry: the same editor, the same Markdown body,
the same status, author and updated stamp. Its title and slug are content, not
a string derived from the type — Posts is the type, `Writing` at `/blog` is the
archive someone actually wrote. The type's URL base and the index page's slug
are the same value seen from two screens; changing either changes both.

It is **pinned at the top of the type's list**, in its own `tbody` above the
rows, so it survives sorting, paging and the status tabs. Everything else about
the row is identical to its neighbors — same columns, same status pill, same
overflow menu. Only two marks say what it is: a pin where the checkbox would
be, and an `Index` tag beside the title.

What follows from it being singular and permanent:

- It is **not counted** in the type's totals. "Posts 28" means 28 posts; the
  archive is not one of them, and the tab counts ignore it too.
- It **never appears in Trash**, and its row menu has no Duplicate and no Move
  to trash. Leave those out rather than showing them disabled — a menu of dead
  items reads as a bug.
- It is **not bulk-selectable**, which is why the checkbox is a pin.
- Under a search or filter it behaves like any other row: it stays pinned if it
  matches and disappears if it does not. Pinning is about position, not about
  exemption from the filters.
- In the editor it loses what does not apply — the type's custom fields, the
  taxonomy picker, the scheduled status — and gains one line in the Document tab
  saying what it is and that it cannot be deleted.
- A type with an archive is **never empty**: a new type is born with its index
  page, so the first-run empty state sits *below* a table that already has a row.

Whether a type has one is a per-type switch, set in the type wizard and on the
type's detail screen, shown there beside a link to the index page itself. Pages
ships without one, because a page tree has no archive — its root is the site.
Turning the switch off does not delete the entry; it stops the site routing to
it.

*Provisional:* Pages having no index is a judgment about hierarchical types, not
a rule. If a hierarchical type turns out to want an archive, the switch already
exists — nothing else in this pattern depends on the type being flat.

### Taxonomy terms are entries
A topic or a tag is an entry of a taxonomy-kind content type, so it gets the
same treatment as a page or a post: the same list screen, the same status
lifecycle, and **the same editor**. A term's description is its body, written
in Markdown with components, not a one-line field in a form. Never build a
separate inline add/edit form for terms — that would make taxonomies a second
class of thing, which the model says they are not.

Terms differ only in what the list shows: an **Entries** column counting uses,
no author, and no pending-changes state. Deleting a term reparents its children
rather than orphaning them, and says so.

### Trash
Trash is a status, not a separate screen. A trashed entry's row menu replaces
the ordinary actions with **Restore as a draft** and **Delete permanently**,
and the Trash tab gains an **Empty trash** action. Restoring returns an entry
as a draft rather than to its previous status, because a silent republish is
worse than an extra click.

### Loading
A screen waiting on the API shows **skeletons in the shape of the content**,
not a spinner: table rows with bars where the title, status, author and date
will be, cards with a blank thumbnail in the media grid, tiles and list rows on
the dashboard. The page chrome — header, tabs, filters — renders immediately
and stays put, so nothing jumps when the data lands. The skeleton is a
one-directional shimmer, and it holds still under
`prefers-reduced-motion: reduce`.

Row counts in skeletons are a guess at the real count, not a fixed number; six
rows reads as "a list is coming", twenty reads as a promise you may not keep.

### Offline and failed saves
Losing the connection shows one bar under the top bar, in warn, saying that
changes are kept on the device — never a modal, and never a block on typing.
The editor's save indicator becomes **Waiting for a connection** rather than
claiming a save that did not happen.

A save that fails shows the failure **where the save state lives** (the
indicator turns red) and a bar with a Try again action. The copy leads with
what is safe: the change is still on the device, nothing is lost. Never an
apology, never "Oops".

### Edit conflicts
This is the state an autosaving editor gets wrong most often. When the server
has a newer version than the one being edited, the editor stops saving, says
who saved it and when, and offers three ways out: **Keep theirs**, **Compare**,
**Keep mine**. It never resolves silently in either direction, and it never
discards the local edit to fetch the remote one without asking.

The rule: an autosave may not overwrite someone else's work, and a conflict may
not lose the typing that caused it.

### Validation
Required fields come from the type, so validation is data-driven rather than
hand-written per screen. Publishing with a required field empty does not
publish. It shows a count in the notice bar, marks each offending field, and
puts the reason under the field itself. Errors clear as each field is filled,
not only on the next publish attempt. A draft still saves — validation gates
publishing, not saving.

### Empty and first-run
Distinguish **no results** from **nothing yet**. A filter that matches nothing
offers to clear the filter. A type with no entries at all shows what the type
is for and the action that creates the first one, and hides the tabs and the
filter bar entirely — there is nothing to filter, so the controls are noise.

A site with no content at all replaces the dashboard with a short numbered
setup path: first page, first content type, media, invite people. Each step
does the thing rather than linking to documentation about it.

### Type-driven variation
The list screen reads these from the type and changes nothing else:
`label`, `singular`, `icon`, `hierarchical`, and its taxonomy (which produces
one filter select). The New button, the empty state, and every action toast
take their noun from `singular`.

### Permissions
Capabilities are read-only in this pass, but the UI is capability-aware from
the start.

*Provisional:* an action the account cannot perform is **hidden**, not
disabled — a disabled control with no explanation is worse than its absence.
The exception is a control whose absence would make a layout look broken; that
one is disabled with a tooltip naming the missing capability. This is a guess
made before any role had real capabilities attached. If hiding turns out to
confuse operators who are told a feature exists and cannot find it, invert it.

## 9. Motion

Transitions are 120–180ms, ease-out, and limited to: rail collapse, drawer
slide, disclosure rotation, toast and bulk-bar entry. Nothing animates on
initial render — the first frame is complete. Everything is disabled under
`prefers-reduced-motion: reduce`.

## 10. Copy

- Write from the operator's side. "Move to trash", not "Soft-delete entry".
- Buttons name the outcome; toasts confirm it in the past tense. **Publish** →
  *Published 3 posts*.
- Errors say what went wrong and what to do. No apologies, no "Oops".
- No exclamation marks, no emoji in the interface.
- Sentence case for buttons, labels and headings. Uppercase only for the 11px
  micro-labels, which take tracking.
- Counts are exact. "1,204 files", not "over a thousand".
- **US English throughout** — color, gray, behavior, organized, catalog. This
  applies to interface copy, code identifiers, CSS token names and comments.

## 11. Vue conventions

```
src/
  styles/
    tokens.css          only file with literal values
    base.css            resets and element defaults
  components/
    layout/    AppShell, AppRail, AppTopbar, CommandPalette
    data/      EntryTable, EntryRow, StatusPill, BulkBar, TablePager, EmptyState
    ui/        BaseButton, BaseSelect, SearchInput, SegmentedControl,
               DropdownMenu, ToastHost, StatTile, PanelCard
  composables/
    useEntries, useContentTypes, useSelection, useAutosave, useCapabilities
```

- Scoped styles per SFC, tokens only. No utility framework.
- A component that renders type-specific content takes the type object as a
  prop and branches on its declared flags — never on a type key string.
- Selection is a `Set` of ids in a composable, not row-level state.
- Every interactive element is a real `<button>` or `<a>`; never a `div` with a
  click handler.
- `aria-current="page"` on the active nav item, `aria-selected` on tabs,
  `aria-sort` on the sorted column header, `role="checkbox"` with tri-state
  `aria-checked` on the select-all control.

## 12. Do not

The first four break the interface for real people and hold regardless of how
the project evolves:

- Write a literal color, font stack, or radius in component CSS.
- Define a color token only inside a dark or `[data-admin-theme]` block.
- Convey status by color alone.
- Use `100vh` for the app height.

The rest are strong defaults. Break them with a reason, and record it:

- Giving a custom field its own table column.
- Showing a spinner where a skeleton row or quiet text would do.
- Resolving an edit conflict without asking, in either direction.
- Reporting a failure without saying what happened to the person's work.
- Adding a second accent hue.
- Using a sparkle or star icon for anything that is not generation.
- Rounding every container the same amount — radius marks a thing as a separate
  object, so spend it deliberately.
- Putting a destructive action next to a common one without a divider between
  them.
- Building a master/detail split panel where a list screen and a detail screen
  would do.

## 13. Open questions

Unresolved at the time of writing. If you settle one, replace it here with the
decision.

- *Settled:* API conventions (session cookie and CSRF header, `page`/`per`
  paging with `total` and `pages`, errors as `{"error"}`; `docs/admin.md`),
  content type discovery (`GET types`, with `label` and `singular`; D-234),
  the Markdown surface (a text area over a highlighted copy, D-241), the
  component inserter (`GET components`, D-243, D-247), the settings' two
  tabs (D-245), mapping the caret to its directive and rewriting options
  in place (`markdown.ts`, D-241, D-245), and invoking the media picker
  from an option (**Choose** beside it, D-247).
- The content-type builder's own screens.
- Whether type provenance ("Posts addon", "Custom type") belongs in the list
  header at all — useful at three types, clutter at fifteen.
