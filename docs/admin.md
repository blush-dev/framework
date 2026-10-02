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

The rail at the far left has four sections: **Home** (the dashboard and
content health), **Content** (each content type's entries, with its own
taxonomies under it, the taxonomies several types share, and media),
**People** (your profile, everyone, and roles), and **Config**
(content types, fields, settings, themes, and extensions). The panel
beside it lists the section you're in. Choosing a section changes the
panel and nothing else, so you never leave the screen you're on (an
entry you're writing stays open); choose a link in the panel to go
there. Choosing the section that's already showing hides the panel,
leaving just the rail (your browser remembers it), and choosing it
again brings the panel back. You only see what your account can use.

The top bar says where you are: the section, then the screens above
this one, then this one, such as *Content / Posts / Editing* or *Config
/ Content Types / Pages*. The section's name shows its panel; the
screens before the last go back to them.

**Search or jump to…** in the top bar (or ⌘K, Ctrl+K on Windows and
Linux) opens the command palette: type to find a screen, a command such
as **New post**, or an entry by its title, then press Enter. In the
editor, the editor's own commands come first, such as **Focus mode**
and **Insert media**.

The editor hides the panel while you write and puts it back as it was
when you leave. On a narrow screen, the button at the top left opens
the rail and panel as a menu instead. **View site** opens your site in a new tab, and
the round button at the top right has **Your profile** and **Sign out**.

## Your profile

**Your profile** is one person: you. Once your account is linked to
an author with an author page, it's the editor for that page, where the
title is your name (in the admin and on the site) and the body is your
bio. Your account's own settings are the editor's **Account** tab:
your username, roles, linked author, and when you last signed in, your
password, and the admin's **theme** and **color scheme**. The address
stays `/profile`, and your page's slug can't change there, since your
account is linked by it.

Without an author page, Your profile shows those settings on their own,
with your **name** (what the admin calls you until you have an author
page) and, when your account is linked to an author, **Create your
author page**, which starts it as a draft (publish it to show your
name) and opens it here. The theme is **Neutral** (cool gray
with a blue accent, the default) or **Editorial** (warm paper, a teal
accent, and serif titles). The color scheme is light, dark, or your
device's setting (the default). Each choice is saved with your
account, so it follows you to every device you sign in on, and it only
changes what you see: someone else on the same site keeps their own.

**Change password** asks for your current password and a new one (at
least 12 characters, unless your site sets another length). You stay
signed in where you changed it, and you're signed out on every other
device. Too many wrong current passwords lock you out of changing it
for a while, as with signing in.

## The dashboard

