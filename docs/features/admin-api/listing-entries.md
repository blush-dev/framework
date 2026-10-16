# Listing entries

The entries an account may edit, a page at a time, as the entry lists show them. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `GET entries` | The entries the account may edit, a page at a time (see below) |

`GET entries` lists the entries the account may edit: an author's own,
or everyone's for an editor. Narrow it with:

| Parameter | What it does |
|---|---|
| `status` | `draft`, `scheduled`, `published`, a status a plugin adds, `any` (the default: every status but the trash), or `trash` (the entries in the trash the account may delete, most recently trashed first, each with its `trashed` time) |
| `type` | A content type's name |
| `search` | Text the title or slug must contain, in any case |
| `author` | An author's slug the entries must credit |
| `terms` | `type:slug` pairs for types of terms, comma separated (`topic:art,era:1990s`); an entry needs every one |
| `days` | Entries updated in the last so many days, from 1 |
| `account` | For profiles: `linked` (an account is linked to them) or `guest` (none is) |
| `over` | A relation's name, with `above` (0 by default): only the entries with more values in it than that. Needs `type`; answered as `over`, `{"label", "above"}` |
| `sort` | `title`, `status`, `author`, `date`, or `updated` |
| `dir` | `asc` or `desc`: by default the dates sort newest first and the rest A to Z |
| `page` | The page, from 1 |
| `per` | Entries per page: 20 by default, at most 100 |

Unless `sort` says otherwise, a type's whole list (no `status`) comes by
`position` and then title for pages and terms, newest first by date
for a collection, and by title for profiles; drafts, and the list of
every type, come most recently changed first, scheduled entries soonest
first, and published entries newest first. `by` says which the list is
in order of (`position`, `date`, `title`, `updated`, or the column
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
