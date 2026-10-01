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
docs/ui-design-direction.md     this file
src/styles/tokens.css           the token definitions
```

Add to `CLAUDE.md`:

```md
## Admin UI

`docs/ui-design-direction.md` describes the intended direction for the admin
SPA. Read it before writing admin UI. It is a prototype-stage document, not a
spec — follow it where it fits the project, depart from it where it does not,
and update it in the same change when you do.

Two things in it are not negotiable without a deliberate decision: the theming
cascade, and that all color, type, radius, spacing and density values come from
`src/styles/tokens.css` as `var(--token)`. Never write a literal color,
font-family or px radius in component CSS.
```

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
| **Element** | Any one thing a document is made of — a paragraph, a heading, a table, a component | Block, node |
| **Content** | What an element holds, one level down, as shown in its panel | Children, body |
| **Outline** | The whole document as a list of its elements | Structure, tree, TOC |
| **Component** | An author-inserted directive in Markdown — one kind of element | Directive, shortcode, block |
| **Addon** | A server-side extension | Plugin, module, extension |
| **Media** | The file library | Attachments, assets, uploads |
| **Index page** | The entry that is a type's archive | Archive settings, listing page |

Counting reads naturally: "12 entries", "3 accounts", "No pages match".
Type names take their own noun in context: "New page", "New post", "New
release" — derive the button label from the type, never hardcode "New entry".

## 2. Principles

1. **The table is the product.** Most admin time is spent scanning a list.
   Legibility beats decoration everywhere they conflict — but legibility is not
   the same as density. A list that fits more rows on screen is not easier to
   read; a row with room around it is. Spend the space, and give anyone who
   wants the rows back a compact toggle rather than shipping compact by default.
2. **Space is the cheapest material available.** When a screen feels wrong, the
   first thing to try is more room — not another border, tint, shadow or rule.
   Most "cramped" is a padding value, and most "busy" is a separator that space
   would have made unnecessary.
3. **State is visible without reading numbers.** Anything that needs attention
   carries a shape — a pill, a dot, an outline — not just a different figure.
4. **One component per job, varied by declaration.** Types differ in data, not
   in code paths. The entries list is one component for every content type;
   what changes is what the type declares.
5. **Chrome recedes.** One accent hue, achromatic surroundings. Color spent on
   status and selection, not on making things look designed.
6. **Nothing invented.** No metric appears unless the API can produce it. A
   panel that summarizes derived state says so.
7. **Destructive and irreversible actions look different from safe ones** and
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
The mono face is **Fira Code** — it is also the editor's writing face, where its
wider figures and clearly distinguished `l 1 I` / `0 O` matter most. Its
ligatures are a stylistic set, not a default; leave them off in the admin so a
slug reads as the characters it contains.

Scale, from `tokens.css`: `--base` 13px, `--h1` 26px, entry titles
`--title-size`. Everything else is a fixed step off those — 11px for uppercase
micro-labels (with `.04em`–`.07em` tracking), 11.5–12.5px for secondary text.
Do not introduce new sizes without adding a token.

Give headings `text-wrap: balance`. Give uppercase labels letter-spacing.
Anywhere digits stack in a column, `font-variant-numeric: tabular-nums`.

### Icons

**One set: [Lucide](https://lucide.dev).** Not "mostly Lucide" — a single
drawn-by-one-hand set is most of what makes an interface look assembled rather
than collected. Every glyph in the admin comes from it, including ones an addon
contributes.

- Ship them as an inline SVG sprite of `<symbol viewBox="0 0 24 24">` and use
  them with `<use href="#i-name">`. One request, no font, no runtime library.
- Symbols carry **geometry only** — no stroke, fill or size attributes. One CSS
  rule (`.ic`) sets `stroke: currentColor`, `fill: none`, `stroke-width: 1.6`
  and round caps and joins, so an icon inherits the color of whatever it sits
  in and a theme can change the weight in one place.
- Keep Lucide's own geometry. Redrawing a path to "fix" it at 16px breaks the
  optical consistency that made the set worth choosing.
- Two sizes: **16px** inline with text and in controls, **18–22px** where an
  icon stands alone. Below 16px a 24-grid icon turns to mush; scale the box, not
  the stroke.
- Name symbols for the **thing**, not the picture: `i-page`, `i-trash`,
  `i-schema`. When two ideas want the same glyph, one of them is using the wrong
  metaphor.
- An icon alone is a label only where the same glyph appears in the same place
  every time — a row's overflow menu, a table's sort arrow. Everywhere else it
  needs a word beside it.

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

### Elevation

**Flat by default.** `--shadow-1` is `none`. Panels, tables, cards, buttons,
selects and stat tiles separate themselves with a border and a surface step, not
with a drop shadow. A page of forty softly-shadowed rectangles reads as forty
things floating at slightly different heights, which is both busier and less
legible than forty things sitting flat with clear edges.

Two levels of shadow remain, and both mean "this is genuinely above the page":

- `--shadow-2` — menus, popovers, toasts. Small and short: they sit a couple of
  pixels above, not a couple of centimeters.
- `--shadow-3` — modals and off-canvas drawers, which also have a scrim under
  them doing most of the separating.

If something needs to look distinct and is not in that list, the answer is a
border, a surface step or space — never a shadow. Hover states change
background, never elevation.

## 6. Layout

```
┌──────┬────────────┬──────────────────────────────┐
│ icon │ section    │ top bar              --bar   │
│ rail │ panel      ├──────────────────────────────┤
│ 74px │  --rail    │                              │
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
- **The rail never navigates. Not even once.** Clicking a section changes what
  the panel offers and nothing else; the work area keeps whatever is open until
  something in the *panel* is clicked. There is no exception for Home, however
  tempting a single-screen section makes one — an author halfway through an
  entry who taps Content to check a term name would lose the entry, and a rule
  that holds four times out of five is a rule nobody can rely on. Two levels,
  two jobs: the rail scopes, the panel navigates.