The dashboard greets you by your [name](accounts.md#names) ("Good
afternoon, Sam Smith") and shows how many entries you have, by status, and the actions
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
several types or every type, each saying which. Authors are in
**People**, with accounts, since they're the public side of accounts.
Each type opens a list of its entries you can edit, newest changes
first. Types are named from their `labels` setting, and
shown with their `icon` (see
[Content types](content-types.md#names-descriptions-and-icons-in-the-admin)).

**People** has Your profile, People, and Roles. In
**Config**, **Settings** has General, Reading, Addresses and Search, and
System, and **Customize** has Themes and Extensions. You only see the
screens your roles allow: Media needs `media.upload`, People
`accounts.manage` or `content.edit`, Roles `accounts.manage`, and
Content types, Settings, Themes, and Extensions
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
top, marked **Continued**. On another tab, in a search, with a filter,
or sorted by a column, the tree is flattened, and a note above the list
says how to get it back. Under each title is the entry's address on your
site (for a draft, the address it will have). In other tabs and in
search results, a page or a term of a hierarchical taxonomy has the
titles of the entries above it before its own (such as "Web › Web
design › CSS"). Click a title to edit the entry.

Beside the tabs is a row of filters:

- **Search** matches titles and file paths. Press `/` anywhere on the
  list to start typing in it.
- **Author** shows only the entries crediting one author (not on a
  taxonomy's list, since terms aren't credited).
- One select for each taxonomy the type uses, such as **Any topic**,
  shows only the entries filed under one term.

The Author and taxonomy selects offer only the authors and terms this
type's entries use (drafts included), so a choice never comes up empty.
A filter with nothing to offer isn't shown.
- **Updated** shows only the entries changed in the last 7, 30, or 90
  days.

**Clear filters** turns them all off, and the tabs' counts follow the
filters. The Trash tab takes only the search. The two buttons at the end
of the row switch between roomy rows and compact ones, which leave out
the address line; your browser remembers the choice.

Click the **Title**, **Status**, **Authors**, or **Updated** header to
sort by that column, and again to turn the order around. Titles, statuses,
and authors start from A, and Updated from the newest. Below a list longer
than 10 entries, choose how many show on a page (20 unless you change it).
The filters, the sort, and the page size are part of the page's address,
so going back or sharing the link keeps them.

To change several entries at once, tick their checkboxes (the box in the
header ticks every entry on the page). A bar appears at the bottom with
how many you've chosen and what you can do to them: **Publish** (if your
account can publish), **Move to draft**, and **Move to trash** (if it can
delete). **Clear** unticks them all, and so does moving to another tab,
filter, sort, or page. Publishing dates an undated entry now, as it does
in the editor, and leaves out any entry with a required field empty. A
message at the bottom right says how many changed, and a notice above
the list names any that didn't, with why. The index page can't be ticked: its pin sits where the
checkbox would be.

The **⋯** button at the end of each row has **Edit**, then **View** and
**Copy link** once the entry is live (**View archive** for a term),
**Duplicate**, and **Move to trash** if your account can delete it.

**Duplicate** copies the entry beside the original as a draft titled
"… (Copy)", with the original's slug plus `-copy` (then `-copy-2`, and so
on, if that's taken). Everything else is copied as it is: the body, the
authors, and the other front matter, except its own `slug` and
`redirect_from` (the copy is named by its file, and old addresses stay
the original's); a dated entry is dated today, and
an entry in its own folder is copied with its media. A message at the
bottom right says so; the copy is listed with the drafts. Terms and index pages can't
be duplicated.

A collection's or taxonomy's **index page** (the `index.md` in its
folder, which introduces its archive) is pinned at the top of its list
with a pin and an **Index** tag, on the list's first page. It isn't
counted in the list's totals, and it can't be moved to the trash (see
[Editing an index page](#editing-an-index-page)). It still follows the
tabs and search: it shows only when it matches them. Pages have no index page; the site's home page is listed
with the other pages.

A type with [author archives](content-types.md#author-archives) may
have an **authors page** (`_authors.md` in its folder, which introduces
its list of authors). It's pinned under the index page with an
**Authors** tag, set apart from the totals the same way, and opens in
the editor as **Edit Authors Page**, without the type's fields or a
date, and with its slug fixed. Unlike the index page, it can be moved to
the trash.

A taxonomy's list (such as Categories) holds its **terms**. Instead of
authors, it shows how many published entries use each term.

A type with no entries yet skips the tabs and search: it says what the
type is for (its `description`, if it has one) and offers to create the first one. A site with no content
at all shows the same offer on the dashboard, one step per type.

**New post** (named for the type you're looking at) opens the editor on
a new post, with the cursor in the title. Nothing is written until you
save: the first save creates the file, named for the title (or the slug,
if you give one in the settings), as a draft unless you publish or
schedule it. A post needs a title to be saved. It's credited to your
account's author. If you leave before saving, nothing is created.

## Editing an entry

The editor is one column of text: the title (press Enter to start a new
paragraph at the top of the body), then the body in Markdown. It opens
with the section panel and the settings closed.

The body stays Markdown, set in Fira Code (the admin's monospace font
throughout), but the words read first: every mark (`*`, `**`, `#`, `>`,
a link's brackets, an attribute block's braces) is dimmed, and what it
wraps keeps full strength. *Emphasis* is slanted, **strong text** is
bold, headings are bold (lighter from `###` down), quotes are muted, and
struck text is dimmed. A link's text is colored and its address dimmed;
an image's text is muted. Inline code sits on a gray background, and a
fenced code block, fences and all, is one gray box, with its language
named. A task's `[x]` is green.
A table's pipes and its `| --- |` row are dimmed, but for the row's
alignment colons; its header row is bold. In a definition list, a term
is bold and a definition's `:` is dimmed. Footnotes are
colored. Attribute blocks, such as `{.bleed-wide}` after an image or a
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

There's no back button: the type's name in the top bar (**Posts** in
*Content / Posts / Editing*) goes back to its list. The header's left
side is what you do to the text, in three groups:

- **What goes in the entry**, always there: **+** for a block component
  or a Markdown element (heading, quote, list, definitions, code block,
  table, divider), which goes in with its placeholder selected so you
  can type over it, and the picture for media.
- While the cursor is in the text, **▴▾** move the element it's in (the
  one the footer names last) up or down past the one beside it, inside
  whatever holds it: a list item within its list, a paragraph within its
  callout, a callout among the entry's other elements (⌥↑ and ⌥↓ do
  too). An arrow is off when there's nothing to swap with. A numbered
  list keeps counting from where it started, and the blank lines
  between elements stay where they are.
- **What goes in a sentence**, shown only where Markdown's marks mean
  something (not in a code block, on a component's `:::` line, a
  divider, or a table's `| --- |` row): **Bold** and **Italic**, lit
  when the text at the cursor is bold or italic; the **link** form
  (**Text** and **Address**, filled in from the selection, the word at
  the cursor, or the link the cursor is in, with **Remove** for a link
  that's there); the shapes for an icon; and the **A** menu for a
  component inside a sentence. Inside a component that holds only some
  things, such as a gallery's images, they go, the component panel
  offers only what it holds, and the media picker shows only images.
- **Bleed**, for an element at the top of the entry: how far it reaches
  past the text column. **Base** is the column's width and writes
  nothing; **Wide** and **Full** write the classes your theme names (see
  [Bleed](themes.md#bleed)). The button shows the width in force, and is
  colored while the element is widened.

One of these is open at a time: opening a menu, the link form, or a
picker closes the component panel and anything else open. Its right
side says whether your changes are
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

The settings (⌘/ or Ctrl+/) open beside the text and push it aside, on
the element the cursor is in when it's in one, else on the entry's tab;
close them with their **×** or Escape. Your browser remembers whether
you left them open, so the next entry you edit opens the same way (on a
small screen, where they'd cover the text, they always start closed).
They have two tabs:

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
  read-only); each [field set](content-types.md#field-sets) added to the
  type, under its label; front matter the type doesn't declare, kept as it is; and
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
When the cursor leaves the text (you click the title, the margin, or
the entry's tab), the footer shows only the entry, the element's tab
says nothing's selected, and the sentence tools and bleed go; using the
header, the footer, the settings, or a picker doesn't count as leaving.

In a list, Enter starts the next item with the same marker (the next
number, renumbering the list; an open `[ ]` for a task), and Enter on
an empty item ends the list, leaving a blank line so what you write next
is a paragraph. A quote carries its `>` the same way. In a table, Enter
adds a row with the same columns (and the `| --- |` row a table needs, if
it hasn't one yet); Enter on an empty row ends the table. Typing three
backticks alone on a line writes the rest of the code block (an empty
line and the closing fence), with the cursor left where the language
goes; Enter from there steps into the block.

Formatting has the usual keys (Ctrl in place of ⌘ on Windows and
Linux), each turning it on for the selected text, or off when it's
already there (either way it was written: `*` or `_`, `**` or `__`).
With nothing selected, bold and italic act on the word at the cursor:

| Keys | Does |
|---|---|
| ⌘B | **Bold** (`**text**`) |
| ⌘I | *Italic* (`_text_`, or `*text*` inside a word, where `_` isn't italic) |
| ⌘E | Inline code (`` `text` ``) |
| ⌘⇧X | Strikethrough (`~~text~~`) |
| ⌘K | The link form, for the selected text, the word at the cursor, or the link the cursor is in. Outside the text, ⌘K opens the command palette as usual |
| ⌘⇧K | Remove the link the cursor is in, leaving its text |
| ⌘⌥1 to ⌘⌥6 | Make the line (or the selected lines) a heading of that level; the same keys again make it a paragraph. In a quote or list item, the heading goes inside it |
| ⌘⌥0 | Make the line a paragraph |
| ⌥↑, ⌥↓ | Move the element the cursor is in up or down among the ones beside it |
| Backspace | Just after a list item's marker, a quote's `>`, or a heading's `#`s: take the marker off, keeping the words (an indented list item comes out a level first) |
| Tab, Shift+Tab | In a list, nest the item under the one above, or bring it back out; what's nested under it moves with it. In a quote, add a `>` level, or take one off (the last one leaves plain paragraphs): the whole quote, or just the selected lines. Over several selected lines of a code block, indent them two spaces, or take up to two off. Elsewhere, Tab leaves the text as usual |

Text can't land inside the syntax around it by accident: a character
typed right after an attribute block at the end of a line goes before
it, at the end of the words; one typed right after a component's
`:::name{…}` or `::name[…]` goes on a new line below; and anything pasted
or inserted into a component's own line goes onto a line after it.
Typing inside the braces or a component's name still edits them.

Pasting an address over selected text makes it a link. Pasted
components are tidied so they can't break the ones around them: one
that was copied without its closing `:::` gets one, a stray `:::` left
over from copying part of a component is dropped, longer fences such as
`::::` become `:::`, and a pasted component goes on lines of its own,
as the inserter puts it. Dropping or
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

A type's index page opens in the same editor, as **Edit Index Page**,
and the entry's tab (**Index Page**) says what it is. It introduces the
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
option, such as a video's **Poster image**. A field or option that takes
one kind of file (a video's file, its poster, the featured image, an
image's **Replace**, or a field with a `kind`) shows only that kind,
without the kind buttons, and its upload refuses a file of another
kind, saying why.

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

An image is plain Markdown, `![A lake](/media/lake.jpg "The lake at dawn"){.bleed-wide}`,
and its panel edits each part of it:

- **Variant**: the looks your theme offers images, such as **Float
  Left** (see [Image variants](themes.md#image-variants)). Each is a
  class on the image, so choosing one swaps that class and leaves the
  others, its bleed included. How wide it is is **Bleed**, in the
  header.
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
and its fields; **Type settings** on a type's list goes there too.

Types in `user/data/types` are edited on their screen; types from
`config/content.php`, extensions, and Blush itself are defined in code,
so their screens only show them (see [Content types](content-types.md)).

### Creating a type

**New Content Type** walks through three steps, with **What Gets
Created** beside them:

1. **Basics:** content (a collection) or a taxonomy, its names, a key
   (made from the name, such as `recipe`), the folder its entries live in
   under `user/content` (made from the plural name), a description, and
   an icon. The key and folder can't change later.
2. **Behavior:** the URL prefix (the folder's by default), whether it's
   visible on the site, in the sitemap, and has a feed; for content, date
   archives and a featured image (an `image` media field); for a
   taxonomy, whether terms nest and which types its terms group; an
   index page, the type's landing page (on by default); and, when the
   site has authors, whether entries **credit authors** (on for content,
   off for taxonomies), whether each author **has an archive** under the
   type, the **word in the address** (`authors` unless you change it;
   the hint shows where the list and archives will be), and a **page
   introducing the list** (see [Author archives](content-types.md#author-archives)).
3. **Fields:** the fields its entries carry beside the title, slug,
   status, dates, and body.

**Create type** writes `user/data/types/{key}.yaml`, the index page
as `index.md` in its folder, titled with the plural name, and the
authors page, when chosen, as `_authors.md`, titled "Authors".

### Editing a type

A `user/data/types` type's screen has General (names, description,
icon), Behavior (as above), and Fields: open a field to change its
label, key, type, help, whether it's required, its default, and its
type's options (a number's limits and whole numbers, a choice's options,
a list's item type, a reference's type and whether it takes more than
one), move fields up or down, or remove one; **Add field** adds one.
Groups of fields (`object`) are kept as written; edit those in the file.
A field whose type can be edited more than one way has **Edited with**:
a choice as a menu or radio buttons, text on one line, several, or in
code type, a list of choices as checkboxes (see
[How the admin edits a field](content-types.md#how-the-admin-edits-a-field)).
The field types offered include ones extensions add. Choose **Save** to
write what you changed; **Revert** puts it back.

**Field Sets** lists the [field sets](#fields) added to the type, each
linking to its screen, where the types it's added to are chosen.

Only the options you change are written, and nothing at its default:
the rest of the file stays as you wrote it, comments included. A change
that doesn't fit with the other types, such as two types in one folder,
is refused with the reason, and the file is left as it was. The site
uses a change on the next request.

**Delete this type** removes its file. Its entries stay in its folder,
unlisted until a type claims the folder again. A type a taxonomy groups
can't be deleted until the taxonomy stops grouping it.

## Fields

With `site.settings`, **Config → Fields** lists every
[field set](content-types.md#field-sets): groups of fields added to
content types, to [media files' details](media.md#details-about-a-file),
or to the [Settings](#settings) screens, beside their own. The list shows where each set is added (a place the
site doesn't have is grayed), where it's defined, and how many fields it
has.

Sets in `user/data/fields` are edited on their screen; sets from
`config/fields.php` and extensions are defined in code, so their screens
only show them.

**New Field Set** has a label (heading the set's fields in the editor),
a key (made from the label; it's the file's name, so it can't change
later), help shown under the label, the kind of place to add it to
(content types; kinds of media file: images, videos, sound, and other
files; or the Settings screens; a set's places are all one kind), the
places of that kind, and its fields, edited as a type's are. **Create
Field Set** writes
`user/data/fields/{key}.yaml`.

A set's screen edits the same things; choose **Save** to write what you
changed, or **Revert** to put it back. Only what you change is written,
and the rest of the file stays as you wrote it, comments included. A set
can't use a field name a type it's added to already has: that's refused
with the reason, and the file is left as it was.

**Delete this field set** removes its file, and its fields leave the
types it was added to. Entries keep their values, shown as other front
matter.

## Settings

With `site.settings`, the **Settings** group in **Config** has four
screens:

- **General:** the site's name, its language and region (such as
  `en_US`), and its time zone, with the time there now. Beside them,
  shown but not changed here: the site's address, the environment, and
  detailed error pages.
- **Reading:** the home page (the page at `user/content/index.md`, or the
  latest entries of a collection) and feeds: the formats (RSS, Atom,
  JSON Feed; none turns feeds off), whether they carry each entry's full
  content, and how many entries each holds (1 to 100).
- **Addresses and Search:** whether addresses end in a slash (`/about/`;
  the other form redirects, so old links keep working), whether the
  site has a sitemap and `robots.txt`, and the paths `robots.txt` asks
  search engines to skip, one a line. Shown: the media address, and
  whether search engines are asked not to index the site (outside
  production, they are).
- **System:** shown only: where content types come from, caching, and
  publishing and previews.

Settings [field sets](content-types.md#field-sets) add come after a
screen's own, a panel for each set under its label. They have no config
file behind them: a saved one has **Clear it** instead (see
[Your own settings](themes.md#your-own-settings)).

Change something and a bar at the bottom counts your unsaved changes,
with **Revert** and **Save changes**; leaving the screen with changes
unsaved asks first. Saving keeps them in `user/data/settings.json`, where
they win over `config/` and `.env`, and they take effect on the next
page load, with nothing to compile. Under each setting, the admin says
whether it's saved there or comes from its config file. A saved one has
**Use `config/…`'s value**, which goes back to the config's value when
you save.

A setting that's only shown says when it's still the default and names
the file it's set in, and a risky one is flagged, such as detailed error
pages on a live site. Secrets are never shown, only whether one is set.
Those settings live in `config/` and `.env` (see
[Configuration](configuration.md)); after changing them on a site
you've compiled, run `bin/blush cache:compile` again.

## Themes

With `site.settings`, **Config → Themes** shows the site's theme:
every installed theme, with the active one marked **Active** and the
themes it builds on marked **In use**. A theme that can't be used (its
`theme.json` is broken) is listed with the reason.

Switching themes isn't done here: the active theme is set in
`config/theme.php`, so run `bin/blush theme:activate {slug}` (**Copy
command** beside a theme copies it). In development, **Preview** opens
the site with that theme (`?theme={slug}`, see [Themes](themes.md)).

How the admin itself looks is set per account, on **Your profile**.

## Extensions

With `site.settings`, **Config → Extensions** lists every installed
[extension](extending.md#extensions), the ones that are on first, with
its version, where it's installed, and what it requires. For each one
that's on, it shows what the admin can see it adding: content types
(each links to its screen, and says so when `config/content.php`
redefines it), components, icons, dashboard actions, and commands. An
extension can add more than that (routes, fields, code that runs on
every request), so one that lists nothing may still be doing something.

It's read-only: install extensions in `user/extensions` or with
Composer, and turn one off in `config/extensions.php`. An extension
that's off says how to turn it back on.

## Accounts and roles

**People → People** lists everyone in one list, by name: people with
an account, guest authors (an author page with no account), and authors
your entries credit who have no page yet (**No page yet**). Their
public name is their author page's title. With `accounts.manage`, tabs
split **Accounts** from **Guests**, and each row shows the account's
username, status (Invited or Suspended when it isn't active), roles,
and last sign-in; a name opens the account's screen. Otherwise a name
opens the author page in the editor, and you see only the authors you
may edit. Every row says how many published entries credit them.
**New Author** starts an author page; **New Account** makes an
account. **Roles** lists each role
with how many capabilities it has and who holds it. See
[Accounts and roles](accounts.md) for what roles and capabilities are.

### Accounts

- **New Account** asks for a username, its roles, and optionally a
  [name](accounts.md#names) and its author. Blush doesn't send email, so instead of a password the
  account gets a **password link**: copy it from the account's screen
  and send it however you like. It's shown only that once, and it
  works once, for a week. Until it's used, the account is **Invited**.
- On an account's screen, tick or untick its **roles** (they save
  right away; an account always keeps one), link it to an **author**,
  and change its **name** while it has no author page. Once it has one,
  the page's title is its name, and the screen links to the page.
- **Make a password link** is for a forgotten password: the person
  chooses a new one with it. Their old password keeps working until
  the link is used, and a new link replaces the old one.
- The **Danger Zone** suspends an account (it's signed out and can't
  sign in until you **Reinstate** it) or removes it. Removing an
  account leaves its author page and the entries crediting it alone.

### Roles

- **New Role** makes a role from a name, a key, a description, and its
  capabilities. **Duplicate** on any role starts a new one with its
  capabilities ticked.
- A role you made can be renamed, described, given or denied
  capabilities, and deleted once no account holds it.
- The built-in Editor, Author, and Contributor keep their names, but
  their capabilities can change; **Reset to built-in** puts them back.
- The Administrator always has every capability, and roles defined in
  `config/auth.php` are changed there, so both are shown read-only.

### What you can't do

So that managing people never hands out more than you have, or locks
everyone out:

- You can't give a role, or a capability, that you don't have yourself,
  or change an account that can do something you can't.
- You can't change your own account here. Your password is on **Your
  profile**; someone else (or `bin/blush`) changes the rest.
- A change that would leave no account able to manage accounts is
  refused.

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
signed-in account's `colorScheme` and `adminTheme` (`null` when no one
is signed in). For a light or dark account, `<html>` also carries
`data-color-scheme`, and for an Editorial one, `data-admin-theme`.

The API is JSON under `/admin/api`, and uses the session cookie:

| Request | What it does |
|---|---|
| `GET session` | The signed-in account (its username, `name`, `displayName`, `roles` (each `{"name", "label"}`), capabilities, and preferences) and a CSRF token, or `{"account": null}` |
| `POST login` | Sign in with `{"username", "password"}` |
| `POST logout` | Sign out |
| `POST password` | Change the account's own password with `{"current", "password"}`; answers `204`. Other sessions are signed out; this one stays, with a new id. A wrong current password or a short new one is a `422` whose `field` names it |
| `PATCH preferences` | Change the account's own preferences: `colorScheme` (`system`, `light`, or `dark`) and `adminTheme` (`neutral` or `editorial`), such as `{"colorScheme": "dark"}`; answers `{"preferences"}` |
| `PATCH profile` | Change the account's own name: `{"name"}` (`null` or empty removes it); answers `{"name", "displayName"}`. A name over 100 characters is a `422` |
| `GET dashboard` | The site, entry counts by status, and the actions the account may run |
| `POST actions/{name}` | Run an action; the answer is `{"successful", "message", "details"}` |
| `GET icons` | The icons the active theme can show: `{"icons": [{"name", "label", "keywords", "category", "source", "svg"}]}`; a built-in icon has its `category` (such as `arrows` or `media`) and a `null` `source`, and the rest have a `null` `category` and a `source` like a component's |
| `GET media` | The media files an entry can use (see below) |
| `GET media/{path}` | One file in the library, by its path under `user/media`, with its details (see below) |
| `POST media` | Upload a file to the library (see below) |
| `PATCH media/{path}` | Change a library file's details (see below) |
| `GET components` | The components the editor's inserter offers: `{"components": [{"name", "label", "description", "content", "kind", "category", "source", "props"}]}` (see below) |
| `GET roles` | Every capability (`{"name", "label"}`) and role: `{"name", "label", "description", "capabilities", "builtIn", "origin", "accounts", "grantable", "editable"}` (`accounts` is each holder's `{"username", "displayName"}`), and a changed built-in's `defaults`. `origin` is `built-in`, `changed`, `custom`, or `config`; `grantable` is whether you may give it, and `editable` whether you may change it. Needs `accounts.manage`, as do all of these |
| `POST roles` | Make a role: `{"name", "label", "description", "capabilities"}`; answers `201` with `{"role"}` |
| `PATCH roles/{name}` | Change a role: any of `label`, `description`, and `capabilities` (a built-in takes only `capabilities`); answers `{"role"}` |
| `DELETE roles/{name}` | Delete a role no account holds, or reset a changed built-in; answers `{"role"}` (`null` once deleted) |
| `GET accounts` | Every account: `{"username", "name", "displayName", "roles", "author", "authorPage", "created", "lastLogin", "status", "link", "manages"}`. `name` is its own name or `null`; `authorPage` is its author page's `{"id", "handle"}` or `null`; `displayName` is what the admin calls it, its one name: its author page's title, else the name, else the username; `status` is `active`, `invited`, or `suspended`; `link` is its password link's `{"expires", "expired"}` or `null`; `manages` is whether you may change it. Times are Unix |
| `GET people` | Accounts and authors as one list, by name: `{"people": [{"name", "author", "entry", "virtual", "account", "uses"}]}`. `entry` is the author page's `{"id", "handle", "status"}` or `null`; `virtual` is whether entries credit them without a page; `account` is as `GET accounts` has it, or `null` for a guest (and always `null` without `accounts.manage`); `uses` counts the published entries crediting them. Needs `accounts.manage` or `content.edit`; without `accounts.manage`, only the author pages you may edit (and, with `content.edit.others`, authors with no page) |
| `POST accounts` | Make an account: `{"username", "roles", "author", "name"}` (the last two optional); answers `201` with `{"account", "link": {"url", "expires"}}`. The link is shown only this once |
| `PATCH accounts/{username}` | Change an account: any of `roles`, `author` (`null` unlinks), `name` (`null` or empty removes it), and `suspended`; answers `{"account"}` |
| `POST accounts/{username}/link` | A new password link, replacing any other: `{"account", "link"}` |
| `DELETE accounts/{username}` | Remove an account; answers `204` |
| `POST set-password` | Choose a password with a link: `{"account", "token", "password"}`; signs in and answers `204`. No account needed. A short password is a `422` (`field` `password`); a link that's expired, used, replaced, or for a suspended account is a `410`, and too many tries a `429` |
| `GET appearance` | The installed themes: `{"active", "chain", "config", "preview", "themes": [{"slug", "name", "version", "description", "parent", "source", "active"}], "invalid": [{"slug", "reason"}]}`. `chain` is the active theme, the themes it builds on, then `default`; `config` is whether `config/theme.php` exists; `preview` is whether `?theme=` works (development only); `source` is `framework`, `local`, or `composer`. Needs `site.settings` |
| `GET settings` | The site-wide settings, to show: `{"groups": [{"key", "title", "hint", "file", "note", "items": [{"key", "label", "value", "kind", "default", "help", "warning"}]}]}`. `kind` is `text`, `mono`, `bool` (the value is `true` or `false`), or `list`; `default` is whether it's unchanged (`null` for one that follows from others); `note` marks code with backticks. A setting the admin changes adds its `setting` (`feed.limit`, or `site.{name}` for one a field set adds), its `field` (as forms take it, with `choices` and a `caption`), the `input` the form starts from, and whether it's `saved`. Secrets are never sent. Needs `site.settings` |
| `GET extensions` | Every installed extension, the ones that are on first: `{"extensions": [{"name", "version", "description", "source", "path", "requires", "enabled", "adds"}], "config"}`. `source` is `local` or `composer`; `path` is from the site's root; `adds` has `types` (`{"name", "label", "overridden"}`), `components`, `icons` (namespaces), `actions`, and `commands`, all empty for one that's off; `config` is whether `config/extensions.php` exists. Needs `site.settings` |
| `GET types` | The site's content types: `{"types": [{"name", "labels", "description", "icon", "kind", "dated", "authors", "origin", "folder", "prefix", "fields"}], "authors"}`, by plural label, taxonomies and authors last. `kind` is `collection`, `taxonomy`, `pages`, or `authors`, and each type's `authors` is whether its entries credit authors. A taxonomy adds `"types"`, the types it groups (empty for every type), and `"hierarchical"`; the authors type adds `"types"`, the types that credit authors. `fields` is how many the type defines, `icon` is `null` for the kind's, and the top-level `authors` names the authors type (`null` when the site has none). `labels` has every [label](content-types.md#names-descriptions-and-icons-in-the-admin), defaults filled in |
| `GET types/{name}` | One type, with its own `fields`, the field `sets` added to it (`{"name", "label", "fields"}`), the `taxonomies` that group it, `public`, `feed`, `sitemap`, `editable` (defined in `user/data/types`), `dateArchives`, `folderPrefix` (the URL prefix its folder gives), `file`, its `index` page (`{"id", "title"}` or `null`), `authorsWord` (the word its author archives sit under, `false` for none, `null` without URLs), and its `authorsPage` (`{"id", "title"}` or `null`). `GET types` adds `create` (whether types can be created here) and `urls` (whether they may set URLs) |
| `POST types` | Create a type in `user/data/types`: `{"name", "kind"` (`collection` or `taxonomy`), `"folder", "set", "index", "authorsPage"}`; answers `201` with the type. `set` maps options to values: `labels`, `description`, `icon`, `prefix`, `authorsWord` (the word author archives sit under; `false` for none, `null` for `authors`), `public`, `sitemap`, `feed`, `authors`, `dateArchives`, `hierarchical`, `types`, and `fields`; `index: true` adds its index page, and `authorsPage: true` its authors page (a `422` without author archives). Needs `site.settings` |
| `PATCH types/{name}` | Change a `user/data/types` type: `{"set", "index", "authorsPage"}`, as above (`null` removes an option); answers with the type. A change that doesn't fit is a `422` with the reason |
| `DELETE types/{name}` | Delete a `user/data/types` type's file (its entries stay); answers `{"deleted"}` |
| `POST types/refresh` | After a change: compile the routes again (on a compiled site) and reindex, so the site uses the change; answers `{"routes", "indexed"}` |
| `GET fields/types` | The field types definitions can use, built in and from extensions: `{"types": [{"type", "label", "description", "controls", "options"}], "controls"}`. `controls` are `{"value", "label"}`, a type's first being its default; `options` are the type's own definition keys as JSON Schemas |
| `GET fields/sets` | The field sets: `{"sets": [{"name", "label", "description", "kind", "slot", "origin", "editable", "file", "targets", "fields"}], "create", "targets"}`. `origin` is `extension`, `config`, or `data`; each set's `targets` are `{"key", "label", "found"}` (`found` is whether the site has it); `fields` is how many; the top-level `targets` are every place a set can be added to (`{"key", "label", "group", "kind"}`: content types, kinds of media file, and Settings screens), and `kinds` each kind with its `slots` (`{"name", "label", "description"}`, the first its default); `create` is whether sets can be created here |
| `GET fields/sets/{name}` | One set, with its `fields` as definitions, `options`, every place it can be added to, and `kinds` |
| `POST fields/sets` | Create a set in `user/data/fields`: `{"name", "set"}`, where `set` maps `label`, `description`, `targets` (all one kind), `slot`, and `fields` to values; answers `201` with the set. Needs `site.settings` |
| `PATCH fields/sets/{name}` | Change a `user/data/fields` set: `{"set"}`, as above (`null` removes a key); answers with the set. A field a target already has is a `422` with the reason |
| `DELETE fields/sets/{name}` | Delete a `user/data/fields` set's file; answers `{"deleted"}`. Then `POST types/refresh` reindexes |
| `GET references/{type}` | What a reference field to `type` can point at, for the editor's picker (see below) |
| `GET entries` | The entries the account may edit, a page at a time (see below) |
| `GET health` | Content problems by file, with counts (`?strict=1` adds notices); needs `content.edit.others` |
| `POST previews` | A preview link to an entry the account may edit, from `{"entry": id}`: `{"url", "expires"}` |
| `GET entries/{id}` | An entry for editing (see below) |
| `GET content/{type}/{key}` | The same, found by its handle, such as `content/post/hello` |
| `POST entries` | Create an entry: `{"type", "title"}`, and optionally `"slug"`, `"set"`, `"body"`, `"status"` |
| `PATCH entries/{id}` | Change an entry: `{"revision"}` plus any of `"set"`, `"remove"`, `"body"`, `"status"`, `"published"`, `"slug"` |
| `DELETE entries/{id}?revision=…` | Move an entry to the trash |
| `POST entries/bulk` | Publish, move to draft, or trash several entries at once (see below) |
| `POST entries/{id}/duplicate` | Copy an entry as a draft (see Duplicate above); answers `201` with the copy as `GET entries/{id}` shows it. Needs `content.create` and the right to edit the entry; an index page is refused with a `422` |
| `GET trash` | The trashed entries the account may handle, newest first (`?type=` for one type): `{"trash": [{"id", "entry", "title", "type", "bundle", "trashed", "authors", "own"}]}` |
| `GET trash/{id}` | One trashed entry, as in `GET trash`, with its `frontMatter` and `body`; 404 if it isn't there or the account can't handle it |
| `POST trash/restore` | Restore `{"id"}` as a draft: `{"id"}` is the entry's id again; 409 when something else has its place |
| `POST trash/delete` | Delete `{"id"}` permanently |
| `POST trash/empty` | Delete everything the account may handle permanently (`{"type"}` for one type): `{"deleted"}` |

Once signed in, send the token from `session` or `login` in an
`X-CSRF-Token` header with every `POST`, `PATCH`, and `DELETE`. Errors are JSON too:
`{"error": "…"}`, with a 400, 401, 403, 404, 413, 422, or 429 status.

### Changing several entries

`POST entries/bulk` takes `action` (`publish`, `draft`, or `trash`) and
`ids` (1 to 100 entry ids). Each entry is changed as it is now, so no
`revision` is needed: a status change or a move to the trash doesn't
overwrite anyone's writing. Each is checked on its own, with the same
rules as a single change: `content.edit` for the entry, `content.publish`
for anything that isn't a draft, `content.delete` to trash it, required
fields filled to publish, and never an index page in the trash. The
answer is `action`, `done` (the ids changed), and `skipped` (each with
its `id`, `title`, and the `reason`); an entry that couldn't be changed
doesn't stop the rest.

### Listing entries

`GET entries` lists the entries the account may edit: an author's own,
or everyone's for an editor. Narrow it with:

| Parameter | What it does |
|---|---|
| `status` | `draft`, `scheduled`, `published`, or `any` (the default) |
| `type` | A content type's name |
| `search` | Text the title or file path must contain, in any case |
| `author` | An author's slug the entries must credit |
| `terms` | `taxonomy:slug` pairs, comma separated (`topic:art,era:1990s`); an entry needs every one |
| `days` | Entries updated in the last so many days, from 1 |
| `sort` | `title`, `status`, `author`, or `updated` |
| `dir` | `asc` or `desc`: by default `updated` sorts newest first and the rest A to Z |
| `page` | The page, from 1 |
| `per` | Entries per page: 20 by default, at most 100 |

Unless `sort` says otherwise, drafts and the whole list come most
recently changed first, scheduled entries soonest first, and published
entries newest first. Sorting by status uses the status as it is now (a
published entry dated in the future sorts as scheduled), and by author
the first author's slug. The answer has `status`, `type`, `search`,
`author`, `terms`, `days` (or `null`), `sort` and `dir` (or `null`
unsorted), `tree` (whether it's in tree order), `total`, `page`,
`pages`, `per`, and `entries`, each with its id, handle, title, type, status, dates, file,
`url` (its path on the site, where it is or will be once published, or
`null`), authors, whether it's the account's own, `index`,
`can.delete` and `can.duplicate`, and `ancestors`: the titles of the entries above it, from
the top down (a page's parent pages, or a hierarchical term's parents;
empty for the rest). With a `type` whose entries nest (pages, or a
hierarchical taxonomy) and no `status`, `search`, `author`, `terms`,
`days`, or `sort`, entries come in tree order instead: each followed by its children, siblings by title,
each with its `depth` (0 at the top) and how many `children` it has. A
page that starts inside a branch begins with the entries above it,
marked `continued: true` and not counted in `total`. Otherwise `depth`
and `children` are `null`, and `continued` is `false`.

With a `type` that isn't pages, the type's index page (its landing page)
is left out of `entries`, `total`, and `pages`, and answered as `index`
on the first page when it matches `status` and `search` and the account may
edit it; otherwise `index` is `null`. Its authors page (`_authors`) is
answered the same way, as `authorsPage`, and each entry says whether
it's one (`authorsPage`). A page past the last has no
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

`for=post` (a content type's name) narrows a taxonomy to the terms that
type's entries use, in any status, counting only the entries the account
may edit; a hierarchical taxonomy keeps the parents of each, so the tree
holds together. The entry list's filters use it.

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
(described as a content type's are), the field `sets` on its kind
(`{"name", "label", "description", "fields"}`, the field names), their `values`, keys its metadata
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
- `authorsPage`: whether it's its type's authors page, which is
  described the same way, with `can.rename` and `can.duplicate`
  `false`.
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
