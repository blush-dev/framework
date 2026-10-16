# Ids and links between entries

Every entry has an id that never changes, so other entries, menus, and
redirects can point at it however it's renamed or moved.

## Ids

An entry's `id` is a UUID in its front matter that stays the same
when the entry is renamed or moved, so other things can point at it.
Blush writes one, as the last key, whenever it creates an entry (in the
admin, with `content:new`, or as a copy), and adds one when the admin
saves a file that doesn't have it:

```yaml
---
title: Hello, World
date: 2026-10-05 09:00:00 -05:00
id: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74
---
```

You don't need to write it yourself. For files you add by hand, or
content from before ids, run:

```sh
bin/blush content:ids          # list files missing an id, and ids files share
bin/blush content:ids --write  # give each file missing one a new id
```

**Site Health** in the admin does the same, under Content Files. A copied file shares its
original's id, which `content:lint` reports as an error. Tell Blush
which file keeps it, and the others get new ones:

```sh
bin/blush content:ids --keep=blog/2026-10-05.hello.md
```

Don't edit an id by hand, and never give two files the same one. A file
without a valid id isn't an entry: the site, its feeds, and the admin's
lists leave it out until it has one, and `content:lint` reports it as an
error. Types can't have a field named `id`.

## Links between entries

An entry names other entries by slug: its terms (`tag: [php, cms]`),
its authors (`authors: jane`), the `parent` of an entry in a
[nesting collection](relationships.md#nesting-and-order) (such as a category),
and the entries any other relation links. A page of a tree is named by its path
(`about/team`). You can write an entry's id instead of its slug, and
Blush reads it as that entry:

```yaml
tag: [php, 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74]
```

Beside the slugs, `refs` can keep each one's id, by the relation's name
(see [Relationships](relationships.md)),
then by the slug written. The name is usually the key too; when a
relation is written under another key, such as `authors` for a relation
named `author`, its ids are still under `refs.author`:

```yaml
tag: [php, cms]
refs:
  tag:
    php: 0199a1b4-2c6d-7e3f-8a90-1b2c3d4e5f60
    cms: 0199a1c8-4d5e-7f60-9a1b-2c3d4e5f6071
```

When a slug in the list and its id in `refs` disagree (the term was
renamed since), the id wins, so the entry keeps its link. A
`parent` is kept in `refs.parent` too. A page's parent is its folder,
and an id kept for it that names another page is ignored. Types can't
have a field named `refs`.

You don't need `refs` in files you write by hand: Blush writes it
whenever it saves a file, in the admin or from the command line. It
also writes each value as the slug its entry has now, so an id you
wrote becomes a slug; a value that names nothing yet is kept as you
wrote it. When you rename an entry or move a page in the admin, the
entries linking to it are rewritten to its new slug or path. To file
`refs` in files written some other way, run:

```bash
bin/blush content:refs          # list files whose links aren't filed with their ids
bin/blush content:refs --write  # file them
```

or use **Links Between Entries** in Site Health.

`content:lint` reports an entry that names itself, a value that names
entries of two types (write the id to say which), an id of the wrong
type, and a relation naming an entry that doesn't exist.
