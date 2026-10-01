# Foundations

> **Part of the Meridian admin design direction**, in this project under
> `design/`. Start at `design/00-project-brief.md`. The other parts:
> `10-foundations.md` · `20-components.md` · `30-editor.md` · `40-screens.md` ·
> `90-conventions.md`, with `50-open-questions.md`, `60-decisions-log.md` and
> `70-build-runbook.md` alongside them. Section numbers are the original
> document's, so a cross-reference like "§8" still finds its target wherever it
> now lives.

What every part of the admin is built out of: the words, the principles, the
theming cascade, the type, the color, the layout and the spacing scale. **Read
this one whatever you are working on** — the rules here are the ones that break
things when they are broken, and the other files assume them.

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
