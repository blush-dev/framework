# The rest of the admin

> **Part of the Meridian admin design direction**, in this project under
> `design/`. Start at `design/00-project-brief.md`. The other parts:
> `10-foundations.md` · `20-components.md` · `30-editor.md` · `40-screens.md` ·
> `90-conventions.md`, with `50-open-questions.md`, `60-decisions-log.md` and
> `70-build-runbook.md` alongside them. Section numbers are the original
> document's, so a cross-reference like "§8" still finds its target wherever it
> now lives.

Everything outside the editor: the entry lifecycle and autosave, list
screens and hierarchy, the index page, taxonomies, the media library, and the
states every screen has to handle — loading, failure, conflict, validation,
emptiness and permissions.

---

## 8. Patterns — the rest of the admin

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
