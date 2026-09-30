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
beside it lists the section you're in. Choosing **Content** or
**Config** changes the panel without leaving the screen you're on;
**Home** goes to the dashboard. You only see what your account can use.

**Search or jump to…** in the top bar (or ⌘K, Ctrl+K on Windows and
Linux) opens the command palette: type to find a screen, a command such
as **New post**, or an entry by its title, then press Enter. In the
editor, the editor's own commands come first, such as **Focus mode**
and **Insert media**.

The button at the top left hides the panel, leaving just the rail (your
browser remembers the choice); on a narrow screen it opens the rail and
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

## The dashboard

The dashboard shows how many entries you have, by status, and the actions
your account may run. On a site with no content yet, it shows the steps
to write the first page and the first entry of each other type instead
of the counts. The actions:

| Action | What it does | Who can run it |
|---|---|---|
| Publish | Put content changes live, like `bin/blush publish` | Anyone with `site.publish` (editors) |
| Reindex content | Bring the content index up to date with your files | Anyone with `site.publish` |
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
first. Types are named from their `label` and `singular` settings, and
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
paths. Click a title to edit the entry.

The **⋯** button at the end of each row has **Edit**, then **View** and
**Copy link** once the entry is live (**View archive** for a term), and
**Move to trash** if your account can delete it.

A collection's or taxonomy's **index page** (the `index.md` in its
folder, which introduces its archive) is pinned at the top of its list
with a pin and an **Index** tag, on the list's first page. It isn't
counted in the list's totals, and it can't be moved to the trash from
the list. It still follows the tabs and search: it shows only when it
matches them. Pages have no index page; the site's home page is listed
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
body), then the body in Markdown. While you type, the header and footer
fade back; moving the pointer brings them back.

The body stays Markdown, set in Fira Code (the admin's monospace font
throughout), but it reads as it will look: *emphasis* is italic, **strong text** is bold, headings are bold,
quotes are muted, and the marks themselves (`*`, `**`, `#`, `>`) are
dimmed. List markers, links, inline code, and components are colored.

Each entry's editor has its own address, from its content type and slug:
`/admin/content/post/hello-world` edits the post `hello-world`, and a
page's address has its folders, such as `/admin/content/page/about/team`.
An entry that can't be found that way (another language's, or one of two
files claiming the same slug) is edited at its file's path instead, such
as `/admin/entries/_posts/2026-09-29.hello.md`, which works for every
entry.

The header's left side has a link back to the type's list, then three
ways to put something in: **+** for a component, the picture for media,
and the shapes for an icon. Its right side says whether your changes are
saved and the entry's status, then has the settings button, a **⋯** menu
(**Save draft** or **Switch to draft**, **View**, **Focus mode**, and
**Move to trash**), and the main button, which depends on the entry:

| The entry is... | You can |
|---|---|
| A draft | **Save draft**, or **Publish** (or **Schedule**, when the publish date is in the future) |
| Scheduled | **Update**, **Publish** once its date is past, or **Switch to draft** |
| Published | **Update** (or **Schedule**, with a future date), or **Switch to draft** |

The settings (⌘/ or Ctrl+/) open beside the text and push it aside.
They have two tabs:

- **Document:** the publish date, a preview link (or **View** once it's
  live); the content type's other fields, such as the subtitle, summary,
  and categories (fields that take several values say how to separate
  them, and a few kinds, such as `collection`, show their value
  read-only); the components the body uses (choose one to go to it);
  front matter the type doesn't declare, kept as it is; and what content
  health finds in the file, as last saved (notices only if you ask).