- Switching sections keeps the current screen's panel item marked
  `aria-current="page"` when that screen lives in the section being shown, so
  coming back to a section tells you where you already are.
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
- Work area padding: `--s-6` (36px) desktop, `--s-4` at phone width. At least a
  16px side gutter at every width. The page body never scrolls horizontally;
  only the table does, inside its own `overflow-x: auto`.
- Space siblings with flex/grid `gap`, not per-element margins.

### Space

There is one spacing scale and nothing invents its own gap:

```css
--s-1: 4px   /* inside a control: icon to label */
--s-2: 8px   /* between adjacent controls */
--s-3: 12px  /* inside a small component */
--s-4: 18px  /* between fields; card padding */
--s-5: 26px  /* panel and section padding */
--s-6: 36px  /* between sections; screen gutter */
--s-7: 52px  /* above a page's first content */
```

Four density tokens sit on top of it and are the ones an admin theme moves:
`--pad-row` (table and list rows), `--pad-panel` (panel headers), `--pad-x` (the
horizontal padding shared by every panel, table cell and list row, so their
content lines up down the screen), and `--ctl` (the height of a button, input,
select or search field — one number, so a toolbar never looks assembled from
parts). Editorial runs looser than Neutral on all four; that difference *is*
most of what makes the two themes feel different.

Rules:

- **Left edges line up.** A panel heading, a table cell and a form label in the
  same column all start at `--pad-x`. When they disagree by two or three pixels
  the screen reads as slightly broken without anyone being able to say why.
- **Padding grows with the container.** A 40px chip does not get 26px of air,
  and a full-width panel does not get 8px. Roughly: small components take
  `--s-2`/`--s-3`, cards take `--s-4`/`--s-5`, screens take `--s-6`.
- **Vertical rhythm beats horizontal.** Given a choice, spend on row height and
  the gap between sections rather than on wider gutters; scanning is vertical.
- **Space before a separator.** Two groups far enough apart do not need a rule
  between them. Reach for `--s-5` before reaching for a border.
- **A compact toggle, not a compact default.** The list screens ship roomy and
  offer a density control that drops row padding and hides the slug line. The
  people who want 40 rows on screen get them by asking.
- **Empty states get the most room of anything**, because a screen with nothing
  in it is the one place where tightness reads as neglect.

Breakpoints: **1100px** (stat row to 2-up, two-column panels stack),
**980px** (editor panels overlay instead of pushing), **860px** (rail to
drawer), **640px** (phone: one step down the spacing scale, search bar
collapses to an icon, breadcrumb root and panel subtitles drop).

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

### Selects
The native `<select>` is the one control a browser refuses to let you style. Its
closed state can be made to fit; its open list cannot — that popup is drawn by
the operating system, in the system font, at the system size, with the system's
idea of a checkmark, and it ignores the admin theme entirely. In an interface
whose whole argument is one consistent surface, a control that opens a hole in
that surface is not acceptable, so the admin draws its own.

**The select is still the source of truth.** The real `<select>` stays in the
DOM, holding the value and firing `change`; it is only visually hidden
(`position:absolute; 1px; opacity:0; clip`, never `display:none`, which would
drop it from the form). A sibling button draws the current label and a caret,
and opening it builds the list from `sel.options`. Every screen keeps writing
options into the select and toggling `hidden`/`disabled` on it exactly as
before — none of them know the enhancement exists. A `MutationObserver` on
`childList`, `subtree` and the `hidden`/`disabled` attributes re-runs the
enhancement and re-syncs the labels on the next frame, so a re-rendered panel
never leaves a stale button behind.

```
enhanceSelects()   wrap every select:not([data-xs])
syncXsel(sel)      button label, hidden and disabled follow the select
toggleXsel(btn)    build .xs-list from sel.options, honoring data-depth
```

Requirements on the drawn list: it takes the trigger's width and flips above
when it would overflow; the current option is checked, not merely tinted;
`data-depth` on an option becomes indentation, which is how hierarchy reaches
the list (see *The parent dropdown shows the tree*); Escape closes the list
before anything else handles the key; and a click outside closes it. The button
carries `aria-expanded`, and the hidden select keeps the accessible name.

**Width is opt-in, not inherited.** The wrapper is `width:auto` by default, with
`as-inp` and `as-val` stretching to 100% and `as-sel` sitting `flex:none` in a
toolbar row. A drop-in replacement that silently makes every filter full-width
is not a drop-in replacement.

### Tabs, toolbar, filters
Status tabs with counts sit directly under the page header. Filters live in one
row below them: search, then selects, then toggles, with view/density controls
pushed right. A **Clear filters** button appears only when a filter is active.

### Bulk bar
Floating pill, fixed to the bottom center, appearing only with a selection.
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
- **Two tabs in the drawer, both always visible**: one for the entry, one for
  whatever the caret is inside. Neither replaces the other's header and there is
  no back arrow — you can always see where you are and what the alternative is.
