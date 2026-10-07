# How the Blush admin departs from the design direction

The numbered documents beside this file are the admin's design
direction, kept exactly as the author uploads them, so a new version can
replace them wholesale: start at `00-project-brief.md`, and read
`10-foundations.md` and the part for what you're changing (`30-editor.md`
for the editor, `40-screens.md` for the rest). "Meridian" in them is the
design project's codename, never used in the product, which is Blush.
The direction is design; code directions are this project's (D-313).
Section numbers (§1 to §13) are the original single document's, kept
through the split. This file is the project's side: where the files
are, where the admin departs from the direction and why, and which of
its open questions are settled. Read both before writing admin UI, and
update this one (not the direction) when you depart from it.

Sketches, mockups, and design files (these and any the author adds
later) may differ in detail from the admin as built. They're close
enough to read as the admin: build each part from the components and
shared classes the admin already has (D-509), not from the sketch's own
markup and CSS, and draw something new only where nothing fits. Where
a sketch and the admin (or two designs) disagree, ask the author which
way to go; record the answer here.

## Files

```
.claude/docs/admin-design/00-project-brief.md … 90-conventions.md
                                               the design direction (as uploaded)
.claude/docs/admin-design/meridian-admin.html  its clickable prototype, standalone
.claude/docs/admin-design/meridian-profiles.html, meridian-role-capabilities.html
                                               sketches of single screens (D-351, D-359)
.claude/docs/admin-design/blush-themes-screen.html, blush-extensions.html
                                               sketches of the Extensions screens (D-381, D-385)
.claude/docs/admin-design/toast-sketch.html     the toast, as every toast in the admin is drawn (D-387)
.claude/docs/admin-design/departures.md        this file
.claude/docs/admin-design/tokens.css           the prototype's tokens (reference only)
.claude/docs/admin-design/old/                 the earlier single admin.md and its prototype
resources/admin/css/tokens.css                 the tokens the admin builds from
```

The brief's file locations (`design/`, `project_read`, the published
prototype artifact), its rules for splitting work across conversations,
`70-build-runbook.md`'s republishing steps, and §11's directory layout
describe the design project, not this repo; `AGENTS.md` and the layout
below are this one's. The theming cascade, and that all color, type,
radius, spacing, and density values come from
`resources/admin/css/tokens.css` as `var(--token)`, are not negotiable
without a deliberate decision.

## Departures so far

Each is recorded in `.claude/docs/decisions.md`.

- **Type tokens added** (D-231): `--text-sm` (12px), `--text-xs` (11px),
  `--text-2xs` (10px), and `--h2` (15px since D-265), so no type size is a
  literal.
- **Shared pieces are global classes** in `admin.css` when they're only
  how something looks (buttons, panels, pills, tables, stat tiles,
  notices), and components, without a `Base` prefix, when the same
  markup repeats in three or more places or carries behavior (D-231,
  D-509); components keep their own layout in scoped styles. What
  screens do alike is shared in modules (D-505). The directory layout
  follows the repo: `resources/admin/{css,fonts,js/{components,views}}`.
- **Tables aren't sticky-headed** (D-231): a table that scrolls sideways is its
  own scroll container.
- **No autosave or pending changes** (D-233): the writer has nowhere to keep a
  pending draft, so the editor saves when asked and warns before leaving
  unsaved work.
- **Status tabs are links** with `aria-current`, since each is a URL (D-233).
- **New writes nothing until the first save** (D-336): the prototype
  writes an "Untitled …" draft when New is pressed; here the editor
  opens on an unwritten entry, and the first save creates the file,
  named for its title (so it needs one). Abandoned starts leave nothing
  behind.
- **Trash is a status outside All** (D-237, D-484): a trashed file stays
  where it is with `status: trash`, the Trash tab lists those, and "All"
  doesn't include them.
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
  and show a row's parents before its title. There's no tree/flat switch.
  Filters and sorting flatten it too (D-300), and the note bar's **Clear
  the sort** button is how a sort is undone.
- **The filter row** (D-300): search, Author, one select per taxonomy
  the type uses (§8 says "its taxonomy", one), and Updated (7, 30, or 90
  days), but no **Unpublished changes** toggle, since there's no
  autosave. Author and term selects list only what the type's entries
  use (D-303), at most 100 items. The status
  tabs' counts follow the filters (the prototype's ignore them). The
  Trash tab takes only the search, and nothing sorts it (it's newest
  trashed first, paged like the rest since D-484).
- **Bulk selection** (D-301): the bulk bar has Publish, Move to draft,
  and Move to trash, but no **Discard changes** (no autosave). Selection
  is the page's: changing the tab, a filter, the sort, or the page
  clears it. A bulk change toasts what changed, and a notice names
  each skipped entry and why (D-302). Continued rows can't be
  selected. The Trash tab has no checkboxes.
- **Sorting and page size** (D-300): sorting runs on the server across
  every page; Authors sorts by the first author's slug, not the name
  shown. The pager offers 10, 20, 50, or 100 a page (20 by default, not
  the prototype's 25), shown once a list is longer than 10.
- **Profiles are under Users** (D-259, D-353, D-354), not Content:
  they're the public side of accounts. Users' panel is Your Account,
  **Accounts** (who signs in, as the prototype has it), **Profiles**
  (the profiles type's entry list), and Roles: two lists with one link
  between them, from the profiles sketch (`meridian-profiles.html`),
  whose revision (D-369) puts them there too. The Profiles list is the entries list
  with the type's own columns (Name, Status, Account with a **Guest**
  tag, Bylines, Updated); a name opens the profile's screen (Identity,
  Where This Profile Appears, Linked Account), not the editor. An
  account's screen has a **Public Profile** panel in the sketch's three
  states, plus a fourth: linked to a slug with no file (**Create it**).
  From the revised sketch (D-369), where the admin differs: linking
  picks from every profile, with those another account has shown but
  disabled (D-356), not only the free ones, in New Account's select
  (the account and profile screens' modals list only the free ones,
  D-373); notices keep the admin's rounded corners and have no ruled
  edge, where the sketch's are square with a darker left border (the
  author's calls, D-373, D-376); colored ones have a full border in
  their color, as the Home sketch's do (D-542), and are every screen's notices, in
  place of the direction's (D-506); the
  profiles bulk bar is the entries list's (Publish, Move to draft, Move
  to trash), without **Copy links**; suspending is undone with
  **Reinstate** (the CLI's word), not **Reactivate**; a written archive
  page goes to the trash from a row's **Edit** menu (the author keeps
  it restorable, D-370), where the sketch deletes it for good. The
  section panel shows the sketch's counts beside every list's link
  (D-371, and Media and Themes, D-372; the direction's panel has
  none). The screens themselves are drawn as the
  sketch is, under a `.people` root (D-370), so other screens keep the
  direction's spacing and pills; tabs are the direction's on every
  screen, these too, with less padding (D-507). "You" marks your own account and profile in those two
  lists; entry lists keep "Yours" for entries crediting you.
