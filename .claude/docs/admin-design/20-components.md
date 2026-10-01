# Components

> **Part of the Meridian admin design direction**, in this project under
> `design/`. Start at `design/00-project-brief.md`. The other parts:
> `10-foundations.md` · `20-components.md` · `30-editor.md` · `40-screens.md` ·
> `90-conventions.md`, with `50-open-questions.md`, `60-decisions-log.md` and
> `70-build-runbook.md` alongside them. Section numbers are the original
> document's, so a cross-reference like "§8" still finds its target wherever it
> now lives.

The shared controls: buttons, pills, panels, tiles, tables, selects, tabs,
the bulk bar, menus and empty states. Anything used on more than one screen.
A control that exists on exactly one screen is described where that screen is.

---

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
