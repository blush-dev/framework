# The admin

The admin is where people with an [account](accounts.md) run the site
from a browser. So far it has a dashboard (your content at a glance, and
buttons to publish, reindex, and clear caches), a list of each content
type's entries, an editor, and a content health check.

> **The admin is early.** The editor edits Markdown as plain text for
> now; a live preview, inserting components, and a media library come
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

The sidebar lists the screens your account can use, with your username
(a link to **Your profile**) and a sign-out button at the bottom. The button at the top left
collapses the sidebar to icons (your browser remembers the choice); on
a narrow screen it opens the sidebar as a menu instead. **View site**
opens your site in a new tab.

## Your profile

**Your profile** shows your account (username, roles, linked author,
and when you last signed in) and your **color scheme**: light, dark, or
your device's setting (the default). The choice is saved with your
account, so it follows you to every device you sign in on, and it only
changes what you see: someone else on the same site keeps their own.

## The dashboard

The dashboard shows how many entries you have, by status, and the actions
your account may run:

| Action | What it does | Who can run it |
|---|---|---|
| Publish | Put content changes live, like `bin/blush publish` | Anyone with `site.publish` (editors) |
| Reindex content | Bring the content index up to date with your files | Anyone with `site.publish` |
| Clear caches | Empty the page, body, and fragment caches | Anyone with `cache.clear` (editors) |

Actions you can't run don't appear. Extensions can add their own actions
(see [Extending Blush](extending.md#admin-actions)).

## Entries

The sidebar lists your content types by name: **Content** has your
collections (such as Posts) and Pages, and **Taxonomies** has the types
that group them (such as Categories and Authors). Each opens a list of
that type's entries you can edit, newest changes first. Types are named
from their `label` and `singular` settings (see
[Content types](content-types.md#names-in-the-admin)).

The tabs above a list show all of them, or only published entries,
drafts, or scheduled ones, with a count on each. You see your own
entries if you're an author or contributor, and everyone's if you're an
editor; entries credited to your account's author are marked "Yours".
Drafts come most recently changed first, and scheduled entries in the
order they'll go live. Search matches titles and file paths. Click a
title to edit the entry.

A taxonomy's list (such as Categories) holds its **terms**. Instead of
authors, it shows how many published entries use each term.

**All entries**, at the end of the Content group, lists every type
together, with a menu to narrow it to one. Its Drafts and Scheduled tabs
are what the dashboard's counts link to.

**New post** (named for the type you're looking at) asks for a type and
a title, creates the entry as a draft, and opens it in the editor. It's
credited to your account's author.

## Editing an entry

The editor has the title and the body (Markdown) on the left, and on the
right:

- **Publishing:** the publish date, a preview link (or **View** once it's
  live), and **Move to trash**.
- **Fields:** the content type's other fields, such as the subtitle,
  summary, and categories. Fields that take several values say how to
  separate them. A few kinds (such as `collection`) can't be edited here
  yet and show their value read-only.
- **Other front matter:** keys the content type doesn't declare. They're
  kept as they are.
- **Problems:** what content health finds in the file, as last saved.
  Notices are hidden unless you ask for them.

The buttons at the top depend on the entry:

| The entry is... | You can |
|---|---|
| A draft | **Save draft**, or **Publish** (or **Schedule**, when the publish date is in the future) |
| Scheduled | **Update**, **Publish** once its date is past, or **Switch to draft** |
| Published | **Update** (or **Schedule**, with a future date), or **Switch to draft** |

Ctrl+S (⌘S on a Mac) saves without changing the status. Saving changes
only what you changed: every other line of the file stays exactly as it
was. If someone else saved the entry after you opened it, your save is
refused, so neither change is lost silently; load their version, then
make your change again. Leaving the editor with unsaved changes asks
first.

If you can't publish, you can save drafts but not publish them.

**Move to trash** takes the entry off your site and puts it in the
**Trash** tab of its list.

## Trash

Each list has a **Trash** tab (if your account can delete entries) with
the entries moved there, most recent first. For each one:

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
| `GET types` | The site's content types: `{"types": [{"name", "label", "singular", "kind", "dated"}]}`, by label, taxonomies last |
| `GET entries` | The entries the account may edit, a page at a time (see below) |
| `GET health` | Content problems by file, with counts (`?strict=1` adds notices); needs `content.edit.others` |
| `POST previews` | A preview link to an entry the account may edit, from `{"entry": id}`: `{"url", "expires"}` |
| `GET entries/{id}` | An entry for editing (see below) |
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
`entries`, each with its id, title, type, status, dates, file, authors,
and whether it's the account's own. A page past the last has no
entries.

### Editing entries

An entry's id is its file's path under `user/content`, such as
`_posts/2026-09-29.hello.md`. `GET entries/{id}` answers with:

- `values`: front matter by field name, read from whichever name the file
  uses (a 1.x `date` is `published`), and `extra`: anything the type
  doesn't declare.
- `body`, and `revision`: send the revision back with a change. If the
  file changed meanwhile, the change is refused with a 409, so no one's
  edit is lost.
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
