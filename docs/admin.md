# The admin

The admin is where people with an [account](accounts.md) run the site
from a browser. So far it has a dashboard (your content at a glance, and
buttons to publish, reindex, and clear caches), a list of each content
type's entries, an editor, and a content health check.

> **The admin is early.** The editor edits Markdown as text; a live
> preview, a form for a component's options, and a media library come
> later.

## Turning it on

The admin is off until you turn it on in `config/admin.php`:

```php
<?php

declare(strict_types=1);

use Blush\Admin\AdminConfig;

return new AdminConfig(enabled: true);
```

Then create an account, if you haven't yet:

```sh
bin/blush account:add jane
```

Open `/admin` on your site and sign in. While the admin is off, none of
its URLs exist.

| Option | Default | What it does |
|---|---|---|
| `enabled` | `false` | Turn the admin on |
| `path` | `'/admin'` | Where it lives, such as `'/dashboard'` |
| `app` | `null` | A folder with your own admin front end (see below) |

## Getting around

The rail at the far left has three sections: **Home** (the dashboard and
content health), **Content** (each content type's entries, with its own
taxonomies under it, the taxonomies several types share, and media), and
**Config** (content types, the site's settings, and people, including
authors). The panel
beside it lists the section you're in. Choosing a section changes the
panel and nothing else, so you never leave the screen you're on (an
entry you're writing stays open); choose a link in the panel to go
there. You only see what your account can use.

**Search or jump to…** in the top bar (or ⌘K, Ctrl+K on Windows and
Linux) opens the command palette: type to find a screen, a command such
as **New post**, or an entry by its title, then press Enter. In the
editor, the editor's own commands come first, such as **Focus mode**
and **Insert media**.

The button at the top left hides the panel, leaving just the rail (your
browser remembers the choice). The editor hides it while you write and
puts it back as it was when you leave. On a narrow screen it opens the rail and
panel as a menu instead. **View site** opens your site in a new tab, and
the round button at the top right has **Your profile** and **Sign out**.

## Your profile

**Your profile** shows your account (username, roles, linked author,
and when you last signed in), your **author page**, and your **color
scheme**. Your author page is the entry of your linked author: your name
in bylines, your bio, and your archive. **Edit your author page** opens
it in the editor; it's yours to edit even though no entry credits it. If
it doesn't exist yet, **Create your author page** starts it as a draft
(publish it to show your name). The color scheme is light, dark, or
your device's setting (the default). The choice is saved with your
account, so it follows you to every device you sign in on, and it only
changes what you see: someone else on the same site keeps their own.

**Change password** asks for your current password and a new one (at
least 12 characters, unless your site sets another length). You stay
signed in where you changed it, and you're signed out on every other
device. Too many wrong current passwords lock you out of changing it
for a while, as with signing in.

## The dashboard

The dashboard shows how many entries you have, by status, and the actions
your account may run. On a site with no content yet, it shows the steps
to write the first page and the first entry of each other type instead
of the counts. The actions:

| Action | What it does | Who can run it |
|---|---|---|
| Publish | Put content changes live, like `bin/blush publish` | Anyone with `site.publish` (editors) |
| Reindex content | Bring the content and media indexes up to date with your files | Anyone with `site.publish` |
| Clear caches | Empty the page, body, and fragment caches | Anyone with `cache.clear` (editors) |