- **Both tabs are named for what they hold, not for what they are.** The first
  is **the content type's own name** — *Post*, *Page*, *Release*, *Topic* — not
  "Document", because "document" is a word about software and a writer opening
  this drawer is editing a post. The second **renames itself to what it is
  showing**: "Callout", "Heading 2", "List", falling back to *Elements* for the
  outline or an empty selection. The panel under it changes every time the caret
  moves, so a constant label would be the one thing on screen not telling you
  anything. (`singular` is stored lowercase for use inside sentences — "New
  post" — so a name standing on its own is title-cased at the point of use.)
- **The tabs sit left, the close button sits right.** Tabs stretched to fill the
  width read as segmented buttons, not tabs, and a panel with no visible way to
  shut it sends people hunting the toolbar for the control that opened it. The
  first tab is **flush with the panel's edge**: its own padding lines its label
  up with the fields below, so the whole drawer shares one left edge.
- **The element tab is never disabled.** A disabled tab is a dead end that still
  costs a click to discover. It names whatever the caret is in, and in the rare
  state where the caret is nowhere it says so in words.
- **The Outline is a drilldown inside the entry tab, not a third tab and not a
  borrowed one.** "What is in this entry" is a question about the entry, so it
  is asked where the entry's other properties are: one quiet row at the foot of
  that tab, opening a panel *over* its fields with a way back.

  ```
  ←  Post / Outline                            36
  ```

  The path says what you left as well as where you are, so the panel reads as
  gone-one-level-deeper rather than replaced, and the tab above stays on **Post**
  the whole time — borrowing the element tab to show it made the drawer look
  like it had navigated somewhere else. Opening it also **scrolls the panel back
  to the top**: the row that opens it sits at the foot of a scrolled panel, and
  landing halfway down the list hides the elements it starts with. Picking a row
  selects that element, which is the one moment the element tab takes over,
  because the panel is now showing that element's options.
- **The drawer says nothing about `container` / `leaf` / `inline`.** That is the
  syntax's business, not the author's: they can see the shape of the thing in
  the text, and the word adds a vocabulary they never asked to learn.
- **No "go to it in the text" button.** The caret is already inside the
  component — that is why the panel is showing it. A button that goes where you
  already are is furniture.
- **The title is part of the document**, not a form field above it — display
  face, 30px, no box, wrapping to as many lines as it needs, and it scrolls
  away with the text. Enter moves to the body.
- **Chrome recedes while typing.** The header and footer fade to a third
  opacity as soon as keys move and come back on any pointer movement. Nothing
  disappears; it just stops competing.
- **Focus mode** (⌘⇧F, or the palette) drops the rail and top bar entirely,
  leaving the column. Escape returns.
- **The footer says where you are, and how much you have written.** Two things
  and nothing else: the **breadcrumb** on the left, **words and reading time**
  on the right. No shortcut hints — a hint that is always on screen is being
  read by nobody after the first day, and the space it takes is the space the
  breadcrumb needs. The commands those hints named live in the editor's own
  menu, which is where someone goes when they are looking rather than typing.
- **Attribute blocks are marked, and marked differently from directives.**
  `{.class #id key=value}` can hang off any ordinary Markdown — an image, a
  link, a heading, a paragraph — and an author who cannot see where one ends
  will break one. It gets a quiet gray chip: braces and punctuation in `--fg-3`,
  key/value pairs in `--fg-2`, and the class and id names in full `--fg`,
  because the names are what you scan a document for. The accent chip stays
  reserved for directives, so the two are never confused: accent means *this is
  a component*, gray means *this is metadata about the line it is attached to*.
  A brace that is not a valid attribute block — prose, or one inside a code
  span — is left as plain text, so the highlight doubles as a syntax check.
- **The toolbar is bigger than the admin's default.** 36px targets and 18px
  icons against 32/16 elsewhere, with more space between them. It is the most
  used toolbar in the product and the one people reach for without looking.
- **A selected element is named, not opened.** The breadcrumb follows the caret
  whether the drawer is open or shut, and opens it only when a crumb is clicked.
  Interrupting writing to show a panel nobody asked for is the thing this design
  is against.
- **The editor opens with both panels closed.** The section panel collapses on
  the way in and the settings drawer starts shut, so an entry opens as a column
  of text and nothing else. The list of other things you could be working on is
  not what you came here for. Both reopen on request, and **the section panel is
  put back the way it was found** when you leave — collapsing it for the editor
  is a courtesy, not a setting the editor gets to change on your behalf.
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

What earns a permanent place in the header: back, where you are, the four
insert tools, save state, status, settings, overflow, and the primary action.
Everything else is in the overflow menu or a shortcut.

### Marking the source
The editor shows Markdown, not a preview, so the highlighting *is* the typography
of the page. Two rules decide all of it.

**The words are the point.** Every syntax character — `#`, `**`, `>`, `-`, the
brackets around a link, the braces around an attribute — drops to `--fg-3`, and
the content it wraps keeps full ink. The page then reads as prose with faint
scaffolding rather than as code with prose in it. Nothing is hidden: an author
who cannot see where a `**` ends will break one.

**Nothing may change a character's advance width.** The highlight layer is a
`<pre>` sitting under a transparent `<textarea>`; if one of them lays out a
character a pixel from where the other does, the caret drifts away from the
letter it is on and the editor feels broken. So the palette is: color, weight,
slant, background, `text-decoration`, vertical padding, `border-radius`,
`box-shadow`. Never: `font-size`, `letter-spacing`, `font-family`, horizontal
padding or margin. Weight and slant are safe *because the face is monospaced* —
every weight shares one advance, and a synthesized oblique is a shear. This is
also why headings are told apart by weight rather than size.

| Element | How it is marked |
|---|---|
| Heading | `#`s dim; text full ink, 600 at h1–h2, 500 from h3 |
| Bold / italic / strike | Markers dim; content bold, oblique, or struck and dimmed |
| Inline code | Backticks dim, content on a `--surface-2` chip |
| Link | Brackets dim, **label in the accent**, target in `--fg-3` |
| Image | Marked exactly like a link: `!` and brackets dim, **alt in the accent**, source in `--fg-3`, **caption in full ink at 500** |
| Blockquote | `>` dim, quoted text `--fg-2` |
| List | Marker `--fg-2` at 600; text untouched |
| Task | `[ ]` dim, `[x]` in `--good`; the text is never struck through |
| Rule | `--fg-2` at 500 — it is a divider, it should divide |
| Table | Pipes dim; **header row cells full ink at 600**; the delimiter row dim except its alignment colons, which are `--fg-2` at 600 |
| Definition list | The term's line full ink at 600; the `:` dim; the definition full ink |
| Fence | Delimiters and body on a `--surface-2` slab, language named in `--fg-2` |
| Footnote | Reference and definition marker in the accent |
| Directive | Prefix dim, **name in the accent**, label in full ink |
| Attributes | The gray chip, wherever they appear |

Three consequences worth keeping:

- **A link's label is read in the sentence and its target is not.** Coloring the
  whole `[label](url)` in the accent makes a paragraph with three links unreadable.
  The label takes the accent; the URL steps back to `--fg-3`.
- **An image is marked as a link**, because that is what it is — a reference out
  of the document that happens to point at a picture. Alt text sits where a
  link's label sits and takes the accent; the path steps back the same way. The
  quoted caption is the exception: the reader sees it, so it takes full ink at
  500 while the quotes around it dim. Three parts, three weights, one glance.
- **A directive is not boxed.** Its name carries the accent and that is enough.
  The box is reserved for **the component the caret is inside**, so a highlight
  in the source always means *you are here* rather than *this is a component*.
  A container marks its **opener and its closer only** — never the body between
  them. The body is the writing; putting a tint behind three paragraphs an author
  is in the middle of is the opposite of a writing surface, and the two marked
  lines already say where the container starts and stops.
- **A selection is a tint, not a fill.** The textarea's own text is transparent —
  every color on screen comes from the `<pre>` underneath it — so an opaque
  selection background paints over the only legible copy of the text. The
  selection is a translucent accent (`color-mix`, ~26%) on the textarea, and
  transparent on the `<pre>`, so selected text keeps its highlighting and its
  contrast. This is the same class of bug as the advance-width rule: two layers
  drawing the same characters, and only one of them being looked at.
- **Some lines cannot tell you what they are.** A table's header row is a table
  row, and a definition list's term is a plain line of prose; only the block scan
  knows which is which. So the scan tells the highlighter — a set of line offsets
  passed in — rather than the highlighter guessing from the line alone.
- **Valid syntax is the only syntax that lights up.** A brace that is not an
  attribute block, a `*` that closes nothing, a `[` with no `]` — all stay plain
  text. The highlighting doubles as a syntax check: if it did not light up, it
  will not parse.

### The document panel
The Document tab holds everything about the entry that is not its text, which is
a lot of different kinds of thing. It works because it is a **list of label →
value rows**, not a stack of form fields.

```
Status      ✓ Published        ⌄
Visibility  ◉ Public           ⌄
Date        Tue 29 Sep 2026  08:20 am  ⌄
Slug        grid-survives-editors
```

The label states the question in flat ink; the value answers it in the accent
and opens whatever control that answer needs. **Boxes are spent only on things
you type into.** Ten bordered inputs stacked down a sidebar make ten identical
targets out of ten different decisions; a row list makes the *answers* the thing
you scan, which is what someone opening this panel came for.

Order is by how often it is touched: **Publish, Featured Image, Authors,
taxonomies, Summary, custom fields.** Every group has a heading, and a heading
may carry one piece of **right-aligned meta** — "2 selected", "84 / 160" — so a
glance tells you the state of a group you are not looking at.

**Status and Visibility are the same shape of decision**, so they share one
menu: a short list where every option carries a line saying what it *does*
("Not on the site. Only editors see it", "Readers need the password you set").
A status a person picks once a week is worth one sentence of explanation.

**The date opens a month.** Monday-first grid, today ringed, the chosen day
tinted — **soft, not solid**: a filled accent square is the loudest thing in the
panel and it is only saying "this one". A line under the rows says what the date
**means** — "Goes live 6 days from now", "Published today" — because a date on
its own is a fact an author still has to do arithmetic on.

**Time is two fields and a switch, on a 12-hour clock.**

```
[08] : [20]   ( AM | PM )
```

Two short monospace inputs and a two-button group — not one string to parse, and
not the native `<input type="time">`, which brings its own AM/PM widget, its own
clock glyph and its own idea of what a control looks like, none of which match
anything else here. The fields are `type="text"` with `inputmode="numeric"` and
`maxlength="2"`, so a phone offers digits without the browser also offering a
stepper. Hours read 1–12, and noon and midnight are written `12`, not `00` — the
one place where padding a number would say the wrong thing. AM/PM is a
`role="group"` pair with `aria-pressed`, not a select: a two-way choice that is
always on screen should not cost a click to read. Storage stays a `Date`;
12-hour is a presentation layer applied on render and undone on commit.

**Accent is ink here, not fill.** Values, links and the selected day are
accent-colored *text*; the only solid accent in the whole panel is a checked
checkbox. Tags are neutral chips with a border. A sidebar of blue fills reads as
a sidebar of buttons.

**A hierarchical taxonomy is one box, not three controls.** Search, tree and
*New Topic* share a single bordered container with dividers between them — three
boxes with gaps between them read as three unrelated things that happen to be
stacked. Every row carries its entry count, because a term's weight is worth
knowing *before* you file something under it. Searching keeps a matched term's
ancestors visible, and indentation rather than disclosure triangles carries
depth: you are picking, not browsing. The footer opens an inline **name +
parent** form, so a term is created where the author is already looking.

**The parent dropdown shows the tree, not an index.** Options come out in tree
order, each carrying its depth, and the list indents them accordingly:

```
Typography
  Variable fonts
  Specimens
Interfaces
  Tables
Field notes
```

A flat alphabetical list of candidate parents is technically complete and
practically useless — it hides the shape you are adding to, which is the only
thing you are looking at the list to learn. The same rule governs the **entry
parent** selector on hierarchical content types.

**A flat taxonomy is a token field.** Chips and a bare input share one box.
Typing filters, Enter takes the first suggestion, Enter with nothing matching
**creates the tag**, Backspace on an empty input removes the last chip.

**Authors are people, not a select.** Each one is an avatar, a name and a role,
with the first marked *Lead*; removing is an × that appears on hover. Adding is
a **search**, because a site with forty accounts makes a dropdown useless and the
same field still works at four. A type declares whether it takes more than one.

**An entry always has at least one author.** With one author left, the × is not
shown and the handler refuses the removal — the rule is enforced in the data
path, not only in the markup. Under the search sits one line of copy saying why
("An entry always has at least one author, so this one cannot be removed until
another is added"), because a control that silently stops working reads as
broken. To hand an entry over, you add the new author first and then remove
yourself; there is no moment where the entry belongs to nobody.

**The featured image reuses the image component**, at 16:9 rather than 4:3
because that is the shape it will be used in, with the same hover Replace and
Remove over a veil. One preview component, two places; the alternative is two
things that look alike and behave differently. It appears only for types that
declare one — a switch in the type builder beside the index-page switch.

### The inserters
Four ways to put something in an entry. The shape of each follows how much of a
decision it is and where in the document the result lands.

**Block components slide in from the left.** Choosing one is part of writing,
often with browsing involved, so the panel widens the layout aside and stays
open — it never covers the sentence you were writing, and it does not snap shut
after one pick.

A tile is **an icon and a name, and nothing else**. No border, no plate behind
the icon, no kind label. Twenty-odd bordered boxes read as twenty-odd objects
competing; the same twenty-odd names with space between them read as a list you
can scan. The highlight appears only where the pointer or the keyboard is.
Anything more about a component — its description, whether an addon supplied
it — goes in the strip at the foot of the panel, which follows the selection.

**There is no category control at all.** The grid is already headed by
category, so a filter for them was a control standing in for a scroll — and it
cost a band of the panel that the components themselves should have. The search
field is the only control in the panel: one plain row, no box around it, the
full width. Twenty-odd items is a scroll, not a search problem; the field is
there for the person who already knows the name.

**Three tiles across.** A tile with no border and no icon plate needs less room
than one with both, and the width it gives back buys a third column — four
category groups in view at once instead of two. The count of what fits is the
point: a panel you scroll twice is a panel you stop opening.

Keyboard maps to the grid: left and right step, up and down move a row, Enter
inserts, Escape closes. Hovering a tile highlights it, so pointer and keyboard
agree on what Enter would do.

**Typing `/` at the start of a line opens the same panel**, with what follows
filtering it. The query lives in the document while it is typed and is removed
when something is inserted, so an abandoned slash is just text. This is the
path most authors end up using; the toolbar button is the discoverable one that
teaches it.

**Inline components have their own dropdown.** They are not in the panel at all,
because putting a badge inside a sentence is a different act from putting a
gallery between two paragraphs: the panel's grid implies "pick a block to place
here", and inline components do not go *here*, they go *inside this word*. Its
button carries a caret so it reads as a menu, and the menu is a short list with
a description on each row — there are few of them and each is one decision.

**Icons are a modal, and it is the same modal the media library uses** — a
header, a search field, a left column of categories, a grid, and a footer naming
the selection with the directive it will write. An icon set is a *library*: three
dozen glyphs now and more later, and nobody remembers which one is called
"schema". A 300px popover turns that into a scrolling memory test. Categories go
down the left rather than across the top, because a vertical list takes a column
that a wide modal has to spare while a horizontal one eats the height the grid
needs, and it holds its shape as the set grows. Below 760px the same list turns
into one scrolling row.

Its button is a shapes glyph — triangle, square, circle. Avoid a sparkle or a
star here, or anywhere that is not generation: a sparkle now reads as "AI"
before it reads as anything else, and an icon that promises the wrong thing is
worse than a dull one.

**Media inserts Markdown for images**, and a component only where Markdown has
no syntax: an image becomes `![alt](src "caption")` with the library's own alt
text and caption filled in, a video becomes `::video`, anything else becomes
`::file`.

**Media is a dropdown onto a modal.** The toolbar button offers two ways in —
*Media library* and *Upload a file* — because they are different acts with
different expectations: one browses what exists, the other adds something new,
and an author usually knows which before they click. Both land in the same
modal on the matching tab, so the menu is a way in, not a fork.

The modal itself is the same picker used everywhere else in the admin, so a file
is chosen the same way wherever you are.

The rule: **a panel for what you browse while writing, a dropdown for a short
fixed list, a modal for a library.** The question is not how important the
choice is, it is how much there is to look through — and whether the looking
happens while a sentence is half-written.

Both modals share one shell: head, filter bar, body, footer, with the primary
button disabled until something is chosen and labeled for the errand. Two
libraries that behave differently is two things to learn.

### Every element is an object
The element tab does not only show components. **Every block-level thing in
the source has a panel** — heading, paragraph, list item, quote, code block,
table, divider, image, directive — because every one of them can take a class or
an id, and an author should not have to remember where the braces go.

**The most specific element wins.** One resolver runs over both scans — the
directives and the blocks — and takes whichever span containing the caret is
smallest. Inside a callout the caret is in a *paragraph*, and the callout is
that paragraph's parent, which is what the breadcrumb is for; the container only
wins on its own opening or closing line, where there is no block to lose to.
"Component or block" was never the question a writer is asking, and an editor
that answers it anyway reports the wrong thing every time a component has
content in it.

Two controls are the same in every one of them, because the syntax is the same
everywhere: **Classes** (space separated, without the dots) and **ID**. Above
them sits whatever else that block has to say:

| Block | Its own controls |
|---|---|
| Heading | Level, 1–6, which rewrites the hashes |
| Code block | Language, written as the fence's info string |
| List | Its type — Bulleted, Numbered, Task — and its Content |
| List item | Whether it is a task, and whether the task is done |
| Definition list | Its Content: the terms and definitions in it |
| Image | The picture, alt text and caption |
| Everything else | Attributes alone |

Rules that keep it from becoming noise:

- **A blank line belongs to the element above it — at its own level.** Otherwise
  the panel empties itself every other line as you arrow through a document,
  which is worse than being a line behind. The level clause is what keeps it
  honest: on the blank line under a closed `:::`, walking back naively lands on
  the container's last paragraph and the breadcrumb then says you are somewhere
  you have just left. Candidates buried in a container the caret has stepped out
  of are skipped, so that line resolves to the container itself.
- **Blocks get no highlight in the source.** The ring means *you are here*, and
  the caret already says that; tinting the whole paragraph you are typing in
  would undo the writing surface.
- **The outline is a state, not a footer.** Once the panel always has something
  to show, printing the whole index under every heading and paragraph is
  padding. It is reached from **one quiet row** — icon, "Elements in This
  Entry", the count, a chevron — at the bottom of the **Document** tab, and only
  there. Picking a row, or moving the caret, turns the list back into that
  element's options.
- **Attributes go where the syntax puts them**: at the end of the block's last
  line, after the info string on a code fence, and on a line of its own *above*
  a list. Three placements, each one the framework's, none of them the editor's
  invention. *Provisional:* the placement for tables and dividers follows the
  end-of-block rule, which the parser should be checked against.
- **A list is an element, and a list item's parent.** Items come out of the scan
  flat, so the runs and their nesting are rebuilt from indentation into `list`
  records. A list is what you reach for to restyle the whole thing rather than
  one line of it, and it is what makes *List Item* a thing with a parent to
  climb to. An item's `first`/`last` stay on its own text, so an attribute still
  writes to the right line; its **span** reaches over anything nested under it,
  so a nested list belongs to the item it was written under rather than to that
  item's list.
- **The List panel offers what a list has: its type.** Bulleted, Numbered, Task
  — switching rewrites every marker at that list's own indent, renumbers as it
  goes, and leaves nested lists and the items' text alone.
- **A list carries its attributes on a line above it.** This is the one place
  the syntax puts an attribute block *before* the thing it describes, because a
  list has no last line of its own to hang one off:

  ```
  {.checklist}
  - list
  - item
  - three
  ```

  So that line is not a paragraph — the scanner skips it and the list claims it,
  and the list's span reaches up to include it, which is why selecting the list
  from the outline or the breadcrumb selects the thing the attributes are on.
  The panel's Classes and ID fields **write the line when it is needed and take
  it away when both fields are emptied**: a bare `{}` left sitting over a list is
  litter the author did not write. Everything else keeps attributes at the end
  of its last line, as before.

### Content: what is inside this one
An element that holds other elements gets a **Content** group in its panel: a
list of the level directly beneath it, each row selecting that element the same
way an outline row does.

```
CONTENT
  Term        Container
  Definition  A component that wraps other content…
  Term        Leaf
  Definition  A component that takes a single line.
```

**One level, never the subtree.** The whole tree is what the Outline is for;
repeating it inside a panel would make every callout a second outline, and the
deeper rows belong to the elements that own them — a nested list's items are the
nested list's business. One level answers the question the panel is being asked:
what is in *this*.

The elements that hold content are the ones that open a level in the outline:
**a container directive, a list, a list item, a definition list.** The same
predicate drives both, so a thing that indents its children in the outline is
exactly a thing with a Content group, and neither can drift from the other.

Rows are the shared outline row with its indent dropped, because inside a
Content group everything is one level by definition — and because an element
should look like itself wherever it is listed.

### Definition lists
Terms and their definitions, in the syntax the framework reads:

```
First Term
: This is the definition of the first term.

Second Term
: This is the first definition of the second term.
: This is a second definition for the same term.

Term Three
Term Four
: Multiple terms can share a single definition.
```

- **The marker is exactly one colon.** Two would be a leaf directive, so the
  scan and the highlighting both require `:` not followed by another.
- **Groups separated by a single blank line are one list.** That is how they
  render — three `<dl>`s in a row is not what the author wrote — so the scan
  keeps walking across one blank line as long as what follows is another
  term-and-definitions group.
- **The list, each term and each definition are all elements.** *Definitions* is
  the container, carrying the count ("4 terms · 4 definitions") and a Content
  group; *Term* and *Definition* sit inside it and take attributes of their own.
- A term is a plain line of prose, so nothing about the line says it is a term —
  see *Marking the source* on why the scan has to tell the highlighter.

### Enter carries the marker
A list is a run of lines that each repeat a marker, so the editor repeats it.
Enter at the end of `- The first point` opens `- ` on the next line; Enter in an
ordered list opens the next number and **renumbers the whole run**, not the tail,
because a list with an item pushed into the middle is wrong from that point
down. A task item opens another `[ ]`, unticked.

**Enter on an item with nothing in it ends the list.** The marker goes away and
the caret lands in a paragraph. That second press is how every editor people
already use ends a list; without it, the only way out is to delete characters
you did not type. Blockquotes work identically: Enter carries the `>` down,
Enter on a bare `>` takes it away. Tables work the same way: Enter opens
another row with the same columns, and — if the table has no delimiter row yet —
writes the one the syntax requires first, because a table without it is not a
table. Enter on a row of empty cells ends the table.

One trap worth naming: a row of empty cells is all pipes and spaces, which the
delimiter pattern also matches. **A delimiter row is only a delimiter row if it
has dashes in it**, and every test for one has to say so, or the second Enter
silently does nothing.

**The escape leaves a blank line above the caret.** A paragraph written straight
under a list item is a lazy continuation of that item, not a paragraph — so
ending a list this way has to produce source that actually parses as what the
author just did. Getting the markers right and the blank line wrong would be a
bug you only find at publish time.

This is done on the source, not through a rich-text model, because **the source
is the document**: what the author sees is what gets saved, and every one of
these edits goes through one write path so the caret, the scan, the highlight
and the panel can never end up describing different versions of the text.

### The breadcrumb
The bottom bar's left half says where the caret is, from the entry down to the
smallest thing holding it:

```
Post  ›  List  ›  List Item  ›  List  ›  List Item
```

A line and column number says where you are in a *file*. This says where you
are in a *document*, which is the question someone writing one actually has —
and it answers the one a nested structure always raises: what am I inside?

- **The root is the content type**, named the same way the drawer's first tab is
  — *Post*, *Page*, *Topic*. It is the entry itself, so clicking it opens that
  tab.
- **Every crumb is a way in, not a label.** Clicking one moves the caret to that
  element, scrolls the source to it, and opens the drawer on its options. That
  includes the ancestors, which is how you select the list a list item is in
  without hunting for its first line.
- **Ancestors are containment, computed, not stored.** The path is every element
  whose span swallows the current one, outermost first. Nothing has to maintain
  a tree; the same spans that drive the outline drive this.
- **A component crumb is accent, the rest is flat ink** — the same rule as the
  source and the outline. The last crumb is the one you are in, so it is full
  ink and never the one that gets truncated: crumbs shrink from the middle.

### The editor's menu
Everything the editor can do that is not the one primary button, in a menu
**to the right of that button** — the last thing in the toolbar, where a menu of
everything else belongs, rather than wedged between the status and the action.

Sections, because a flat list of eight actions is a list nobody reads:

| Section | Holds |
|---|---|
| **View** | Settings panel, Outline, Focus mode, Preview |
| **Entry** | Copy link, Duplicate, Revisions, — , Move to Trash |

- **No inserters here.** Putting something *into* the entry is the toolbar's job
  and it is one keystroke away; a menu that repeats the toolbar is a second place
  to look for the same thing, and the two drift apart the first time one of them
  gains an item.
- **The trigger is a vertical ellipsis**, because it sits at the end of a
  horizontal row of controls. A horizontal one reads as *more of this row*; a
  vertical one reads as *a list opens below*.
- **Sections are named, not just divided.** A rule between two groups says they
  differ; a heading says how.
- **Shortcuts are printed here**, right-aligned, which is why the footer no
  longer has to carry them. A menu is where someone looks for a command; a
  status bar is where they look for a number.
- **An index page and a taxonomy term lose Duplicate and Move to Trash** — both
  are singular and permanent. Leaving items out beats showing them disabled; a
  menu of dead entries reads as a bug.
- **The destructive item gets a divider**, always.

### The Outline
One list of everything the document is made of, in source order — the Markdown
the author wrote and the directives they placed, together:

```
  Paragraph   An opening paragraph. Nothing here is rendered…
  Callout     Before you start
  │ Paragraph   This is a container component. The cursor…
  Heading 2   How it works
  Quote       A blockquote keeps the marker faint and the…
  List        Bulleted · 4 items
  │ List Item   The first point
  │ List Item   The second point, with a nested list
  │ │ List        Numbered · 2 items
  │ │ │ List Item   Ordered items are marked the same way
  Table       3 columns
  Code Block  php · 2 lines
  Entry List  post
```

**One list, not two.** Splitting components from Markdown would ask the author
to know which of the two a thing is before they could go looking for it, and
that distinction is exactly what the rest of the editor works to stop mattering.
A table and a callout are both things in the document; the outline is the
document's shape, and half a shape is no shape.

**Container and leaf level only — inline directives are left out.** A badge or a
footnote reference lives *inside* a sentence, so it is not something the
document is made of; listing them would bury the structure under every scrap of
markup in the prose. The three things that own a line of their own are what
appear.

**The excerpt is the point.** "Paragraph" eight times in a row tells you nothing
about the document you are looking at, so every row carries one line of what is
actually in it: the heading's words, the quote's first sentence, a code block's
language and length, a table's column count, a component's title attribute. A
divider has nothing to say and says nothing.

**The type name is a column; the excerpt flows out of it.** Pushing the excerpt
to the right edge would make a key/value table out of a table of contents. The
name is quiet mono in a fixed column and the excerpt reads down the page like
the document it describes.

**The name is accent only when it is a component.** That is the same rule the
source highlighting follows: the accent means *someone placed this*. Ordinary
Markdown keeps the flat ink it has everywhere else, so one glance down the list
separates what was inserted from what was written — and because an image is
Markdown, an image's name is flat too.

**Depth is containment, drawn with indentation and a hairline.** Anything
starting before a container closes is inside it — and the things that open a
level are a container directive, a list, and a list item, which is what puts a
nested list under the item it was written under. No disclosure triangles: they
would ask you to open the document a second time to see what is in it.

**A line that is nothing but an image is listed as the image**, not as a
paragraph that happens to contain one — otherwise the same object appears twice
under two names.

Every row is a way to select: it puts the caret in that element, scrolls the
source to it, and turns the panel into that element's options.

### An image is Markdown
There is **no figure component**. An image is what Markdown already says it is:

```
![alt text](/media/2026/06/icon-dream.webp "The caption"){.stretch-wide}
```

Four parts, each with a job: alt text for anyone who cannot see it, the source,
the quoted title — which this framework renders as the **caption** — and an
attribute block carrying the width class. A component wrapping the same four
things would be a second syntax for one object, and the entry's source would
then depend on which button the author happened to press.

So the editor makes the Markdown itself selectable. An image is scanned like a
directive, appears in **the Outline**, names the element tab, and
gets the same panel: **Variant** (Default, Wide, Full bleed, Float left, Float
right), the **image itself**, **Alt text**, and **Caption**.

**The panel shows the picture, not its file name.** A path in a read-only text
field asks someone to recognize a photograph by its slug. The preview is the same
4:3 cropped frame the library uses, so a file looks the same wherever it appears,
with the path and dimensions on one quiet line beneath it. **Replace** and
**Remove** sit on the image itself on hover or keyboard focus, over a veil that
still lets it show through — the actions are on the thing they act on, and the
panel stays two controls lighter until someone reaches for them. An image whose
file is not in the library says so rather than showing a broken frame.

- The **caption field is called Caption, not Title**, because that is what it
  does here. Naming a field after the syntax rather than the effect is how an
  interface ends up teaching its own implementation.
- An image's variant is a **class**, not `variant=`, because `{.stretch-wide}`
  is what the framework reads. Same idea as a directive's variant, spelled the
  way images spell it — changing it swaps that one class and leaves every other
  class and attribute alone.
- An **empty caption writes nothing**, not an empty pair of quotes.
- The inserter still lists **Image** under Media, but choosing it opens the
  media library rather than writing a directive. The entry point stays where
  people look for it; only the output changed.

The same holds in reverse: video and downloads *do* get components, because
Markdown has no syntax for them. The test is whether Markdown already says it.

**`:::figure` is a container, not an image.** It wraps *anything* that needs a
caption and a width — a table, a gallery, a code sample, an image — and its own
options are the caption and the alignment. That is a different job from an image,
which is why it can exist alongside one without being a second way to write the
same thing: an image is the content, a figure is the frame around content.

### Variants
Every component has a **variant**: a named style the theme provides. The
component decides what the thing *is*; the variant decides how it looks. A
callout is a callout whether it is tinted, bordered or compact.

- Variants are **named and described**, never numbered or previewed as a
  swatch. "Bordered — no fill, a rule down the left and plain text" tells an
  author what they are choosing; a thumbnail of a rectangle does not.
- Every component has a **Default**, and Default writes **no attribute at all**.
  An entry that has never been styled carries no styling in its source, so a
  theme change reaches it.
- Any other variant writes `variant=key` on the directive, beside the options.
  It is an ordinary attribute, so nothing new is needed to parse it.
- The selector sits **at the top of the element tab**, above Options, because
  it usually changes what the options mean.
- **Themes own the list.** A component ships with the variants its theme
  defines; an entry referring to a variant the current theme does not have falls
  back to Default rather than failing. This is the seam where a site's design
  system meets its content, so it is worth keeping narrow: a handful of named
  looks, not a style panel.

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

### The media library
The library is a browsing screen that sometimes appears in a modal. It gets the
room a browsing screen needs, not the room a dialog usually takes: the picker is
`min(1180px, 100vw - 64px)` wide and up to 90vh tall. A file grid squeezed into
a 760px dialog is the reason picking an image feels like a chore.

- **Every thumbnail is the same 4:3 box, and images are cropped to fill it**
  (`aspect-ratio` on the frame, `object-fit: cover` on the image). A grid of
  ragged rectangles is the single thing that makes a library look untended, and
  cropping a thumbnail costs less than letterboxing one — the detail view shows
  the whole file.
- **Card height is fixed too.** File name on one line with an ellipsis,
  dimensions and size on a second. A name that wraps drags its neighbors out of
  alignment.
- **Selection is a ring and a tick**, not a tint alone: an accent border on the
  card and a filled check in the corner of the thumbnail. On a grid of images,
  a tint is invisible against half of them.
- **A kind badge only where it says something.** `VIDEO` and `DOC` earn a
  corner label because their thumbnail is a placeholder; `IMAGE` on an image
  is noise repeated forty times.
- **Kind filters are a segmented control** beside the search field — All,
  Images, Video, Files — because the set is short and fixed.
- **The footer names what is selected** and what happens next. The primary
  button is disabled until something is chosen, and it is labeled for the
  errand: *Insert* when inserting into an entry, *Choose* when filling a field.

**Two tabs: Library and Upload.** Uploading is the same act as choosing, one
step earlier — a file lands in the library and is then selected — so it belongs
in the same modal rather than behind a separate dialog that ends somewhere else.

- **The Upload tab is a drop zone and a button**, in that order, with the size
  limit and accepted types stated before anyone tries. The button carries no
  icon: there is already a large upload glyph above it, and a second one three
  inches below reads as a different action rather than the same one. A drop zone with no
  button excludes anyone not dragging; a button with no drop zone ignores how
  most people actually move a file.
- **Dropping anywhere on the modal uploads**, and switches to the Upload tab as
  the drag enters. Nobody aims for the dashed rectangle, and a file dropped an
  inch outside it should not vanish.
- **An upload ends in the library, selected.** The Upload tab keeps a short
  receipt — file name, dimensions, size, a check — and the footer already names
  the newest file, so *Insert* finishes the job without a detour. **Show in
  library** is there for anyone who wants to see it in place first.
- **The empty search state offers the other tab**: "No file matches" is often
  the moment someone realizes they never uploaded it.
- The library screen's own **Upload** button opens the same modal on the same
  tab. One uploader, one set of rules about what a file may be.

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
- **Title Case names things; sentence case says things.** Anything that *is* a
  name takes Title Case: page titles, panel and section headings, empty-state
  headings, modal titles, tab labels, sidebar and breadcrumb entries, component
  and variant names, and the command palette rows that name a screen. Anything
  that is a sentence or an instruction stays sentence case: buttons, form
  labels, hints, descriptions, toasts, menu items, and every line of body copy.
  So *Content Types* in the sidebar and as the page title, but *New type* on the
  button and *Name (plural)* on the field. The test is whether you would
  capitalize it mid-sentence: you would write "open Content Types", but you
  would not write "click New Type".
- In Title Case, small words stay lowercase unless they lead: *Nothing Links to
  This File*, *Insert an Icon*, *Upload to the Library*.
- Uppercase is only for the 11px micro-labels, which take tracking. They are
  set from Title Case text, so turning the styling off leaves correct copy.
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
- Letting the icon rail change what is on screen.
- Spending the editor's status bar on shortcut hints instead of on where the
  caret is.
- Showing a structure an author cannot click into, or one that names a list item
  without offering the list it is in.
- Reporting the container when the caret is in something inside it.
- Making Enter end a list any way other than a second Enter on an empty item.
- Writing source that does not parse as the thing the author just did.
- Painting an opaque selection over a transparent-text editor.
- Repeating a whole subtree inside a panel that was asked about one element.
- Shipping a bare `<select>` whose list an author will see, or replacing one
  with a widget that no longer holds the value.
- Showing a native `<input type="time">`, or offering a parent picker that
  flattens the hierarchy it is picking from.
- Leaving an entry with no author, or hiding a control without saying why it is
  gone.

## 13. Open questions

Unresolved at the time of writing. If you settle one, replace it here with the
decision.

- API conventions: auth, pagination shape, error format, and how the admin
  discovers content types at runtime.
- The editor: Markdown surface, the component inserter, and the right sidebar
  that swaps between Document fields and Component options.
- How the parser maps cursor position to the directive under it, and rewrites
  component options back into the source without disturbing the author's text.
- The content-type builder's own screens.
- How the media picker is invoked from a component option.
- Whether type provenance ("Posts addon", "Custom type") belongs in the list
  header at all — useful at three types, clutter at fifteen.
- How long a breadcrumb is allowed to get before the middle should collapse into
  a menu. Five crumbs is the realistic worst case in the prototype, and it fits;
  a deeply nested outline in real content may not.
