# References

What a reference field can point at, for the editor's pickers. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `GET references/{type}` | What a reference field to `type` can point at, for the editor's picker (see below) |

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