Actions you can't run don't appear. Extensions can add their own actions
(see [Extending Blush](extending.md#admin-actions)).

## Entries

The sidebar lists your content types by name. **Content** has your
collections (such as Posts) and Pages, each with the taxonomies that
group only that type under it (a taxonomy whose `types` setting names
one type, such as Categories under Posts), then **Media**.
**Structure** has **Content types** and the taxonomies that group
several types or every type, each saying which. Authors are under
**People**, beside accounts, since they're the public side of accounts.
Each type opens a list of its entries you can edit, newest changes
first. Types are named from their `labels` setting, and
shown with their `icon` (see
[Content types](content-types.md#names-descriptions-and-icons-in-the-admin)).

**Site** has Appearance, Extensions, Accounts, Roles, and Settings.
Those screens, Media, and Content types aren't built yet: each says
what it will do and where to do that for now (a command or a config
file). You only see the ones your roles allow: Media needs
`media.upload`, Accounts and Roles `accounts.manage`, and the rest
`site.settings`.

The tabs above a list show all of them, or only published entries,
drafts, or scheduled ones, with a count on each. You see your own
entries if you're an author or contributor, and everyone's if you're an
editor; entries credited to your account's author are marked "Yours".
Drafts come most recently changed first, and scheduled entries in the
order they'll go live. Pages, and the terms of a hierarchical taxonomy,
list as a tree on the **All** tab when you aren't searching: each one
followed by the ones under it, indented, and those alphabetically
(Books, then Book Reviews indented under it, then Film). The triangle
beside an entry with others under it collapses or expands that branch;
the admin remembers which until you close it. When a later page starts
partway through a branch, the entries above it are shown again at the
top, marked **Continued**. On another tab or in a search the tree is
flattened, and a note above the list says how to get it back. Under each title is the entry's address on your
site (for a draft, the address it will have). In other tabs and in
search results, a page or a term of a hierarchical taxonomy has the
titles of the entries above it before its own (such as "Web › Web
design › CSS"). Search matches titles and file
paths. Click a title to edit the entry. The two buttons beside the
search switch between roomy rows and compact ones, which leave out the
address line; your browser remembers the choice.

The **⋯** button at the end of each row has **Edit**, then **View** and
**Copy link** once the entry is live (**View archive** for a term),
**Duplicate**, and **Move to trash** if your account can delete it.

**Duplicate** copies the entry beside the original as a draft titled
"… (Copy)", with the original's slug plus `-copy` (then `-copy-2`, and so
on, if that's taken). Everything else is copied as it is: the body, the
authors, and the other front matter, except its own `slug` and
`redirect_from` (the copy is named by its file, and old addresses stay
the original's); a dated entry is dated today, and
an entry in its own folder is copied with its media. A notice above the
list says so, with a link to open the copy. Terms and index pages can't
be duplicated.

A collection's or taxonomy's **index page** (the `index.md` in its
folder, which introduces its archive) is pinned at the top of its list
with a pin and an **Index** tag, on the list's first page. It isn't
counted in the list's totals, and it can't be moved to the trash (see
[Editing an index page](#editing-an-index-page)). It still follows the
tabs and search: it shows only when it matches them. Pages have no index page; the site's home page is listed
with the other pages.

A taxonomy's list (such as Categories) holds its **terms**. Instead of
authors, it shows how many published entries use each term.

A type with no entries yet skips the tabs and search: it says what the
type is for (its `description`, if it has one) and offers to create the first one. A site with no content
at all shows the same offer on the dashboard, one step per type.

**New post** (named for the type you're looking at) asks for a type and
a title, creates the entry as a draft, and opens it in the editor. It's
credited to your account's author.

## Editing an entry

The editor is one column of text: the title (press Enter to move to the
body), then the body in Markdown. It opens with the section panel and
the settings closed.

The body stays Markdown, set in Fira Code (the admin's monospace font
throughout), but the words read first: every mark (`*`, `**`, `#`, `>`,
a link's brackets, an attribute block's braces) is dimmed, and what it
wraps keeps full strength. *Emphasis* is slanted, **strong text** is
bold, headings are bold (lighter from `###` down), quotes are muted, and
struck text is dimmed. A link's text is colored and its address dimmed;
an image's text is muted. Inline code and fenced code sit on a gray
background, with a fence's language named. A task's `[x]` is green.
A table's pipes and its `| --- |` row are dimmed, but for the row's
alignment colons; its header row is bold. In a definition list, a term
is bold and a definition's `:` is dimmed. Footnotes are
colored. Attribute blocks, such as `{.stretch-wide}` after an image or a
heading, are a gray chip with their class and id names in full
strength. A component's name is colored, and a component or image the
settings are showing is boxed (a container on its first and last lines).
Only valid Markdown lights up, so something that stays plain won't be
read the way you meant.

Each entry's editor has its own address, from its content type and slug:
`/admin/content/post/hello-world` edits the post `hello-world`, and a
page's address has its folders, such as `/admin/content/page/about/team`.
An entry that can't be found that way (another language's, or one of two
files claiming the same slug) is edited at its file's path instead, such
as `/admin/entries/_posts/2026-09-29.hello.md`, which works for every
entry.

The header's left side has a link back to the type's list, then four
ways to put something in: **+** for a block component, the picture for
media, the shapes for an icon, and the **A** menu for a component inside
a sentence. Its right side says whether your changes are
saved and the entry's status, then has the settings button, the main
button, and a **⋮** menu in two parts: **View** (the settings panel,
the **Outline**, **Focus mode**, and **Preview**, or **View** once it's
live; a preview shows the entry as last saved) and
**Entry** (**Save draft** or **Switch to draft**, **Copy link** once
it's live, **Duplicate**, and **Move to trash**), with their shortcuts.
The main button depends on the entry:

| The entry is... | You can |
|---|---|
| A draft | **Save draft**, or **Publish** (or **Schedule**, when the publish date is in the future) |
| Scheduled | **Update**, **Publish** once its date is past, or **Switch to draft** |
| Published | **Update** (or **Schedule**, with a future date), or **Switch to draft** |

**Update** and **Save draft** stay off until you change something, since
there's nothing to save yet.

The settings (⌘/ or Ctrl+/) open beside the text and push it aside;
close them with their **×** or Escape. They have two tabs:

- **The entry's**, named for its type (**Post**, **Page**): under
  **Publish**, its **Status** (choose it to see what each status does,
  and to change it), its **Date** (choose it for a calendar and the
  time, on a 12-hour clock; a line under it says what the date means,
  such as "Goes live tomorrow"), its **Slug** (see below), its
  **Visibility** (**Public**, **Unlisted**: it has an address but isn't
  in lists, feeds, or the sitemap, or **Hidden**: no address), and a
  term's **Parent**. A line under them says what the date means and when
the file was last edited.
  Then the **Featured Image** (choose, replace, or remove it), the
  **Authors**, each taxonomy (see
  [Choosing terms and authors](#choosing-terms-and-authors)), the
  **Summary**, and the content type's other fields, such as the
  subtitle (a few kinds, such as `collection`, show their value
  read-only); front matter the type doesn't declare, kept as it is; and
  what content health finds in the file, as last saved (notices only if
  you ask). Only the taxonomies that group the content type are offered,
  plus any the file already uses. At its foot,
  **Outline** lists everything in the entry (see
  [The outline](#the-outline-and-the-breadcrumb)).
- **The element's**, named for what the cursor is in (**Callout**,
  **Heading 2**, **List**), with its settings (see
  [Element settings](#element-settings)).

The body is an ordinary text field, so undo, spelling, and your
browser's shortcuts work as usual. The footer shows where the cursor is
on the left (see below), and the words and reading time on the right.

In a list, Enter starts the next item with the same marker (the next
number, renumbering the list; an open `[ ]` for a task), and Enter on
an empty item ends the list, leaving a blank line so what you write next
is a paragraph. A quote carries its `>` the same way. In a table, Enter
adds a row with the same columns (and the `| --- |` row a table needs, if
it hasn't one yet); Enter on an empty row ends the table.

Formatting has the usual keys (Ctrl in place of ⌘ on Windows and
Linux), each turning it on for the selected text, or off when it's
already there:

| Keys | Does |
|---|---|
| ⌘B | **Bold** (`**text**`) |
| ⌘I | *Italic* (`*text*`) |
| ⌘E | Inline code (`` `text` ``) |
| ⌘⇧X | Strikethrough (`~~text~~`) |
| ⌘K | With text selected, a link: `[text]()`, with the cursor where the address goes (a selected address becomes `[](address)`). With nothing selected, ⌘K opens the command palette as usual |
| ⌘⌥1 to ⌘⌥6 | Make the line (or the selected lines) a heading of that level; the same keys again make it a paragraph. In a quote or list item, the heading goes inside it |
| ⌘⌥0 | Make the line a paragraph |
| ⌥↑, ⌥↓ | Move the line (or the selected lines) up or down; a numbered list is renumbered |
| Tab, Shift+Tab | In a list, nest the item under the one above, or bring it back out; what's nested under it moves with it. Elsewhere, Tab leaves the text as usual |

Pasting an address over selected text makes it a link. Dropping or
pasting files into the text uploads them to the library and puts each
in where the cursor is, as the media picker does (see below); that
needs `media.upload`. They're in the command palette too.

**Focus mode** (⌘⇧F or Ctrl+Shift+F, or the **⋮** menu) hides
everything but the text and the editor's own header. Press Escape to
leave it.

Ctrl+S (⌘S on a Mac) saves without changing the status. Saving changes
only what you changed: every other line of the file stays exactly as it
was. Leaving the editor with unsaved changes asks first.

If you can't publish, you can save drafts but not publish them.

Fields the content type marks as required must be filled in to publish,
schedule, or update a live entry. Anything missing is named at the top
and marked under the field; a draft saves without them.

### Choosing terms and authors

A field that points at other entries (a taxonomy's terms, the authors,
or any other type) is a picker rather than a list of slugs to type:

- **A hierarchical taxonomy**, such as categories, is its whole tree,
  each term with how many entries use it. Search to narrow it (a
  match's parents stay in view) and tick the ones that apply. **New
  category** (named for the taxonomy) asks for a name and a parent and
  adds the term to your site, ticked.
- **Other taxonomies**, such as tags, are chips. Type to see matching
  terms and press Enter for the first, or, when nothing matches, to add
  what you typed as a new tag. Backspace in the empty field removes the
  last chip.
- **Authors** are listed by name, the first marked **Lead**. Search to
  add someone. An entry always has an author, so the last one can't be
  removed until another is added.
- **One value**, such as a term's parent, is a list to choose from,
  indented to show the tree.

A slug that nothing answers to is shown as written, marked as not found.

### Changing the slug

The **Slug** field on the Document tab is the last part of the entry's
address, such as `hello-world` in `/archives/2026/09/30/hello-world`.
Change it and save to rename the entry: its file is renamed (a dated
file keeps its date), or, if the file sets its own `slug` in its front
matter, that is changed instead. Slugs are lowercase letters, numbers,
and hyphens, and another entry of the same type can't already have it;
if the name is refused, nothing is saved and the field says why.

For a published entry, the field shows the new address, and **Redirect
the old address here** (on by default) adds the old address to the
entry's `redirect_from`, so links to it keep working. Landing pages
(the home page, and a folder's `index.md`) take their folder's name,
so they have no Slug field.

### Editing an index page

A type's index page opens in the same editor, marked **Index** beside
its type, and the Document tab says what it is. It introduces the
type's archive rather than being one of its entries, so it leaves out
what doesn't apply: the type's fields (such as categories or a
subtitle; any already in the file are kept as they are, under front
matter the type doesn't declare), the publish date, and scheduling. It
can be a draft or published, and publishing doesn't add a date. There's
only one, so it has no **Move to trash**.

### Inserting components

**+** opens the block components beside the text, on the left, grouped
by category: search by name or what it does, or scroll, then choose one
(or use the arrow keys and Enter). The strip at the bottom describes the
highlighted one. The panel stays open, so you can add several; close it
with its **×** or Escape.

The **A** menu lists the inline components, the ones that go inside a
sentence (a keyboard key, an abbreviation), each with what it does.

Typing `/` at the start of an empty line opens the same panel: keep
typing to narrow it (`/call` for a callout), then press Enter or Tab.
Press Escape to keep the `/` as text.

The component is written into the Markdown by its full name, such as
`:::blush/callout` … `:::`, with the cursor where your text goes. An
inline component (a keyboard key, an abbreviation, an icon) goes at the
cursor, inside the sentence; the rest go on lines of their own. Select
some text first to make it the component's text. Options the component
needs are written empty for you to fill in, such as
`::blush/video{src=""}`. Undo takes an insertion back.

The list has every registered component with a class that your active
theme can draw: the built-in ones, your theme's, your site's, and your
extensions' (grouped by where they come from). See
[Registering a component](components.md#registering-a-component) to
add yours.

### Inserting media and icons

The picture button has two ways in: **Media Library** and **Upload a
File**. Both open the same picker, on its **Library** or **Upload** tab.
**Image** in the components panel opens it too, showing only images.

The Library tab has the library in `user/media`, newest first. Search by file name, or show only images,
video, audio, or other files. Choose a file (it gets a tick), then
**Insert** (or double-click it). An image goes in as plain Markdown on a
line of its own, `![](/media/photo.jpg)`, with the cursor where its
description goes (selected text becomes the description), and its
settings open on the element tab (see
[Images](#images-and-blocks)). When the library has alt text and a
caption for the file (see [Media](#media)), they're filled in:
`![A lake at dawn](/media/lake.jpg "The lake at dawn")`, with selected
text still winning for the description. On the site it's a figure; a quoted title
after the address, `![A lake](/media/lake.jpg "The lake at dawn")`, is
its caption. A video goes in as a video, a sound as audio, and anything
else as a download, and their options open in the settings.

The Upload tab takes files from your computer: drag them anywhere onto
the picker, or use **Choose files**. It says how large a file may be and
which types the library takes. Each one goes into `user/media` under the
year and month (`user/media/2026/09/`), with its name made safe for an
address (`My Photo.JPG` becomes `My-Photo.jpg`); a name that's taken gets
`-2`, `-3`, and so on, so nothing is replaced. An upload lands at the top
of the library, chosen, so **Insert** finishes the job; **Show in
library** switches tabs to see it there. Uploading needs `media.upload`.
How large a file may be is up to PHP (`upload_max_filesize` and
`post_max_size`).

The same picker is **Choose** beside every media field and component
option, such as a video's **Poster image**.

The shapes button opens your theme's [icons](components.md#icons),
grouped: the built-in icons by category (Status, Interface, Arrows, and
so on), then your theme's, your site's, and your extensions'. Pick a
group on the left, or search by name or what it shows (`home` finds the
house), then choose one and **Insert** (or double-click it; after a
search, Enter inserts the first match). It goes in at the cursor as
`:blush/icon[]{name=house}`; select some text first to give it a label
for screen readers.

### The outline and the breadcrumb

The footer's breadcrumb says where the cursor is, from the entry down to
the smallest thing it's in: `Post › List › List Item › List › List
Item`. Choose any part of it to select that element and open its
settings; the first opens the entry's own tab.

**Outline**, at the foot of the entry's tab (or in the **⋮** menu),
lists every element in the entry in order: paragraphs, headings, lists
and their items, quotes, code blocks, tables, definition lists,
images, and block components (inline components are part of their
sentence, so they aren't listed), each with a line of what's in it.
What's inside a component, list, or list item is indented under it.
Choose one to select it; **←** goes back.

### Element settings

The element tab follows the cursor and shows whatever it's most
precisely in: inside a callout, that's the paragraph (the callout is
above it in the breadcrumb); on the callout's own first or last line,
it's the callout. On a blank line, it's the element above, but not one
inside a component that has already closed. A component, a list, a list
item, and a definition list list what's directly inside them under
**Content**; choose one to select it.

A component's options are a form. A component with
[variants](components.md#variants) lists them first, under **Variant**,
with what each one looks like; **Default** writes nothing. Changing one
rewrites just that option in the Markdown: the rest of what you wrote
stays as it is. Setting an option back to its default removes it, so the
default applies. A component that takes a line of text has it here as
**Text**. **Classes** and **ID** set its `.class` and `#id`; other
attributes that aren't options of the component are listed but edited in
the text.

**Remove component** takes the component out: a container with
everything inside it, a line component with its line, and an inline
component leaving its text in the sentence. Undo in the text puts it
back.

### Images and blocks

An image is plain Markdown, `![A lake](/media/lake.jpg "The lake at dawn"){.stretch-wide}`,
and its panel edits each part of it:

- **Variant**: the looks your theme offers images, such as **Wide**
  (see [Image variants](themes.md#image-variants)). Each is a class on the
  image, so choosing one swaps that class and leaves the others.
- **Image**: the picture itself, with its address and size under it.
  Point at it (or tab to it) for **Replace**, which opens the media
  picker, and **Remove**. A file that isn't there says so. A replacement
  brings the library's alt text and caption, if the image had none.
- **Decorative**, for an image that adds nothing a reader needs (an
  ornament, a divider). An image without alt text is decorative, so it's
  on when the brackets are empty; turn it off to write **Alt text**,
  which describes the picture for anyone who can't see it. Turning it on
  clears the alt text. If the library has alt text for the file, a
  decorative image offers **Use the library's**.
- **Caption**, the quoted part the site shows under it (left empty,
  nothing is written).

  Alt text and caption are this image's, in this entry: the library's
  don't change.
- **Classes** and **ID**.

Every block of Markdown takes classes and an id too, so a heading's,
paragraph's, list's, list item's, quote's, code block's, table's,
divider's, definition list's, term's, or definition's panel has
**Classes** and **ID**, which write `{.class #id}` where the site reads
it: at the end of a heading, paragraph, list item, term, or definition,
and on a line of its own above a list, definition list, quote, code
block, table, or divider (emptying both fields takes the line away
again). Some have
more: a heading's **Level** (1 to 6), a code block's **Language**, a
list's **List Type** (Bulleted, Numbered, or Task, which rewrites its
markers and leaves the lists inside it alone), and whether a list item
is a task, and done.

### When a save doesn't go through

Your changes aren't lost:

- **Unsaved changes stay in your browser** as you type. If the tab
  closes or the browser crashes, open the entry again and choose
  **Restore them** (or **Throw them away**).
- **Offline**, a bar under the top bar says so, and you can keep
  writing. A save you ask for shows **Waiting for a connection** and goes
  ahead once you're back online.
- **A save that fails** (a server error, say) shows **Not saved** and a
  **Try again** button.
- **If the entry changed after you opened it** (someone else saved it,
  or its file was edited or pulled from git), the editor stops saving
  and says when it changed. **Compare** shows the fields and body lines
  that differ; **Keep theirs** throws your changes away for their
  version; **Keep mine** saves your version of everything the editor
  shows over theirs.

**Move to trash** takes the entry off your site and puts it in the
**Trash** tab of its list.

## Media

**Content → Media** shows the files in `user/media`, newest first, each
by its title (or its file name, until it has one):
search by name or details, show only images, video, audio, or other
files, or choose **Missing alt text** for the images without it (each
is marked in the grid too). Choose one for a
preview, its **Details** (the fields files of its kind have: **Alt
text** for an image, **Caption**, **Credit**, and **Description**, and
any your site adds; see [Details about a file](media.md#details-about-a-file)),
what the file says about itself (**From the File**, with **Use** to
copy a value into your details, and a warning if it carries its
location; see [What a file says about itself](media.md#what-a-file-says-about-itself)),
facts about the file, and what to write to use it, with **Copy**
buttons. Alt text describes the file for anyone
who can't see it, and the caption goes under it; both are filled in when
it's inserted as an image. After that, the entry's copy is its own: the
page shows what the entry wrote, and an image with empty brackets,
`![](/media/rule.png)`, has empty alt text (`alt=""`), which marks it
decorative. Changing an image's alt text or caption in the editor never
changes the library's, and changing the library's never changes an
entry. They
need `media.upload` to change, and are saved when you choose **Save**.

They're kept apart from the file, in `user/data/media/`, which mirrors
the media paths: `user/media/2026/09/lake.jpg` has
`user/data/media/2026/09/lake.jpg.yml`:

```yaml
alt: A lake at dawn, with mist on the water.
caption: The lake at dawn
```

(or `.yaml` or `.json`). You can write these by
hand; saving from the admin changes only the fields you changed and
keeps the rest (keys that aren't fields are listed, as they are), and
removes a file left with nothing in it. If you rename or
delete a media file by hand, move or delete its metadata file too. **Upload** opens the same picker the editor uses, on its Upload
tab; **Open** goes to the file you uploaded. You can also put files in
`user/media` yourself.

## Content types

With `site.settings`, **Config → Content types** lists every type,
taxonomies too, with where it's defined, how many fields it has, and how
many entries. Choose one for its settings, the taxonomies that group it,
and its fields; **Type settings** on a type's list goes there too. It's
read-only for now: types are defined in `config/content.php`,
`user/data/types`, and extensions (see [Content types](content-types.md)).

## Accounts and roles

With `accounts.manage`, **Config → Accounts** lists who can sign in, with
their roles, linked author, and when they last signed in; choose one for
its details. **Roles** lists each role with how many capabilities it has
and who holds it; a role's screen shows every capability it grants or
doesn't, in groups. Both are read-only for now: roles are set in
`config/auth.php`, and accounts are changed with the `account:*`
commands (see [Accounts](accounts.md)).

## Trash

Each list has a **Trash** tab (if your account can delete entries) with
the entries moved there, most recent first. Each one's **⋯** button has:

- **Restore as a draft** puts it back where it was, as a draft, even if
  it was published before; publish it again from the editor when you're
  ready. If something else now has its file name, rename or move that
  first.
- **Preview** (or clicking its title) shows what's in it: the body, as
  the editor shows it but read-only, and its front matter. From there
  you can restore it or delete it permanently. A trashed entry isn't on
  your site, so it can't be viewed there or edited until it's restored.
- **Delete permanently** removes it for good.

**Empty trash** deletes everything in that tab permanently. Authors and
contributors see and handle their own trashed entries; editors see
everyone's.

On the server, each trashed entry is a folder in `storage/trash/` (an
entry in its own folder, `trip/index.md`, takes the folder with it), so you can also restore one by moving its
file back into `user/content/`.

## Previewing drafts

In the editor, an entry that isn't live yet has a **Get link** button
under Publishing. It makes a
preview link: a private URL that shows the entry, with your theme, as it
will look once it's live. **Open** it in a new tab, or **Copy** it to
send to someone. Anyone with the link can see the entry, without an
account, until the link expires a week later.

You can also make one from the command line:

```sh
bin/blush content:preview post my-new-post
bin/blush content:preview post my-new-post --hours=2
```

Links are signed with the `APP_SECRET` in your `.env` (`bin/blush init`
adds one), so they can't be changed to show another entry or to last
longer. Changing `APP_SECRET` ends every link you've given out.
Previews are never cached or indexed by search engines.

## Content health

**Content health** checks every content file for problems, as
`bin/blush content:lint` does: front matter that isn't valid, such as an
unknown status or a date that isn't one, and two files claiming the same
entry. It checks media details in `user/data/media/` too: one that
can't be read, a value that doesn't fit its field, or details left for
a file that's gone. Turn on **Include notices** to also see undeclared keys, 1.x
names, and terms without their own file. It's for editors and
administrators.

## Your own admin

Blush's admin is one front end for the admin's API, and you can swap in
your own. Build it with Vite (with `build.manifest` on) and point `app`
at the build folder:

```php
return new AdminConfig(enabled: true, app: __DIR__ . '/../public/my-admin');
```

Blush puts the entry's script and styles on the admin's page, and serves
the build folder's files at `/admin/assets/` (scripts, styles, images,
and fonts; never `.vite/`). Build with plain file names, as the
[theme build](themes.md) does: Blush adds `?v=` and a hash of each
file's contents to its URL, so browsers cache the files until they
change. The page also includes a JSON block (`#blush-admin-config`)
with the admin's path, the API's path, the site's name, and the
signed-in account's `colorScheme` (`null` when no one is signed in). For
a light or dark account, `<html>` also carries `data-color-scheme`.

The API is JSON under `/admin/api`, and uses the session cookie:

| Request | What it does |
|---|---|
| `GET session` | The signed-in account (its username, roles, capabilities, and preferences) and a CSRF token, or `{"account": null}` |
| `POST login` | Sign in with `{"username", "password"}` |
| `POST logout` | Sign out |
| `POST password` | Change the account's own password with `{"current", "password"}`; answers `204`. Other sessions are signed out; this one stays, with a new id. A wrong current password or a short new one is a `422` whose `field` names it |
| `PATCH preferences` | Change the account's own preferences, such as `{"colorScheme": "dark"}` (`system`, `light`, or `dark`); answers `{"preferences"}` |
| `GET dashboard` | The site, entry counts by status, and the actions the account may run |
| `POST actions/{name}` | Run an action; the answer is `{"successful", "message", "details"}` |
| `GET icons` | The icons the active theme can show: `{"icons": [{"name", "label", "keywords", "category", "source", "svg"}]}`; a built-in icon has its `category` (such as `arrows` or `media`) and a `null` `source`, and the rest have a `null` `category` and a `source` like a component's |
| `GET media` | The media files an entry can use (see below) |
| `GET media/{path}` | One file in the library, by its path under `user/media`, with its details (see below) |
| `POST media` | Upload a file to the library (see below) |
| `PATCH media/{path}` | Change a library file's details (see below) |
| `GET components` | The components the editor's inserter offers: `{"components": [{"name", "label", "description", "content", "kind", "category", "source", "props"}]}` (see below) |
| `GET roles` | Every capability and role, with the accounts holding each; needs `accounts.manage` |
| `GET accounts` | Every account's username, roles, author, and created and last sign-in times (Unix); needs `accounts.manage` |
| `GET types` | The site's content types: `{"types": [{"name", "labels", "description", "icon", "kind", "dated", "origin", "folder", "prefix", "fields"}], "authors"}`, by plural label, taxonomies last; a taxonomy adds `"types"`, the types it groups (empty for every type), and `"hierarchical"`. `fields` is how many the type defines, `icon` is `null` for the kind's, and `authors` names the type accounts' authors belong to (`null` when it's disabled). `labels` has every [label](content-types.md#names-descriptions-and-icons-in-the-admin), defaults filled in |
| `GET types/{name}` | One type, with its own `fields`, the `taxonomies` that group it, `public`, `feed`, `sitemap`, and `editable` |
| `GET references/{type}` | What a reference field to `type` can point at, for the editor's picker (see below) |
| `GET entries` | The entries the account may edit, a page at a time (see below) |
| `GET health` | Content problems by file, with counts (`?strict=1` adds notices); needs `content.edit.others` |
| `POST previews` | A preview link to an entry the account may edit, from `{"entry": id}`: `{"url", "expires"}` |
| `GET entries/{id}` | An entry for editing (see below) |
| `GET content/{type}/{key}` | The same, found by its handle, such as `content/post/hello` |
| `POST entries` | Create an entry: `{"type", "title"}`, and optionally `"slug"`, `"set"`, `"body"`, `"status"` |
| `PATCH entries/{id}` | Change an entry: `{"revision"}` plus any of `"set"`, `"remove"`, `"body"`, `"status"`, `"published"`, `"slug"` |
| `DELETE entries/{id}?revision=…` | Move an entry to the trash |
| `POST entries/{id}/duplicate` | Copy an entry as a draft (see Duplicate above); answers `201` with the copy as `GET entries/{id}` shows it. Needs `content.create` and the right to edit the entry; an index page is refused with a `422` |
| `GET trash` | The trashed entries the account may handle, newest first (`?type=` for one type): `{"trash": [{"id", "entry", "title", "type", "bundle", "trashed", "authors", "own"}]}` |
| `GET trash/{id}` | One trashed entry, as in `GET trash`, with its `frontMatter` and `body`; 404 if it isn't there or the account can't handle it |
| `POST trash/restore` | Restore `{"id"}` as a draft: `{"id"}` is the entry's id again; 409 when something else has its place |
| `POST trash/delete` | Delete `{"id"}` permanently |
| `POST trash/empty` | Delete everything the account may handle permanently (`{"type"}` for one type): `{"deleted"}` |

Once signed in, send the token from `session` or `login` in an
`X-CSRF-Token` header with every `POST`, `PATCH`, and `DELETE`. Errors are JSON too:
`{"error": "…"}`, with a 400, 401, 403, 404, 413, 422, or 429 status.

### Listing entries

`GET entries` lists the entries the account may edit: an author's own,
or everyone's for an editor. Narrow it with:

| Parameter | What it does |
|---|---|
| `status` | `draft`, `scheduled`, `published`, or `any` (the default) |
| `type` | A content type's name |
| `search` | Text the title or file path must contain, in any case |
| `page` | The page, from 1 |
| `per` | Entries per page: 20 by default, at most 100 |

Drafts and the whole list come most recently changed first, scheduled
entries soonest first, and published entries newest first. The answer
has `status`, `type`, `search`, `total`, `page`, `pages`, `per`, and
`entries`, each with its id, handle, title, type, status, dates, file,
`url` (its path on the site, where it is or will be once published, or
`null`), authors, whether it's the account's own, `index`,
`can.delete` and `can.duplicate`, and `ancestors`: the titles of the entries above it, from
the top down (a page's parent pages, or a hierarchical term's parents;
empty for the rest). With a `type` whose entries nest (pages, or a
hierarchical taxonomy), no `status`, and no `search`, entries come in
tree order instead: each followed by its children, siblings by title,
each with its `depth` (0 at the top) and how many `children` it has. A
page that starts inside a branch begins with the entries above it,
marked `continued: true` and not counted in `total`. Otherwise `depth`
and `children` are `null`, and `continued` is `false`.

With a `type` that isn't pages, the type's index page (its landing page)
is left out of `entries`, `total`, and `pages`, and answered as `index`
on the first page when it matches `status` and `search` and the account may
edit it; otherwise `index` is `null`. A page past the last has no
entries.

### Listing components

`GET components` lists the components the editor's inserter offers:
registered components with a class that the active theme can draw. Each
has:

| Key | What it is |
|---|---|
| `name` | Its full name, as written in Markdown: `blush/callout`, `acme/tabs` |
| `label`, `description` | From the translation catalogs, or a label made from the name |
| `content` | What it wraps: `none`, `text` (its `[label]`), or `blocks` |
| `kind` | How it's written: `container` (`:::`), `leaf` (`::`), or `inline` (`:`) |
| `category` | A built-in component's group (`text`, `media`, `layout`, `navigation`, `data`), else `null` |
| `source` | Where the rest come from: `{"kind": "theme", "site", or "extension", "label"}`, else `null` |
| `props` | Its props as schema fields, each with its `label` and, for a choice, `choices` labels by value |
| `variants` | Its variants under the active theme, not including Default: `{"name", "label", "description", "source"}`, where `source` is `null` when the component's own namespace declared it, else like the component's |

Beside `components`, `image` has the `variants` the active theme offers
Markdown images (its `theme.json` `variants.image`): each a class, with
its `name`, `label`, `description`, and `source` (`null`).

### Listing references

`GET references/{type}` answers what a reference field to `type` (a
taxonomy's terms, the authors, or any other type) can point at, for
anyone who can edit content, including entries they can't edit
themselves. Each item has the `slug` a reference stores, `title`,
`status`, its `parent`'s slug (or `null`), `uses` (how many published
entries use a term; `null` for other types), `depth` (its depth in a
tree, else `null`), `virtual` (a term entries use that has no file), and
`missing` (a slug you asked for that nothing answers to). A type's index
page isn't included.

A hierarchical taxonomy answers every term, in tree order (each
followed by its children, siblings by title), with `tree: true`. Any
other type answers the items whose title or slug contains `search`, by
title, at most `limit` (20 by default, up to 100), with the `total`
found. `slugs=a,b` adds those slugs to the answer, found or not, so a
field can name what it holds. `create` is `true` for a taxonomy: a slug
with no term is fine there, and becomes a virtual term.

### Listing media

`GET media` lists the library (`user/media`), newest first, a page at a
time, for accounts that can edit content. Narrow it with `search` (text
the path must contain), `kind` (`image`, `video`, `audio`, `file` for
anything else, or `any`),
`page`, and `per` (48 by default, at most 100). The answer has `total`,
`page`, `pages`, `per`, and `files`; each file has its `reference`
(what to write in content: the library's URL path), `name`,
`folder`, `url`, `mime`, `kind`, `size`, `width` and `height` (images),
`modified`, and the library's `alt` and `caption` for it (`""` for
none). Only the file types your site allows are listed. When
the account may upload (`media.upload`), `upload` has the largest file
PHP takes (`limit`, in bytes, or `null` for none) and the `extensions`
the library takes; otherwise it's `null`.

`POST media` uploads one file, sent as the multipart field `file`, and
needs `media.upload`. It goes in `user/media/{year}/{month}/` with a
name safe for a URL, and `-2`, `-3`, and so on when the name is taken.
Its extension must be one the library lists, and its contents must be
of a type your site allows ([`MediaConfig`](configuration.md)). The
answer is a 201 with the file, as `GET media` describes one; a file too
large is a 413, and one of the wrong type a 422.

`GET media` lists from the media index (see
[The media index](media.md#the-media-index)): `search` matches a file's
path or details, and `missing=alt` narrows it to images without alt
text.

Every file the media API answers has its `title`, `alt`, and
`caption` (`''` for none), and a sound's or video's `duration` in
seconds (`null` when unknown). `GET media/{path}` adds what the file says about itself, `embedded`:
its `values` (read from its EXIF, IPTC, and XMP: `title`,
`description`, `creator`, `copyright`, `credit`, `keywords`, `created`,
`camera`, `lens`, `focalLength`, `aperture`, `exposure`, `iso`,
`orientation`, `software`, and for sound and video `album`, `track`,
`genre`, `duration` (seconds), `artwork`, `width`, and `height`,
whichever it has) and `location`, whether
it carries one (never where). It also adds the file's details: the `fields` its kind has
(described as a content type's are), their `values`, keys its metadata
file keeps that aren't fields (`extra`), and `violations` for what
doesn't fit. `PATCH media/{path}` changes them: `{"set": {field:
value}, "remove": [field]}`, for a file in the library, needs
`media.upload`, and answers with the file. An empty value removes a
field, each value is checked by its field (a 422 naming the `field`
when it doesn't fit), and `alt` and `caption` may still be sent on
their own.

### Editing entries

An entry's id is its file's path under `user/content`, such as
`_posts/2026-09-29.hello.md`. Its `handle` is its type and key, such as
`post/hello`, and `GET content/{type}/{key}` finds it by that; a landing
page's key is `index`. An entry has no handle (`null`) when it isn't in
the site's language or another file claims the same key. `GET
entries/{id}` answers with:

- `values`: front matter by field name, read from whichever name the file
  uses (a 1.x `date` is `published`), and `extra`: anything the type
  doesn't declare.
- `body`, and `revision`: send the revision back with a change. If the
  file changed meanwhile, the change is refused with a 409, so no one's
  edit is lost. `modified` is when the file was last written (ISO 8601),
  or `null` if that isn't known.
- `type`: the type's name, kind, whether it's dated, and a description
  of each field (name, type, label, and options).
- `slug`: the last part of its key.
- `can`: whether the account may edit, publish, rename, delete, and
  duplicate it (`rename` and `duplicate` are `false` for a landing page;
  `duplicate` needs `content.create`).
- `index`: whether it's its type's index page. An index page's `type`
  describes only its `title` and `status` fields (the rest of its front
  matter is in `extra`), and `can.delete` is `false`.
- `violations`: the file's problems, as content health shows them.

A change only touches what it names; the rest of the file stays as it
was written. `set` takes field names to values, `remove` a list of
field names, and `body` the whole body. `status` is a shortcut:

| `status` | What happens |
|---|---|
| `draft` | Sets `status: draft` |
| `published` | Removes `status`, and dates the entry now if it has no date or a future one |
| `scheduled` | Removes `status` and sets `published` to the given `"published"` date, which must be in the future |

An index page can't be `scheduled` (a 400), publishing it doesn't date
it, and deleting it is refused with a 422.

`slug` renames the entry: when its front matter has a `slug`, that's
changed; otherwise its file is renamed first (a dated file keeps its
date, and an entry in its own folder moves the folder), so a refused name
changes nothing. A slug that isn't one, that another entry of the type
has, or that's a landing page's is a `422` with `"field": "slug"`. With
`"redirect": true`, a published entry's old address is added to its
`redirect_from`. The answer is the entry as `GET` would show it, with
its new id and revision.

New entries are drafts unless `status` says otherwise, credit the
account's author, and dated types get today's date.

What an account may do follows its [capabilities](accounts.md#capabilities):
editing needs `content.edit` for that entry, anything that leaves an
entry published or scheduled needs `content.publish`, and taking your
own author off an entry needs `content.edit.others`. So a contributor
can create and change drafts, but never publish them.
