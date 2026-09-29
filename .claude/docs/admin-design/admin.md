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
- **Terms aren't hierarchical**, so there's no reparenting on delete (D-236).
- **Vocabulary follows Blush** where it differs: extensions, not addons, and
  whatever taxonomies a site defines (no built-in Topic).

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
┌────────────┬──────────────────────────────────────┐
│ site       │ top bar                    --bar     │
│ header     ├──────────────────────────────────────┤
│            │                                      │
│ nav        │ work area (scrolls)                  │
│  --rail    │   .wrap  max --work-max, centered     │
│            │                                      │
│ rail foot  │                                      │
└────────────┴──────────────────────────────────────┘
```

- The app is `height: 100%`, not `100vh`. The work area is the only scroll
  container; the rail and top bar do not move.
- Rail collapses to `--rail-min` (icons only) on a toggle.
- Below 860px the rail becomes an off-canvas drawer with a scrim, and the
  collapse toggle is replaced by a menu button.
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

### Hierarchy
Hierarchical types (Pages) default to a **tree**: disclosure triangles, 18px
indent per level, expansion state held client-side. Sorting a column or
applying any filter flattens the tree — when that happens, show a bar above the
table saying why and how to get the hierarchy back. Pagination is suppressed in
tree mode; the count reads "Showing all 14 pages as a tree".

Flat types get a sortable, paginated table. Both are the same component.

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
- Adding a second accent hue.
- Rounding every container the same amount — radius marks a thing as a separate
  object, so spend it deliberately.
- Putting a destructive action next to a common one without a divider between
  them.

## 13. Open questions

Unresolved at the time of writing. If you settle one, replace it here with the
decision.

- *Settled:* API conventions (session cookie and CSRF header, `page`/`per`
  paging with `total` and `pages`, errors as `{"error"}`; `docs/admin.md`) and
  content type discovery (`GET types`, with `label` and `singular`; D-234).
- The editor: the Markdown surface (a plain text area for now, D-233), the
  component inserter, and whether the right sidebar swaps between Document
  fields and Component options.
- How the parser maps cursor position to the directive under it, and rewrites
  component options back into the source without disturbing the author's text.
- The content-type builder's own screens.
- How the media picker is invoked from a component option.
- Whether type provenance ("Posts addon", "Custom type") belongs in the list
  header at all — useful at three types, clutter at fifteen.
