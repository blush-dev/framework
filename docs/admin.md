# The admin

The admin is where people with an [account](accounts.md) run the site
from a browser. So far it has a dashboard (what you were editing, and
the entries waiting on you), a list of each content type's entries, an editor, a content
health check, and Tools (buttons to publish, reindex, and clear caches,
background jobs, and the site's log).

> **The admin is early.** The editor edits Markdown as text; a live
> preview, a form for a block's options, and a media library come
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

The rail at the far left has five sections: **Home** (the dashboard,
Site Health, and tools), **Content** (each content type's entries, with
the types of terms that file only it under it, the terms several types
share, and media),
**Extend** (themes, plugins, and icon packs), **Users** (your profile,
accounts, profiles, and roles), and **Config** (content types,
relationships, fields, and settings). The panel
beside it lists the section you're in. Choosing a section changes the
panel and nothing else, so you never leave the screen you're on (an
entry you're writing stays open); choose a link in the panel to go
there. Choosing the section that's already showing hides the panel,
leaving just the rail (your browser remembers it), and choosing it
again brings the panel back. You only see what your account can use.
A link to a list shows how many things are in it: each content type's
entries you can edit, media files, accounts, roles, content types,
field sets, themes, plugins, and icon packs. The counts update as you move between screens.

**Shortcuts**, under Home's own screens, are the screens you pin there,
in your order: Your Account until you choose your own.
Choose **Edit** to move them up or down, remove them, or **Add a
Shortcut** from any screen the panel shows you, then **Done** to save
your changes. They're kept with your account, so they follow
you to any browser.

The top bar says where you are: the section, then the screens above
this one, then this one, such as *Content / Posts / Editing* or *Config
/ Content Types / Pages*. The section's name shows its panel, or hides
the panel when it's already showing, like the rail; the screens before
the last go back to them.

**Search or jump to…** in the top bar (or ⌘K, Ctrl+K on Windows and
Linux) opens the command palette: type to find a screen, a command such
as **New Post**, or an entry by its title, then press Enter. In the
editor, the editor's own commands come first, such as **Focus mode**
and **Insert media**.

The editor hides the panel while you write and puts it back as it was
when you leave. On a narrow screen, the button at the top left opens
the rail and panel as a menu instead. **View Site** opens your site in a new tab, and
the round button at the top right has **Your account** and **Sign out**.

## Your account

**Your account** is your own account's screen, at its own address
(`/admin/accounts/{your username}`; `/admin/profile` goes there): the same one an
administrator sees from **Accounts**, with two differences. Your
password, email address, display name, and the admin's look are yours
to change, and your roles and standing aren't (someone else who
manages accounts changes those). While the site has no
[owner](accounts.md#owners), anyone who can do all an Administrator
can sees **Make Me the Owner** here. It shows your username, email address, display name
(what the admin calls you), when the account was made and last signed
in, your roles, and your **Public Profile**: the
[profile](content-types.md#built-in-types) your account is linked to,
your public name and bio on the site. **Change Name or Email** changes
your display name (left empty, the admin uses your profile's title,
then your username) and your email address, which every account needs.
**Open Profile**
shows the profile's screen, where **Edit Profile** opens it in the
editor like any entry; its slug can't change, since your account is
linked by it. When your account is linked to a profile with no file
yet, **Create it** starts it as a draft (publish it to show your name)
and opens it. If you can edit accounts (`accounts.edit`), you can also
unlink your profile, link an existing one, or create one, as on anyone's
account.

**Theme and Color Scheme** sets how the admin looks to you. The theme is **Neutral** (cool gray
with a blue accent, the default) or **Editorial** (warm paper, a teal
accent, and serif titles). The color scheme is light, dark, or your
device's setting (the default). Each choice is saved with your
account, so it follows you to every device you sign in on, and it only
changes what you see: someone else on the same site keeps their own.

**Change Password** asks for your current password and a new one (at
least 12 characters, unless your site sets another length). You stay
signed in where you changed it, and you're signed out on every other
device. Too many wrong current passwords lock you out of changing it
for a while, as with signing in.

## The dashboard

The dashboard greets you by your [name](accounts.md#names) ("Good
afternoon, Sam Smith") with today's date. **New** starts an entry of any
type you can write, or uploads to the media library.

- **You Were Editing** is the entry you last saved in the admin, unless
  it's in the trash. **Continue Editing** opens it.
- **Entries** lists what needs you first, then the rest: drafts (last
  changed first), then scheduled entries (soonest first), then recently
  published ones (newest first), up to five of each, from pages and
  collections. **Mine** shows the entries that credit your profile, and
  **Everyone** every entry you can edit, with who each one credits.
  Without a profile, you see everyone's. A draft untouched for a week
  says so. **Browse by Type** opens a type's list.
- When none of your entries is a draft or scheduled, it says **Nothing
  Is Waiting on You**, with what you published last, and lists your
  recently published entries.

Until the site publishes something, a numbered setup path takes the
dashboard's place: write your first page, make a content type of your
own, add media, and invite the people you work with. Each step opens
the screen that does it, shows when it's done, and is there only when
your account can take it. **Skip to the Dashboard** puts it away for
your account.

## Tools

**Home → Tools** has the tasks you'd otherwise run from the command
line, as buttons, grouped by where they come from (Blush, the site, or a
plugin). The result of each stays beside its button. An action that
asks first says so.

| Action | What it does | Who can run it |
|---|---|---|
| Publish | Put content changes live, like `bin/blush publish` | Anyone with `site.publish` (editors) |
| Reindex content | Bring the content and media indexes up to date with your files | Anyone with `site.publish` |
| Clear caches | Empty the page, body, and fragment caches | Anyone with `cache.clear` (editors) |

Actions you can't run don't appear. Plugins can add their own actions
(see [Extending Blush](extending.md#admin-actions)). Publish and Reindex
content run as [background jobs](going-live.md#background-jobs-and-cron):
the button shows their progress while they run, and if you leave the
screen, they finish on their own.

With `site.jobs` (administrators), the **Jobs** tab shows the work done
in the background. **Scheduled Tasks** lists what runs on a timetable
(putting scheduled posts live, and cleaning out the cache, idle
sessions, and old jobs), how often, and when each last ran, with **Run
Now**. **Jobs** lists recent jobs, newest first: what each is, who
started it, its status, and what it last said, with **Retry** for a
failed job and **Delete**. Above them, a notice says whether cron is
running jobs, and if it isn't, the line to add to your server's crontab.

With `site.logs` (administrators), the **Logs** tab shows the last 50
entries in the site's log (`storage/logs/blush.log` unless
[`config/log.php`](configuration.md#logging) says otherwise), newest
first, one line each, with errors and warnings marked. An entry with an
exception opens to show its trace. **Refresh** reads it again and **Download** saves the
whole file. It's read only, for when something fails on a host you
can't reach over SSH. A site that logs to the server's error output, or
not at all, has no file to show.

Tools is in the panel when your account can run an action, see the
jobs, or read the log.

## Entries

The sidebar lists your content types by name. **Content** has your
collections (such as Posts) and Pages, each with the
[types of terms](content-types.md#terms-and-relationships) that file
only that type under it (one whose relation's `from` names one type,
such as Categories under Posts), then **Shared Terms**, the types of
terms that file several types or every type, each saying which, then
**Media**. In **Config**, **Structure** has **Content Types**,
**Relationships**, and **Fields**. Profiles are in
**Users**, with accounts, since they're the public side of accounts.
Each type opens a list of its entries you can edit, newest changes
first. Types are named from their `labels` setting, and
shown with their `icon` (see
[Content types](content-types.md#names-descriptions-and-icons-in-the-admin)).

**Users** has Your Account, Accounts, Profiles, and Roles. In
**Config**, **Settings** has General, Reading, Addresses and Search, AI,
and System, and **Extensions** has Themes, Plugins, and Icon Packs. You only see the
screens your roles allow: Media needs one of the media capabilities
(see [Accounts](accounts.md#capabilities)), Accounts and
Roles `accounts.view` (and New Account `accounts.create`, New Role
`roles.manage`), Profiles editing profiles, each content type
editing its entries,
Content types and Settings `site.settings`, and Themes, Plugins, and
Icon Packs seeing that kind (`extensions.themes.view`, and so on; see
[Capabilities](accounts.md#capabilities)). On those screens, activating
a theme or turning a plugin or pack on and off needs the kind's
`activate`, and deleting its `delete`; without them, the buttons aren't
shown.

The tabs above a list show all of them, or only published entries,
drafts, or scheduled ones, with a count on each. For a type that credits
authors, when your account has a profile, **Mine** beside **All** shows
the entries crediting you. You see your own
entries if you're an author or contributor, and everyone's if you're an
editor; in Profiles, your own profile is marked "You".
On the **All** tab, a collection's entries come newest published first,
pages and the entries of a collection ordered by position or nesting
(such as terms) by their **Position** and then by title (those without
one after the rest), and profiles by name; the date column shows the
date the list goes by. Drafts come most recently changed first,
published entries newest first, and scheduled entries in the order
they'll go live. Pages, and the entries of a
[nesting collection](content-types.md#nesting-and-order) (such as
categories), list as a tree on the **All** tab when you aren't searching: each one
followed by the ones under it, indented, in the same order (Books, then
Book Reviews indented under it, then Film). The triangle
beside an entry with others under it collapses or expands that branch;
the admin remembers which until you close it. When a later page starts
partway through a branch, the entries above it are shown again at the
top, marked **Continued**. On another tab, in a search, with a filter,
or sorted by a column, the tree is flattened, and a note above the list
says how to get it back. Under each title is the entry's address on your
site (for a draft, the address it will have). In other tabs and in
search results, a page or an entry of a nesting collection has the
titles of the entries above it before its own (such as "Web › Web
design › CSS"). Click a title to edit the entry.

Beside the tabs is a row of filters:

- **Search** matches titles and slugs. Press `/` anywhere on the
  list to start typing in it.
- **Author** shows only the entries crediting one author (not on a
  list of terms, since terms aren't credited).
- One select for each type of terms the type is filed under, such as
  **Any topic**, shows only the entries filed under one term.

The Author and term selects offer only the authors and terms this
type's entries use (drafts included), so a choice never comes up empty.
A filter with nothing to offer isn't shown.
- **Updated** shows only the entries changed in the last 7, 30, or 90
  days.

**Clear Filters** turns them all off, and the tabs' counts follow the
filters. The Trash tab takes only the search. The two buttons at the end
of the row switch between roomy rows and compact ones, which leave out
the address line; your browser remembers the choice.

Click the **Title**, **Status**, **Authors**, or date (**Published** or
**Updated**) header to sort by that column, and again to turn the order
around. Titles, statuses, and authors start from A, and dates from the
newest. Below a list longer
than 10 entries, choose how many show on a page (20 unless you change it).
The filters, the sort, and the page size are part of the page's address,
so going back or sharing the link keeps them.

To change several entries at once, tick their checkboxes (the box in the
header ticks every entry on the page). A bar appears at the bottom with
how many you've chosen and what you can do to them: **Publish** (if your
account can publish), **Move to Draft**, and **Move to Trash** (if it can
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
authors, and the other front matter, except its own `slug` (the copy is
named by its file); a dated entry is dated today, and
an entry in its own folder is copied with its media. A message at the
bottom right says so; the copy is listed with the drafts. Terms and index pages can't
be duplicated.

A collection's **index page** (the `index.md` in its
folder, which introduces its archive) is pinned at the top of its list
with a pin and an **Index** tag, on the list's first page. It isn't
counted in the list's totals, and it can't be moved to the trash (see
[Editing an index page](#editing-an-index-page)). It still follows the
tabs and search: it shows only when it matches them.

Pages pin their **root page** (`user/content/index.md`) the same way.
When it's the homepage, it has a house in place of the pin and a
**Homepage** tag. It isn't counted in the totals, isn't offered as a
parent, and can't be duplicated or moved, and the editor leaves out
its slug and position, since it's the top of the tree. It can be moved
to the trash; the site then shows a welcome page at `/`.

When the homepage is set to show a collection (**Settings › Reading**),
that collection's index page gets the house and the **Homepage** tag,
and the root page is tagged **Not shown**, since visitors never see it.
If you can change the site's settings, **Make homepage** beside the tag
(also in its row menu, and in the editor) switches the homepage back to
it.

A type with a [relation archive](content-types.md#people-archives),
such as its authors', may have a **list page** for it (`_authors.md` or
`_cooks.md` in its folder, which introduces the list). It's pinned under
the index page with the relation's label as its tag (**Authors**), set
apart from the totals the same way, and opens in the editor as **Edit
Authors Page**, without the type's fields or a date, and with its slug
fixed. Unlike the index page, it can be moved to the trash. The pages
written for one target's archive (`_cooks/jane.md`) aren't listed at
all; a profile's are reached from [the profile's screen](#profiles).

Pages' **error pages** (`_errors/404.md`, and 1.x's `_error/` folder,
whose title and text the site shows for that error) are pinned at the
top of Pages with a pin and their status as a tag (**Error 404**), by
status, followed by anything else kept in those folders. The folders'
own pages (`_errors/index.md`) aren't listed. They aren't
counted in the totals, aren't offered as a parent, can't be duplicated,
and keep their slug, since it's the status. Moving one to the trash is
allowed: the theme's own message is shown in its place.

A type of terms' list (such as Categories) holds its **terms**. Instead
of authors, it shows how many published entries use each term.

A type with no entries yet skips the tabs and search: it says what the
type is for (its `description`, if it has one) and offers to create the first one. A site that hasn't
published anything shows a setup path on the dashboard.

**New Post** (named for the type you're looking at) opens the editor on
a new post, with the cursor in the title. Nothing is written until you
save: the first save creates the file, named for the title (or the slug,
if you give one in the settings), as a draft unless you publish or
schedule it. A post needs a title to be saved. It's credited to your
account's author. If you leave before saving, nothing is created.

A new page (or an entry of any [tree](content-types.md#trees)) can go
under another: choose its **Parent** on the Document tab before the
first save, or leave it at **None, at the top level**. The page is
saved in its parent's folder (`about/team.md`), and its address follows
(`/about/team`). If the parent is a single file (`about.md`), it moves
into the folder first, as `about/index.md`; its address stays the same.
**Position** (under Parent, for pages and for collections ordered by
position or nesting, such as terms) sets its place among
its siblings: lower numbers first, and those without one after them, by
title. A type's **All** tab and the Parent list follow it; term
filters and pickers stay alphabetical.

To move a page later, choose another **Parent** (or **None, at the
top level**) and save. The pages under it move with it, and a parent
kept as a single file moves into its folder the same way. A page can't
go under itself or a page under it, or where another page has its slug.
If the page is live, its address changes, so **Redirect the old
address here** is offered, as for a new slug; with it on, the old
address of every live page that moved (the page and those under it)
redirects to the new one. Renaming a page's slug does the same for the
pages under it.

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
strength. A block's name is colored, and a block or image the
settings are showing is boxed (a container on its first and last lines).
A container's closing line is followed by the full name of the block
it closes, such as `blush/callout`, faintly; it's only shown, never
part of the text. With the cursor on a container's first or last
line, the colons on both lines are colored, so you can find its other
end. Pressing Enter at the end of a container's first line, when
nothing closes it yet, adds its closing `:::` too, with the cursor on
the empty line between.
Only valid Markdown lights up, so something that stays plain won't be
read the way you meant.

Each entry's editor has its own address, from its content type and
[id](content.md#ids), such as
`/admin/content/post/0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74`. It stays
the same when you rename or move the entry, so a bookmark keeps
working.

There's no back button: the type's name in the top bar (**Posts** in
*Content / Posts / Editing*) goes back to its list. The header's left
side is what you do to the text, in three groups:

- **What goes in the entry**, always there: **+** for a block
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
  something (not in a code block, on a block's `:::` line, a
  divider, or a table's `| --- |` row): **Bold** and **Italic**, lit
  when the text at the cursor is bold or italic; the **link** form
  (**Text** and **Address**, filled in from the selection, the word at
  the cursor, or the link the cursor is in, with **Remove** for a link
  that's there); the **A** menu of other things that go in a sentence:
  **Strikethrough**, **Highlight**, and **Inline code** (each lit when
  it's on at the cursor), **Mention** (a search for a published profile
  by name, which writes `@slug` where the cursor is; shown when
  [mentions](#settings) are on), then any block that goes inside a sentence;
  and the shapes for an icon. Typing `@` in the text does the same as you
  type: the published profiles whose names or slugs match are listed
  under the cursor; ↑ and ↓ move, Enter or Tab writes `@slug`, and Esc
  leaves what you typed. Mentions are colored like links. Inside a block that holds only some
  things, such as a gallery's images, they go, the blocks panel
  offers only what it holds, and the media picker shows only images.
  The site leaves out anything else typed inside it, so the Outline
  marks it, its settings say so, and the block's own settings list
  the lines that won't show. A block written another way than its
  own (see [Using directives in content](directives.md#using-directives-in-content)),
  such as `::button[…]` for `:button[…]`, is marked the same way, and
  its settings say how it's written.
- **Bleed**, for an element at the top of the entry: how far it reaches
  past the text column. **Base** is the column's width and writes
  nothing; **Wide** and **Full** write the classes your theme names (see
  [Bleed](themes.md#bleed)). The button shows the width in force, and is
  colored while the element is widened.

One of these is open at a time: opening a menu, the link form, or a
picker closes the blocks panel and anything else open. Its right
side has the settings button, **Save Draft** beside the main button
while the entry is a draft you can publish, the main button, and a
**⋮** menu in two parts: **View** (the settings panel,
the **Outline**, **Focus mode**, and **Preview**, or **View** once it's
live; a preview shows the entry as last saved) and
**Entry** (**Switch to draft** once it's scheduled or live, **Copy link** once
it's live, **Duplicate**, and **Move to trash**), with their shortcuts.
The main button depends on the entry:

| The entry is... | You can |
|---|---|
| A draft | **Save Draft**, or **Publish** (or **Schedule**, when the publish date is in the future) |
| Scheduled | **Update**, **Publish** once its date is past, or **Switch to draft** |
| Published | **Update** (or **Schedule**, with a future date), or **Switch to draft** |

The buttons show whether your changes are saved: **Update** and **Save
draft** stay off until you change something, since there's nothing to
save yet; the one you pressed reads **Saving…** while it saves; and
once it's done, it reads **Saved** until you change something again. The
entry's status is in the settings, under its publishing.

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
  new page's **Parent**, or the parent of an entry in a nesting
  collection (such as a category). A line under them says what the date means and when
the file was last edited.
  Then the **Featured Image** (choose, replace, or remove it), the
  **Authors**, each type of terms it's filed under (see
  [Choosing terms and authors](#choosing-terms-and-authors)), the
  **Summary**, and the content type's other fields, such as the
  subtitle (a few kinds, such as `collection`, show their value
  read-only); each [field set](content-types.md#field-sets) added to the
  type, under its label; and
  what Site Health finds in the file, as last saved (notices only if
  you ask). Front matter the type doesn't declare isn't shown, and
  saving keeps it as it is. Only the types of terms whose relation files the content
  type are offered, plus any the file already uses. At its foot,
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
| ⌘⇧H | Highlight (`==text==`) |
| ⌘K | The link form, for the selected text, the word at the cursor, or the link the cursor is in. Outside the text, ⌘K opens the command palette as usual |
| ⌘⇧K | Remove the link the cursor is in, leaving its text |
| ⌘⌥1 to ⌘⌥6 | Make the line (or the selected lines) a heading of that level; the same keys again make it a paragraph. In a quote or list item, the heading goes inside it |
| ⌘⌥0 | Make the line a paragraph |
| ⌥↑, ⌥↓ | Move the element the cursor is in up or down among the ones beside it |
| Backspace | Just after a list item's marker, a quote's `>`, or a heading's `#`s: take the marker off, keeping the words (an indented list item comes out a level first) |
| Tab, Shift+Tab | In a list, nest the item under the one above, or bring it back out; what's nested under it moves with it. In a quote, add a `>` level, or take one off (the last one leaves plain paragraphs): the whole quote, or just the selected lines. Over several selected lines of a code block, indent them two spaces, or take up to two off. Elsewhere, Tab leaves the text as usual |

Text can't land inside the syntax around it by accident: a character
typed right after an attribute block at the end of a line goes before
it, at the end of the words; one typed right after a block's
`:::name{…}` or `::name[…]` goes on a new line below; and anything pasted
or inserted into a block's own line goes onto a line after it.
Typing inside the braces or a block's name still edits them.

Pasting an address over selected text makes it a link. Pasted
blocks are tidied so they can't break the ones around them: one
that was copied without its closing `:::` gets one, a stray `:::` left
over from copying part of a block is dropped, longer fences such as
`::::` become `:::`, and a pasted block goes on lines of its own,
as the inserter puts it. Dropping or
pasting files into the text uploads them to the library and puts them
in together where the cursor is, in the order given, as the media picker
inserts several (see below); a file that isn't uploaded is left out; that
needs uploading the file's kind (`media.image.upload` and so on). They're in the command palette too.

**Focus mode** (⌘⇧F or Ctrl+Shift+F, or the **⋮** menu) hides
everything but the text and the editor's own header. Press Escape to
leave it.

Ctrl+S (⌘S on a Mac) saves without changing the status. Saving changes
only what you changed: every other line of the file stays exactly as it
was. Going to another admin screen with unsaved changes asks first, and
so do the reload shortcuts (⌘R, Ctrl+R, F5); either way your changes
are kept, as below. Closing the tab or leaving the admin doesn't ask.

If you can't publish, you can save drafts but not publish them.

Fields the content type marks as required must be filled in to publish,
schedule, or update a live entry. Anything missing is named at the top
and marked under the field; a draft saves without them.

### Choosing terms and authors

A field that points at other entries (terms, the authors, or any other
type, through a [relation](content-types.md#terms-and-relationships))
is a picker rather than a list of slugs to type:

- **Nesting terms**, such as categories, are their whole tree, each
  term with how many entries use it. Search to narrow it (a match's
  parents stay in view, dimmed) and tick the ones that apply; ticking a
  term doesn't tick its parent. A draft term says **Draft**, since the
  site doesn't list the entry under it until it's published. **New
  category** (named for the type) asks for a name and a parent and adds
  the term to your site, ticked; with nothing matching a search, it
  offers the search as the name.
- **Other terms**, such as tags, are chips. Click in the field to see
  the most used, or type to see matches, with each one's count. Enter
  takes the highlighted match; when nothing matches, **Create** adds
  what you typed as a new chip, marked with a plus, and the term is
  written when you save the entry. Backspace in the empty field removes
  the last chip. Past 12 chips, the rest fold into **+N more**.
- Adding a term as you type needs its relation to allow it (`create`)
  and your account to be able to create and publish that type's
  entries.
- **People**, such as authors or a recipe's cooks, are listed by name
  and slug, the first marked **Lead** when there are two or more and the
  order matters. Click in the search to see who was credited most
  recently, or search to add someone. When an entry must have one, the
  last can't be removed until another is added.
- **Other entries**, such as a recipe's pairings, are listed above the
  search with their image, type, and date; results show as cards, a
  chosen one ticked (choose it again to take it out). The entry you're
  editing is never offered, and at the limit the search is replaced by
  a line saying so.
- **One value**, such as a cuisine, a category's parent, or the one
  person who tested a recipe, is a value on the Document tab's first
  rows, like Status. Its list is indented to show the tree, a nested
  value shows its parent's name, and from 13 options it has a filter.
  A category's parent leaves out the category and the ones under it.

**On large sites,** a field with more than 50 to choose from never lists
them all. Nesting terms show what's chosen, with each one's path
(`Mains › Pasta`), and the most used; a list of one value opens on a
search with the recently used. A search shows the best 8 matches (6
cards), those starting with what you typed first, and says how many
more there are: keep typing to narrow it.

A slug that nothing answers to is shown as written, and when one is
close enough to be a typo, the picker offers it: **Did you mean Tomato?
Replace**.

The relation's settings shape the picker too:

- **How many:** with a `max`, the field's heading shows how many of how
  many you've chosen ("2 of 3"), and it takes no more once it's full; a
  `min` says how many are needed to publish ("Needs 1"), and publishing
  waits for them. A draft saves without them.
- **Order:** when the order matters (`ordered`), drag a chip or a row by
  its grip, or focus it and press ⌥↑ or ⌥↓ (Alt with an arrow key).
- **Picker:** a relation can choose its picker with `control` (see
  [Relation options](content-types.md#relation-options)).
- **Translations:** a translation shows what it uses from its original,
  dashed and named ("Also shown, from Pasta alla Norma (English)"): the
  original's when it has none of its own, or, for a relation that adds
  them (`translations: add`, such as credits), the original's beside its
  own.

### Rows at the end of the Document tab

The Document tab ends in rows for what you can't change there, always
last, after every field, in this order: **Archive Page** (only on a page
that introduces an archive), **Linked From**, and **Outline**. On a
short tab they sit at the drawer's bottom edge; on a long one they
follow the last field. Each answers the quick question in one line, and
opens a level down, in place of the fields, for the rest. The path at
the top goes back, and so does Escape.

- **Linked From** names up to three entries that link to this one, or
  counts them by relationship ("Cooks 208 · Tested By 6"), or says
  nothing links here. Opened, it lists them by relationship and the
  type that stores the link, live entries and drafts (marked; they link
  here once published), never the trash. Each opens its entry, since
  that's where the link is changed. A group shows 8; up to 50, **Show
  More** shows the rest in place, and past that **View All** opens that
  type's list filtered to the entries linking here. The list says so,
  with **Show All** to clear it.
- **Archive Page** names the archive the page introduces (**Recipes**,
  or **Cooks › Jane Doe**). Opened, it says which archive, which entry
  it's for, its address, how many entries it lists (and how many drafts
  it leaves out), and what the archive shows without the page.
- **Outline** lists every element in the text; see [the
  outline](#the-outline-and-the-breadcrumb).

### Changing the slug

The **Slug** field on the Document tab is the last part of the entry's
address, such as `hello-world` in `/archives/2026/09/30/hello-world`.
Change it and save to rename the entry: its file is renamed (a dated
file keeps its date), or, if the file sets its own `slug` in its front
matter, that is changed instead. Slugs are lowercase letters, numbers,
and hyphens, and another entry of the same type can't already have it;
if the name is refused, nothing is saved and the field says why.

For a published entry, the field shows the new address, and **Redirect
the old address here** (on by default) adds a
[redirect](#redirects) from the old address to the entry, so
links to it keep working, wherever it moves later. Landing pages
(the homepage, and a folder's `index.md`) take their folder's name,
so they have no Slug field.

### Editing an index page

A type's index page opens in the same editor, as **Edit Index Page**,
and the entry's tab (**Index Page**) says what it is. It introduces the
type's archive rather than being one of its entries, so it leaves out
what doesn't apply: the type's fields (such as categories or a
subtitle; any already in the file are kept as they are, though not
shown), the publish date, and scheduling. It
can be a draft or published, and publishing doesn't add a date. There's
only one, so it has no **Move to trash**. Its [**Archive
Page**](#rows-at-the-end-of-the-document-tab) row says what the archive
lists.

### Inserting blocks

Blocks are what Markdown calls [directives](directives.md): callouts,
galleries, buttons, and the rest, written as `:::callout` … `:::` and
the like.

**+** opens the blocks beside the text, on the left, grouped
by category: search by name or what it does, or scroll, then choose one
(or use the arrow keys and Enter). The strip at the bottom describes the
highlighted one. The panel stays open, so you can add several; close it
with its **×** or Escape.

The **A** menu lists the inline blocks, the ones that go inside a
sentence (a keyboard key, an abbreviation), each with what it does.

Typing `/` at the start of an empty line opens the same panel: keep
typing to narrow it (`/call` for a callout), then press Enter or Tab.
Press Escape to keep the `/` as text.

The block is written into the Markdown by its full name, such as
`:::blush/callout` … `:::`, with the cursor where your text goes. An
inline block (a keyboard key, an abbreviation, an icon) goes at the
cursor, inside the sentence; the rest go on lines of their own. Select
some text first to make it the block's text. Options the block
needs are written empty for you to fill in, such as
`::blush/video{src=""}`. Undo takes an insertion back.

The list has every registered directive with a class: the built-in
ones and your plugins' (grouped by where they come from).
Themes don't add blocks; they style them. See
[Making a directive](directives.md#making-a-directive) to add yours.

### Inserting media and icons

The picture button has two ways in: **Media Library** and **Upload a
File**. Both open the same picker, on its **Library** or **Upload** tab.
**Image** in the blocks panel opens it too, showing only images.

The Library tab has the library in `user/media`, newest first. Search by file name, or show only images,
video, audio, or other files. Choose one or more files, then **Insert**
(or double-click a file). Each file you choose is numbered, in the order
it goes in; choosing it again takes it out, and **Clear** starts over.
Several files go in one after another, each on lines of its own with a
blank line between; inside a gallery, its images go in one to a line.
An image goes in as plain Markdown on a
line of its own, `![](/media/photo.jpg)`, with the cursor where its
description goes (selected text becomes the description), and its
settings open on the element tab (see
[Images](#images-and-markdown-elements)). When the library has alt text and a
caption for the file (see [Media](#media)), they're filled in:
`![A lake at dawn](/media/lake.jpg "The lake at dawn")`, with selected
text still winning for the description. On the site it's a figure; a quoted title
after the address, `![A lake](/media/lake.jpg "The lake at dawn")`, is
its caption. A video goes in as a video, an audio file as audio, and anything
else as a download, and their options open in the settings.

The Upload tab takes files from your computer: drag them anywhere onto
the picker, or use **Choose Files**. It says how large a file may be and
which types the library takes. Each one goes into `user/media` under the
year and month (`user/media/2026/09/`), with its name made safe for an
address (`My Photo.JPG` becomes `My-Photo.jpg`), or the folder the
**Media** settings give its kind; a name that's taken gets
`-2`, `-3`, and so on, so nothing is replaced. An upload lands at the top
of the library, chosen (along with any you'd chosen already), so
**Insert** finishes the job; **Show in
library** switches tabs to see it there. Uploading needs the kind's
capability (`media.image.upload`, or `media.*.upload` for every kind),
and the Upload tab takes only the kinds you may upload.
How large a file may be is up to the **Media** settings, within what
PHP takes (`upload_max_filesize` and `post_max_size`).

The same picker is **Choose** beside every media field and block
option, such as a video's **Poster image**, where it takes one file. A field or option that takes
one kind of file (a video's file, its poster, the featured image, an
image's **Replace**, or a field with a `kind`) shows only that kind,
without the kind buttons, and its upload refuses a file of another
kind, saying why.

The shapes button opens your theme's [icons](directives.md#icons),
grouped: the built-in icons by category (Status, Interface, Arrows, and
so on), then your theme's, your icon packs', and your plugins'. Pick a
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

**Outline**, last at the end of the entry's tab (or in the **⋮** menu),
lists every element in the entry in order: paragraphs, headings, lists
and their items, quotes, code blocks, tables, definition lists,
images, and blocks (inline blocks are part of their
sentence, so they aren't listed), each with a line of what's in it.
What's inside a block, list, or list item is indented under it.
Choose one to select it; **←** goes back.

### Element settings

The element tab follows the cursor and shows whatever it's most
precisely in: inside a callout, that's the paragraph (the callout is
above it in the breadcrumb); on the callout's own first or last line,
it's the callout. On a blank line, it's the element above, but not one
inside a block that has already closed. A block, a list, a list
item, and a definition list list what's directly inside them under
**Content**; choose one to select it.

A block's options are a form. A block with
[variants](directives.md#variants) lists them first, under **Variant**,
with what each one looks like; **Default** writes nothing. Changing one
rewrites just that option in the Markdown: the rest of what you wrote
stays as it is. Setting an option back to its default removes it, so the
default applies. A block that takes a line of text has it here as
**Text**. **Classes** and **ID** set its `.class` and `#id`; other
attributes that aren't options of the block are listed but edited in
the text.

**Remove Block** takes the block out: a container with
everything inside it, a line block with its line, and an inline
block leaving its text in the sentence. Undo in the text puts it
back.

### Images and Markdown elements

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

Every Markdown element takes classes and an id too, so a heading's,
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

- **Unsaved changes stay in your browser** as you type, and as you
  leave the editor or the page. If you go elsewhere in the admin, the
  tab closes, you reload, or the browser crashes, open the entry again and choose **Restore Them** (or **Throw
  Them Away**). They're only in that browser until you save.
- **Offline**, a bar under the top bar says so, and you can keep
  writing. A save you ask for shows **Waiting…** on its button and goes
  ahead once you're back online.
- **A save that fails** (a server error, say) shows **Not saved** and a
  **Try Again** button.
- **A save that adds HTML your role can't add** is refused, naming
  what (`<iframe>`, say). The editor marks such HTML, and links to
  `javascript:` addresses, with a wavy red underline as you type, along
  with any already there that someone else wrote; only what you add
  counts, so you can still edit their words, or take it out. See
  [Capabilities](accounts.md#capabilities).
- **If the entry changed after you opened it** (someone else saved it,
  or its file was edited or pulled from git), the editor stops saving
  and says when it changed. **Compare** shows the fields and body lines
  that differ; **Keep Theirs** throws your changes away for their
  version; **Keep Mine** saves your version of everything the editor
  shows over theirs.

**Move to trash** takes the entry off your site and puts it in the
**Trash** tab of its list.

If a relationship is full on the other side (a collection that features
12 recipes at most already has 12), publishing or updating is refused,
and a bar says which entry is full, with a button to open it in a new
tab. The field says so, and names another with room. A select lists
each choice's count against that limit ("7 of 12", "Full · 12 of 12"),
so a full one is seen before it's picked.

## Media

**Content → Media** shows the files in `user/media`, newest first, each
by its title (or its file name, until it has one), laid out like the
entries lists: **All** and **Mine** tabs with their counts (**Mine** is
what you uploaded), then a search by name or details and a filter for
images, video, audio, documents, or other files. A sound or video
shows its [artwork](media.md#artwork) as its thumbnail, marked with its
kind. Choose one for its
screen, in two columns. On the left: a preview (an image or video; a
sound's artwork, title, artist, and album with a player; or a PDF's
page with its page count); a sound's or video's **Artwork** (see
[Artwork](media.md#artwork)); **Usage**, its address
and what an entry writes to show it, each with a Copy button, then the
entries that use it (the first five of a long list, with **Show All**),
and for an image, the sounds and videos that show it as their artwork;
**Storage**, where it lives, its type, size, length or pages, and who
uploaded it; and an image's **Other Sizes**. On the right: its **Details** (the
fields files of its kind have: **Title**, **Alt text** for an image,
**Caption**, **Credit**, and **Description**, and any your site adds;
see [Details about a file](media.md#details-about-a-file)), the only
part that saves, and its **Metadata**, what the file says about itself,
with a warning if it carries its location. A value the file holds that
fits a detail is offered under that field, with **Use It**, and **Fill
from the File** fills every empty one (see
[What a file says about itself](media.md#what-a-file-says-about-itself)).
The heading is the file's title, else its file name, and follows the
Title field as you type. An image without alt text says so under the
field. Alt text describes the file for anyone
who can't see it, and the caption goes under it; both are filled in when
it's inserted as an image. After that, the entry's copy is its own: the
page shows what the entry wrote, and an image with empty brackets,
`![](/media/rule.png)`, has empty alt text (`alt=""`), which marks it
decorative. Changing an image's alt text or caption in the editor never
changes the library's, and changing the library's never changes an
entry. They
can be changed by whoever uploaded the file (`media.edit`), or by anyone
with `media.edit.others`, which files with no uploader recorded need;
otherwise they're shown read-only. Changes wait in the **save bar** at
the bottom, which counts them: **Save Changes** saves them, and
**Revert** puts them back.

The entries a file's screen lists use it by any of its addresses, in
their text or front matter. **Delete** (your own files with
`media.delete`, anyone's with `media.delete.others`) asks first, saying
how many entries use it, which will be left pointing at an address that
no longer resolves until they're changed, and which files it's the
artwork of, which lose it; it removes the file,
its details, and the copy `media:publish --copy` made.

They're kept apart from the file, in `user/data/media/`, which mirrors
the media paths: `user/media/2026/09/lake.jpg` has
`user/data/media/2026/09/lake.jpg.json`:

```json
{
    "alt": "A lake at dawn, with mist on the water.",
    "caption": "The lake at dawn"
}
```

You can write these by hand too. Any JSON
file in `user/data` may start with a `"$schema"` key for your editor;
it isn't read as data, and the admin keeps it when it saves. Saving
from the admin edits the file that's there, changing
only the fields you changed and keeping the rest (keys that aren't
fields are listed, as they are), and
removes a file left with nothing in it. If you rename or
delete a media file by hand, move or delete its metadata file too. **Upload** opens the same picker the editor uses, with only its
Upload panel (the library is the screen behind it); **Open** goes to the
file you uploaded. You can also put files in
`user/media` yourself.

## Content types

With `site.settings`, **Config → Content types** lists every type, with
its kind (**Terms** for a [type of terms](content-types.md#terms-and-relationships)),
where it's defined (built in, a plugin, or `user/data/types`), how
many fields it has, and how many entries. Tabs
narrow it (Collections, Terms, Trees, Profiles; only the ones the site
has), beside a search. Choose one for its settings, its relationships,
and its fields; **Type Settings** on a type's list goes there too.

Types in `user/data/types` are edited on their screen. So are
collections and [trees](content-types.md#trees) from plugins:
what you change is saved in `user/data/types/{key}.json` over the code's
definition (see [Changing a type from code](content-types.md#changing-a-type-from-code)).
The pages and profiles types defined in code stay as they are, so their
screens only show them. A type still written as a taxonomy can't be
edited until it's migrated (see [Site Health](#site-health) and
[Moving from taxonomies](content-types.md#moving-from-taxonomies)), nor
can one that still names its own folder until it's moved (see
[Moving a type into its folder](content-types.md#moving-a-type-into-its-folder)).

### Creating a type

**New Content Type** walks through three steps, with **What Gets
Created** beside them:

1. **Basics:** a **Collection**, **Terms** (a collection whose entries
   file other entries, such as topics or tags), or a
   [**Tree**](content-types.md#trees), its names, a key
   (made from the name, such as `recipe`), a description, and an icon.
   Its entries live in `_` and the key under `user/content`
   (`_recipe`), and the key can't change later.
2. **Behavior:** the URL prefix (made from the plural name, such as
   `recipes`; empty for the key), whether it's
   visible on the site, in the sitemap, listed in `llms.txt` (on for
   collections and trees, off for terms, by default), and has a feed;
   for a collection, date archives, a featured image (an `image` media
   field), whether entries nest under a parent, and its **Order**
   (newest published first, or by position for terms); for terms,
   **Files**, the types filed under them (none for every type); an
   index page, the type's landing page (on by default); and, when the
   site has profiles, whether entries **credit authors** (on for content,
   off for terms), which adds the type to the `authors`
   [credit relation](content-types.md#crediting-people) (writing it when
   there's none), and whether its list of authors has a **page
   introducing it** (see [People archives](content-types.md#people-archives)).
   Other credits are relationships, added on the type's screen. A tree
   has no feed or author archives: its entries are at their paths under
   its prefix. **File names** and **Folders** (for a type with thousands
   of entries) are offered only when content is kept in files.
3. **Fields:** the fields its entries carry beside the title, slug,
   status, dates, and body.

**Create Type** writes `user/data/types/{key}.json`, the index page
as `index.md` in its folder, titled with the plural name, and the
authors page, when chosen, as `_authors.md`, titled "Authors". For
terms, it also writes the classify relation that files the chosen types
under them, as `user/data/relations/{key}.json`. Terms are a collection
ordered by position, with no authors, and left out of `llms.txt`.

### Editing a type

A type's screen has General (names, description, icon), Behavior (as
above), **Archives**, **Relationships**, Addresses, and Fields.

In Behavior, a collection has **Entries can nest under a parent, as
categories do** (`hierarchical`) and **Order** (`order`; see
[Nesting and order](content-types.md#nesting-and-order)).

**Relationships** lists every [relation](content-types.md#terms-and-relationships)
the type takes part in, from either side, each said as a sentence from
its side ("Credits **Profiles** as Cooks", "Filed under **Courses**",
"Linked from **Posts** as Mentions"), with its key and what it takes
("At least 1, in order", "Up to 3"). The rows are split by where each
is stored: the ones **Stored on** this type (its files carry the key)
have **Edit** and **Remove**; one stored on another type has only the
way to that type (**Edit on Posts**), since that's where it's edited.
One from a plugin keeps its row with **In code**, and says where it's defined. **View in Relationships** opens
the [Relationships](#relationships) list for this type, and **Add
Relationship** starts one stored here.

**Archives** shows each [relation archive](content-types.md#people-archives)
under the type (its authors', say, at `/blog/authors`): its list page,
if it has one, or **Has a page introducing the list** to write one,
titled with the relation's label. A type with more than one credit
chooses its **Byline** there, the credit that names an entry's author.
Whether a relation has archives, and their word, are set on its
[screen](#a-relationships-screen); a page written for one target's archive is kept
when they're off, and on a profile it's marked **Unreachable**.

**Addresses** lists every address the type has: its listing and later
pages, date archives, entries (or terms), feeds, and author archives.
Each shows its path after the type's prefix, its default when empty,
the whole address, and the {placeholders} it needs and may hold: an
entry's address needs `{name}` and may hold the date's parts
(`{year}` to `{second}`) and the name of a type of terms it's filed
under; later pages need
`{page}`. A path that leaves out what it needs, or holds something it
can't fill, is refused with the reason. Changing an address moves those
pages, so add [redirects](#redirects) for the old ones.

In **Fields**, open a field to change its
label, key, type, help, whether it's required, its default, and its
type's options (a number's limits and whole numbers, a choice's options,
a list's item type, a reference's type and whether it takes more than
one), move fields up or down, or remove one; **Add Field** adds one.
Groups of fields (`object`) are kept as written; edit those in the file.
A field whose type can be edited more than one way has **Edited with**:
a choice as a menu or radio buttons, text on one line, several, or in
code type, a list of choices as checkboxes (see
[How the admin edits a field](content-types.md#how-the-admin-edits-a-field)).
The field types offered include ones plugins add. Changes wait in the
**save bar** at the bottom, which counts them: **Save Changes** writes
them, and **Revert** puts them back.

**Field Sets** lists the [field sets](#fields) added to the type, each
linking to its screen, where the types it's added to are chosen.

Only the options you change are written, and nothing at its default:
the rest of the file stays as you wrote it, comments included. A change
that doesn't fit with the other types, such as two types in one folder,
is refused with the reason, and the file is left as it was. The site
uses a change on the next request.

**Delete This Type** removes its file. Its entries stay in its folder,
unlisted until a type claims the folder again. A type a relation names
can't be deleted until the relation is removed ("The "tag" relation
names post; remove it first.").

A type from code says where it's defined and where changes go. Its file
keeps only what differs from the code, and is removed when everything
is back at the code's values. **Reset the Type** removes the file,
going back to the plugin's definition and undoing every change made here; a type
from code can't be deleted here. If some of its fields are field
classes from code, its fields are shown but changed in code.

## Relationships

With `site.settings`, **Config → Relationships** lists every
[relation](content-types.md#terms-and-relationships) on the site: the
place they're made and edited, since a type's screen shows only its
side. Tabs narrow it by purpose (**Credits**, **Files Under Terms**,
**Links**), beside a search over names and keys, a type filter that
matches either end (everything that points at Profiles, and everything
Recipes points at), and a source filter. Each row has its name (its
key under it), what it **Connects** (the type that stores it, then the
one it points at), its kind, its **Source**, and how many entries have
a value in it. A relation from a plugin is listed, but changed where it's defined; one still written as a
taxonomy is marked, with **Migrate** going to
[Site Health](#site-health). **New Relationship** opens a new one.

### A relationship's screen

A relationship's screen is rows of settings in panels, as the Settings
screens are. The sentence under its name says it whole ("Recipes
credit Profiles as Cooks."), and changes as you change it.

- **Purpose:** **Files Entries Under Terms**, **Links Entries to Other
  Entries**, or **Credits People**. It's chosen when it's made.
- **Endpoints:** **Stored On**, the types whose files carry its key
  (for terms and credits, none chosen means every type), and what it
  points at: the **Terms** type (one nothing files under yet), a type
  of entries, or, for credits, the profiles.
- **Names:** the **Front Matter Key** (`authors` for a new credit
  unless you change it), what it's **Called** in the editor, such as
  "Cast", and what **One Is Called** ("Add a cook").
- **Options:** whether each entry takes **Several** or **Exactly One**;
  whether their **Order Matters** (the first is the lead); whether
  they're **Created as Typed**; and, for a link to its own type,
  whether a link counts **Both Ways**.
- **Limits:** how many an entry needs to publish (**At Least**), the
  most it takes (**At Most**), and the most entries that may point at
  one target (an episode in one season), restated as a sentence under
  them. They're checked when an entry is published; a draft always
  saves.
- **Editing:** the **Picker** (chips, cards, or, for terms that nest, a
  tree) and which links a translation uses: **Fall Back** to its
  original's, **Add to the Original**, or **Keep Their Own**.
- **What Links to It:** what the list is called on the other side, such
  as "Acted in"; whether it's on the target's **Own Page**; and whether
  it has an **Archive**, under a word (see
  [Linking entries to other entries](content-types.md#linking-entries-to-other-entries)).

Opened from a type's screen, a new one is stored on that type, and
saving goes back there. It's saved in
`user/data/relations/{name}.json`. A relation can't take the name of
one defined in code. **Remove Relationship**, at the foot, removes it.

Changing a relation entries already use says what it does before it
does it, with the default the choice that changes no files:

- It can't **point at another type** while entries have values in it,
  or go from **several to one** while an entry has more than one. It
  says so, lists the entries in the way (most first, up to 8), and
  offers to keep the setting as it was; **Show All** opens the type's
  list with only those entries.
- A **new key** keeps the old one working as another name for it, so no
  file changes, unless you check **Rewrite All Files Now**.
- **No longer filing a type** keeps those entries' values in their
  files, unread, unless you check to remove them.
- **Tighter limits** list the entries they put over, which keep their
  values and can't be published again until they're within them.
  Nothing in any file changes.
- **Remove** says how many entries have values, and offers to strip
  them from their files, unchecked at first: kept, adding the
  relationship again brings the links back.

## Fields

With `site.settings`, **Config → Fields** lists every
[field set](content-types.md#field-sets): groups of fields added to
content types, to [media files' details](media.md#details-about-a-file),
or to the [Settings](#settings) screens, beside their own. The list shows where each set is added (a place the
site doesn't have is grayed), where it's defined, and how many fields it
has.

Sets in `user/data/fields` are edited on their screen; sets from
`config/fields.php` and plugins are defined in code, so their screens
only show them.

**New Field Set** has a label (heading the set's fields in the editor),
a key (made from the label; it's the file's name, so it can't change
later), help shown under the label, the kind of place to add it to
(content types; kinds of media file: images, videos, audio, documents, and other
files; or the Settings screens; a set's places are all one kind), the
places of that kind, and its fields, edited as a type's are. **Create
Field Set** writes
`user/data/fields/{key}.json`.

A set's screen edits the same things; changes wait in the **save bar**:
**Save Changes** writes them, or **Revert** puts them back. Only what you change is written,
and the rest of the file stays as you wrote it, comments included. A set
can't use a field name a type it's added to already has: that's refused
with the reason, and the file is left as it was.

**Delete This Field Set** removes its file, and its fields leave the
types it was added to. Entries keep their values, shown as other front
matter.

## Settings

With `site.settings`, the **Settings** group in **Config** has seven
screens (and [Redirects](#redirects), with its own capability):

- **General:** the site's name, a one-line description (for
  `llms.txt`, and the homepage and feeds when nothing more specific
  describes them), its language and region (picked from a menu of every
  language and region PHP knows, each named in its own language with
  its English name beside it, and each region under its language;
  search by either name or the code, or choose **Other…** to type any
  code, such as `en_US`); on a site with other languages, **Untranslated
  pages**, what another language does with an entry not translated
  into it (see [Translations](content.md#pages-that-arent-translated));
  and its time zone, with the time there now.
  Time zones are listed by city under their regions, each with its
  common name and offset today (Chicago: Central Time · UTC−5); search
  by city, name, abbreviation (`CST`, `CDT`), offset (`-05:00`), or an
  old name such as `US/Central`. The zone is still saved by its full
  name, such as `America/Chicago`. Then the date and time formats
  themes show dates with, which the admin uses too wherever a date
  reads as a date (when a file changed, when an account last signed
  in, when something went to the trash), though lists keep their
  short dates and times stay in your own time zone: each menu shows how today reads in the formats
  the site's language defines (Full, Long, Medium, Short) and in a few
  fixed ones (`2026-10-04`, `14:30`), or choose **Custom…** to type an
  [ICU pattern](https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax),
  such as `d MMMM y`, and see how it reads as you type. Then
  **Accounts**: **Sign-ups** (whether anyone can make an account, off
  by default) and **Role for new accounts** (Member by default; any
  role but Owner and Administrator, and locked while sign-ups are off).
  These are saved, but there's no sign-up form yet, so they don't
  change anything. Beside them,
  shown but not changed here: the site's address, the environment, and
  detailed error pages.
- **Reading:** the homepage (the page at `user/content/index.md`, or the
  latest entries of a collection) and feeds: the formats (RSS, Atom,
  JSON Feed; none turns feeds off), whether they carry each entry's full
  content, and how many entries each holds (1 to 100).
- **Writing:** how Markdown renders: **Mentions** (`@name` links to
  the profile with that slug once it's published; anyone else's stays
  text), **Smart punctuation** (curly quotes, dashes, and ellipses),
  **Heading anchors** (each heading links to itself), and **Images as
  figures** (an image on a line of its own becomes a figure, its title
  the caption), all on by default; and **Raw HTML**, what HTML written
  in content does on the page, whoever wrote it: **Allowed** (the
  default), **Filtered** (script, frames, forms, and styles show as
  text, and `javascript:` links lose their address), or **Shown as
  Text**. Who may add HTML in the editor is up to their role (see
  [Capabilities](accounts.md#capabilities)). Under **Embeds**, a button
  for each site whose links play on the page, A to Z (CodePen, Flickr,
  Reddit, SoundCloud, Spotify, TED, TikTok, Twitch, Vimeo, X, YouTube,
  and any your site adds), with its logo; press one to turn it on or off,
  or use **Turn All On** and **Turn All Off**. Hover a site to see the
  addresses it embeds. A site turned off keeps its links as links.
  They're saved together as `embed.off`, over `config/embed.php`'s
  `off`.
- **Media:** what may be uploaded, as a grid: **All Files**, then
  Images, Videos, Audio, Documents, and Other Files, each with an
  **Uploads** switch, its **Largest file** in megabytes, and its
  **Path** under `user/media` (a folder, or a pattern: click into one for
  the tokens `{year}`, `{month}`, `{day}`, and `{kind}`, and
  it shows the file a pattern would make). A kind's empty box takes All
  Files' value, shown in gray; All Files turned off stops every upload.
  A kind your site allows no file types of can't be turned on. A size
  can't be more than the server takes. Changing a path doesn't move a thing: files
  already uploaded keep their addresses, and the screen says so. On a
  narrow screen All Files and each kind are a line saying what they do;
  open one to change it.
  Under it, **Artwork from uploads** adds the cover art an uploaded
  sound or video carries to the library as its artwork (see
  [Artwork](media.md#artwork)), when the uploader may upload images.
- **Addresses and Search:** whether addresses end in a slash (`/about/`;
  the other form redirects, so old links keep working), whether the
  site has a sitemap and `robots.txt`, and the paths `robots.txt` asks
  search engines to skip, one a line. Shown: the media address, and
  whether search engines are asked not to index the site (outside
  production, they are).
- **AI:** whether every page has a Markdown copy and the site has
  `llms.txt`, with a link to view it and how many pages it lists;
  whether the site also has `llms-full.txt` (off by default, locked
  while the Markdown copies are off, and, once served, with its size
  and a warning when it's too big for the page cache); the
  types it lists (each content type chooses with **Listed in
  `llms.txt`** on its screen; new types of terms and profiles start off), and the description it
  uses; and which kinds of AI crawler `robots.txt` asks to stay away:
  training crawlers, AI search crawlers, and fetchers acting for a
  person, each saying what its crawlers do and naming its bots (see
  [Configuration](configuration.md#sitemap-and-robotstxt)). It warns
  when the choices aren't used: outside production, or with your own
  `robots.txt`.
- **System:** shown only: where content types come from, caching, and
  publishing and previews.

Settings [field sets](content-types.md#field-sets) add come after a
screen's own, a panel for each set under its label. They have no config
file behind them: a saved one has **Clear it** instead (see
[Your own settings](themes.md#your-own-settings)).

Change something and a bar at the bottom counts your unsaved changes,
with **Revert** and **Save Changes**; leaving the screen with changes
unsaved asks first. Saving keeps them in `user/data/settings/` (a file
for each group, such as `feed.json`), where
they win over `config/` and `.env`, and they take effect on the next
page load, with nothing to compile. Each setting is a row: its name, its
control (a yes or no is a switch saying On or Off), and, beside it, what
it does and whether it's saved there or comes from its config file. A saved one has
**Use `config/…`'s value**, which goes back to the config's value when
you save.

A setting that's only shown says when it's still the default and names
the file it's set in, and a risky one is flagged, such as detailed error
pages on a live site. Secrets are never shown, only whether one is set.
Those settings live in `config/` and `.env` (see
[Configuration](configuration.md)); after changing them on a site
you've compiled, run `bin/blush cache:compile` again.

## Redirects

With `site.redirects` (administrators and editors), **Redirects** in
**Config**, after **Addresses and Search**, lists the site's
[redirects](content.md#redirects): old addresses that send visitors
somewhere new.

The tabs are **All**, **Permanent**, **Temporary**, and **Problems**
(while there are some), each with its count. Search the old and new
addresses and the titles of the pages they lead to, filter by where
they go (this site or another), and switch to compact rows. Each row
shows **From**, **To**, the **Type** (in words, with its code beside
it), and when it was **Added**, by which account and how: **Added**, **Renamed**,
**Moved**, or **Imported by** the account's name (linked to its account). **To** says what kind of place it is: a page by its
title, with its address under it; a path; or another site, its host in
bold with an arrow out.

**Search is also a test.** Type or paste an address (a path, or a whole
link to the site) and the list shows the redirects that handle it, with
a line at the top that follows it as a visitor would: an arrow and the
status code for each redirect (hover one to see which redirect it is),
then where it ends up, a page, another site, or **Not Found**. Not Found
offers **Add a Redirect From Here**.

**Add Redirect**, or **Edit** in a row's menu, opens a small form:

- **From:** a path on this site. `{name}` matches one part of it and
  can be used again in To. A whole link to the site is saved as its
  path.
- **To:** a path, a whole address on another site, or part of a page's
  name, which lists matching pages to pick from. A picked page shows
  in the box, and the redirect follows it if it moves; × (or
  Backspace) clears it. A typed path that's a page's address becomes
  that page.
- **Type:** **Permanent** (301) or **Temporary** (302), or one of the
  three under **For Programs**: See Other (303), and Temporary and
  Permanent, Same Method (307 and 308), each with what it does.

As you type, the form says what it will do, and what stops it is a red
line under its field: a path without its `/`, another redirect from the
same address, a placeholder in To that From doesn't have, or a redirect
that would loop. A redirect that only leads to another redirect offers
**Go Straight There**. Changes save as soon as you press Save.

A row with a problem has a chip beside its old address; press it for
what's wrong and its fix:

- **Does nothing:** a page answers the old address, so the redirect
  never runs. **Delete Redirect**.
- **Leads nowhere:** the page it leads to was deleted, is in the trash,
  or isn't published. **Choose Another Page**.
- **Missing page:** nothing answers where it leads. **Change Where It
  Goes**.
- **Chained:** where it leads redirects again. **Point Straight There**.
- **Overruled:** the site's code redirects the same address first.
  **Show It** lists the code's redirects, which are under the table and
  can't be changed here.

Rows with problems keep working as far as they can. A row's menu also
has **Test this address**, **Copy old address**, and **Make temporary**
(or permanent). Select rows to make them permanent or temporary, or
delete them, together. Every change has **Undo** in its message.

## Themes

With `extensions.themes.view`, **Extend → Themes** shows every installed theme as
a card, the active one first and marked **Active**. Each card has a
sketch of a page in the theme's colors (from its `theme.json`'s
`preview`, see [The admin's preview](themes.md#the-admins-preview)),
its label, version, and description, where it's installed, and the
theme it falls back to, if it has a parent. The icons above the cards switch to a compact
list, a row for each theme; the choice is kept in this browser, and
Icon Packs shares it.

Above the list (on Plugins and Icon Packs too), **Filter themes** finds
a theme by its label, name, description, or
[keywords](extending.md#plugins); **Status** narrows it to the active
theme, the inactive ones, or those that need attention (one that can't
be activated or isn't running, a broken one, or an abandoned one), and
**Source** to Composer's, folders in `extensions/`, or the one that
ships with Blush. The filters are in the address, so a filtered list
can be bookmarked; the line under them says how many match, with
**Clear Filters**.

- **Activate** asks first, naming any extensions it would stop, then
  switches the site to that theme. It's
  saved in `user/data/settings/theme.json`, over `config/theme.php`, and takes
  effect on the next page load. If it doesn't go through, the card says
  so; the site keeps the theme it had.
- A theme that falls back to a theme that isn't installed says
  **Can't activate**, with what to do, and so does one whose
  [requirements](extending.md#requirements) (or those of a theme it
  falls back to) aren't met, saying what it needs. So does a theme whose
  `theme.json` is broken (or whose namespace another extension has),
  listed by where it was found, with the reason.
- When the active theme's requirements stop being met (a plugin it
  needs is turned off, say), its card says **Not running**, with what
  it needs: visitors see the default theme until that's fixed.
- The **⋯** menu opens the theme's details, copies its folder path or
  its `theme:activate` command, and in development, **Preview on the site** opens the site
  with that theme (`?theme={name}`, see [Themes](themes.md)).
- **Delete Theme** (in the menu) removes a theme's folder from
  `extensions/`, after asking. The active theme, and any theme it falls
  back to, can't be deleted; activate another first. Themes installed
  with Composer are removed with `composer remove`, and the default
  theme can't be removed. A theme that fell back to the one you deleted
  can't be activated until it's pointed at one that's installed.

A theme's label, or **Theme details** in its menu, opens its details:
**Appearance**, its preview in both its light and dark colors, each with
its palette's six colors listed under it; then **Details** (its name,
version, authors, license, where common open source licenses link to
their text, namespace, type, and folder, then its links and where to
fund it) beside **Dependencies**: the theme it falls back to (its
`parent`, if it has one), its
requirements (checked as if it were active), conflicts, and the rest of
its package links, then, under **What others say about it**, the themes
that fall back to it and the extensions that require it. **Activate** and
**Delete Theme** are there too; a theme the site uses says why it can't
be deleted, and a Composer theme gives the `composer remove` command.
Broken themes have no details page.

When the active theme was set here, the note under the cards has
**Use `config/theme.php`'s theme**, which removes the saved one.
Running `bin/blush theme:activate {name}` also clears it.

**Install Theme** takes a `.zip` of a theme's folder: drop it anywhere
on the modal, or choose it. The modal shows it being sent, then what was
installed, with **Activate** as the next step; a theme that's already
installed is offered for replacing, with both versions named; and a zip
that can't be installed says why, with nothing written. A zip of a
plugin or icon pack offers that screen instead. See
[Installing from a zip](extending.md#installing-from-a-zip) for what's
checked. **Install Plugin** and **Install Icon Pack** work the same way,
with **Turn On** as the next step. An
[abandoned](extending.md#plugins) theme, plugin, or pack is marked
**Abandoned** on its card, and its details say its author no longer
maintains it, naming the package to use instead (linked, when it's
installed); it still works, and installing one says so too. What an
extension [suggests](extending.md#plugins) is listed on its details,
under **Suggests**, and installing one lists it too. Its
[conflicts](extending.md#conflicts) are listed under **Conflicts**, and
what it [replaces](extending.md#replacing-another-extension) under
**Replaces**, each checked against what's on, and what it
[provides](extending.md#providing-a-package) under **Provides**. Its
details also name the extensions that conflict with it, replace it, or
also provide it ([the other side](extending.md#seeing-it-from-the-other-side)),
and turning one on (or activating a theme) asks first when it would stop
others, naming them. After a replace, the details screen
of a theme, plugin, or pack has the version that was kept, with **Roll
Back** and **Discard** (see
[Installing from a zip](extending.md#installing-from-a-zip)).

How the admin itself looks is set per account, on **Your Account**.

## Plugins

With `extensions.plugins.view`, **Extend → Plugins** lists every installed
[plugin](extending.md#plugins) by label, with its name, version, and
description, and a switch; its mark is green while it runs. The filters
above the list work as on [Themes](#themes), with On and Off for its
status. Turning a plugin on or off takes effect
straight away, and a message says so, naming any other extensions
(plugins, the active theme, or icon packs) that started or stopped with
it (the ones that [require](extending.md#requirements) it). What a plugin adds shows on the screens it belongs to, not here.

A plugin in `extensions/` is off until it's turned on here or named in
`config/plugins.php`'s `enabled` list; a Composer plugin is on. Once
you've used a switch, the list saved here names every plugin that's
on, so a Composer plugin can be turned off too, and one Composer
installs later starts off, saying why. A plugin whose requirements aren't met can't be
turned on, and says what it needs, such as "Needs Blush ^3.0 (this site
runs 2.1.0)." A broken plugin, whose manifest can't be read, is listed
by its folder (or Composer package) with what's wrong, and can't be
turned on until that's fixed; one in `extensions/` can be deleted. The switches are saved in `user/data/settings/plugins.json`, over
`config/plugins.php`'s `enabled` list; once they are, the note under the
list has **Use `config/plugins.php`'s list** to go back to it.

A plugin's name, or **Plugin details** in its **⋯** menu, opens its
details: **Details** (its name, version, who made it, license, where
common open source licenses link to their text, namespace, and folder,
then its links, such as homepage, documentation, and issues, without
email addresses, and where to fund it) beside **Dependencies**: each of
its requirements, checked against this site, a required extension
linked to its details, its conflicts, what it replaces, provides, and
suggests, then, under **What others say about it**, the extensions that
require it, conflict with it, replace it, or also provide it. **Delete Plugin** removes a plugin's folder from
`extensions/` once it's off; a Composer plugin is removed with
`composer remove` instead. **Install Plugin** installs one from a `.zip`,
as **Install Theme** does; it arrives turned off.

## Icon packs

With `extensions.icon-packs.view`, **Extend → Icon Packs** shows every installed
[icon pack](extending.md#icon-packs) by label, as a card of its first
icons, with its version, where it's installed, how many icons it has,
and a switch. A pack that's off adds no icons, so anywhere one of them
is used shows nothing. A pack whose [requirements](extending.md#requirements)
aren't met can't be turned on, and says what it needs; one that's on
adds no icons until they're met. Turning a pack off names any plugins
that stopped with it. Blush's own icons are the **Core** card, always
on. A pack that can't be used is listed by where it was found, with the
reason. Only icon packs are listed, not the icons themes and plugins
carry. The filters above the cards work as on [Themes](#themes), and
so does the switch to a compact list, where each row is marked with
the pack's first icon.

A pack in `extensions/` is off until it's turned on here or named in
`config/icons.php`'s `enabled` list; a Composer pack is on. As with
plugins, once a switch is used the saved list names every pack that's
on, Composer's included. The switches are saved in `user/data/settings/icons.json`, over that list, with
**Use `config/icons.php`'s list** to go back. A pack's name, or **Icon pack details** in its menu,
opens its details: every icon in it, with a filter, then **Details** and
**Dependencies**, as a plugin's are, with its namespace and how many
icons it has; click one to copy how it's used
(`weather/sun`). **Delete Icon Pack** removes a pack's folder (or a
broken pack's) from `extensions/`. **Install Icon Pack** installs one
from a `.zip`, as **Install Theme** does; it arrives turned off.

## Accounts and roles

The **Users** section's panel has **Your Account**, **Accounts**, **Profiles**, and
**Roles**. Accounts and profiles are two lists, because they're two
things: an account signs in, and a profile is a public identity that
bylines point at. One person usually has both, linked; a profile with
no account is a **guest profile**, and an account with no profile
doesn't appear on the site. An account's display name is its own, in
the admin; without one, it goes by its profile's title, then its
username. Every account has an email address. ⌘K finds either: accounts by name or
username, profiles among the entries. See
[Accounts and roles](accounts.md) for what roles and capabilities are.

### Profiles

**Profiles** is the profiles type's list, like any type's, with a
**Name** (beside its initials, dashed for a guest), its **Status**, the
**Account** linked to it (a **Guest** tag without one, **Locked** for a locked one), how many
published entries credit it (**Bylines**), and when it was updated.
Besides the usual filters, **Any account** shows only the profiles
linked to an account, or only guests. **New profile** starts one. A
name opens the profile's screen:

- **All profiles** above the name goes back to the list. The header has
  its address, status, and bylines, with **View**, **Publish** (for a
  draft), **Edit Profile** (the editor, where the title, the byline
  title, the avatar, and the bio are written), and a **⋮** to unlink
  or link an account, or move the profile to the trash.
- **Identity** shows what a byline renders: the display name, slug,
  **byline title** (shown under the name), and the avatar.
- **Linked Account** shows the account (at most one), its standing and
  last sign-in, with **Open Account** and, if you manage it,
  **Unlink**. Unlinking leaves the profile and its bylines, as a guest
  profile, and the account goes by its username. A guest profile can
  be linked here to an account that has no profile. If you can link
  accounts, a guest profile also has a **Can be linked** switch:
  turning it off locks the profile (`linkable: false` in its file), so
  no account can be linked to it, and it shows as **Locked** on the
  Profiles list and can't be chosen when linking from an account.
- **Where This Profile Appears** lists the profile's own page, then each
  credit relation of each type that credits people: its archive address,
  how many entries credit them there, and where the archive's body
  comes from: **Inherited** (the profile's own) or **Written** (a page
  written for that archive). **Write One** creates that page, a draft
  titled with the profile's name, and opens it; **Edit** opens it, or
  **Move to trash** puts the archive back on the profile's body (the
  page can be restored from its type's Trash tab). A credit whose archive is off
  says so, and a page written for it shows **Unreachable**. Each row
  counts the entries crediting the profile that way.

You see the profiles you may edit: your own, or anyone's with
`content.profile.edit.others` (or the type's name on your site).

### Accounts

**Accounts** lists the people who can sign in, by name, with tabs for
their standing (All, Active, Invited, Suspended) and, below them, a
search, a role, and whether they have a profile. Your own account,
marked **You**, is pinned at the top when it's in the list. Each row shows the
display name and username (an account with no profile is in a dashed
circle), the email address (**No email** for one made before they were
asked for), roles, its **Profile** (its name, with its status when
it isn't published yet, or the slug when it's linked to a profile with
no file), and the last sign-in; its **⋮** opens the account or its
profile, copies the email address, or makes a password link. **New Account** makes one; **Roles** lists each role with its
description, the content types it reaches (every type, some, or none)
and how many site capabilities it has, and how many accounts hold it.

- **New Account** asks for a username, an email address (required, and
  no other account's), an optional display name, its roles (**Member**
  is ticked to start, and stays the only choice if you can't give
  roles), and its profile: none, **Create a new profile…** (a display name and a slug,
  made as a draft and linked), or one of the profiles no other account
  has. A note says what the account will be called. Blush doesn't send email, so instead of a password the
  account gets a **password link**: copy it from the account's screen
  and send it however you like. It's shown only that once, and it
  works once, for a week. Until it's used, the account is **Invited**.
- An account's screen has **All accounts** above its name, then its
  username, standing, roles, and last sign-in. **Edit Details** changes
  its display name and email address. Tick or untick its **roles**,
  then **Save Roles** (or **Discard**); unticking the last one leaves
  the account a **Member**. An account with no email address says so,
  with **Add an Email Address**.
- **Public Profile** on an account's screen shows its profile: linked
  (**Open Profile**, **Unlink**), linked but not yet public
  (**Publish**), or none. With none, **Link an Existing One** opens a list of the
  profiles no other account has (a profile belongs to one account) to
  pick from, and **Create One** asks for a display name and slug, makes
  a draft, links it, and
  opens it.
- **Actions** has **Make a password link**, for a forgotten password:
  the person chooses a new one with it. Their old password keeps
  working until the link is used, and a new link replaces the old one.
  It also suspends an account (it's signed out and can't sign in until
  you **Reinstate** it) or deletes it. Deleting an account leaves its
  profile (as a guest profile) and the entries crediting it alone.

### Roles

A role's screen shows its key, where it comes from, and who holds it,
then its capabilities in sections: **Site Capabilities** (Media,
Structure, Site, People, and any a plugin adds) and **Content
Capabilities**, **Every Type** first, then one section for each
content type, under **Content types** and then **Terms** (the
[types of terms](content-types.md#terms-and-relationships)). Each section says in a sentence what the role can do; open it
to tick or untick its capabilities. **Expand All** opens every section,
and **Show Keys** shows each capability's key.

- Whatever **Every Type** grants applies to every content type,
  including ones added later, and shows ticked (and fixed) in each
  type's section. A type it grants everything on says **Set by Every
  Type**. To let a role do less on one type, choose **Set each
  type separately** from Every Type's **⋮** first: it moves those
  capabilities into each type there is now (types added later then
  get nothing).
- Each section's **⋮** sets the whole section at once: a type's to
  **Full access**, **Their own only**, **Drafts only**, or **No
  access**; a site group's to everything or nothing.
- Ticking **anyone's** ticks **their own** too, and unticking their own
  unticks anyone's: one without the other does nothing.
- Changes wait in the **save bar** at the bottom, which counts them;
  **Save Changes** saves, **Revert** puts them back, and a section with
  unsaved changes says **Changes**.
- **New Role** makes a role from a name, a key, a description, and its
  capabilities, in the same sections. **Duplicate** on any role starts a
  new one with its capabilities ticked.
- A role you made can be renamed (**⋮ → Rename this role**), described,
  given or denied capabilities, and deleted (**⋮ → Delete this role**)
  once no account holds it. **⋮ → Copy as JSON** copies any role.
- The built-in Editor, Author, and Contributor keep their names, but
  their capabilities can change; **⋮ → Reset to built-in capabilities**
  puts them back.
- The built-in Administrator can change too, and be reset. It starts
  with every built-in capability but installing, updating, and deleting
  plugins and themes.
- The Owner always has every capability, so its screen says so
  instead of listing them; it can't be changed. Roles defined in `config/auth.php` are
  changed there, so they're shown read-only.

### What you can't do

So that managing people never hands out more than you have, or locks
everyone out:

- You can't give a role, or a capability, that you don't have yourself,
  or change an account that can do something you can't.
- You can't change your own account here. Your password is on **Your
  profile**; someone else (or `bin/blush`) changes the rest.
- Only an [owner](accounts.md#owners) can change an owner's account or
  give the Owner role.
- A change that would leave no account able to manage accounts and
  roles (holding all seven of those capabilities) is refused.
- Each action has its own capability, so a role may, say, see accounts
  and suspend them but nothing else. Controls you can't use aren't
  shown.

## Trash

Each list has a **Trash** tab (if your account can delete entries) with
the entries moved there, most recent first. The **All** tab never
includes them. Each one's **⋯** button has:

- **Restore as a Draft** makes it a draft again, even if it was
  published before; publish it again from the editor when you're
  ready.
- **Preview** (or clicking its title) shows what's in it: the body, as
  the editor shows it but read-only, and its front matter. From there
  you can restore it or delete it permanently. A trashed entry isn't on
  your site, so it can't be viewed there, previewed, or edited until
  it's restored.
- **Delete Permanently** removes its file for good. When other entries
  link to it (as a term, a credit, or any other
  [relation](content-types.md#terms-and-relationships)), it lists them
  and offers to remove it from those you can edit, checked at first, so
  nothing names it after; uncheck it to leave their values as they are.

Entries linking to an entry keep their links while it's a draft or in
the trash; the site just stops showing it there, and publishing or
restoring it brings them back. So switching a published entry to draft,
or moving one to the trash, says first how many live entries link to it,
and lists them (up to 8, with what each links through). Only live
entries count: a draft that links loses nothing. When none link, it just
happens, with its toast and Undo. A profile says it's credited, and how
many entries credit no one else, so they'll show no byline.

Moving one entry to the trash, from its list, the editor, or a profile,
shows a toast with **Undo**, which puts it back as it was, published or
a draft, and opens it again if you were editing it. Undo needs
permission to publish for an entry that was published, and it's refused
if someone changed the entry in the trash meanwhile; restore it from
the Trash tab instead. Moving several at once has no Undo. Moving several to the trash or to draft says first which of them live
entries link to, and how many each, most linked first, naming the ones
nothing live links to. Past 8, **Show Only the Linked** cancels and
filters the list to the entries live entries link to.

**Empty Trash** deletes everything in that tab permanently. Authors and
contributors see and handle their own trashed entries; editors see
everyone's.

Moving an entry to the trash doesn't move its file. The file stays
where it is, with `status: trash` and the time in `trashed` in its front
matter (see [Front matter](content.md#front-matter)), so it keeps its
[id](content.md#ids) and its address. Nothing new can take that address
while it's there: restore it, or delete it permanently, first. You can
also restore one by hand by changing its `status`.

## Previewing drafts

In the editor, an entry that isn't live yet has a **Get Link** button
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

A link names the entry by its [id](content.md#ids), not its file, so it
keeps working if the entry is renamed or moved, and it doesn't show
your folders. Links are signed with the `APP_SECRET` in your `.env` (`bin/blush init`
adds one), so they can't be changed to show another entry or to last
longer. Changing `APP_SECRET` ends every link you've given out.
Previews are never cached or indexed by search engines.

## Site Health

**Home → Site Health** answers one question: is anything wrong? It's
for accounts with `site.health`: owners, unless an owner gives it to
another role. It shows the last check, with when it ran, and checks
again when you choose **Run a Check**; the first time you open it, it
checks right away. The number beside **Site Health** in the panel is
how many checks needed a look last time. Checking content and media
files again on their screens, as after a fix, updates their part of it.
Blush keeps the last check in `storage/health.json`.

**Checks** shows how many checks need attention, how many pass, and how
many ran. **Issues** lists what isn't passing, failures first, each with
what it found and what to do. A row with a chevron opens where it's
fixed. **Areas** lists the five areas the checks are in, and whether
each is clear:

| Area | What it checks |
|---|---|
| Content | Entries' files: problems in their front matter, ids, terms and profiles with no file, collection folders, and file names; and types still written as taxonomies |
| Media | Library files' ids, their details files, and image sizes |
| Extensions | A theme, plugins, or icon packs that are on but can't run |
| System | PHP and its extensions, `.env`, debugging and `APP_URL` in production, the public folder, and writable storage |
| Accounts | Whether the site has an owner |

Extensions, System, and Accounts are the checks
[`bin/blush doctor`](cli.md) runs. Here they run under the web server's
PHP, which can differ from the command line's.

**Requirements** compares what Blush needs with what this server has:
the PHP version, the required extensions and what uses them (`dom`,
`intl`, `mbstring`), the optional ones and what uses them (`opcache` and
`fileinfo` are recommended; `zip` installs extensions from a `.zip`,
`exif` reads photos' embedded details, `zlib` reads compressed PDFs'
details, and `apcu` keeps the cache in memory, needed only when the
cache or one of its stores uses the `apcu` driver), PHP's upload
limits against the largest upload the [Media settings](#settings) allow,
and the storage folders Blush writes to. Under **Plugins and Themes**
are the PHP extensions the plugins, themes, and icon packs that are on
require or suggest, each naming which ones ask, and a PHP version when
one needs a newer PHP than Blush does. One that requires an extension
that's missing doesn't run until it's installed. **Site & Server** lists the facts you'd be asked for in a bug
report, about this site and the server it runs on, in plain words: the
theme by its name, languages by theirs, PHP's memory and time limits in
megabytes and seconds, with the PHP setting each comes from beneath its
label. **Copy Report**, on either tab, copies the requirements and the
facts as text, the facts as they're configured (`memory_limit: 256M`,
`languages: en, es`), which is what a bug report needs.

### Fixing content and media

Each Content and Media issue opens a screen of its own, where it's
fixed: **Content Files**, **Entry IDs**, **Terms and Profiles**,
**Parent Pages**, **Links Between Entries**, **Collection Folders**, **File Names**,
**Taxonomies**, and **Type Folders**; **Media Details**, **Media IDs**, and **Image Sizes**.
Each shows the last check, with when it ran, and **Check Again**
checks every content and media file again, which updates Site Health
too. It reads the files in the background, a couple of hundred at a
time (content files, then media details), and its button shows how far
along it is; **Run a Check** on Site
Health does the same for the files, after checking the rest at once.

Each screen lists everything its check found, one row per problem.
Problems of one kind are grouped under a heading that says what the
site does about them, such as **Dates That Aren't Real** or **No ID,
or One That Isn't Valid**. A row says which entry (its title, then its
file), what was found, and what the site does because of it.

- **Fixing one row:** a row Blush can fix has a button named for what
  it writes, such as **Add an ID** or **Create Profile**. It changes
  that file at once. The row stays where it was, marked **Fixed**, until
  you check again. Problems Blush can't fix for you, such as a value
  that doesn't fit its field, have **Open in Editor**. A row's menu
  (**…**) opens the entry and copies its file's path.
- **Fixing a group:** when every row in a group would get the same
  kind of change, its heading has a button for all of them, such as
  **Give 12 Files New IDs**. It asks first, listing the files and what
  each gets. Groups whose rows each need a choice, such as which file
  keeps a shared id, are fixed a row at a time. A group fix runs as a
  [background job](going-live.md#background-jobs-and-cron), a hundred
  files at a time, and its button shows how far along it is; on a large
  site, if you leave the screen, it finishes on its own (you'll find it
  under **Tools → Jobs**).
- **Ignoring:** a warning or notice you've decided to leave can be
  ignored from its row's menu (**Ignore This**). It's ignored for
  everyone on the site, moves to the **Ignored** tab, which says who
  ignored it and when, and stops counting here and in Site Health.
  **Stop Ignoring** brings it back. Errors can't be ignored, since the
  site is already leaving something out. An ignored problem that's
  fixed some other way is forgotten, so if it comes back, it's seen
  again. Blush keeps them in Site Health's own settings,
  `user/data/settings/health.json`.
- **Finding rows:** the tabs say how many problems need attention, how
  many are notices (on screens that have them; notices change nothing
  on the site), and how many are ignored. Search titles and files
  (press `/`), narrow to errors or warnings, and switch between roomy
  and compact rows. On Content Files and Media Details, **Group by
  file** shows each file once with all its problems. A group shows 5
  rows; **Show 50 More** shows more.

Every fix changes only files you may edit. One problem is reported by
one check: a missing id is under Entry IDs, not Content Files too.

**Content Files** lists the problems in content files, as
`bin/blush content:lint` finds them: front matter that can't be read,
values that don't fit their fields, missing required fields, dates that
aren't real, two files claiming the same entry, parents and
translations that can't be used, and links that can't be followed.
Its notices are keys nothing reads and 1.x field names.

**Entry IDs** lists the files missing an [id](content.md#ids), or with
one that isn't valid; **Add an ID** gives one a new id. For an id two
or more files share (usually a copied file), choose the file that keeps
it, and **Give the Others New IDs**. A file without an id isn't an
entry, so lists leave it out until it has one.

**Terms and Profiles** lists each term and profile your entries name
that has no file, which the site leaves out (entries' terms and bylines
too, so an author isn't credited until their profile has a file), with
how many entries name it. **Create** writes its file, published and titled as the entries
name it, for the types you may create and publish.

**Parent Pages** lists each folder of pages with no page of its own,
which leaves the pages in it at the top of their tree, with how many
pages it holds. **Create** writes its page as a draft, titled by the
folder, for the types you may create.

**Links Between Entries** lists the files whose links aren't filed with
their ids. **File Links** files them, so they follow what they link to
through a rename or a move.

**Taxonomies** lists the types in `user/data/types` still written as
taxonomies, which Blush no longer has. **Migrate** rewrites each as a
collection and its classify relation in `user/data/relations`, as
`bin/blush content:taxonomies --write` does (see
[Moving from taxonomies](content-types.md#moving-from-taxonomies)). It
migrates them all at once, and needs Site Health and `site.settings`.

**Type Folders** lists the types in `user/data/types` that still name
their own folder, with where their entries move. Every type is kept in
`_` and its name, so their entries aren't found until they move.
**Move Types** moves them, as `bin/blush content:type-folders --write`
does (see
[Moving a type into its folder](content-types.md#moving-a-type-into-its-folder)):
each type's files move, and its file is written without the folder,
with the prefix its addresses came from. It moves them all at once,
and needs Site Health and `site.settings`.

**Collection Folders** lists the collection entries not in the folders
their [collection](content-types.md#collections-are-flat) keeps them in:
entries kept as folders, files in other folders, and files in a
[pattern's folder](content-types.md#folders-for-many-files) for another
date. It shows where each moves to; no address changes.

**File Names** lists, for each type with a file name
[pattern](content-types.md#naming-new-files) of its own (not the
default), the entries whose files aren't named by it, such as posts
written before you changed it, and the name each gets. No address
changes. Entries kept as folders keep their names.

**Media Details** lists the problems in media details in
`user/data/media/`: one that can't be read, a value that doesn't fit
its field, or details left for a file that's gone. **Media IDs** works
as entry ids do, for [media files](media.md#ids-and-image-sizes), by
their paths in the media folder, changing only files whose details you
may edit. A media file's screen shows its id under **File**. **Image
Sizes** lists the images whose details don't list their
[sizes](media.md#ids-and-image-sizes) as they are; **Record Sizes**
lists them. In the Media library, an image's sizes aren't items of
their own: its screen lists them under **Sizes**.

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
| `GET session` | The signed-in account (its username, `email`, `name`, `displayName`, `author`, `profile` (as `GET accounts` has it), `created`, `roles` (each `{"name", "label"}`), capabilities, and preferences) and a CSRF token, or `{"account": null}` |
| `POST login` | Sign in with `{"username", "password"}` |
| `POST logout` | Sign out |
| `POST password` | Change the account's own password with `{"current", "password"}`; answers `204`. Other sessions are signed out; this one stays, with a new id. A wrong current password or a short new one is a `422` whose `field` names it |
| `PATCH preferences` | Change the account's own preferences: `colorScheme` (`system`, `light`, or `dark`), `adminTheme` (`neutral` or `editorial`), `setupSkipped` (`true` or `false`), and `shortcuts` (a list of up to 30 screen ids, such as `"media"`, `"type:post"`, or `"settings:general"`, or `null` for the default), such as `{"colorScheme": "dark"}`; answers `{"preferences"}`, which also has `lastEdited`, the id of the entry the account last saved |
| `GET dashboard` | The site, `published` (how many entries the site has published), `resume` (the entry the account last saved, while it's a draft or scheduled, or `null`), `yours` and `everyone` (each `{"draft", "scheduled", "published"}`, up to five entries of pages and collections each, as `{"id", "path", "handle", "title", "type", "status", "url", "published", "updated", "authors", "yours"}`; `yours` is `null` for an account without a profile), and `setup` (`{"type", "page", "ownTypes"}`: the pages type, the newest page, and whether the site has a type of its own) |
| `GET counts` | The section panel's counts: `{"types"}` (each content type the account edits, by name: how many entries its list shows the account, without its index page), and, when the account may see them, `actions` (how many actions the account may run), `health` (how many checks need a look in Site Health's last check; `site.health`), `media` (the library's files; any media capability), `accounts` and `roles` (`accounts.view`), `contentTypes` and `fieldSets` (`site.settings`), and `themes`, `plugins`, and `iconPacks` (installed; each with seeing its kind, `extensions.themes.view` and so on) |
| `GET actions` | The actions the account may run, as `{"groups": [{"source": {"kind", "label"}, "actions": [{"name", "label", "description", "confirm"}]}]}`; a source's `kind` is `core`, `site`, `plugin`, or `other` |
| `POST actions/{name}` | Run an action; the answer is `{"successful", "message", "details"}` |
| `GET logs` | The end of the site's log, with `site.logs`: `{"driver", "file", "size", "entries"}`, its last 50 entries, newest first, each `{"time", "channel", "level", "message", "details"}` (`details` is the lines under it, such as an exception's trace); `file` is `null` unless the driver writes one |
| `GET logs/download` | The whole log file, to save, with `site.logs` |
| `GET icons` | The icons the active theme can show: `{"icons": [{"name", "label", "keywords", "category", "source", "svg"}]}`; a built-in icon has its `category` (such as `arrows` or `media`) and a `null` `source`, and the rest have a `null` `category` and a `source` like a directive's |
| `GET media` | The media files an entry can use (see below) |
| `GET media/{path}` | One file in the library, by its path under `user/media`, with its details (see below) |
| `POST media` | Upload a file to the library (see below) |
| `PATCH media/{path}` | Change a library file's details (see below) |
| `DELETE media/{path}` | Delete a library file, with its details (see below) |
| `GET directives` | The directives the editor's inserter offers as blocks: `{"directives": [{"name", "label", "description", "content", "kind", "category", "source", "props"}]}` (see below) |
| `GET roles` | Every capability (`{"name", "label", "group"}`, and a content capability's `type`, `*` for every type, and `action`), the content `types` (`{"name", "label", "kind", "icon"}`), and every role: `{"name", "label", "description", "capabilities", "builtIn", "origin", "accounts", "grantable", "editable"}` (`accounts` is each holder's `{"username", "displayName"}`), and a changed built-in's `defaults`. `origin` is `built-in`, `changed`, `custom`, or `config`; `grantable` is whether you may give it, and `editable` whether you may change it. Needs `accounts.view`, as do all of these; each change needs its own capability too (`accounts.create`, `accounts.edit`, `accounts.roles`, `accounts.suspend`, `accounts.delete`, or `roles.manage`; see [Capabilities](accounts.md#capabilities)) |
| `POST roles` | Make a role: `{"name", "label", "description", "capabilities"}`; answers `201` with `{"role"}` |
| `PATCH roles/{name}` | Change a role: any of `label`, `description`, and `capabilities` (a built-in takes only `capabilities`); answers `{"role"}` |
| `DELETE roles/{name}` | Delete a role no account holds, or reset a changed built-in; answers `{"role"}` (`null` once deleted) |
| `PATCH profile` | Change the account's own `name` (`null` or empty removes it) or `email`, or both; answers `{"name", "email", "displayName"}`. A name over 100 characters, or an email address that's missing, invalid, or another account's, is a `422` naming the `field` |
| `GET accounts` | Every account: `{"username", "email", "name", "displayName", "roles", "author", "profile", "created", "lastLogin", "status", "link", "manages"}`. `author` is the slug of the profile it's linked to, or `null` (an account is linked by the profile's id, so a renamed profile stays linked); `profile` is that profile: `{"path", "id", "handle", "slug", "title", "status", "url", "uses"}` (`uses` counts the published entries crediting it), else `null`; `email` is its email address (`null` only for one made before they were asked for); `name` is its own display name or `null`; `displayName` is what the admin calls it: its name, else its profile's title, else the username; `status` is `active`, `invited`, or `suspended`; `link` is its password link's `{"expires", "expired"}` or `null`; `manages` is whether you may change it. Times are Unix |
| `GET profiles` | Every profile, for linking accounts: `{"profiles": [{"slug", "title", "status", "account", "linkable"}]}`, by name, with `account` the one linked to it (`{"username", "displayName"}`) or `null`, and `linkable` `false` for a locked profile. Needs `accounts.view` |
| `GET profiles/{slug}` | A profile's screen: `{"profile", "appears", "linked", "account"}`. `profile` is `{"slug", "title", "subtitle", "avatar", "status", "path", "id", "type", "handle", "url", "uses", "linkable"}` (`id` and `handle` are `null` for a file without a valid id); `appears` lists each credit relation of each type that credits people: `{"type", "typeLabel", "relation", "label", "entries", "archive", "page"}`, where `entries` counts the published entries crediting them there, `archive` is the archive's address (or `null` without one), and `page` is the page written for it (`{"path", "id", "type", "handle", "title", "status"}`, kept while the archive is off) or `null`; `linked` says whether an account is linked to it, and `account` is that account, as `GET accounts` has it, for whoever has `accounts.view` (else `null`). Needs to be allowed to edit the profile (your own, or anyone's with the profiles type's `edit.others`) |
| `PATCH profiles/{slug}` | Lock a profile against being linked to an account, or unlock it: `{"linkable": false}` writes `linkable: false` to its front matter, `{"linkable": true}` removes it; answers `{"linkable"}`. Needs to be allowed to edit the profile, and `accounts.view` and `accounts.edit` |
| `POST profiles/{slug}/pages` | Write the page for the profile's archive under a credit relation: `{"type", "relation"}`, one with archives under the type. It's a draft at `_{word}/{slug}` in the type's folder, titled with the profile's name; answers `201` with `{"id", "handle"}`, or `409` when it exists. Needs to create entries of that type |
| `DELETE profiles/{slug}/pages/{type}/{relation}` | Move that page to the trash, so the archive shows the profile's body again; answers `{"removed"}`. Needs to delete that page |
| `POST accounts` | Make an account: `{"username", "email", "roles", "author", "profileTitle", "name"}` (`email` is required; the last three are optional). An `author` slug with no profile makes one, a draft titled `profileTitle` (else the name, else the slug), which needs creating profiles too; a missing, invalid, or taken email address is a `422` with `field: email`; answers `201` with `{"account", "link": {"url", "expires"}}`. The link is shown only this once |
| `PATCH accounts/{username}` | Change an account: any of `roles`, `author` (its profile's slug, `null` unlinks; a slug with no profile makes one, a draft, as `POST accounts` does; a profile another account has is a `422` with `field: author`), `name` (`null` or empty removes it), `email`, and `suspended`; answers `{"account"}`. Your own account takes only `author` (with `accounts.edit`) |
| `POST accounts/{username}/link` | A new password link, replacing any other: `{"account", "link"}` |
| `DELETE accounts/{username}` | Remove an account; answers `204` |
| `POST set-password` | Choose a password with a link: `{"account", "token", "password"}`; signs in and answers `204`. No account needed. A short password is a `422` (`field` `password`); a link that's expired, used, replaced, or for a suspended account is a `410`, and too many tries a `429` |
| `GET themes` | The installed themes: `{"active", "chain", "problem", "fallback", "config", "preview", "themes": [{"name", "label", "namespace", "version", "description", "parent", "source", "active", "authors", "license", "licenses", "links", "funding", "keywords", "running", "requirements", "conflicts", "replaces", "provides", "blocked", "requiredBy", "abandoned", "replacement", "suggests", "conflictedBy", "replacedBy", "providedBy", "stops"}], "invalid": [{"where", "reason"}]}`. `fallback` says why the active theme doesn't run (its requirements, or those of a theme it falls back to, aren't met, so the default theme shows), or is `null`; `running` is whether a theme is in the chain that runs; `requirements`, `conflicts`, and `replaces` are checked as if it were active; `blocked` says why it can't be activated, or is `null`; `requirements` and `requiredBy` are as `GET plugins` has them. Themes are by name (`vendor/name`), the active one first, then by label; `chain` is the active theme, then the themes it builds on; `invalid` names where each broken theme was found (`extensions/{vendor}/{name}`, or a package's name); `config` is whether `config/theme.php` exists; `preview` is whether `?theme=` works (development only); `source` is `framework`, `local`, or `composer`; `licenses`, `links`, `funding`, `keywords`, `abandoned`, `replacement`, `provides`, `suggests`, `conflictedBy`, `replacedBy`, `providedBy`, and `stops` (what activating it would stop) are as `GET plugins` has them. Needs `extensions.themes.view` |
| `GET settings` | The site-wide settings, to show: `{"groups": [{"key", "title", "hint", "file", "note", "items": [{"key", "label", "value", "kind", "default", "help", "warning"}]}]}`. `kind` is `text`, `mono`, `bool` (the value is `true` or `false`), or `list`; `default` is whether it's unchanged (`null` for one that follows from others); `note` marks code with backticks. A setting the admin changes adds its `setting` (`feed.limit`, or `site.{name}` for one a field set adds), its `field` (as forms take it, with `choices` and a `caption`), the `input` the form starts from, and whether it's `saved`. A shown one may add a `link` (`{"label", "href"}`, a page on the site) or `links` (`{"label", "to"}`, admin paths). The screens are `general`, `reading`, `search`, `ai`, and `system`. Secrets are never sent. Needs `site.settings` |
| `GET settings/date-format` | How a date or time format reads now: `format`, `kind` (`date` or `time`), and the `locale` to read it in (the site's by default). Answers `{"text"}`, or a `422` saying what's wrong with the format. Needs `site.settings` |
| `GET plugins` | Every installed plugin, by label: `{"plugins": [{"name", "label", "namespace", "version", "description", "authors", "license", "licenses", "links", "funding", "keywords", "source", "path", "folder", "enabled", "running", "requirements", "conflicts", "replaces", "provides", "blocked", "requiredBy", "abandoned", "replacement", "suggests", "conflictedBy", "replacedBy", "providedBy", "stops", "deletable"}], "saved", "config"}`. `licenses` is the license's parts, `{"text", "url", "operator"}`: each license it names, with `url` linking a common one's text (or `null`), and the `or`, `and`, or `with` between them; `links` is its homepage and support links in order, `{"kind", "url"}` (`kind` is `homepage`, `docs`, `source`, `issues`, `forum`, `chat`, `wiki`, `irc`, `rss`, `security`, or `email`, whose `url` is `mailto:`); `funding` is `{"type", "url"}`; `keywords` is its list of [keywords](extending.md#plugins); `source` is `local` or `composer`; `path` is from the site's root, and `folder` is its folder in `extensions/` (`null` for Composer); `enabled` is whether it's turned on, and `running` whether it runs (it doesn't when its requirements aren't met); each of `requirements` is `{"name", "constraint", "kind", "met", "note", "label", "metBy"}` (`kind` is `blush`, `php`, `extension`, `plugin`, `theme`, `icon-pack`, `library` for a package Composer installed, `composer` for what only Composer checks, such as `lib-icu`, `missing` for a name that isn't installed, or `unknown`), checked as if it were on for one that's off; `conflicts` are its [conflicts](extending.md#conflicts) in the same shape, each `met` when it doesn't conflict with what's on, and `replaces` what it [replaces](extending.md#replacing-another-extension), each `met` when that isn't on (its `constraint` is the versions it stands in for, `self.version` resolved); `provides` what it [provides](extending.md#providing-a-package), each `{"name", "constraint"}`; `conflictedBy`, `replacedBy`, and `providedBy` the extensions whose `conflict` hits it, that replace it, and that provide it, and `stops` what turning it on would stop, each `{"name", "label", "kind"}`; a requirement met by an extension replacing or providing what it names, or a conflict with one, has that extension's name in `metBy` and its kind in `kind` (otherwise `metBy` is empty); `blocked` says why it can't run, or is `null`; `requiredBy` lists the extensions of every kind that require it or a package it replaces or provides, `{"name", "label", "kind"}`; `abandoned` is `false`, `true`, or the package to use instead, and `replacement` that package when it's an installed extension, `{"name", "label", "kind"}`, or `null`; each of `suggests` is `{"name", "reason", "extension", "loaded"}`, `extension` being the suggested extension when it's installed (`{"name", "label", "kind"}`, or `null`), `loaded` whether a suggested PHP extension (`ext-{name}`) is loaded (`null` for anything else); `deletable` is a folder plugin that isn't running. `saved` is whether the admin has saved its own list; `config` is whether `config/plugins.php` exists. Needs `extensions.plugins.view` |
| `PUT plugins/{vendor}/{name}` | Turns a plugin on or off: send `{"enabled": true}` or `false`. Saves `plugins.enabled` in `user/data/settings/plugins.json`: every plugin that's on, Composer's included, starting from what's on by default. Answers `{"enabled", "started", "stopped", "refresh"}`: the labels of other extensions, of any kind, that start or stop with it, and that `POST settings/refresh` should follow. A plugin `config/plugins.php`'s `enabled` list leaves out is a `409`; one whose requirements aren't met a `422`. Needs `extensions.plugins.activate` |
| `DELETE plugins/{vendor}/{name}` | Deletes a plugin's folder from `extensions/` (a broken one's too, by its folder): `{"deleted"}`. One that's running, or that `config/plugins.php` turns on by name, is a `409`. Needs `extensions.plugins.delete` |
| `GET icon-packs` | Every installed icon pack, by label: `{"packs": [{"name", "label", "namespace", "version", "description", "authors", "license", "licenses", "links", "funding", "keywords", "source", "path", "folder", "enabled", "running", "requirements", "conflicts", "replaces", "provides", "blocked", "requiredBy", "abandoned", "replacement", "suggests", "conflictedBy", "replacedBy", "providedBy", "stops", "deletable", "count", "icons"}], "core": {"label", "version", "count", "icons"}, "invalid": [{"where", "reason", "deletable"}], "saved", "config"}`. `count` is how many icons it has, and `icons` the first twelve, each `{"name", "svg"}` (`brands/github`; a core icon's name alone), the `svg` empty when the file is too large. `enabled` is whether it's turned on, and `running` whether its icons load (they don't when its requirements aren't met); `keywords`, `requirements`, `conflicts`, `replaces`, `provides`, `blocked`, `requiredBy`, `abandoned`, `replacement`, `suggests`, `conflictedBy`, `replacedBy`, `providedBy`, and `stops` are as `GET plugins` has them. `saved` is whether the admin has saved its own list; `config` is whether `config/icons.php` exists. Needs `extensions.icon-packs.view` |
| `GET icon-packs/{vendor}/{name}`, `GET icon-packs/core` | One pack (`{"pack"}`) or the core set (`{"core"}`), with every icon. Needs `extensions.icon-packs.view` |
| `PUT icon-packs/{vendor}/{name}` | Turns a pack on or off: send `{"enabled": true}` or `false`. Saves `icons.enabled` in `user/data/settings/icons.json`: every pack that's on, Composer's included, starting from what's on by default. Answers `{"enabled", "started", "stopped", "refresh"}`, as `PUT plugins` does. One whose requirements aren't met is a `422`. Needs `extensions.icon-packs.activate` |
| `DELETE icon-packs/{vendor}/{name}` | Deletes a pack's folder (or a broken pack's) from `extensions/`: `{"deleted"}`. Needs `extensions.icon-packs.delete` |
| `POST themes`, `POST plugins`, `POST icon-packs` | Installs an extension from a `.zip` of its folder, sent as the multipart field `file`, with `replace` set to `1` to replace an installed one with its name. Answers `201` with `{"installed": {"name", "label", "version", "folder", "abandoned", "suggests"}, "replaced", "backup", "refresh"}` (`abandoned` as `GET plugins` has it; `suggests` what it suggests, each `{"name", "reason"}`; `replaced` is the version it replaced, or `null`; `refresh` asks for `POST settings/refresh`). One already installed, without `replace`, is a `409` with `{"clash": {"installed", "incoming"}}`; anything else that stops it is a `422` saying why, with the `kind` an archive of another kind holds. Nothing is written either way. Needs `extensions.{kind}.install`, or `.update` to replace. Each kind's list (`GET themes`, `GET plugins`, `GET icon-packs`) also has `upload`: `{"limit", "problem"}`, the largest archive taken in bytes and why nothing can be installed |
| `POST {themes,plugins,icon-packs}/{vendor}/{name}/rollback` | Rolls a folder extension back to the version replacing it kept, keeping the version it replaces in its place: `{"rolledBack": {"name", "label", "version", "folder"}, "from", "refresh"}`. No kept version is a `404`; one that wouldn't run (a plugin's requirements, an active theme's missing parent) a `422`. Each extension in its list has `backup`: `{"version"}`, or `null`. Needs `extensions.{kind}.update` |
| `DELETE {themes,plugins,icon-packs}/{vendor}/{name}/backup` | Discards the kept version: `{"discarded": true}`. Needs `extensions.{kind}.delete` |
| `GET types` | The site's content types: `{"types": [{"name", "labels", "description", "icon", "kind", "dated", "authors", "origin", "folder", "prefix", "fields"}], "authors"}`, by plural label (`folder` is `_` and its name, `''` for `page`; `prefix` its URL prefix, a tree's own, or `null` for a type without URLs), types of terms and the profiles type last. `kind` is `collection`, `tree`, or `profiles`, and each type's `authors` is whether its entries credit people. Each also has `terms` (whether a classify relation files entries under it), `hierarchical` (whether its entries nest by a `parent`), and `order` (a collection's, `published` or `position`; `null` for other kinds). A type of terms adds `"types"`, the types its relation files (empty for every type); the profiles type adds `"types"`, the types that credit people. `fields` is how many the type defines, `icon` is `null` for the kind's, and the top-level `authors` names the profiles type (`null` when the site has none). `labels` has every [label](content-types.md#names-descriptions-and-icons-in-the-admin), defaults filled in |
| `GET types/{name}` | One type, with its own `fields`, the field `sets` added to it (`{"name", "label", "fields"}`), the `taxonomies` (types of terms) whose relations file it, its `relations` (every relation from or to it, each as `GET relations` describes it), `public`, `feed`, `sitemap`, `llms` (whether it's listed in `llms.txt`; off by default for profiles), `editable` (defined in `user/data/types`, or a collection or tree in a folder from code, and not still written as a taxonomy), `overridden` (from code, with a file in `user/data/types` changing it) and `overrides` (the options that file sets), `fieldsEditable`, `routes` (each address: `{"key", "path", "default", "requires", "allows", "root"}`, paths relative to the prefix), `dateArchives`, `filename` and `folders` (its file name and folder patterns, or `null`), `defaultPrefix` (the URL prefix it has without one of its own, its name), `file`, its `index` page (`{"id", "path", "title"}` or `null`), its `byline` (the credit relation it names, or `null`), `credits` (the names of the credit relations from it), and `archivePages` (each relation archive under it: `{"relation", "label", "word", "page"}`, `page` its list page, `{"id", "path", "title"}` or `null`). `GET types` adds `create` (whether types can be created here), `urls` (whether they may set URLs), and `files` (whether content is kept in files, which alone have file name and folder patterns) |
| `POST types` | Create a type in `user/data/types`, kept in `_` and its name: `{"name", "kind"` (`collection` or `tree`), `"set", "index", "listPages", "authors"}`; answers `201` with the type. `set` maps options to values: `labels`, `description`, `icon`, `prefix` (a tree's is the path its pages are served under), `paths` (route keys to paths, `null` for a key's default), `public`, `sitemap`, `llms`, `feed`, `byline` (the [credit relation](content-types.md#crediting-people) its byline uses), `dateArchives`, `filename`, `folders`, `hierarchical`, `order`, and `fields`; `index: true` adds its index page, `listPages` (relation names) adds each relation archive's list page, `_{word}.md` titled with its label (a `422` for a relation without archives under it), and `authors: true` adds the type to the `authors` credit relation, writing it when there's none. Needs `site.settings` |
| `PATCH types/{name}` | Change a `user/data/types` type, or a collection or tree in a folder from code (saved in `user/data/types` over it): `{"set", "index", "listPages"}`, as above (`null` removes an option); answers with the type. A change that doesn't fit is a `422` with the reason |
| `DELETE types/{name}` | Delete a `user/data/types` type's file (its entries stay); answers `{"deleted"}`. A type a relation names is a `422` saying which |
| `GET relations` | The site's [relations](content-types.md#terms-and-relationships): `{"relations": [{"name", "kind", "from", "to", "field", "aliases", "label", "multiple", "ordered", "min", "max", "create", "symmetric", "translations", "control", "inverse", "definition", "origin", "editable", "singular", "entries", "legacy"}], "create"}`. `inverse` is `false` or `{"label", "page", "archive", "types", "max"}` (`archive` a word or `false`); `definition` is the relation as a data file writes it; `origin` is `extension`, `config`, or `data`; `editable` is whether it's in `user/data/relations` (and not a taxonomy waiting to be migrated); `create` is whether relations can be created here; `singular` is what one target is called, `entries` how many entries have a value in it, and `legacy` whether it's still written as a taxonomy |
| `POST relations` | Create a relation in `user/data/relations/{name}.json`: its whole definition, with `name` (the keys a data file takes); answers `201` with it, as `GET relations` describes it. A definition that doesn't fit, or a name defined in code, is a `422` with the reason. Needs `site.settings` |
| `PATCH relations/{name}` | Replace a `user/data/relations` relation with the whole definition sent; answers with it. Needs `site.settings` |
| `POST relations/{name}/check` | What replacing a relation with the definition sent would do, without saving it: `{"refusal", "refused", "inWay", "inWayCount", "warnings", "uses", "moved", "unfiled", "stripped", "over", "overCount", "fewer", "inverseOver"}`. `refused` is `type` (it points at another type while entries have values) or `one` (it takes one where entries have several), or `null`; `inWay` and `over` name up to 8 entries (`{"id", "type", "title", "count"}`, most values first) in the way of a refusal and over a new `max`; `fewer` counts published entries under a new `min`, and `inverseOver` targets over a new inverse `max`. Needs `site.settings` |
| `DELETE relations/{name}` | Delete a `user/data/relations` relation's file (entries keep what they wrote); answers `{"deleted"}`. Needs `site.settings` |
| `POST types/{name}/reset` | Put a type from code back as the code defines it, removing its file in `user/data/types`; answers with the type |
| `POST types/refresh` | After a change: compile the routes again (on a compiled site) and reindex, so the site uses the change; answers `{"routes", "indexed"}` |
| `GET fields/types` | The field types definitions can use, built in and from plugins: `{"types": [{"type", "label", "description", "controls", "options"}], "controls"}`. `controls` are `{"value", "label"}`, a type's first being its default; `options` are the type's own definition keys as JSON Schemas |
| `GET fields/sets` | The field sets: `{"sets": [{"name", "label", "description", "kind", "slot", "origin", "editable", "file", "targets", "fields"}], "create", "targets"}`. `origin` is `extension` (from a plugin), `config`, or `data`; each set's `targets` are `{"key", "label", "found"}` (`found` is whether the site has it); `fields` is how many; the top-level `targets` are every place a set can be added to (`{"key", "label", "group", "kind"}`: content types, kinds of media file, and Settings screens), and `kinds` each kind with its `slots` (`{"name", "label", "description"}`, the first its default); `create` is whether sets can be created here |
| `GET fields/sets/{name}` | One set, with its `fields` as definitions, `options`, every place it can be added to, and `kinds` |
| `POST fields/sets` | Create a set in `user/data/fields`: `{"name", "set"}`, where `set` maps `label`, `description`, `targets` (all one kind), `slot`, and `fields` to values; answers `201` with the set. Needs `site.settings` |
| `PATCH fields/sets/{name}` | Change a `user/data/fields` set: `{"set"}`, as above (`null` removes a key); answers with the set. A field a target already has is a `422` with the reason |
| `DELETE fields/sets/{name}` | Delete a `user/data/fields` set's file; answers `{"deleted"}`. Then `POST types/refresh` reindexes |
| `GET references/{type}` | What a reference field to `type` can point at, for the editor's picker (see below) |
| `GET entries` | The entries the account may edit, a page at a time (see below) |
| `GET health/site` | Site Health's last check, checking first when there's none, and `POST health/site` checks again; with `site.health`: `{"checked", "areas", "checks", "requirements", "site", "server"}`. Each check is `{"area", "key", "status", "label", "message", "hint", "link"}` (`status` is `pass`, `warning`, or `failure`; `link` names what the admin opens, or `null`), each requirement `{"group", "name", "why", "needs", "installed", "status"}` (`status` may also be `optional`), and each fact `{"label", "value", "mono"}` |
| `GET health` | Content and media problems by file, as Site Health last checked them (checking first when it never has), and `POST health` checks again; with `site.health` like every `health` route. It answers `version` (the report's shape), `at` (when), each file with its `area` (`content` or `media`) and its problems, each `{"field", "message", "severity", "kind"}`, notices included, with counts; `entries`, each content file's `{"title", "type", "id"}` by path; and `ids`: `{"missing", "duplicates"}`, the files missing a valid id and each id files share (`{"id", "paths"}`). A problem another check reports (an id, a term with no file, an entry in a folder, an image's sizes) isn't among the files' problems. It also answers `ignored`: the problems ignored for the site, by key, each `{"by", "name", "at"}` (`by` the id of the account that ignored it); `POST health/ignore` and `POST health/unignore` take `{"key"}` and answer `ignored`. Each fix below that takes `paths` (a list) changes only those files; without it, every file the check found |
| `POST health/terms` | Write a published file for each term and profile entries name with no file, of the types the account may create and publish: `{"created", "failed"}`, the new paths and why any couldn't be written, each by `{type}/{slug}`. Send `{"terms": ["{type}/{slug}", …]}` to write only those. `GET health`'s `terms` (`{"count", "items"}`, each `{"type", "label", "slug", "title", "entries"}`) says what's missing |
| `POST health/type-folders` | Move each `user/data/types` type that still names its folder into `_` and its name, as `content:type-folders --write` does: `{"migrated", "failed"}`, how many files and folders moved for each type and why any couldn't be. `GET health`'s `typeFolders` lists the types left, each `{"name", "from", "to"}`. Needs `site.health` and `site.settings` |
| `POST health/taxonomies` | Migrate each `user/data/types` type still written as a taxonomy to a collection and its classify relation, as `content:taxonomies --write` does: `{"migrated", "failed"}`, the files written for each type and why any couldn't be. `GET health`'s `taxonomies` names the types left. Needs `site.health` and `site.settings` |
| `POST health/ids` | Give each file missing a valid id, that the account may edit, a new one (or only `{"paths"}`): `{"assigned", "failed"}`, the new ids by path and why any file couldn't be changed |
| `POST health/ids/keep` | Keep a shared id on `{"path"}` and give the other files sharing it (that the account may edit) new ones; answers as above, or a `422` when the file doesn't share its id |
| `POST previews` | A preview link to an entry the account may edit, from `{"entry": id}`: `{"url", "expires"}` |
| `GET entries/{id}` | An entry for editing (see below) |
| `POST entries` | Create an entry: `{"type", "title"}`, and optionally `"slug"`, `"set"`, `"body"`, `"status"` |
| `PATCH entries/{id}` | Change an entry: `{"revision"}` plus any of `"set"`, `"remove"`, `"body"`, `"status"`, `"published"`, `"slug"` |
| `DELETE entries/{id}?revision=…` | Move an entry to the trash: `{"trashed": id, "restore": {"status", "revision"}}`, where `restore` is what an Undo sends to `POST entries/{id}/restore` (`status` is what its file named, or `null` for none). A `422` for one that's there already |
| `DELETE entries/{id}?permanently=1` | Delete an entry that's in the trash for good: `{"deleted": id}`. A `422` for one that isn't |
| `POST entries/{id}/restore` | Restore an entry from the trash as a draft: `{"id"}`. For an Undo, send `{"status", "revision"}` as moving it to the trash answered: `"published"` or `null` puts it back published (`null` leaving no `status` in its file) and needs permission to publish; a `428` without the revision, and a `409` if it changed since |
| `POST entries/empty-trash` | Delete everything in the trash the account may delete, for good (`{"type"}` for one type): `{"deleted"}`, how many |
| `POST entries/bulk` | Publish, move to draft, or trash several entries at once (see below) |
| `POST entries/{id}/duplicate` | Copy an entry as a draft (see Duplicate above), with an id of its own; answers `201` with the copy as `GET entries/{id}` shows it. Needs to create entries of its type and to edit the entry; an index page is refused with a `422` |

Once signed in, send the token from `session` or `login` in an
`X-CSRF-Token` header with every `POST`, `PATCH`, and `DELETE`. Errors are JSON too:
`{"error": "…"}`, with a 400, 401, 403, 404, 413, 422, or 429 status.

### Changing several entries

`POST entries/bulk` takes `action` (`publish`, `draft`, or `trash`) and
`ids` (1 to 100 entry ids). Each entry is changed as it is now, so no
`revision` is needed: a status change or a move to the trash doesn't
overwrite anyone's writing. Each is checked on its own, with the same
rules as a single change, with the entry's type's capabilities: editing
the entry, publishing anything that isn't a draft, deleting to trash it, required
fields filled to publish, and never an index page in the trash. An
entry that's already in the trash is skipped. The
answer is `action`, `done` (the ids changed), and `skipped` (each with
its `id`, `title`, and the `reason`); an entry that couldn't be changed
doesn't stop the rest.

### Listing entries

`GET entries` lists the entries the account may edit: an author's own,
or everyone's for an editor. Narrow it with:

| Parameter | What it does |
|---|---|
| `status` | `draft`, `scheduled`, `published`, `any` (the default: every status but the trash), or `trash` (the entries in the trash the account may delete, most recently trashed first, each with its `trashed` time) |
| `type` | A content type's name |
| `search` | Text the title or slug must contain, in any case |
| `author` | An author's slug the entries must credit |
| `terms` | `type:slug` pairs for types of terms, comma separated (`topic:art,era:1990s`); an entry needs every one |
| `days` | Entries updated in the last so many days, from 1 |
| `account` | For profiles: `linked` (an account is linked to them) or `guest` (none is) |
| `over` | A relation's name, with `above` (0 by default): only the entries with more values in it than that. Needs `type`; answered as `over`, `{"label", "above"}` |
| `sort` | `title`, `status`, `author`, `published`, or `updated` |
| `dir` | `asc` or `desc`: by default the dates sort newest first and the rest A to Z |
| `page` | The page, from 1 |
| `per` | Entries per page: 20 by default, at most 100 |

Unless `sort` says otherwise, a type's whole list (no `status`) comes by
`position` and then title for pages and terms, newest published first
for a collection, and by title for profiles; drafts, and the list of
every type, come most recently changed first, scheduled entries soonest
first, and published entries newest first. `by` says which the list is
in order of (`position`, `published`, `title`, `updated`, or the column
sorted by). Sorting by status uses the status as it is now (a
published entry dated in the future sorts as scheduled), and by author
the first author's slug. The answer has `status`, `type`, `search`,
`author`, `terms`, `days` (or `null`), `sort` and `dir` (or `null`
unsorted), `tree` (whether it's in tree order), `total`, `page`,
`pages`, `per`, and `entries`, each with its `path` (its file), `id`, handle, title, type, status, dates,
`url` (its path on the site, where it is or will be once published, or
`null`), authors, whether it's the account's own, `index`, a profile's
`linked` (whether an account is linked to it) and `account` (`{"username",
"displayName"}`, with `accounts.view`, else `null`),
`can.delete` and `can.duplicate`, and `ancestors`: the titles of the entries above it, from
the top down (a page's parent pages, or the parents of an entry in a
nesting collection; empty for the rest). With a `type` whose entries
nest (pages, or a nesting collection) and no `status`, `search`, `author`, `terms`,
`days`, or `sort`, entries come in tree order instead: each followed by its children, siblings by position and then title,
each with its `depth` (0 at the top) and how many `children` it has. A
page that starts inside a branch begins with the entries above it,
marked `continued: true` and not counted in `total`. Otherwise `depth`
and `children` are `null`, and `continued` is `false`.

With a `type` that isn't pages, the type's index page (its landing page)
is left out of `entries`, `total`, and `pages`, and answered as `index`
on the first page when it matches `status` and `search` and the account may
edit it; otherwise `index` is `null`. A relation archive's list page
(`_authors`, the first the list finds) is answered the same way, as
`archivePage`, and each entry says whether it's one (`archivePage`) and
for which relation (`archiveLabel`). Pages written for one target's
archive are left out. A page past the last has no entries.

### Listing directives

`GET directives` lists the directives the editor's inserter offers as
blocks: registered directives with a class. Each has:

| Key | What it is |
|---|---|
| `name` | Its full name, as written in Markdown: `blush/callout`, `acme/tabs` |
| `label`, `description` | From the translation catalogs, or a label made from the name |
| `content` | What it wraps: `none`, `text` (its `[label]`), or `blocks` |
| `kind` | How it's written: `container` (`:::`), `leaf` (`::`), or `inline` (`:`) |
| `category` | A built-in directive's group (`text`, `media`, `layout`, `navigation`, `data`), else `null` |
| `source` | Where the rest come from: `{"kind": "theme", "site", "icon-pack", or "plugin", "label"}`, else `null` |
| `props` | Its props as schema fields, each with its `label` and, for a choice, `choices` labels by value |
| `variants` | Its variants under the active theme, not including Default: `{"name", "label", "description", "source"}`, where `source` is `null` when the directive's own namespace declared it, else like the directive's |

Beside `directives`, `image` has the `variants` the active theme offers
Markdown images (its `theme.json` `variants.image`): each a class, with
its `name`, `label`, `description`, and `source` (`null`).

### Listing references

`GET references/{type}` answers what a reference field to `type` (a
type of terms, the authors, or any other type) can point at, for
anyone who can edit content, including entries they can't edit
themselves. Each item has the `slug` a reference stores, `title`,
`status`, its `parent`'s slug (or `null`), `uses` (how many published
entries use a term; `null` for other types), `depth` (its depth in a
tree, else `null`), and `missing` (a slug you asked for that nothing
answers to). A slug entries use with no file isn't a term, so it isn't
listed. A type's index
page isn't included.

A nesting collection answers every entry, in tree order (each
followed by its children, siblings by title), with `tree: true`. Any
other type answers the items whose title or slug contains `search`,
those whose title starts with it first, then those with a word starting
with it, then the rest, by title within each, at most `limit` (20 by
default, up to 100), with the `total` found. `slugs=a,b` adds those
slugs to the answer, found or not, so a field can name what it holds: a
trashed one has `status: trash`, and a missing one has `closest` (the
`slug` and `title` it most likely meant, or `null`). `create` is `true`
for a type of terms whose classify relation says `create`.

The editor's pickers also use:

- `upto=50`: with no `search` and that many candidates or fewer, every
  one is answered (`whole: true`, in tree order for a nesting
  collection); with more, none are, and `total` says how many there
  are. Items of a nesting type have a `path` (`Mains › Pasta`).
- `suggest`: what to offer before anything's typed, as `suggested`, at
  most `suggestions` (5 by default, up to 20): `uses` (the most used
  terms), `edited` (the most recently changed), or `recent` (the
  targets most recently linked through the relation named by `from`,
  such as `from=recipe.cooks`).
- `except=slug` leaves out an entry, and with `branch=1` the entries
  under it, answering how many of those it left out as `excluded`.

`for=post` (a content type's name) narrows a type of terms to the terms
that type's entries use, in any status, counting only the entries the
account may edit; a nesting collection keeps the parents of each, so the tree
holds together. The entry list's filters use it.

### Listing media

`GET media` lists the library (`user/media`), newest first, a page at a
time, for accounts that can edit content. Narrow it with `search` (text
the path must contain), `kind` (`image`, `video`, `audio`, `document`,
`file` for anything else, or `any`),
`page`, and `per` (48 by default, at most 100). The answer has `total`,
`page`, `pages`, `per`, and `files`; each file has its `reference`
(what to write in content: the library's URL path), `name`,
`folder`, `url`, `mime`, `kind`, `size`, `width` and `height` (images),
`modified`, and the library's `alt` and `caption` for it (`""` for
none). Only the file types your site allows are listed. When
the account may upload some kind (`media.{kind}.upload`), `upload` has the largest file
it may upload (`limit`, in bytes, or `null` for none: the upload rules'
largest, within PHP's) and the `extensions` that may be uploaded;
otherwise it's `null`.

`POST media` uploads one file, sent as the multipart field `file`, and
needs the capability to upload its kind (`media.{kind}.upload`, a 403
otherwise). Its uploader is recorded in its details (`owner`). It goes in the folder the upload rules give its
kind (`user/media/{year}/{month}/` by default; see
[`MediaConfig`'s `uploads`](configuration.md#media)) with a
name safe for a URL (dots before its extension become hyphens, so
`shell.php.jpg` is saved as `shell-php.jpg`), and `-2`, `-3`, and so on
when the name is taken. Its extension must be one the library lists, and
its contents must be of a type your site allows
([`MediaConfig`](configuration.md)). SVG, HTML, XML, JavaScript, PHP, and
Office files with macros are never taken, whatever your site allows. The
answer is a 201 with the file, as `GET media` describes one; a file too
large (for PHP or its kind) is a 413, one of the wrong type or a kind
turned off a 422, and any upload while uploads are off a 403.

`GET media` lists from the media index (see
[The media index](media.md#the-media-index)): `search` matches a file's
path or details, `missing=alt` narrows it to images without alt
text, and `mine=1` to the account's own uploads. It's for accounts that
can edit entries, or have a media capability.

Every file the media API answers has its `title`, `alt`, and
`caption` (`''` for none), its uploader's account id (`owner`, `''` for
none), an audio file's or video's `duration` in
seconds (`null` when unknown), and its `artworkUrl`, for a thumbnail
(its artwork image's URL, else where the cover art it carries is
served, else `null`). `GET media/{path}` adds what the file says about itself, `embedded`:
its `values` (read from its EXIF, IPTC, and XMP: `title`,
`description`, `creator`, `copyright`, `credit`, `keywords`, `created`,
`camera`, `lens`, `focalLength`, `aperture`, `exposure`, `iso`,
`orientation`, `software`, and for audio and video `album`, `track`,
`genre`, `duration` (seconds), `artwork`, `width`, and `height`,
whichever it has) and `location`, whether
it carries one (never where). It also adds the file's details: the `fields` its kind has
(described as a content type's are), the field `sets` on its kind
(`{"name", "label", "description", "fields"}`, the field names), their `values`, keys its metadata
file keeps that aren't fields (`extra`), `violations` for what
doesn't fit, its `uploader` (`{"username", "name"}`, or `null`), what
the account `may` do to it (`{"edit", "delete", "addArtwork"}`), and the entries
that use it (`usedIn`: `{"path", "title", "type"}`, `path` being the
document's path). A sound or video has its `artwork` (`{"id",
"image"}`, the image being `{"path", "reference", "url", "name",
"title"}`, or `null` when the id names no image in the library; `null`
for none), and an image the files that show it (`artworkFor`:
`{"path", "name", "title", "kind"}`). `PATCH media/{path}` changes them: `{"set": {field:
value}, "remove": [field]}`, for a file in the library, needs
`media.edit` (and `media.edit.others` for a file that isn't the
account's, or has no uploader), and answers with the file. An empty value removes a
field, each value is checked by its field (a 422 naming the `field`
when it doesn't fit), and `alt` and `caption` may still be sent on
their own.

`PUT media-artwork/{path}` sets a sound's or video's artwork: `{"image":
"{id}"}` names a library image, and `{"from": "file"}` adds the cover
art it carries to the library first (or finds the image with the same
bytes), which also needs uploading images (`may.addArtwork`).
`DELETE media-artwork/{path}` takes it off, leaving the image. Both
need what changing the file's details does, and answer with the file;
`GET media-artwork/{path}` answers with the cover art the file carries.

`DELETE media/{path}` deletes the file, its details, and the copy
`media:publish --copy` made, and answers `{"deleted"}` with its path.
It needs `media.delete`, and `media.delete.others` for a file that
isn't the account's or has no uploader. Deleting an image takes it off
the files that show it as their artwork. It doesn't check where the file
is used; `GET media/{path}`'s `usedIn` is for asking first.

### Redirects

With `site.redirects` (a 403 otherwise), a redirect is named by its old
path, `from`:

- `GET redirects` lists them, sorted by `from`, a page at a time:
  `tab` (`all`, `permanent`, `temporary`, or `problems`), `search`,
  `goes` (`here` or `away`), `page`, and `per` (20 by default; 10, 50,
  or 100). The answer has `redirects`, `total`, `page`, `pages`, `per`,
  `counts` (for each tab), `code` (the redirects from code, which come
  first, as `{from, to, status}`), and `trace`: when the search is an
  address, its `path` and `hops`, each step a visitor would take
  (`page`, `redirect`, `away`, `gone`, `missing`, or `loop`), or
  `other`, the host of an address on another site. Each redirect has
  `from`, `to` (or `null`), `entry` (`{id, title, url, type, state}`,
  `state` being `live`, `draft`, `scheduled`, `trash`, `hidden`, or
  `deleted`), `status`, `added`, `by` (the account that added it: `{name, username, you}`, `name` and `username` `null` for a removed account), `via`,
  `problem` (`{kind, label, message, final}`, or `null`), and `stored`,
  the row as the file keeps it.
- `POST redirects/check` takes `{from, to, entry, status, was}` and
  answers what the form says about each field (`from`, `to`, and
  `status`, lists of `{kind, parts, fix}`, `kind` being `bad`, `warn`,
  or `say`) and the `row` it would save, or `null` when something stops
  it.
- `POST redirects` adds one, or changes the one from `was`, with the
  same values; a 422 has the messages. A path that's a live entry's
  address is saved as the entry. The answer has the `redirect` and, for
  a change, `was`, the row as it was kept.
- `POST redirects/delete` takes `{from: [...]}` and answers `deleted`,
  the rows as they were kept.
- `POST redirects/status` takes `{from: [...], status}` and answers
  `changed`, the rows that changed, as they were kept.
- `POST redirects/restore` takes `{rows: [...], remove: [...]}`: it
  removes the redirects from `remove`, then writes `rows` as given.
  The admin's Undo uses it.

### Editing entries

The admin's API names an entry by its `id`, the UUID in its front
matter (see [Ids](content.md#ids)), never by its file. A file without
an id can't be edited until it has one (Site Health adds it); lists
show it with an `id` of `null`. Each answer also has the entry's `path`,
its file under `user/content`, such as `_post/2026-09-29.hello.md`,
for showing. Its `handle` is its type and key, such as `post/hello`
(a landing page's key is `index`), which says where it lives but isn't
used to find it. An entry has no handle (`null`) when it isn't in
the site's language or another file claims the same key. `GET
entries/{id}` answers with:

- `values`: front matter by field name, read from whichever name the file
  uses (a 1.x `date` is `published`), and `extra`: anything the type
  doesn't declare (but the `id`, which is answered on its own).
- `body`, and `revision`: send the revision back with a change. If the
  file changed meanwhile, the change is refused with a 409, so no one's
  edit is lost. `modified` is when the file was last written (ISO 8601),
  or `null` if that isn't known.
- `type`: the type's name, kind, whether it's dated, and a description
  of each field (name, type, label, and options).
- `slug`: the last part of its key.
- `can`: whether the account may edit, publish, rename, delete, and
  duplicate it (`rename` and `duplicate` are `false` for a landing page;
  `duplicate` needs to create entries of its type).
- `index`: whether it's its type's index page. An index page's `type`
  describes only its `title` and `status` fields (the rest of its front
  matter is in `extra`), and `can.delete` is `false`.
- `archivePage`: whether it's a relation archive's list page, which is
  described the same way, with `can.rename` and `can.duplicate`
  `false`.
- `archive`: the archive page it is, or `null`: `{"relation", "label",
  "target", "targetTitle", "targetId", "targetType"}`, where `target` is
  the target's slug for a page written for one target's archive
  (described the same way, with `can.delete` also `false`) and `null`
  for a list page.
- `violations`: the file's problems, as Site Health shows them.

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
`"redirect": true`, a published entry's old address
[redirects](content.md#redirects) to its new one. The answer is the entry as `GET` would show it, with
its new id and revision.

New entries are drafts unless `status` says otherwise, credit the
account's author, and get today's date as their publish date.

What an account may do follows its [capabilities](accounts.md#capabilities):
each is its type's (`content.post.edit` for a post). Editing needs
`edit` for that entry, anything that leaves an entry published or
scheduled needs `publish`, and taking your own author off an entry
needs `edit.others`. So a contributor
can create and change drafts, but never publish them.
