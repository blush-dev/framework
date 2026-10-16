# File names and URLs

A type decides how the files it creates are named, which folders a
collection's files are kept in, and the URLs its entries are served at.
For how a file's name becomes its address, see
[Content files](content-files.md).

## Naming new files

Every type names the files it creates (from the admin, `content:new`,
or a copy) by its `filename` pattern. Without one, files are named by
their slug alone (`hello.md`), whether or not the type has date
archives. For dated names, set `filename: "{date}.{slug}"`
(`2026-10-05.hello.md`). With `user/data/types/note.json` like this,
a note is named `2026-10-05-093000.hello.md`:

```json
{ "filename": "{date}-{time}.{slug}" }
```

A pattern ends in `{slug}`, with a `.` before it when anything comes
first. Before it you can use `{date}` (`2026-10-05`), `{time}`
(`093000`), `{year}`, `{month}`, `{day}`, `{hour}`, `{minute}`,
`{second}`, letters, digits, `-`, `_`, and `.`. It can't start with `_`
or `.`. Dates are the entry's `date`; every new entry gets one.

A pattern names files, never folders (a collection's folders have
[their own pattern](#folders-for-many-files)). A page's subpages go in a folder
named for its slug (`about/team.md` under `2026-10-05.about.md`), and a
page kept as a folder (`about/index.md`) keeps its folder's name.

Changing the pattern renames nothing. An entry's address comes from the
part of its file name after the last `.`, so files named by an older
pattern keep working beside new ones. Renaming an entry keeps whatever
comes before its slug. Listings never sort by file name, so mixed
names list in order.

When a type's own pattern has a date in it, a new date renames
the entry's file in the admin: changing the date and saving, or
publishing an entry that had no date yet. The file is named by the
pattern, with its translations named after it, the way
`content:filenames` names it. Trashing, restoring, and switching to
draft and back never change an entry's date, so they never rename it.
No address changes.

### Renaming older files

To rename older files to the pattern, run `bin/blush content:filenames`
to see what would change, then `bin/blush content:filenames --write`
(add `--type=post` for one type), or use **File Names** on Site Health
in the admin. Each entry's file is renamed, with its translations
named after it. Dates are each entry's `date` as its file writes
it (`2013-02-09 00:00:00 -5` names the 9th, wherever your site is), or
its `updated` date, or when the file last changed. It leaves alone
a relation archive's pages (`_authors.md`), entries kept as folders, entries with a
translation kept as one, and entries whose date isn't a real date
(such as a `2007-00-00` placeholder, which `content:lint` warns of).
No address changes.

It renames only types that set `filename` themselves. Renaming replaces
everything before the slug, so check the list first: a same-day counter
(`2026-10-05-2.hello.md`) is dropped, and a file whose date differs
from its date takes the date.

In the admin, choose it under **File names** on the type's screen.

## Collections are flat

A collection's entries are files, never folders of their own:
`_post/hello.md`, not `_post/hello/index.md`. Unless it sets
[folders for many files](#folders-for-many-files), they're directly in
its folder, not in `_post/2024/hello.md`. Only a relation
archive's `_` folders (`_post/_authors`) may sit inside one. `content:lint` reports an entry kept as a folder, or in a
folder the collection doesn't keep files in, as an error. To move
them, run `bin/blush content:folders` to see what would change, then
`bin/blush content:folders --write`, or use **Collection Folders** on
Site Health in the admin. Each moves into its folder under its own name
(`_post/hello/index.md` becomes `_post/hello.md`), and folders left
empty are removed.

A [nesting collection](relationships.md#nesting-and-order) is flat too: its entries
name their parents in front matter rather than sitting in their folders.

## Folders for many files

A collection with thousands of entries can keep its files in folders
inside its own, so no one folder holds them all. Some hosts' file
managers and FTP clients stop listing a folder past a few thousand
files, and large folders are slow to read. (Folders don't help with a
host's limit on the total number of files, since each folder counts as
one too.)

Set `folders` to a pattern, one token to a folder. With
`user/data/types/post.json` like this, a post is kept in
`_post/2026/hello.md`:

```json
{ "folders": "{year}" }
```

`{year}/{month}` keeps it in `_post/2026/10/hello.md`, and `{initial}`
keeps a tag in `_tag/h/hello.md`. In PHP, it's the `folders` argument:
`new Collection('post', folders: '{year}')`.

| Token       | Folder                                                                 |
|-------------|------------------------------------------------------------------------|
| `{year}`    | The year it was published (`2026`)                                     |
| `{month}`   | The month it was published (`10`), after `{year}`                      |
| `{initial}` | The slug's first letter or digit, lowercased (`h`); `0` for anything else |

The profiles type takes a pattern too, though the built-in one has
none: its files are directly in `_profile/`, as a new type of terms'
are. Trees don't take one, since a tree's
folders are their pages'.

Folders, like [file name patterns](#naming-new-files), are only for a
site that keeps its content in files. A site whose content is in a
[database](../running/sqlite.md) has no folders to fill, so
the admin doesn't offer either there.

These folders are only where files are kept. They're never part of an
entry's address or how it's found: `_post/2026/hello.md` is the post
`hello`, at the same URL as `_post/hello.md`. Use `slug:` or a
[file name pattern](#naming-new-files) to tell apart posts with the same
slug in different years.

- **New entries** go in the folder their date and slug give
  them, and so do copies.
- **A new date or slug moves the file**, as a new date renames a file
  [named by date](#naming-new-files): changing a post's date
  from 2025 to 2026 moves it from `_post/2025` to `_post/2026`.
- **Changing the pattern moves nothing.** Older files keep working
  where they are; `content:lint` warns of each file outside the
  pattern's folders. `bin/blush content:folders --write`, or
  **Collection Folders** on Site Health, moves them, by the date
  as the file writes it (else its updated date).
- **A relation archive's pages stay where they are.** Its `_` file
  (`_post/_authors.md`) and the files in its `_` folder
  (`_post/_authors/jane.md`) are kept outside the pattern's folders,
  and aren't moved into them.

In the admin, choose it under **Folders** on the type's screen: None,
By year, By year and month, or By first letter.

## Custom URLs

`urls` moves a type's URLs. For example, to serve posts from `_post/`
at `/archives/2026/09/26/hello`:

```php
use Blush\Content\Type\TypeUrls;

new Collection(
	'post',
	urls: new TypeUrls(prefix: 'archives', single: '{year}/{month}/{day}/{name}'),
	dateArchives: DateArchives::Day
);
```

`prefix` starts every URL; without it, that's the type's name. (A
[tree](trees.md) takes `prefix` on its own, since it has no other URL
settings.) `single` (an
entry) and `collection` (the listing page) set the rest; `paths` sets any
other route key that `routes:list` shows, such as
`['collection.paged' => 'p/{page}']`.

Each [relation with an archive word](relationship-archives.md#archives-for-references)
from the type, [credits](credits-and-profiles.md#people-archives) included, adds route keys of
its own, named after the relation: `authors.collection`,
`authors.single`, `authors.single.paged`, and the `authors.single.feed`
keys. `paths` can move those too. Their paths hold `{target}`, the
linked entry's slug. The admin lists them with the type's other
addresses.

Single-entry paths can use `{name}`, `{year}`, `{month}`, `{day}`,
`{hour}`, `{minute}`, `{second}`, `{profile}` (the first person
credited), and the name of any [classify relation](relationships.md#filing-entries-under-terms)
(the first term filed under, such as `{category}`). If
someone reaches a post by a wrong date, they're redirected to the right
one.

Run `bin/blush routes:list` to see every URL your types create. The
admin's **Addresses** panel on a type's screen edits these paths too
(see [the admin](../admin/README.md)).