- **Your Account** (D-235, D-355, D-358, D-369; Your Profile in the
  direction, renamed as the sketch suggests): the account screen on
  your own row, as the revised profiles sketch has it, at its own
  address (`/accounts/{you}`, D-371; `/profile` goes there), with your password and the
  admin's theme and color scheme, your name and email, and your roles
  read-only. The editor never holds account settings (D-332's Account
  tab is gone). Where the sketch gives an account no name of its own,
  the admin keeps one (the author's call, D-370): its display name,
  else its profile's title, else its username.
- **A people field's list page** (D-329, D-332, D-353): `_authors` (or
  `_cooks`) in its folder, pinned under the index page tagged with the
  field's name, edited like an index page (no type fields or date, slug
  fixed), but trashable. A page written for one person's archive
  (`_cooks/jane`) isn't listed anywhere; it's written, edited, and
  removed from the profile's screen, as the sketch says. The type
  editor's panel for them is named for the profiles type (**Profiles**,
  D-369; the sketch's), a row per field and a separate Archives panel
  of switches (D-370), with the singular shown under the label rather
  than edited, and a new field's front matter key set under its row; the
  new-type wizard keeps a single Authors group, and more fields are
  added on the type's screen.
- **One back button, above the title** (D-369, from the profiles
  sketch's cleanups): every detail screen's link back to its collection
  ("All accounts", "All types") is a quiet link on a line of its own
  above the title (`.page-back`), not a button among the actions.
- **No list of every type together** (D-240): each content type has its own
  list, and there's no "All entries" screen. The dashboard's Entries
  row (D-538, from the Home sketch) lists drafts, scheduled, and
  recently published entries across types, a few of each, as the
  dashboard's own row, not a screen.
- **Unsaved changes are kept in the browser** (D-240): without autosave, the
  editor keeps a copy of unsaved changes in `localStorage` as they're made
  and offers them back when the entry is opened again. That's what makes the
  offline bar's "changes stay in this browser" true. A save made offline
  waits and goes ahead when the connection is back. Since nothing is
  lost, leaving the page gets no browser warning (D-374), and the reload
  shortcuts ask in the admin's own modal.
- **Conflicts say when, not who** (D-240): a file can change through git or a
  text editor, so the notice gives the time the file was written and "from
  the admin or by editing the file itself". **Keep mine** saves this
  editor's version of every field it shows over theirs; front matter the
  editor doesn't show stays as theirs.
- **Validation runs in the admin only** (D-240), from the schema's
  `required`; the API doesn't refuse to publish yet.
- **The setup path has only steps that do something** (D-240, D-538):
  the first page, a content type of the site's own, media, and inviting
  people, each only for an account that can take it. It shows until the
  site publishes something, as the Home sketch has it, and Skip is
  saved for the account; the sketch's "reachable from Settings
  afterward" isn't built.
- **A type's purpose comes from its kind** (D-240): types have no
  description yet, so an empty type's screen says what pages, collections,
  or taxonomies are for.
- **Content health keeps quiet text while checking** (D-240): its result is
  a summary, not rows, so there's no shape to sketch.
- **The full navigation** (D-241): every screen in it is built now
  (D-309 retired the "comes next" page). A taxonomy moves when its `types` change
  in a config file, not in the admin, so there's no toast announcing the
  move (§8, Sidebar grouping); nav counts are left out until an API gives
  them cheaply.
- **The section rail** (D-244, D-280): no state dot on Content (without
  autosave there are no unpublished changes to live entries), no theme
  button in the top bar (the color scheme is an account preference on Your
  profile), and no site switcher; the site's mark links to the site. The
  account's menu is in the top bar. Home's panel has the Dashboard,
  Site Health (D-543), and Tools (D-540), then shortcuts. The rail never navigates, as §6 says.
  As the decisions log's *The shell* has it (D-317), a rail button
  toggles its panel and the top bar has no collapse button; the trail
  is the section, the screens above (the editor's type, or a detail
  screen's list from its route's `meta.parent`), and the screen; on a
  narrow screen the section crumb goes first. The section crumb shows
  its section in the panel, and closes the panel when it already shows
  it, like the rail button (D-367).
- **Four sections, not three** (D-326): Home, Content, **Users** (named
  People until D-354, with the `user` icon), and
  Config, where the foundations say "three sections, not more" with
  people under Config. The author's call: People passes the
  foundations' own test (someone goes looking for it by name), it's the
  one section every account uses (Your Account), and it kept Config
  long once Settings became four screens (D-325). Users' panel is
  Your Account, Accounts, Profiles, and Roles, with no headings (D-327,
  D-353);
  Config keeps Structure, Settings, and Extensions (named Customize until D-380).
- **Both admin themes ship** (D-317): Neutral and Editorial, a theme
  choice on Your profile beside the color scheme, with Editorial's
  fonts (Karla and Newsreader) served with the admin like the others.
- **The command palette** (D-248): the screen's own commands come first
  (the editor's), then going places and New {singular}, then entries;
  switching the color scheme saves it to the account.
- **The Markdown source editor** (D-241): spelling on, and no horizontal
  padding on inline code (it shifted the text).
- **The component inserter** (D-243, D-247, D-265, D-268): block
  components only, with **Image** first under Media (it opens the media
  picker on images); core ones are grouped by category (Text, Media, Layout,
  Navigation, Data), and a theme's, the site's, or a plugin's by where
  they come from, which the strip at the foot names. Components are written by full name
  (`blush/callout`, D-171). An inline component goes at the caret; a leaf
  or container on lines of its own, with a blank line either side;
  selected text becomes its label or body. Required props are written
  empty; defaults aren't written. In the search field, left and right move
  through the grid only while it's empty. Tab inserts, as Enter does,
  while typing after a slash, and Escape leaves the slash as text. The
  panel opened by a slash closes once a component replaces it.
- **The media picker** (D-246, D-247, D-265, D-268): the library comes
  from `user/media` newest first, a page at a time with **Show more**
  (no "Beside This Entry": media is only in `user/media`, D-294). The Upload tab (only for
  an account that may upload some kind, `media.{kind}.upload`, D-407;
  the menu is a plain button without it) takes the
  library's types as the site allows them (images, sound, video; no
  PDFs by default), up to PHP's limit, into `user/media/{year}/{month}/`.
  Inserted images get the library's alt text and caption (D-269); a
  Replace brings them only where the image had none. An image's own alt
  text and caption are its own there: the panel never writes them to
  the library. **Decorative** is empty alt text (D-272): on, the field
  is hidden (and a library description is offered); off, it shows. It's also
  **Choose** beside every media field and option, and **Replace** on an
  image.
- **The writing surface** (D-245, D-280, D-520): no `Changes` pill, no
  save-state text, and no status pill in the header: the buttons show the
  state (no autosave). A save that changes no status (Update, Save
  Draft, ⌘S) is disabled until something changes, the pressed button
  reads "Saving…" (or "Waiting…" offline), then "Saved" until the next
  change; the state is still read out to screen readers. A draft that
  can be published has a ghost **Save Draft**, then the settings button,
  then **Publish**; nothing sits under the title (D-254); Tab in the
  body moves focus, but in a list item it nests the item (D-284); the
  drawer is remembered (D-299, which departs from §8's "the settings
  drawer starts shut").
- **The chrome doesn't fade while typing** (D-279): the author found the
  darkened toolbar and footer distracting, so §8's "Chrome recedes while
  typing" isn't followed.
- **The editor's menu** (D-280, D-283): **Preview** opens a signed
  preview link to the entry as last saved (a live one has **View**
  instead), and there's no **Revisions** (there are none). **Switch to draft** (scheduled or live) is
  its Entry section's first item, and a term loses only Duplicate (a term
  can be trashed).
- **The element tab** (D-265, D-268, D-271, D-280): the entry's tab is the
  type's singular label ("Index page" for an index page). An element
  chosen from the outline, a Content group, or the breadcrumb stays chosen
  while the caret stays put, since a list and its first item start on one
  line. Only a component or image is boxed in the source; a block isn't.
- **Every block is an object** (D-268, D-280): attributes go at the end of
  a heading's, paragraph's, or list item's last line, and on a line of
  their own just above a list, quote, code block, table, or divider, where
  the parser reads them (the provisional end-of-block rule doesn't reach
  the last four). An underlined heading given a level becomes one with
  hashes. Attributes are on by default in `MarkdownConfig`.
- **An image is Markdown** (D-268): its variants are the classes the
  active theme lists under `theme.json`'s `variants.image`, not a fixed
  list; the framework default theme offers Float Left and Float Right
  only while it's active. Widths are bleed, not variants (D-313). The panel keeps a Source
  line. Without hover, Replace and Remove stay on the image.
- **Selects** (D-280): `AdminSelect`, a component, rather than a
  `MutationObserver` enhancing every `<select>`: Vue owns the DOM, and the
  admin has a handful. The real select stays, hidden, holding the value.
- **The document panel** (D-280, D-281, D-283): Publish (Status,
  Visibility, Date, Slug, and Parent for a term, then one line: what the
  date means and when the file was last edited), Featured Image, Authors, each
  taxonomy (headed by its plural label; only those grouping the type,
  or that the file uses), Summary, then the type's other fields as a
  form, then a group for each field set attached to the type (D-339;
  the direction has no sets), headed by its label with its help below,
  its fields kept out of the groups before it. (Sets below the body,
  D-345, were taken back by D-348: fields stay in the panel, as the
  direction has them.) Fields in the drawer are filled wells (`--bg`), as the
  prototype's are. People show
  an author's slug, not a role, and no avatar images. A term keeps its
  Visibility and Date (Blush terms have both), and shows Featured Image
  and Authors only when its file has one. The reference picker's New
  {term} writes a real term (a virtual term can't have a parent); typing
  a new tag writes a virtual one.
- **Links** (D-313, the author's call): in the text, ⌘K opens the link
  form, with or without a selection, and ⌘⇧K removes the link the
  caret is in; outside the text ⌘K is the command palette. The
  direction has ⌘⇧K open the form and ⌘K never leave the palette. The
  formatting commands are in the palette, not the ⋮ menu, which §8
  keeps for View and Entry. ⌘E (code) and ⌘⇧X (struck) stay, without
  buttons.
- **The toolbar** (D-313): as `30-editor.md` has it, but the trail it
  hands the way out to is `Content / Posts / Editing` (D-317); and the
  move control (▴▾) sits between the insert tools and
  the sentence group, as the prototype has it. It moves the element the
  caret is in among its siblings (the author's call, D-314), not only
  the top-level one; a term or definition moves its definition list. Nothing in the toolbar
  toasts, insertions included (an open question in the direction). No
  Position row in the element panel and no outline handles, as the
  direction's decisions log has it. A component's `only` is its PHP
  class's `HOLDS` (D-314); Blush has no table, divider, or code
  component for the Data Table and divider decisions to apply to.
- **Media kinds** (D-314): a media field's `kind` and
  `#[MediaProp(MediaKind::…)]` say what a picker is locked to; a
  wrong-kind upload is refused by its extension before it's sent.
- **Tab moves a block** (D-284, D-315), but by structure rather than
  the prototype's two spaces per line (which do nothing to a quote and
  turn prose into code after two presses): a list item nests, a quote
  gains or loses a `>` level, and several selected lines of code indent
  two spaces. Elsewhere, a lone caret in code included, Tab still leaves
  the text (D-245), so it's never a trap for the keyboard.
- **Bleed** (D-313): the classes are the active theme's (`theme.json`'s
  `bleed`), `bleed-wide` and `bleed-full` unless it names others, since
  existing content may use other names (jtcom's `stretch-*`).
- **Media** (D-251, D-268, D-269): **Upload** opens the picker on its
  Upload tab and **Open** goes to the file. A file's screen follows the
  media detail sketch (`media-detail-sketch.html`, D-551) rather than
  the prototype: two columns, Details built from the file's fields
  (D-287: title, alt text (warned under its field when an image has
  none), caption, credit, description, then each field set on its kind
  under its label, D-341). From the sketch, it keeps the shared save
  bar (D-508) over the sketch's Discard and Save, goes back to the
  library with a toast after Delete rather than the sketch's "was
  deleted" notice, and adds the read-only notes (D-552: no id). Its
  audio and video players are the shared `<blush-audio-player>` and
  `<blush-video-player>` (D-553, D-554). A video has no poster frame of
  its own; the player shows its first. No
  rename or Replace yet. Only `user/media` is listed (D-294 removed D-292's page
  bundle files and its **Where** control).
- **Content types** (D-250, D-311, D-349, D-350, D-386): types in
  `user/data/types` are edited, created with the wizard, and deleted;
  collections, taxonomies, and trees in a folder from code are edited the
  same way, saved in a file there over the code (its Danger Zone resets
  rather than deletes, and fields that are code classes are read-only);
  the pages and profiles types from code stay read-only. The wizard's
  kinds are **Collection** (the design's Content, renamed to match the
  kind, D-400), Taxonomy, and **Tree** (the design has two), and the
  list's tabs are All and one per kind the site has (D-400): a
  tree's Behavior has no URL base, feed, or author archives, with a note
  on its addresses in place of the URL base. An **Addresses** panel
  (the design has none) edits each route key's path, after Behavior.
  "Show in the sidebar" isn't a type setting in Blush; the hierarchy
  switch is a taxonomy's (`hierarchical`), since pages nest by folder.
  Entries counts are what the account may edit. From the design's type
  builder: the key and folder are fixed after creation (entries are
  filed by them), so the key isn't "fixed once entries exist" but always;
  **URL base** is the URL prefix, empty for the folder's; **Has an index
  page** creates the `index` entry once and then links to it (turning it
  off isn't offered: an index page is an entry, not a setting); **Has a
  featured image** adds or removes an `image` media field, for
  collections only; the switches are checkboxes, as everywhere in the
  admin; a collection's taxonomies are chosen on the taxonomy (its
  **Groups**), not on the collection, since that's where Blush keeps
  them; the icon is a site icon's name with **Choose** (the icon
  library) rather than a row of ten; field types are all but `object`
  (kept as written), with each type's options, and a field's **Default**
  follows its type. The types and their names come from the site's
  field type catalog, so an extension's types are offered with their
  options (D-338); a field whose type has more than one control has
  **Edited with** (the design has no such choice), and a list of
  choices takes its options. In the editor's form, `radios` and
  `checks` fields are a group of radio buttons or checkboxes under the
  label. Delete is in a Danger Zone, confirmed, and refused
  while a taxonomy groups the type. Save and Revert sit below the
  panels.
- **Fields** (D-340): the direction has no field sets, so Structure →
  Fields is built from its rules for collections: a list screen (Name,
  Added to, Source, Fields; no tabs or search for a short list), a
  screen per set with **All field sets**, and **New Field Set** as its
  own screen, not a wizard (General, Added To, Fields, as the set's
  screen has them). Added To asks the kind of place first (D-347), then
  its places as checkboxes (and the kind's slots, when one offers more
  than one; none does now, D-348); a target the site doesn't have is
  kept and shown as such. A type's screen has
  a Field Sets panel linking to its sets, which are attached from the
  set's side.
- **Roles and accounts** (D-249, D-312, D-369): Blush sends no email,
  so the prototype's **Invite** is **New Account**, its own screen
  (like every New) with a username and an email (required, D-370), and
  no note; the account gets a one-time password link shown on its
  screen to copy, and **Send a password reset** and **Resend the
  invitation** are **Make a password link**, and **Email it** isn't
  there. **Change email** is the sketch's dialog as an inline form
  with the display name (**Change name or email**), and changes it at
  once, since there's nothing to confirm by email. Accounts keep a
  display name (D-370); the last sign-in stands in for "last active". An account's roles are ticked, then saved (**Save roles**,
  **Discard**), as the profiles sketch has it. Removing an account reassigns nothing (entries credit
  authors, not accounts). Roles are editable, so the prototype's "fixed
  in this release" banner and note are gone: **New Role** and
  **Duplicate** make roles, and read-only roles say why.
- **A role's screen** (D-359, from the capability sections sketch):
  - No `view` capability, so no **Read only** preset or quieted section
    with a note: the editor has no read-only mode, and a type shows to
    accounts that can edit its entries. The presets are **Full
    access**, **Their own only**, **Drafts only** (a contributor's), and
    **No access**, and "No access" reads "Left out of their admin."
  - Sentences say "drafts" where publishing is missing, since without
    it editing and deleting reach drafts alone (D-219).
  - The sketch's unused "Types added later" row is drawn, as **Every
    Type** ("Includes new types"), first in the panel (D-360): it
    grants on every type (`content.*.…`), shows those ticks fixed in
    each type (a type it grants everything on is marked "Set by Every
    Type", with no ⋮), and its ⋮ adds **Set each type separately**.
  - Blush's site capabilities, not the sketch's: no `types.manage` or
    `roles.assign` (`site.settings` and `accounts.manage` cover them).
    Media (D-407) isn't the sketch's two boxes (Upload files, Delete
    files) but ten, in one grid as every section is: **Upload every
    kind** (`media.*.upload`, which ticks and fixes each kind, as Every
    Type does), **Upload images** and each other kind, then **Change**
    and **Delete** **their own files** and **anyone's files**, paired as
    content actions are, with its own sentence and ⋮ presets (Full
    access, Their own only, Images only, No access).
  - Every section's boxes are three columns (the sketch fits as many as
    the width takes), two under 1,100px, and one on a phone.
  - The facts strip has no "Changed 12 days ago by…": nothing records
    it. Holders link to the one account, or Accounts.
  - The header's ⋮: **Rename this role** for a custom role only (a
    built-in's name is fixed, D-312), opening a Name and Description
    panel; **Copy as JSON** for "Export"; **Delete this role** beside
    **Reset to built-in capabilities**.
  - The administrator's statement says to start from Editor (no
    **Duplicate** on it), and its button links to the holders.
  - **New Role** uses the same sections; its sketch is the detail
    screen only.
- **Appearance is named Themes** (D-327), in the navigation, its
  heading, and its address (`/themes`; `/appearance` redirects): the
  screen lists themes and nothing else. The API keeps `GET appearance`.
- **Themes** (D-381) follows the themes sketch
  (`blush-themes-screen.html`), not the prototype's Appearance (D-306's
  read-only rows are gone). Where it differs from the sketch:
  - **Theme details** (D-383) adds Name and Namespace rows; Author is
    the manifest's `authors` (D-384), each linked to their homepage
    with their role and an email link, and the folder row has a copy
    button. A theme without a palette is sketched
    in the admin's colors, with a note on declaring one. A folder theme
    the active one uses says why it can't be deleted (the sketch shows
    nothing). Broken themes have no details screen, since they have no
    name to address them by.
  - **Activate** saves in `user/data/settings.json` (D-324), and the
    note under the cards says so, with **Use `config/theme.php`'s
    theme** when it's saved; the sketch's note names only
    `config/theme.php`.
  - **Delete** is refused for any theme the active one falls back to,
    not only the active theme.
  - The menu adds **Preview on the site** (development, `?theme=`) and
    **Copy activate command**; it's left out when it would be empty.
    Menu items and card buttons are in sentence case, as the admin's
    other menus are.
  - A theme with no parent says it falls back to Default (the sketch
    names a fallback for every theme); the default theme says every
    theme falls back to it.
  - A blocked theme's message is the server's reason when it isn't a
    missing parent (a broken ancestor, a loop).
  - Broken themes are cards titled by where they were found, without
    the folder fact the title already says.
  - **Install Theme** is the sketch's uploader since D-392; see
    **Installing** below.
  - No author on the cards (it's on the details, D-384). The palette role
    `bg` is `background`. No theme settings yet (D-307).
- **Settings** (D-309, D-324, D-325, D-398): only the settings Blush has, as
  six screens in a **Settings** group of the Config panel (General,
  Reading, Media, Addresses and Search, AI, System), not the prototype's one page.
  The Config panel's groups are Structure, Settings, and Extensions (D-380)
  (Themes, Plugins, and Icon Packs; D-379); People is its own section (D-326). The
  prototype's panels become General's Site, Dates and Time, and
  Environment; Reading's Homepage and Feeds; Addresses and Search's
  Addresses and Search Engines; and System's Content Types, Caching,
  and Publishing and Previews. **AI** (D-398, the direction has none)
  has Markdown Copies (the switch for `.md` copies and `llms.txt`, with
  read-only rows linking to `llms.txt`, the types it lists, and the
  description it uses, and the `llms-full.txt` switch, D-402, locked
  while the copies are off, D-403) and AI Crawlers (the kinds `robots.txt` asks to
  stay away, as checkboxes, with the groups explained in the panel's
  note); plugins' settings will join it through `settings:ai` (D-397).
  A shown setting may link out (`link`) or to the admin screens it's set
  on (`links`). Settings set in code (`config/` and
  `.env` are developer code, D-039) sit beside the editable ones they
  relate to, read-only, naming their file; System is all read-only.
  There's a site **description** on General → Site (D-398), as the
  prototype's tagline was, for `llms.txt` and as the front page's and
  feeds' fallback description; there's no administrator email, front page entry, default
  new entry type, entries per page, trash emptying, date or time format,
  week start, or permalink structure. Since D-404 the screens are drawn
  as the settings sketch (`meridian-settings-sketch.html`) draws them:
  full width, each setting a row of label, control, and help (rows
  reading the panel's width, not the window's), a yes or no as a switch
  saying On or Off (accent-filled, a form value), and the AI crawler
  groups each with a sentence and their user agents. From the sketch,
  not taken: its date and time formats and posts per page (Blush has
  none), and its drawn checkboxes (the admin's own are kept). **Media**
  (D-406) is the sketch's Uploads grid, with these departures: the kinds
  are Blush's (Images, Videos, Sound, Documents, Other Files, as the
  library names them; the sketch's Video, Audio, and Other), and one
  the site allows no file types of can't be turned on, saying why; the switches say On and Off
  (the sketch's Allowed and Turned off), as every settings switch does;
  a size can't be typed or saved past the server's limit (the sketch
  only warns); All Files collapses on a narrow grid too; the path warning
  shows only when a kind's effective path changes; the grid becomes
  per-kind lines by its own width (under 880px) rather than the
  window's; and where the rules come from (saved, or
  `config/media.php`) is a line under the grid, with **Use
  `config/media.php`'s rules**. The
  save bar is the prototype's (count, Revert, Save changes), per
  screen, and also carries a refused save's reason. Each editable
  setting says whether it's saved in `user/data/settings.json` or comes
  from its config file, and a saved one can go back to the config's
  value. Read-only values show a Default tag, help, and warnings;
  booleans are neutral On/Off pills with a green dot when on
  (warn-colored when risky). Each
  editable setting is drawn with the shared field controls (`FieldInput`,
  D-343) in the row's control column. After a screen's own panels, each
  field set on it adds a panel of its settings (the design has none),
  with **Clear it** where the built-ins have the config's value. A site
  with no collection to show shows its homepage read-only.
- **Plugins** (D-308, named Plugins in D-379), the direction's Addons
  (vocabulary below), follows the extensions sketch
  (`blush-extensions.html`) since D-385: rows with a switch, a details
  screen (`/plugins/{vendor}/{name}`) with Details and Requires panels.
  Departures from the sketch:
  - **The switch saves in `user/data/settings.json`** over
    `config/plugins.php` (the sketch's note says it writes
    `config/plugins.php`; the admin never writes `config/`, D-039), and
    the note under the rows says which, with **Use
    `config/plugins.php`'s list** once it's saved, as Themes does.
  - **Requirements** are Composer-style constraints checked by
    `VersionConstraint`, not the sketch's major-version match, and also
    cover `ext-{name}` (shown as "the PHP extension {name}") and
    requirements Blush can't check. A plugin that's turned on but can't
    run says so ("It's turned on, but nothing it adds runs").
  - A plugin `config/plugins.php`'s `enabled` list leaves out can't be
    turned on here, and says why, as one with unmet requirements does.
  - **Delete** is only for a folder plugin that's off (the author's
    call; the sketch deletes any folder plugin), and not one
    `config/plugins.php` turns on by name. A plugin that's on says to
    turn it off first.
  - The toast names the plugins that started or stopped with the one
    switched.
  - No broken-plugin rows: a plugin manifest that doesn't parse still
    fails discovery (D-058), so the screen reports the error instead.
  - Rows are by label, so a row doesn't move when it's switched.
  - The details screen adds Name and Namespace rows; Author is every
    author, as on a theme's (D-384); License is the manifest's or its
    `composer.json`'s.
- **Component options** (D-245, D-268): an option set back to its
  default is removed from the directive; a required one left empty
  stays as `key=""`. Removing a container removes its body too (D-272). Option changes
  are applied to the text directly, so they aren't in the field's own
  undo (a list's List Type too). Classes and ID are fields; other
  undeclared attributes are listed.
- **Icon Packs** (D-379) follows the extensions sketch since D-385:
  cards with two rows of six glyphs, a switch, a details screen
  (`/icon-packs/{vendor}/{name}`) with an icon browser and Details.
  Departures from the sketch:
  - Only icon packs are listed, not icons themes and plugins carry (the
    author's call). The sketch's Core card is Blush's own icons
    (`resources/icons/blush`), with its own details screen at
    `/icon-packs/core`; its icons go by their names alone, not `core/`.
  - The switch saves in `user/data/settings.json` over
    `config/icons.php` (new, `IconConfig`), with **Use
    `config/icons.php`'s list** once it's saved.
  - Broken packs are cards titled by where they were found, with no
    glyphs, and can be deleted when they're folders in `extensions/`
    (D-418).
  - Glyphs are drawn as CSS masks of each SVG (as the icon inserter
    does), not inline markup, so nothing in a pack's files runs.
  - The details screen adds a Name row and every author (D-384, from the
    manifest or its `composer.json`); the core set's names Lucide.
  - **Install Icon Pack** is the sketch's uploader since D-392, as
    **Install Plugin** and **Install Theme** are; see **Installing**.
- **Installing** (D-392) follows the extensions sketch's uploader (one
  modal, `InstallModal`, for all three kinds). Departures from the
  sketch:
  - The manifest is each kind's (`plugin.json`, `theme.json`,
    `icons.json`, or their `.yaml`), not the sketch's `blush.json`, and
    the kind is read from it on the server, not from the file's name.
  - **Progress** is real for the upload (the bar fills as the file is
    sent), then one step, "Unpacking and checking it", while the server
    works; the sketch's Unpacking, Reading, and Writing steps aren't
    reported separately.
  - **Replacing** names the archive's own version and the installed one
    (the sketch bumps the version), "No version" when a manifest has
    none, and "Replace it" then. A plugin or pack that's on says it
    stays on and the new version runs at once, as the sketch says of the
    active theme. A role without the kind's `update` capability sees
    **Replace** disabled, with why in the footer.
  - **An archive of another kind** offers **Go to {kind}**, which opens
    that screen with its Install modal open (`?install=1`), rather than
    swapping modals on the same screen.
  - Refusals the sketch doesn't draw, each with nothing written: a file
    that would land outside its folder or is a link, too many or too
    large files, a manifest that doesn't pass, a namespace that's
    reserved or taken, a name Composer installed, Composer
    requirements, a PHP file that doesn't parse, a folder that already
    exists, and a git checkout to replace. A server that can't install
    at all (no zip extension, a folder it can't write) says so above the
    drop zone, with **Choose file** disabled.
  - The old folder of a replaced extension is kept in
    `storage/backups/{kind}/{folder}` (the sketch doesn't say).
  - **Previous version** (D-393), not in the sketch: a replaced
    extension's details screen has a row with the kept version, **Roll
    back to {version}** (asks first; the toast offers Undo), and
    **Discard**, above the delete zone.
  - The receipt says "It's in the list" (the modal covers the list), and
    the next step is offered only when it can be taken (not for an
    active theme, one whose parent is missing, a plugin whose
    requirements aren't met, or a role without `activate`).
- **The updated extensions sketch** (D-565): the three lists' filter
  row, the compact list, and one detail layout for every kind
  (Appearance or Icons, then Details beside Dependencies). Departures:
  - The filters are in the address (`?q=`, `?status=`, `?source=`, as
    D-505's lists are), so they reset when you leave and come back by
    the back link, as the sketch's do. **Source** says "A folder in
    extensions/" (the sketch's "A folder you added"), as Installed by
    does, and Plugins has no "Ships with Blush", since none can.
  - **Needs attention** also covers an abandoned extension and an
    active theme that isn't running (the author's call; the sketch's is
    only what can't turn on).
  - **Cards or Compact** is kept in this browser, one choice for Themes
    and Icon Packs, as Posts' Roomy and Compact rows are (the author's
    call); the sketch's is page state.
  - No **Install** button beside a missing requirement or suggestion
    (the author's call): there's no registry to fetch one from yet, and
    the uploader can't know the kind.
  - **Details** keeps the Namespace row for every kind, and Author is
    singular for one. **Funding** is the sketch's buttons. Links go
    by Blush's names for `support` keys (D-428), each with Lucide's
    glyph (the admin gained `bug` and `rss`); email isn't shown.
  - **Dependencies**: a section for each list the manifest has, with
    our hints (Replaces says what isn't so while one is on, D-436);
    Provides names packages without linking them. Under **What others
    say about it**, Conflicts with it, Replaced by, and Also provided by
    (D-440) join the sketch's lists, each with its kind's glyph (plug,
    paintbrush, or shapes, as the Config panel has them) rather than an
    on/off verdict; Provides keeps the neutral package glyph, since what
    it names has no kind. A theme's **Falls back to** is a section of
    it, as the sketch has; the default theme's **Used as fallback by**
    says "Every theme". A PHP extension reads "PHP Extension: intl", as
    the sketch has it (the author's call).
  - A theme without a palette is still sketched in the admin's colors,
    with the note on declaring one, where the sketch has a warning and
    no picture. The legend lists both halves even when they're the same.
  - The core icon set has a Details panel and no Dependencies panel. In
    the compact list its row has no menu (its label opens it), and a
    broken pack's row has no switch.
  - The status pill sits beside the title on the details screens, as
    the sketch has; **Abandoned** stays a pill in the lists and a notice
    on the details.
- **Vocabulary follows Blush** where it differs: extensions (plugins,
  themes, and icon packs; D-378), not addons, and
  whatever taxonomies a site defines (no built-in Topic).
- **Type-driven variation uses `labels`** (D-278), not §8's `label` and
  `singular`: the New button, the search field, the empty state, and every
  action toast take their words from `labels`, the phrases (`newItem`,
  `searchItems`) as they are, and `item` and `items` for nouns
  mid-sentence. The admin never lowercases a type's name itself.
- **Markdown reads as it looks** (D-253, then §8's Marking the source in
  D-265, D-268, and D-280): the source is marked as that section's table
  says. Emphasis is Fira Code's slanted face, not a true italic. Below
  480px the header's menus lose their carets, so it fits.
- **Fira Code is the mono** (D-254, D-255), not IBM Plex Mono: 400, 500,
  and a real 600 (so bold keeps its width), and a slanted italic (it has
  none). The writing column is 640px (`--measure`), with nothing between
  the title and the body.
- **Editor addresses by type and id** (D-483, replacing D-253's
  handles): `/content/{type}/{id}`, under the type's list, never the
  file's path, and the same through a rename.
- **Paths stay out of the UI** (D-254): tables show an entry's address on
  the site (`/archives/…`), not its file or folder, and untitled entries
  are "Untitled". Files belong in an info box, later.
- **Row menus** (D-254): Edit, View and Copy link once live (View archive
  for terms), and Move to trash; the trash's are Restore as a draft and
  Delete permanently. Duplicate is there since D-275 (not for terms,
  as in the prototype). The list's actions toast what they did (D-302),
  so a copy or a restored entry has no Open it link; it's in the list. A trashed entry's
  Preview (D-276) opens a read-only screen in the admin (the prototype's
  "Opened the editor"), not the themed page. The editor's Slug field
  (D-277) sits in the entry tab's Publish group, as the
  prototype's does, with a "Redirect the old address here" option
  for a live entry. The floating list opens above the button when there's
  no room below.
- **The pinned index page** (D-255): a collection's or taxonomy's
  landing page. Its pin sits in the checkbox column (D-301), or before
  the title where a table has no checkboxes (a type's first-run screen). It's pinned on the list's first page only, not
  on every page as "survives … paging" says (D-264). In a tree list its
  title leaves the triangle's space like the rows below it. The
  editor's side is done (D-274): the **Index** mark beside the type, a
  line on the entry's tab (under Publish, as the prototype has it),
  no type fields, no date or scheduling, and no Move to trash. Not yet:
  the type screen's switch, or a new type being born with one.
- **The root page and the homepage** (D-420), not in the direction:
  Pages pin `index.md` in the index page's place, and whichever entry
  is the homepage has a house for its pin and a **Homepage** tag; a root
  page the homepage doesn't show is tagged **Not shown**, with **Make
  homepage**.
- **The design refresh of D-265.** From the updated direction: the space
  scale (`--s-1` to `--s-7`), `--ctl` and `--ctl-sm`, flat surfaces
  (`--shadow-1: none`), larger radii, and the looser density tokens, in
  `resources/admin/css/tokens.css`, plus `--drawer` (372px) and
  `--inserter` (376px). The writing column stays 640px (`--measure`,
  D-254), not the direction's 68ch. Icons are `AdminIcon` with Lucide's
  paths in `icons.ts` (inline SVGs, not a sprite of `<symbol>`s); `.icon`
  sets the 1.6 stroke.
- **The compact toggle** (D-265): on each type's list, kept in this
  browser (`density.ts`), not an account preference yet; compact rows drop
  the address line.
- **Four inserters** (D-265): the inline menu lists the inline components
  except the icon, which has its own picker. The icon picker's groups are
  Blush's own fourteen categories for the core icons
  (`resources/icons/blush/categories.json`, `GET icons`' `category`),
  then a theme's, the site's, an icon pack's, or a plugin's icons by where they come
  from. A search selects its best match, so a name and Enter inserts it;
  otherwise nothing is chosen until clicked. The media picker's kinds are
  All, Images, Video, Audio, and Files (`GET media`'s `kind=file`).
- **The Variant select** (D-266, D-280) is an `AdminSelect` with Default
  first, then the component's variants under the active theme (a theme's
  or a plugin's variant of another's component names where it comes
  from), with the chosen one's description under it. A variant the
  component doesn't have here is kept, shown as "not available here".
  Variants aren't previewed.
- **Toasts** (D-387) follow `toast-sketch.html`, not `20-components.md`'s
  "one at a time": up to three stack, a plain one replacing the plain
  one standing. Undo is `--text-sm` (12px) rather than the sketch's
  12.5px, so no type size is a literal. Undo is offered only where the
  reverse is exact (plugin and icon pack switches for now); moving to
  the trash waits for a restore that keeps the status.
- **Title Case** (D-268, §10) is applied to names across the admin,
  core component labels included; `titleCase()` builds names from
  parts ("Edit Page").
- **Buttons are in Title Case** (D-521), where §10 keeps them in sentence
  case: every visible button label, as headings are (**Save Draft**,
  **Clear Filters**). Menu items, form labels, hints, toasts' messages,
  and an icon button's hidden name stay sentence case.
- **The inline menu holds the other inline elements** (D-496, the
  author's call: "This is all about inline elements"), where the
  direction's sentence group has bold, italic, link, icon, and inline
  component, with ⌘E and ⌘⇧X as keys without buttons (D-313). After
  Link, the **A** menu lists Strikethrough (⌘⇧X), Highlight (⌘⇧H, new),
  and Inline code (⌘E), each pressed while on (`aria-pressed`, drawn as
  `aria-current` is), then Mention (D-493), then the inline components;
  the icon button follows it. Mention opens a form beside the menu, as
  the link form does: a search of published profiles, arrows to move,
  Enter or a click to write `@slug`.
- **A floating menu keeps 16px from the window's edges and scrolls**
  when it's taller than the room it has, opening above its button when
  there's more room there, and refits when the window is resized (the
  author's call, for the inline menu).
- **A Writing settings screen** (D-494), which the settings sketch
  doesn't have: Markdown (Mentions, Smart punctuation, Heading anchors,
  Images as figures, as switches) and HTML (Raw HTML, as radios), drawn
  as every settings screen is. The HTML capabilities (D-495) are a
  site group on a role's screen, **HTML**.
- **Raw HTML in the editor** (D-495) is a dim token, and what the
  account couldn't add, and `javascript:` link addresses, are drawn in
  `--danger` with a wavy underline: color and decoration only, so no
  character moves.

- **Components are "Blocks"** (D-532, D-533): what the direction calls
  components (the inserter, the element tab's options, **Remove
  Component**) are directives in the code, and the admin calls them
  blocks: "Insert a block", "Blocks ( / )", **Remove Block**. Components
  in Blush are now a theme's template pieces, which the admin doesn't
  show.

- **Every card's head is over a hairline** (D-566): a panel, a library
  modal, a prompt, the inserter, and the sign-in card all draw their
  heading with `.panel__header`, then a border, then what's in them, where the direction leaves the prompts'
  and the sign-in card's titles flowing into their bodies and a library
  modal's line under its filter bar.

## Settled open questions

From §13, now `50-open-questions.md`:

- API conventions: a session cookie and CSRF header, `page`/`per` paging
  with `total` and `pages`, errors as `{"error"}` (`docs/admin.md`);
  content type discovery is `GET types`, with `labels` (D-234, D-278).
- The editor: the Markdown surface is a text area over a highlighted copy
  (D-241); the component inserter is `GET components` (D-243, D-247); the
  drawer's two tabs (D-245, D-280).
- Mapping the caret to the element under it, and rewriting options in
  place: `elements.ts` and `markdown.ts` (D-241, D-245, D-280).
- The media picker from a component option: **Choose** beside it (D-247).
- Also settled: icon categories (`GET icons`' `category` and `source`,
  D-265), and where a block's attributes go, uploads (`POST media`), and
  image variants (D-268, D-280).
- An attribute block on a thematic break: the site's parser reads a
  divider's attributes on a line of their own above it, not after the
  dashes, so that's where the editor writes them (D-268, D-280).
- From the split direction's list (D-313): the link's key is ⌘K in the
  text (the author's call); insertions don't toast; where the bleed
  steps land is the theme's, which names and styles the classes; and
  the source keeps spelling on.

Still open: the content-type builder's screens, type provenance in the
list header, and how long the breadcrumb may get (D-280 shrinks the
middle crumbs; a menu for them waits for real content that needs one).

## The Home sketch (`meridian-home.html`, D-537)

Built in stages; the first is the Dashboard and Tools (D-538 to
D-541). Where the admin differs from the sketch:

- **Shared pieces over the sketch's own.** Its `lst-row` rows are the
  `.rows` class and `EntryRows` component, its `seg` the `.segmented`
  control, its menus `MenuButton`, its tab chips the page tabs (`.status-tabs`), its clear
  state `EmptyState`, and its "Working…" the `.spin` in the button.
- **No "behind" bar, no Changes pill, no "Last run"** (D-537): nothing
  records unpublished changes or when an action last ran.
- **The page sub** is today's date and the environment as a `.tag`; the
  setup path's heading is "Set Up Your Site" with "A few steps", as the
  sketch has it, but its steps are only those the account can take.
- **Tools' groups** are named by source ("Core", a plugin's label, "This
  site"), with "Registered by …" as the hint; "asks first" is beside an
  action that confirms, as in the sketch.
- **The log** shows 50 entries, one line each with any trace folded under it, newest first as the sketch has it, and marks errors and warnings by
  the level in Blush's own line format.
- **Site Health** (stage 2, D-543): areas are Content, Media,
  Extensions, System, and Accounts, not Delivery and Security; only
  checks Blush can run; each Content and Media issue leads to a screen
  of its own with its fix (`/health/content/names` and so on, D-546),
  showing the kept check; the panel's count
  and "Last checked" come from the kept report (D-545); Requirements lists only what Blush needs, with no ini
  minimums; the two-column layout is the shared `.columns` class, and
  issue rows the shared `.rows` with `.rows__item` for rows that lead
  nowhere.
- **From a screenshot check against the sketch** (2026-10-06): a
  figure's label takes its warn or danger color, the side column is
  the sketch's 420px, requirement groups are shaded bands with the
  values in small mono, facts are edge-to-edge rows with labels in a
  column (`.fact-rows--panel`), a panel's closing links sit on its own
  ground (`.panel__foot`), the dashboard's environment is the sketch's
  small mono chip, and Tools' action names and buttons are Title Case
  (D-521). Kept as the admin has them: underlined page tabs (the
  sketch's are pill chips), the 16px gap between a screen's sections
  (the sketch's Home screens use more), and the notice under the tabs.
- **Shortcuts** (stage 3, D-547): as the sketch has them, but saved
  once, on Done, with one "Shortcuts updated" toast (D-549), where the
  sketch toasts each pin and removal; "Reading Settings" for a Settings
  screen in Add a Shortcut, and no counts on shortcuts. The sketch's
  Calendar isn't taken up (a calendar is for a plugin, D-550).

