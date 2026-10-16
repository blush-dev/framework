# Editing entries

Reading an entry to edit it, creating and changing entries, the trash, duplicates, preview links, and changing several entries at once. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `POST previews` | A preview link to an entry the account may edit, from `{"entry": id}`: `{"url", "expires"}` |
| `GET entries/{id}` | An entry for editing (see below) |
| `POST entries` | Create an entry: `{"type", "title"}`, and optionally `"slug"`, `"set"`, `"body"`, `"status"` |
| `PATCH entries/{id}` | Change an entry: `{"revision"}` plus any of `"set"`, `"remove"`, `"body"`, `"status"`, `"date"`, `"slug"` |
| `DELETE entries/{id}?revision=…` | Move an entry to the trash: `{"trashed": id, "restore": {"status", "revision"}}`, where `restore` is what an Undo sends to `POST entries/{id}/restore` (`status` is what its file named, or `null` for none). A `422` for one that's there already |
| `DELETE entries/{id}?permanently=1` | Delete an entry that's in the trash for good: `{"deleted": id}`. A `422` for one that isn't |
| `POST entries/{id}/restore` | Restore an entry from the trash at its workflow's first status (a draft, in the standard workflow): `{"id"}`. For an Undo, send `{"status", "revision"}` as moving it to the trash answered: a status's name puts it back with that status, and `null` puts it back published, leaving no `status` in its file; a live status (`"published"`) or `null` needs permission to publish; a `428` without the revision, and a `409` if it changed since |
| `POST entries/empty-trash` | Delete everything in the trash the account may delete, for good (`{"type"}` for one type): `{"deleted"}`, how many |
| `POST entries/bulk` | Publish, move to draft, or trash several entries at once (see below) |
| `POST entries/{id}/duplicate` | Copy an entry as a draft (as **Duplicate** does in the admin's editor), with an id of its own; answers `201` with the copy as `GET entries/{id}` shows it. Needs to create entries of its type and to edit the entry; an index page is refused with a `422` |

The admin's API names an entry by its `id`, the UUID in its front
matter (see [Ids](../../concepts/ids-and-links.md)), never by its file. A file without
an id can't be edited until it has one (Site Health adds it); lists
show it with an `id` of `null`. Each answer also has the entry's `path`,
its file under `user/content`, such as `_post/2026-09-29.hello.md`,
for showing. Its `handle` is its type and key, such as `post/hello`
(a landing page's key is `index`), which says where it lives but isn't
used to find it. An entry has no handle (`null`) when it isn't in
the site's language or another file claims the same key. `GET
entries/{id}` answers with:

- `values`: front matter by field name, read from whichever name the file
  uses, and `extra`: anything the type
  doesn't declare (but the `id`, which is answered on its own).
- `body`, and `revision`: send the revision back with a change. If the
  file changed meanwhile, the change is refused with a 409, so no one's
  edit is lost. `modified` is when the file was last written (ISO 8601),
  or `null` if that isn't known.
- `type`: the type's name, kind, whether it's dated, a description
  of each field (name, type, label, and options), and its `sections`
  (each `{"label", "description", "fields"}`, the field names under
  it).
- `slug`: the last part of its key.
- `language`: the code of the language it's written in.
- `can`: whether the account may edit, publish, rename, delete, and
  duplicate it, and move it to another `language` (`rename`,
  `language`, and `duplicate` are `false` for a landing page;
  `language` is `false` on a site with one language; `duplicate` needs
  to create entries of its type).
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
- `violations`: the file's problems, as Site Health shows them
  (`{"field", "message", "severity", "kind"}`, `kind` being what kind
  of problem it is, such as `value` for a value that doesn't fit its
  field, or `null`).

A change only touches what it names; the rest of the file stays as it
was written. `set` takes field names to values, `remove` a list of
field names, and `body` the whole body. `status` is a shortcut, and a
change of status is a step of the type's workflow, refused (`422`, with
`field: status`) when there's none from where the entry is, and (`403`)
when the account may not take it:

| `status` | What happens |
|---|---|
| `published` | Removes `status`, and dates the entry now if it has no date or a future one, or at `"date"` when that's given |
| Another of the workflow's (`draft`) | Sets it (`status: draft`); a live one dates the entry as `published` does |
| `scheduled` | Moves it into a live status (its own, when it's live) and sets `date` to the given `"date"`, which must be in the future |

An entry is described with its stored status (`stored`: `published`,
never `scheduled`), its type's `workflow`, and the `transitions` the
account may take from it (`{"name", "label", "from", "to",
"capability"}`, none in the trash or without edit access), in the
workflow's order. A new entry starts at the workflow's first status
when `POST entries` names none.

An index page can't be `scheduled` (a 400), publishing it doesn't date
it, and deleting it is refused with a 422.

`slug` renames the entry: when its front matter has a `slug`, that's
changed; otherwise its file is renamed first (a dated file keeps its
date, and an entry in its own folder moves the folder), so a refused name
changes nothing. A slug that isn't one, that another entry of the type
has, or that's a landing page's is a `422` with `"field": "slug"`. With
`"redirect": true`, a published entry's old address
[redirects](../../concepts/redirects.md) to its new one. The answer is the entry as `GET` would show it, with
its new id and revision.

`language` moves the entry to another of the site's languages, by code,
renaming its file to that language's (see
[Changing the language](../../admin/README.md)). A language the site
doesn't have, one a translation of the entry is already in, or one
where another entry has its address is a `422` with
`"field": "language"`, and nothing is saved.

Every change sets `updated` to now, in the site's time zone, and so does
each entry a bulk **Publish** or **Switch to draft** changes.

New entries are drafts unless `status` says otherwise, credit the
account's author, and get today's date. A new
entry can name its `language` too.

What an account may do follows its [capabilities](../../concepts/accounts/capabilities.md):
each is its type's (`content.post.edit` for a post). Editing needs
`edit` for that entry, anything that leaves an entry published or
scheduled needs `publish`, and taking your own author off an entry
needs `edit.others`. So a contributor
can create and change drafts, but never publish them.

## Changing several entries

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
