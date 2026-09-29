# The admin

The admin is where people with an [account](accounts.md) run the site
from a browser. So far it has a dashboard (your content at a glance, and
buttons to publish, reindex, and clear caches), a list of drafts and
scheduled entries, and a content health check.

> **The admin is early.** Editing content in the browser comes later.

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

## Drafts

**Drafts** lists the drafts and scheduled entries you can edit: your own
if you're an author or contributor, and everyone's if you're an editor.
Drafts come most recently changed first, and scheduled entries in the
order they'll go live. Entries credited to your account's author are
marked "Yours".

## Previewing drafts

Each entry on the Drafts screen has a **Get link** button. It makes a
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
with the admin's path, the API's path, and the site's name.

The API is JSON under `/admin/api`, and uses the session cookie:

| Request | What it does |
|---|---|
| `GET session` | The signed-in account (its username, roles, and capabilities) and a CSRF token, or `{"account": null}` |
| `POST login` | Sign in with `{"username", "password"}` |
| `POST logout` | Sign out |
| `GET dashboard` | The site, entry counts by status, and the actions the account may run |
| `POST actions/{name}` | Run an action; the answer is `{"successful", "message", "details"}` |
| `GET entries` | The entries the account may edit, a page at a time (see below) |
| `GET health` | Content problems by file, with counts (`?strict=1` adds notices); needs `content.edit.others` |
| `POST previews` | A preview link to an entry the account may edit, from `{"entry": id}`: `{"url", "expires"}` |
| `GET entries/{id}` | An entry for editing (see below) |
| `POST entries` | Create an entry: `{"type", "title"}`, and optionally `"slug"`, `"set"`, `"body"`, `"status"` |
| `PATCH entries/{id}` | Change an entry: `{"revision"}` plus any of `"set"`, `"remove"`, `"body"`, `"status"`, `"published"`, `"slug"` |
| `DELETE entries/{id}?revision=…` | Move an entry to `storage/trash/` |

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
