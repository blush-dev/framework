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

## Files

```
.claude/docs/admin-design/00-project-brief.md … 90-conventions.md
                                               the design direction (as uploaded)
.claude/docs/admin-design/meridian-admin.html  its clickable prototype, standalone
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
- **Shared pieces are global classes** in `admin.css` (buttons, panels, pills,
  tables, stat tiles, notices), not yet `Base*` components; components keep
  their own layout in scoped styles (D-231). The directory layout follows the
  repo: `resources/admin/{css,fonts,js/{components,views}}`.
- **Tables aren't sticky-headed** (D-231): a table that scrolls sideways is its
  own scroll container.
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
  and show a row's parents before its title. There's no tree/flat switch.
  Filters and sorting flatten it too (D-300), and the note bar's **Clear
  the sort** button is how a sort is undone.
- **The filter row** (D-300): search, Author, one select per taxonomy
  the type uses (§8 says "its taxonomy", one), and Updated (7, 30, or 90
  days), but no **Unpublished changes** toggle, since there's no
  autosave. Author and term selects list only what the type's entries
  use (D-303), at most 100 items. The status
  tabs' counts follow the filters (the prototype's ignore them). The
  Trash tab takes only the search, and nothing sorts it.
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
- **The full navigation** (D-241): every screen in it is built now
  (D-309 retired the "comes next" page). A taxonomy moves when its `types` change
  in a config file, not in the admin, so there's no toast announcing the
  move (§8, Sidebar grouping); nav counts are left out until an API gives
  them cheaply.
- **The section rail** (D-244, D-280): no state dot on Content (without
  autosave there are no unpublished changes to live entries), no theme
  button in the top bar (the color scheme is an account preference on Your
  profile), and no site switcher; the site's mark links to the site. The
  account's menu is in the top bar. Home's panel has the Dashboard and
  Content health, then shortcuts. The rail never navigates, as §6 says.
  As the decisions log's *The shell* has it (D-317), a rail button
  toggles its panel and the top bar has no collapse button; the trail
  is the section, the screens above (the editor's type, or a detail
  screen's list from its route's `meta.parent`), and the screen; on a
  narrow screen the section crumb goes first.
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
  Navigation, Data), and a theme's, the site's, or an extension's by where
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
  `media.upload`; the menu is a plain button without it) takes the
  library's types as the site allows them (images, sound, video; no
  PDFs by default), up to PHP's limit, into `user/media/{year}/{month}/`.
  Inserted images get the library's alt text and caption (D-269); a
  Replace brings them only where the image had none. An image's own alt
  text and caption are its own there: the panel never writes them to
  the library. **Decorative** is empty alt text (D-272): on, the field
  is hidden (and a library description is offered); off, it shows. It's also
  **Choose** beside every media field and option, and **Replace** on an
  image.
- **The writing surface** (D-245, D-280): no `Changes` pill and the save
  state reads "Unsaved changes" or "Saved 3:46 PM" (no autosave); a save
  that changes no status (Update, Save draft, ⌘S) is disabled until
  something changes; nothing sits under the title (D-254); Tab in the
  body moves focus, but in a list item it nests the item (D-284); the
  drawer is remembered (D-299, which departs from §8's "the settings
  drawer starts shut"). Below 480px the save
  state is its dot, with its words read out.
- **The chrome doesn't fade while typing** (D-279): the author found the
  darkened toolbar and footer distracting, so §8's "Chrome recedes while
  typing" isn't followed.
- **The editor's menu** (D-280, D-283): **Preview** opens a signed
  preview link to the entry as last saved (a live one has **View**
  instead), and there's no **Revisions** (there are none). **Save draft** or **Switch to draft** is
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
  form. Fields in the drawer are filled wells (`--bg`), as the
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
  Upload tab and **Open** goes to the file. A file's screen has a
  Details panel built from the file's fields (D-287: title, alt text
  (warned when an image has none), caption, credit, description, and a
  site's own), saved with **Save**; no rename, "used in", Replace, or
  Delete yet. Only `user/media` is listed (D-294 removed D-292's page
  bundle files and its **Where** control).
- **Content types** (D-250, D-311): types in `user/data/types` are
  edited, created with the wizard, and deleted; the rest stay read-only.
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
  follows its type. Delete is in a Danger Zone, confirmed, and refused
  while a taxonomy groups the type. Save and Revert sit below the
  panels.
- **Roles and accounts** (D-249, D-312): Blush sends no email, so the
  prototype's **Invite** is **New Account**, its own screen (like every
  New) with a username instead of an email and name, and no note; the
  account gets a one-time password link shown on its screen to copy,
  and **Send a password reset** is **Make a password link**. Accounts
  have no email or name fields (the name comes from the author page,
  linked with an Author field) and no Entries column; the last sign-in
  stands in for "last active". Roles save as they're ticked, as in the
  prototype. Removing an account reassigns nothing (entries credit
  authors, not accounts). Roles are editable, so the prototype's "fixed
  in this release" banner and note are gone: **New Role** and
  **Duplicate** make roles, a role's screen has capability checkboxes
  with Save and Revert and a Danger Zone, and read-only roles say why.
- **Appearance** (D-306): no **Activate** or **Browse themes**. The
  active theme is developer config (`config/theme.php`, D-039), so a
  theme row offers **Copy command** (`theme:activate`) and, in
  development, **Preview** (`?theme=`); the themes the active one builds
  on say **In use**, and broken themes are listed with the reason. Rows
  have no author (manifests don't carry one). No theme settings yet
  (D-307).
- **Settings** (D-309): read-only and only the settings Blush has, so
  no save bar, Revert, inputs, or selects. The prototype's General,
  Content, Dates and Time, and Permalinks become General, Dates and
  Time, Content, Addresses, Feeds, Search Engines, Caching, and
  Publishing and Previews; there's no tagline, administrator email,
  front page entry, default new entry type, entries per page, trash
  emptying, date or time format, week start, or permalink structure.
  Values show a Default mark, help, and warnings; booleans are neutral
  On/Off pills (warn-colored when risky); each panel ends with the file
  it's set in.
- **Extensions** (D-308), the direction's Addons (vocabulary below):
  read-only, so no on/off switch, turn-off confirmation, **Settings**,
  **Browse**, or **Upload**; an **On** or **Off** pill instead, and an
  extension that's off says how to turn it on in `config/extensions.php`.
  A notice replaces the banner, naming where extensions are installed.
  "Provides" chips are grouped by kind (content types link to their
  screens; components, icon namespaces, dashboard actions, commands),
  attributed by class namespace or component/icon namespace, so they
  list only what the admin can see. No author (manifests don't carry
  one); the version, path, and requirements are shown. Rows share the
  global `.package` classes with Appearance's themes.
- **Component options** (D-245, D-268): an option set back to its
  default is removed from the directive; a required one left empty
  stays as `key=""`. Removing a container removes its body too (D-272). Option changes
  are applied to the text directly, so they aren't in the field's own
  undo (a list's List Type too). Classes and ID are fields; other
  undeclared attributes are listed.
- **Vocabulary follows Blush** where it differs: extensions, not addons, and
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
- **Editor addresses by handle** (D-253): `/content/{type}/{key}`, under
  the type's list, not the file's path.
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
  then a theme's, the site's, or an extension's icons by where they come
  from. A search selects its best match, so a name and Enter inserts it;
  otherwise nothing is chosen until clicked. The media picker's kinds are
  All, Images, Video, Audio, and Files (`GET media`'s `kind=file`).
- **The Variant select** (D-266, D-280) is an `AdminSelect` with Default
  first, then the component's variants under the active theme (a theme's
  or an extension's variant of another's component names where it comes
  from), with the chosen one's description under it. A variant the
  component doesn't have here is kept, shown as "not available here".
  Variants aren't previewed.
- **Title Case** (D-268, §10) is applied to names across the admin,
  core component labels included; `titleCase()` builds names from
  parts ("Edit Page").

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
