# Conventions

> **Part of the Meridian admin design direction**, in this project under
> `design/`. Start at `design/00-project-brief.md`. The other parts:
> `10-foundations.md` · `20-components.md` · `30-editor.md` · `40-screens.md` ·
> `90-conventions.md`, with `50-open-questions.md`, `60-decisions-log.md` and
> `70-build-runbook.md` alongside them. Section numbers are the original
> document's, so a cross-reference like "§8" still finds its target wherever it
> now lives.

Motion, copy, the Vue conventions the admin SPA should follow, and the list
of things not to do. Short, and worth re-reading before a pull request.

---

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
