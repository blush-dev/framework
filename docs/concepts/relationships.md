# Relationships

A **relation** says how entries of some types link to entries of other
types: posts filed under categories, a recipe linking to related
recipes. Relations are defined on their own, not on a type, and each
names the types it's `from` and the types it's `to`. Links are stored
in the front matter of the entry that makes them, by slug
(`category: news`), with their ids kept under
[`refs`](ids-and-links.md#links-between-entries).

There are a few kinds:

- **`classify`** files entries under terms: tags, categories, series.
  This is what 1.x and early 2.x called a taxonomy.
- **`reference`** links entries to other entries, such as related
  posts or a recipe's side dishes.
- **`credit`** credits [people](credits-and-profiles.md): a post's authors,
  a recipe's cooks.
- **`parent`** and **`translation`** are built in: an entry's parent in
  a [nesting collection](#nesting-and-order) or a tree, and a
  translation's [`translation_of`](translations.md).

Where the entries linking to one are listed (a term's page, an archive
under the linking type) is the relation's `inverse`; see
[Relationship archives](relationship-archives.md).

## Filing entries under terms

A type of terms is a plain collection, usually ordered by `position`,
with no authors, and left out of `llms.txt`. What makes it terms is a
`classify` relation, named after it, that says which types are filed
under it. For categories filing posts, that's the type in
`user/data/types/category.json`:

```json
{
	"urls": { "prefix": "topics" },
	"hierarchical": true,
	"order": "position",
	"llms": false
}
```

And the relation in `user/data/relations/category.json`,
named by its file:

```json
{"kind": "classify", "from": ["post"], "to": ["category"], "create": true}
```

A plugin defines them with a `ContentTypeSource` and a `RelationSource`
(see [Content types from a plugin](../features/content-types.md)).

In the admin, **New Content Type** with **Terms** creates both, and
**Add Relationship** on any type's screen adds a relation (see
[the admin](../admin/README.md)). The admin edits relations in
`user/data/relations/`; those defined in code, it only shows.

Then a post names its categories in its front matter:

```yaml
category: [painting, news]
```

- A classify relation is **named after the one type it files under**:
  `to` is `[its name]`. That name is also how templates and queries
  know the terms (`$entry->related('category')`,
  `whereTerm('category', 'news')`).
- Entries use a front matter key named after the relation, unless you
  set `field`; `aliases` adds more keys it's read from.
- `from` lists the types filed under it; leave it empty for every type.
  The key is a field of those types only.
- `create: true` lets writers add a term as they type it in the
  admin, which writes its file, published and titled as they typed it.
  Someone who can't create and publish terms of that type can't save
  an entry naming a new one.
- `required: true` (or `min: 1`) means an entry needs a term to be
  published; drafts can be saved without one. `min` can ask for more.
  `multiple: false` allows one term, and `max` a number of them; the
  admin won't publish an entry with more.
- Every term is a file (such as `_category/painting.md`), which gives it
  its title and description. A slug an entry names with no file is left
  out of the site: no link, no page, and no feed. `content:lint`
  reports it, and `bin/blush content:terms --write` (or **Terms and
  Profiles** in Site Health) writes a file for each, titled as the
  entry wrote it.

Relations come from plugins and `user/data/relations/`, in that order, and a later one replaces an
earlier one of the same name. The admin won't create a relation with
the name of one defined in code. With `dataTypes` off,
`user/data/relations/` isn't read.

## Nesting and order

Any collection can nest: set `hierarchical: true`, and an entry names
its parent entry by slug, as categories have subcategories:

```yaml
---
title: CSS
parent: web-design
---
```

Files stay side by side in the collection's folder, so moving an entry
changes one line, and slugs stay unique across the whole collection.
Its entries' URLs follow the tree: with `css` under `web-design` under
`web`, it's `/topics/web/web-design/css`, and a term's later pages and
feeds are under that (`…/css/page/2`, `…/css/feed`). Any other path to
it, such as `/topics/css` or its address before it moved, redirects
there. An entry below the top can't be slugged `page` or `feed`, since
those words start its paged and feed URLs.

Themes show the tree with `$entry->parent()`,
`$entry->ancestors()`, and `$entry->children()` (see
[Themes](../themes/README.md)), and the admin shows each entry's parents before
its title. `bin/blush content:lint` reports a parent with no file (the
entry is shown at the top level) and an entry that's its own ancestor.

A collection's `order` sets how it lists its entries: `date`
(newest first, the default) or `position`, by the `position` front
matter (lowest first), then title. Its entries have a `position` only
when it [supports one](content-types.md#supports) (`"position": true`); without it,
ordering by position sorts by title. In
`user/data/types/category.json`:

```json
{
	"urls": { "prefix": "topics" },
	"hierarchical": true,
	"order": "position",
	"position": true
}
```

A nesting collection is flat on disk: its entries name their parents in
front matter rather than sitting in their folders (see
[Collections are flat](file-names-and-urls.md#collections-are-flat)).

## Linking entries to other entries

A `reference` relation links entries to entries of the types in `to`,
by slug:

```php
new Relation('related', RelationKind::Reference, from: ['recipe'], to: ['recipe'], max: 3)
```

```yaml
related: [lemon-cake, shortbread]
```

`ordered: true` keeps the order they're written in. No entry links to
itself.

The relation's `inverse` says where the entries linking to one are
listed, paged with a feed: on the linked entry's own page, in archives
under the linking type's address, or both. A reference has neither by
default, so a template lists them with `$entry->referencedBy()`. See
[Relationship archives](relationship-archives.md#archives-for-references).

`symmetric: true` makes a link count from both ends, so a post
naming another is related to it and the other to it. In a template,
`$entry->related('related')` lists what an entry links to, and
`$entry->referencedBy('related')` what links to it (see
[Themes](../themes/README.md)). There's no reference field type: a link between
entries is always a relation.

## Relation options

| Option         | Default          | What it does                                                                                       |
|----------------|------------------|----------------------------------------------------------------------------------------------------|
| `kind`         | `reference`      | `classify`, `reference`, or `credit` (`parent` and `translation` are built in)                     |
| `from`         | Every type       | The types whose entries make the link                                                              |
| `to`           |                  | The types linked to. A `classify` relation's is `[its name]`                                       |
| `field`        | The name         | The front matter key the link is written under                                                     |
| `aliases`      | `[]`             | Other keys it's read from                                                                          |
| `multiple`     | `true`           | Whether an entry may link to several                                                               |
| `ordered`      | `false`          | Whether the order they're written in matters                                                       |
| `required`     | `false`          | Whether an entry needs one to be published (in the admin; a draft saves without)                   |
| `min`          | `0`              | The fewest an entry needs to be published (`required` is `min: 1`)                                 |
| `max`          | No limit         | The most an entry may have to be published; `content:lint` reports more                            |
| `create`       | `false`          | Whether writers may add a target as they type it in the admin, which writes it                     |
| `symmetric`    | `false`          | Whether a link counts from both ends (`from` and `to` the same types)                              |
| `control`      | By its shape     | How the admin's editor picks targets: `tree` (a nesting type's terms), `tokens`, `people` (a credit's), `select` (one), or `cards` (search results with images and dates); one its shape can't draw falls back |
| `translations` | `fallback`       | A translation's links: `fallback` (its own, else its original's), `add` (the original's and its own), or `own` |
| `inverse`      |                  | The targets' side: `page`, `archive`, `types`, `listing`, `label`, and `max` (see [Relationship archives](relationship-archives.md)) |
| `singular`     | Made from the label | What one target is called ("Cook")                                                           |
| `label`        |                  | What the relation is called                                                                        |

A relation's name uses lowercase letters, digits, and underscores, and
in a data file it's the file's name. In PHP, `Relation` takes the name
and kind first and the rest by name, and `Relation::fromArray()` takes
the same keys as a data file.

Editors that read JSON Schema can check a relation's file in
`user/data/relations`: start it with
`"$schema": "../../../vendor/blush-dev/framework/resources/schemas/relation.schema.json"`.
The admin keeps a file's `"$schema"` when it saves the relation.

## Moving from taxonomies

Blush no longer has a taxonomy kind. A type still written with `kind:
taxonomy` (or 1.x's `taxonomy: true`) in `user/data/types/` keeps
working until you migrate it. To find them, run:

```sh
bin/blush content:taxonomies
```

Then `bin/blush content:taxonomies --write`, or **Migrate Types** under
**Taxonomies** in the admin's Site Health, rewrites each:

- The type's file is edited in place, keeping its other keys. The taxonomy's keys are removed, and `order: position`,
  and `llms: false` are added unless it said otherwise, and its
  `people` or `authors` key is removed (terms credit no one).
- Its relation is written to `user/data/relations/{name}.json`: its
  `types` (or `term_collect`) become `from`, `field` and `aliases`
  carry over, and its `termListing` becomes `inverse.listing`.

Entries' front matter and term URLs don't change. The admin won't edit
a type still written as a taxonomy until it's migrated.

A taxonomy defined in a plugin stops Blush from
loading, with a message saying what to change: make it a `Collection`
(with `order: TypeOrder::Position`, and `hierarchical: true` if its
terms nest) and add a classify relation named after it, as in
[Filing entries under terms](#filing-entries-under-terms). See
[Coming from 1.x](../getting-started/coming-from-1x.md) for an array example.