- **Component:** the options of the component the cursor is in. See
  [Component options](#component-options).

The body is plain Markdown, shown with headings, code, links, and
[components](components.md) picked out; the component the cursor is in
is highlighted. It's an ordinary text field, so undo, spelling, and your
browser's shortcuts work as usual. The footer counts the words and the
reading time.

**Focus mode** (⌘⇧F or Ctrl+Shift+F, or the **⋯** menu) hides
everything but the text and the editor's own header. Press Escape to
leave it.

Ctrl+S (⌘S on a Mac) saves without changing the status. Saving changes
only what you changed: every other line of the file stays exactly as it
was. Leaving the editor with unsaved changes asks first.

If you can't publish, you can save drafts but not publish them.

Fields the content type marks as required must be filled in to publish,
schedule, or update a live entry. Anything missing is named at the top
and marked under the field; a draft saves without them.

### Inserting components

**+** opens the components beside the text, on the left: search by name
or what it does, or pick a category, then choose a tile (or use the arrow
keys and Enter). The panel stays open, so you can add several; close it
with its **×** or Escape.

Typing `/` at the start of an empty line opens the same panel: keep
typing to narrow it (`/call` for a callout), then press Enter or Tab.
Press Escape to keep the `/` as text.

The component is written into the Markdown by its full name, such as
`:::blush/callout` … `:::`, with the cursor where your text goes. An
inline component (a keyboard key, an abbreviation, an icon) goes at the
cursor, inside the sentence; the rest go on lines of their own. Select
some text first to make it the component's text. Options the component
needs are written empty for you to fill in, such as
`::blush/figure[]{src=""}`. Undo takes an insertion back.

The list has every registered component with a class that your active
theme can draw: the built-in ones, your theme's, your site's, and your
extensions' (marked with where they come from). See
[Registering a component](components.md#registering-a-component) to
add yours.

### Inserting media and icons

The picture button opens your media: the files beside the entry (when
it's a [page bundle](content.md), such as `trip/index.md`), then the
library in `user/media`, newest first. Search by file name, or show only
images, video, or audio. Choose a file, then **Insert** (or double-click
it). An image goes in as a figure, a video as a video, a sound as audio,
and anything else as a download, and its options open in the settings.
Uploading comes later: put files in `user/media` or beside the entry for
now.

The same picker is **Choose** beside every media field and component
option, such as a figure's **Image**.

The shapes button opens your theme's [icons](components.md#icons): search by
name or what it shows (`home` finds the house), then choose one. It goes
in at the cursor as `:blush/icon[]{name=house}`; select some text first
to give it a label for screen readers.

### Component options

With the cursor in a component, the footer names it ("Callout options");
choose that, or the **Component** tab in the settings, to see its
options as a form. Changing one rewrites just that option in the
Markdown: the rest of what you wrote stays as it is. Setting an option
back to its default removes it, so the default applies. A component
that takes a line of text has it here as **Text**. Attributes that
aren't options of the component, such as a class, are listed but edited
in the text.

**Remove component** takes the component out; the text inside a
container, or an inline component's text, stays. Undo in the text puts
it back.

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

**Content → Media** shows the files in `user/media`, newest first:
search by name, or show only images, video, or audio. Choose one for a
preview, its details, and what to write to use it, with **Copy**
buttons. Uploading comes later: put files in `user/media`, or beside an
entry in its own folder.

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
- **Delete permanently** removes it for good.

**Empty trash** deletes everything in that tab permanently. Authors and
contributors see and handle their own trashed entries; editors see
everyone's.

On the server, each trashed entry is a folder in `storage/trash/` (a
bundle's media go with it), so you can also restore one by moving its
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
entry. Turn on **Include notices** to also see undeclared keys, 1.x
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
| `PATCH preferences` | Change the account's own preferences, such as `{"colorScheme": "dark"}` (`system`, `light`, or `dark`); answers `{"preferences"}` |
| `GET dashboard` | The site, entry counts by status, and the actions the account may run |
| `POST actions/{name}` | Run an action; the answer is `{"successful", "message", "details"}` |
| `GET icons` | The icons the active theme can show: `{"icons": [{"name", "label", "keywords", "svg"}]}` |
| `GET media` | The media files an entry can use (see below) |
| `GET media/{path}` | One file in the library, by its path under `user/media` |
| `GET components` | The components the editor's inserter offers: `{"components": [{"name", "label", "description", "content", "kind", "category", "source", "props"}]}` (see below) |
| `GET roles` | Every capability and role, with the accounts holding each; needs `accounts.manage` |
| `GET accounts` | Every account's username, roles, author, and created and last sign-in times (Unix); needs `accounts.manage` |
| `GET types` | The site's content types: `{"types": [{"name", "label", "singular", "description", "icon", "kind", "dated", "origin", "folder", "prefix", "fields"}], "authors"}`, by label, taxonomies last; a taxonomy adds `"types"`, the types it groups (empty for every type), and `"hierarchical"`. `fields` is how many the type defines, `icon` is `null` for the kind's, and `authors` names the type accounts' authors belong to (`null` when it's disabled) |
| `GET types/{name}` | One type, with its own `fields`, the `taxonomies` that group it, `public`, `feed`, `sitemap`, and `editable` |
| `GET entries` | The entries the account may edit, a page at a time (see below) |
| `GET health` | Content problems by file, with counts (`?strict=1` adds notices); needs `content.edit.others` |
| `POST previews` | A preview link to an entry the account may edit, from `{"entry": id}`: `{"url", "expires"}` |
| `GET entries/{id}` | An entry for editing (see below) |
| `GET content/{type}/{key}` | The same, found by its handle, such as `content/post/hello` |
| `POST entries` | Create an entry: `{"type", "title"}`, and optionally `"slug"`, `"set"`, `"body"`, `"status"` |
| `PATCH entries/{id}` | Change an entry: `{"revision"}` plus any of `"set"`, `"remove"`, `"body"`, `"status"`, `"published"`, `"slug"` |
| `DELETE entries/{id}?revision=…` | Move an entry to the trash |
| `GET trash` | The trashed entries the account may handle, newest first (`?type=` for one type): `{"trash": [{"id", "entry", "title", "type", "bundle", "trashed", "authors", "own"}]}` |
| `POST trash/restore` | Restore `{"id"}` as a draft: `{"id"}` is the entry's id again; 409 when something else has its place |
| `POST trash/delete` | Delete `{"id"}` permanently |
| `POST trash/empty` | Delete everything the account may handle permanently (`{"type"}` for one type): `{"deleted"}` |

Once signed in, send the token from `session` or `login` in an
`X-CSRF-Token` header with every `POST`, `PATCH`, and `DELETE`. Errors are JSON too:
`{"error": "…"}`, with a 400, 401, 403, 404, or 429 status.

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
`can.delete`, and `ancestors`: the titles of the entries above it, from
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

### Listing media

`GET media` lists the library (`user/media`), newest first, a page at a
time, for accounts that can edit content. Narrow it with `search` (text
the path must contain), `kind` (`image`, `video`, `audio`, or `any`),
`page`, and `per` (48 by default, at most 100). Add `entry` (an id) to
get `beside` too: the media files next to that entry when it's a page
bundle, else `null`. The answer has `total`, `page`, `pages`, `per`,
`files`, and `beside`; each file has its `reference` (what to write in
content: the library's URL path, or a bundle file's name), `name`,
`folder`, `url`, `mime`, `kind`, `size`, `width` and `height` (images),
and `modified`. Only the file types your site allows are listed.

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
- `can`: whether the account may edit, publish, and delete it.
- `violations`: the file's problems, as content health shows them.

A change only touches what it names; the rest of the file stays as it
was written. `set` takes field names to values, `remove` a list of
field names, and `body` the whole body. `status` is a shortcut:

| `status` | What happens |
|---|---|
| `draft` | Sets `status: draft` |
| `published` | Removes `status`, and dates the entry now if it has no date or a future one |
| `scheduled` | Removes `status` and sets `published` to the given `"published"` date, which must be in the future |

`slug` renames the entry: a dated file keeps its date, and a bundle's
folder moves with its media. The answer is the entry as `GET` would
show it, with its new id and revision.

New entries are drafts unless `status` says otherwise, credit the
account's author, and dated types get today's date.

What an account may do follows its [capabilities](accounts.md#capabilities):
editing needs `content.edit` for that entry, anything that leaves an
entry published or scheduled needs `content.publish`, and taking your
own author off an entry needs `content.edit.others`. So a contributor
can create and change drafts, but never publish them.
