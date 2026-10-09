# Content types

Out of the box, every entry is a **page**. That's enough for a simple
site. When you want a blog, a portfolio, or a recipe box, where entries
are listed, dated, tagged, or given their own fields, define a **content
type**.

Each content type owns a folder in `user/content/`: its name after an
underscore (`_recipe` for `recipe`). Entries in that folder belong to
it, and its `index.md` becomes the listing page. Every type is kept this
way; a type never names its own folder. The underscore keeps type
folders apart from your page folders. A type's addresses come from its
[URL prefix](#custom-urls), which is its name unless you set one: `_recipe/`
is served at `/recipe`, or at `/recipes` with the prefix `recipes`.

## Three kinds of type

Every content type is one of three kinds:

- **Collection:** entries that are listed, such as posts, recipes, or
  projects. A collection has a listing page, and it can have a feed and
  date archives. Tags, categories, and series are collections too: their
  entries are **terms**, which other entries are filed under through a
  [relation](#terms-and-relationships), and each term gets a page
  listing the entries filed under it.
- **Tree:** entries that nest by folder, each served at its path under
  the tree's prefix.
  The built-in `page` type is a tree: it holds every entry that isn't in
  another type's folder, and `about/team.md` is a subpage of `about.md`
  (or `about/index.md`). You can add [trees of your own](#trees), such as
  docs.
- **Profiles:** the built-in `profile` type, the people your entries
  credit. A site has one.

## Built-in types

- **`page`** (tree): every entry that isn't in another type's folder.
- **`profile`** (profiles): people, in `user/content/_profile/`. Each
  file is one person: the title is their public name, `subtitle` a line
  under it (such as "Food editor"), `avatar` a portrait from
  `user/media`, `linkable: false` to lock it against being linked to
  an account (see [Accounts](accounts.md)), and the body their bio. Each profile has a page at
  `/profiles/jane`. Entries credit them through the
  [credit relations](#crediting-people) of their type, such as
  `authors: jane`. Every profile is a file: a person credited with no
  file is left out of bylines and has no page. `content:lint` reports
  it, and `bin/blush content:terms --write` writes the missing files. An
  [account](accounts.md#profiles) can be linked to a profile.

## Two ways to define a type

**In JSON** (no PHP needed): create a file in `user/data/types/`
named after the type, or let the admin's **New Content Type** write it.
Use `kind` to pick the kind; it's `collection` if you leave it out.
`user/data/types/recipe.json`:

```json
{ "urls": { "prefix": "recipes" } }
```

Its entries go in `user/content/_recipe/` and are served under
`/recipes`. The file's name is the type's name; the file never says it, and one
naming another type is refused. When the admin saves a type, it adds an
`"id"` at the end of its file: the type's record id, which stays the
same from then on. A file without one works just as well. Relations in
`user/data/relations/` work the same way. On the
[SQLite driver](going-live.md#large-sites-sqlite), types and relations
are kept in the database instead.

**In a plugin**, in PHP, when the types belong with code you install.
Each kind is its own class (`Collection`, `Tree`, or `Profiles`), which
gives you editor autocomplete and type checking:

```php
new Collection('recipe', urls: new TypeUrls(prefix: 'recipes'))
```

See [Content types from a plugin](extending.md#content-types-from-a-plugin)
for the class that returns them.

### Changing a type from code

A file in `user/data/types/` named after a collection or
[tree](#trees) from a plugin changes that type rather than
defining a new one. Each option it sets replaces the code's, and the
rest stay as the code has them. For recipes defined in a plugin,
`user/data/types/recipe.json`:

```json
{
	"description": "Dinners worth making twice.",
	"feed": false
}
```

This is what the admin writes when you edit such a type, and
it keeps only what differs from the code. A file like this can't change the
type's kind, and the `page` type and the profiles type from
code can't be changed this way. Delete the file (or use **Reset the
Type** in the admin) to go back to the plugin's definition. With `dataTypes` off, these files aren't read either.

Both do the same thing, and every option below works in each. (In
JSON, use the option names as keys.) A type's name uses lowercase letters,
digits, and underscores. It can't be `system`, `error`, or `drafts`:
`_system`, `_error`, and `_drafts` are folders your pages keep.

If two places define the same type, a plugin's type replaces a
built-in one. A JSON type may replace a built-in type; one named for a
plugin's type changes it, as above. When two plugins define the
same type, the first plugin's is used and the other's is left out, and
Site Health says so (see [Extending](extending.md)).

Which to pick:

- **JSON** keeps a type with your content, so a copy of `user/` carries
  it along. It's the only kind the [admin](admin.md#content-types) can
  create and edit.
- **A plugin** keeps it with a feature you can reuse or version on its
  own.

### Moving a type into its folder

Types once named their own folder (`"folder": "recipes"`, or 1.x's
`"path"`). A file in `user/data/types/` that still does is read in `_`
and its name, with the folder's path as its URL prefix, so the admin
keeps working. Its entries aren't found until they're moved, since
they're still in the old folder. To move them, run
`bin/blush content:type-folders` to see what would change, then
`bin/blush content:type-folders --write`, or use **Type Folders** on
Site Health in the admin. Each type's files move into `_` and its name,
and its file is written without the folder: a folder pattern after it
(`_posts/{year}`) becomes `folders`, and a collection or tree whose
addresses came from the folder gets that path as its `prefix`, so no
address changes. Another type's folder inside the old one stays for
that type's own move.

A type from a plugin that names its folder stops the site from loading,
saying what to change: take out `folder` (and move its files), and give
it a `prefix` if its addresses came from the folder.

## Names, descriptions, and icons in the admin

The [admin](admin.md) names each type in its menu, headings, and
buttons with its `labels`. Each label is made from the ones above it,
starting from the type's name, so set only the ones that come out
wrong:

| Label         | Made from  | For `literary_form`   | Where the admin shows it                       |
|---------------|------------|-----------------------|------------------------------------------------|
| `singular`    | The name   | Literary form         | Field headings, type menus                     |
| `plural`      | `singular` | Literary forms        | The list's heading, the page title             |
| `menu`        | `plural`   | Literary forms        | The admin's navigation                         |
| `item`        | `singular` | literary form         | Mid-sentence: "Create the first literary form" |
| `items`       | `plural`   | literary forms        | Mid-sentence: "3 literary forms"               |
| `newItem`     | `singular` | New Literary Form     | The New button and screen, in Title Case       |
| `editItem`    | `item`     | Edit literary form    | The editor's title                             |
| `searchItems` | `items`    | Search literary forms | The list's search field                        |

`plural` adds "s" ("es" after s, x, z, ch, or sh, and "ies" for a y
after a consonant). `item` and `items` lowercase the first letter,
unless the first word is an acronym or has another capital ("FAQ",
"HTML snippet" stay as they are).

Set `plural` when the name doesn't make a good English plural, the
mid-sentence names for a proper noun, and any of them to call the type
something else. `menu` shortens a long name in the navigation only, such
as "Forms" for literary forms listed under Literature; the navigation is
sorted by it:

`user/data/types/person.json`:

```json
{
	"labels": { "plural": "People", "newItem": "Add someone" }
}
```

`user/data/types/literary_form.json`:

```json
{
	"order": "position",
	"labels": { "menu": "Forms" }
}
```

In PHP, `TypeLabels` takes `singular` first and the rest by name:

```php
use Blush\Content\Type\TypeLabels;

new Collection('person', labels: new TypeLabels('Person', plural: 'People'))
```

`description` says what the type is for, in a sentence. The admin shows it
on the type's screen and on its list while it's empty. `icon` names an
icon to show the type with in the admin's menu, from the icons the `icon`
directive offers (`bin/blush icon:list`), such as `film` or
`book-open`. Without one, the type gets its kind's icon.

`user/data/types/recipe.json`:

```json
{
	"description": "Dishes we cook at home, with what goes in them.",
	"icon": "notebook-pen"
}
```

## Example: a blog

Posts live in `user/content/_post/` and are listed at `/blog`, newest
first, with a feed and monthly archives, in `user/data/types/post.json`:

```json
{
	"urls": { "prefix": "blog" },
	"listing": { "orderBy": "published", "order": "desc" },
	"feed": true,
	"dateArchives": "month"
}
```

Tags live in `user/content/_tag/`, listed under `/blog/tags`, ordered
by position, in `user/data/types/tag.json`:

```json
{
	"urls": { "prefix": "blog/tags" },
	"order": "position",
	"llms": false
}
```

And posts are filed under tags with a `tag` key, through a relation in
`user/data/relations/tag.json`:

```json
{"kind": "classify", "from": ["post"], "to": ["tag"], "create": true}
```

The admin's **New Content Type** writes both when you choose **Terms**
(see [Content types](admin.md#content-types)). A plugin can define the
same types and relation in PHP (see
[Content types from a plugin](extending.md#content-types-from-a-plugin)).

Now:

| File                        | URL                                                |
|-----------------------------|----------------------------------------------------|
| `_post/index.md`            | `/blog` (the post listing, newest first)           |
| `_post/2026-09-26.hello.md` | `/blog/hello`                                      |
|                             | `/blog/2026` and `/blog/2026/09` (date archives)   |
|                             | `/blog/feed`, `/blog/feed/atom`, `/blog/feed/json` |
| `_tag/php.md`               | `/blog/tags/php` (every post tagged `php`)         |

Create a post with:

```sh
bin/blush content:new post "Hello world"
```

Tag it in its front matter:

```yaml
tag: [php, news]
```

### Making the blog the homepage

To show the latest posts on the homepage, name the type as `home` in
`config/content.php`:

```php
<?php

declare(strict_types=1);

use Blush\Content\ContentConfig;

return new ContentConfig(home: 'post');
```

The homepage then lists posts, with `/page/2` and so on, and the feed
moves to `/feed`. It takes the place of `user/content/index.md`, which is
no longer shown.

## Terms and relationships

A **relation** says how entries of some types link to entries of other
types: posts filed under categories, a recipe linking to related
recipes. Relations are defined on their own, not on a type, and each
names the types it's `from` and the types it's `to`. Links are stored
in the front matter of the entry that makes them, by slug
(`category: news`), with their ids kept under
[`refs`](content.md#links-between-entries).

There are a few kinds:

- **`classify`** files entries under terms: tags, categories, series.
  This is what 1.x and early 2.x called a taxonomy.
- **`reference`** links entries to other entries, such as related
  posts or a recipe's side dishes.
- **`credit`** credits [people](#crediting-people): a post's authors,
  a recipe's cooks.
- **`parent`** and **`translation`** are built in: an entry's parent in
  a [nesting collection](#nesting-and-order) or a tree, and a
  translation's [`translation_of`](content.md#translations).

### Filing entries under terms

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
(see [Content types from a plugin](extending.md#content-types-from-a-plugin)).

In the admin, **New Content Type** with **Terms** creates both, and
**Add Relationship** on any type's screen adds a relation (see
[Content types](admin.md#content-types)). The admin edits relations in
`user/data/relations/`; those defined in code, it only shows.

Then a post names its categories in its front matter:

```yaml
category: [painting, news]
```

- A classify relation is **named after the one type it files under**:
  `to` is `[its name]`. That name is also how templates and queries
  know the terms (`$template->terms($entry, 'category')`,
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

### Nesting and order

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

Themes show the tree with `$template->parent()`,
`$template->ancestors()`, and `$template->children()` (see
[Themes](themes.md)), and the admin shows each entry's parents before
its title. `bin/blush content:lint` reports a parent with no file (the
entry is shown at the top level) and an entry that's its own ancestor.

A collection's `order` sets how it lists its entries: `published`
(newest first, the default) or `position`, by the `position` front
matter (lowest first), then title. A collection that nests or is
ordered by position has the `position` field. In
`user/data/types/category.json`:

```json
{
	"urls": { "prefix": "topics" },
	"hierarchical": true,
	"order": "position"
}
```

### Term pages

A type of terms with URLs gets a page for each term, listing the
entries filed under it, at `/{prefix}/{slug}` (nested for a nesting
collection). Term pages are paged
(`/topics/painting/page/2`), and have feeds (`/topics/painting/feed`)
when the type of terms has `feed`. The type's own listing page (such as
`/topics`) lists its terms, as its `listing` says.

A term's page lists only the entries filed under that term, not those
under its child terms. The relation's `inverse` sets the term side:

| Option            | Default                 | What it does                                                                    |
|-------------------|-------------------------|---------------------------------------------------------------------------------|
| `page`            | `true` for `classify` and `credit` | Whether each target's own page lists what links to it                |
| `archive`         | The name for `credit`, else `false` | A word for archives under each linking type (`/movies/directors/penny`), or `false` |
| `types`           | The relation's `from`   | The types a term's page lists                                                   |
| `listing`         |                         | How a term's page lists entries, with a type's [`listing`](#listing-entries) keys, such as `order` and `perPage` |
| `label`           |                         | What the term side is called                                                    |
| `max`             |                         | How many entries may be filed under one term; the admin won't publish one more  |

For example, twenty entries to a term's page:

```json
{
	"kind": "classify",
	"from": ["post"],
	"to": ["category"],
	"inverse": {"listing": {"perPage": 20}}
}
```

`"page": false`, or `inverse: false`, gives the terms no pages of
their own.

### Linking entries to other entries

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
listed, paged with a feed. Its two sides can be on at once, and a
reference has neither by default, so a template lists them with
`$template->referencedBy()`:

- `"page": true` lists them on the linked entry's own page, as a term's
  page lists what's filed under it. With `actors` linking movies to
  people, `/people/tom` lists Tom's movies.
- `"archive"`, a word such as `"directors"`, gives archives under the
  linking type's address, as [credits](#people-archives) have:
  `/movies/directors/penny` lists Penny's movies, and `/movies/directors`
  lists everyone a movie names. `_directors.md` in the movie type's
  folder introduces the list, and `_directors/penny.md` Penny's archive
  in place of her own page's text.

```json
{
	"kind": "reference",
	"from": ["movie"],
	"to": ["person"],
	"inverse": {"archive": "directors"}
}
```

`symmetric: true` makes a link count from both ends, so a post
naming another is related to it and the other to it. In a template,
`$template->related($entry, 'related')` lists what an entry links to,
and `$template->referencedBy($entry, 'related')` what links to it (see
[Themes](themes.md)). A
[`reference` field](#custom-fields) with `to` in a type's `fields`
works as before; Blush reads it as a reference relation.

### Relation options

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
| `inverse`      |                  | The targets' side: `page`, `archive`, `types`, `listing`, `label`, and `max` ([Term pages](#term-pages), and above) |
| `singular`     | Made from the label | What one target is called ("Cook")                                                           |
| `label`        |                  | What the relation is called                                                                        |

A relation's name uses lowercase letters, digits, and underscores, and
in a data file it's the file's name. In PHP, `Relation` takes the name
and kind first and the rest by name, and `Relation::fromArray()` takes
the same keys as a data file.

### Moving from taxonomies

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
[Coming from 1.x](coming-from-1x.md#taxonomies) for an array example.

## Trees

A tree is a type whose entries nest by folder, as pages do, in a folder
of its own, `_` and its name. Use one for docs, a manual, or a handbook:

```php
use Blush\Content\Type\Tree;

new Tree('doc', icon: 'book', prefix: 'docs')
```

In JSON, that's `user/data/types/doc.json`, as the admin's **New
Content Type** writes it:

```json
{ "kind": "tree", "icon": "book", "prefix": "docs" }
```

 A tree from a plugin
can be [changed from the admin](#changing-a-type-from-code), as
collections can; the `page` type can't.

- Each entry is served at its path under the tree's `prefix` (its name
  when it has none): `_doc/install/requirements.md` is at
  `/docs/install/requirements`.
- The folder's `index.md` is the tree's landing page, at `/docs`, and the
  admin pins it above the tree's other entries.
- An entry in a subfolder is a child of the entry the subfolder is named
  for: `install/requirements.md` is under `install.md` (or
  `install/index.md`). Themes show the tree with `$template->parent()`,
  `$template->ancestors()`, and `$template->children()` (see
  [Themes](themes.md)), and the admin lists it as a tree.
- A tree has no listing page, feed, or routes of its own. Its entries use
  the `single-{type}` and `single` views.

Only the `page` type sits at the content root, and it takes no
prefix.

## Custom fields

Declare the fields a type's entries use, and Blush checks them when it
indexes your content and in `bin/blush content:lint`. In
`user/data/types/recipe.json`:

```json
{
	"fields": [
		{ "name": "servings", "type": "number", "integer": true, "required": true },
		{ "name": "difficulty", "type": "enum", "options": ["easy", "medium", "hard"], "default": "easy" },
		{ "name": "ingredients", "type": "list" }
	]
}
```

Field types:

| Type        | Holds                  | Extra options                                                                                                       |
|-------------|------------------------|---------------------------------------------------------------------------------------------------------------------|
| `text`      | A line of text         |                                                                                                                     |
| `markdown`  | Formatted text         |                                                                                                                     |
| `date`      | A date and time        |                                                                                                                     |
| `bool`      | `true` or `false`      |                                                                                                                     |
| `number`    | A number               | `integer`, `min`, `max`                                                                                             |
| `enum`      | One of a set of values | `options`                                                                                                           |
| `list`      | Several values         | `item` (a field definition for each value; text by default)                                                         |
| `reference` | Other entries, by slug | `to` (the type), `multiple` (default `true`); read as a [reference relation](#linking-entries-to-other-entries)       |
| `media`     | A media file           | `kind`: `image`, `video`, `audio`, `document`, or `file`, so the admin's picker offers only those (any file when it's left out) |
| `slug`      | A URL-safe name        |                                                                                                                     |
| `object`    | A group of fields      | `fields`, `closed`                                                                                                  |

Every field also takes `required`, `default`, `aliases` (other keys it's
read from), `label`, `description`, and `control`.

`fields` can also be a map of names to definitions, which some people
find easier to read:

```json
{
	"fields": {
		"servings": { "type": "number", "integer": true },
		"difficulty": { "type": "enum", "options": ["easy", "medium", "hard"] }
	}
}
```

### How the admin edits a field

Each field type has a control the admin edits it with. Some types can
use another one instead: set `control` to pick it.

| Type        | Controls (the first is the default)                                                   |
|-------------|---------------------------------------------------------------------------------------|
| `text`      | `text` (one line), `textarea` (several lines), `mono` (one line, for code)            |
| `markdown`  | `textarea`                                                                            |
| `date`      | `date` (a date picker)                                                                |
| `bool`      | `checkbox`                                                                            |
| `number`    | `number`                                                                              |
| `enum`      | `select` (a menu), `radios` (radio buttons)                                           |
| `list`      | `lines` (one per line), `checks` (checkboxes, for a list of `enum` items), `readonly` |
| `reference` | `reference` (an entry picker, with `to`), `mono` (slugs typed with commas)            |
| `media`     | `media` (a media picker), `mono` (a path typed in)                                    |
| `slug`      | `mono`                                                                                |
| `object`    | `readonly` (edited in the file for now)                                               |

```json
{
	"fields": [
		{ "name": "difficulty", "type": "enum", "options": ["easy", "medium", "hard"], "control": "radios" },
		{
			"name": "diets",
			"type": "list",
			"control": "checks",
			"item": { "type": "enum", "options": ["vegetarian", "vegan", "gluten-free"] }
		}
	]
}
```

A list's items need to fit on one line to be written one per line, so a
list of `markdown` is shown read-only. The admin's field editor offers
the controls a field can use under **Edited with**.

Keys you don't declare are still kept, and `content:lint --strict` lists
them. Set `closed: true` on the type to make them errors instead.

## Field sets

A field set is a group of fields you can add to several types at once:
SEO fields for posts and pages, say. Put each set in its own file in
`user/data/fields/`, named for the set, and list the types it's for in
`targets`, as `type:` and the type's name. `user/data/fields/seo.json`:

```json
{
	"label": "SEO",
	"description": "How the entry appears in search results.",
	"targets": ["type:post", "type:page"],
	"fields": {
		"meta_title": { "type": "text", "label": "Title for search engines" },
		"noindex": { "type": "bool", "label": "Hide from search engines" }
	}
}
```

`fields` takes the same field definitions as a type's, as a list or a
map. `label` heads the set's fields in the admin's editor, where each set
has its own group after the type's own fields (its name, made readable,
when it has no label), and `description` is shown under it.

A set's targets are all one kind of place: content types, kinds of
media file, or Settings screens. A field means one thing on an entry and
another in the site's settings, so make a set for each.

In the admin's editor, each set is a group of its own in the document
panel beside the text, after the type's own fields.

- A type's fields come first, then each set's, with the sets in name
  order.
- A set can't use a field name (or alias) that the type, the built-in
  fields, or another of its sets already uses. Rename one; Blush stops
  with a message naming both.
- A target the site doesn't have, such as a type that's turned off, is
  skipped. `bin/blush content:lint` notes it.

Profiles are a content type too, so a set aimed at `type:profile` adds
fields to every profile, such as a website or a pronoun line.

Sets can also add details to media files: target `media:image`,
`media:video`, `media:audio`, `media:document`, or `media:file` (see
[Details about a file](media.md#details-about-a-file)). And they can add
settings to the admin's Settings screens: target `settings:general`,
`settings:reading`, or `settings:search` (see
[Your own settings](themes.md#your-own-settings)).

The admin's **Config → Fields** creates and edits the sets in
`user/data/fields/` ([Fields](admin.md#fields)). Sets can also be
defined in `config/fields.php`, where the admin only shows them:

```php
<?php

declare(strict_types=1);

use Blush\Field\FieldConfig;
use Blush\Field\FieldSet;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\TextField;

return new FieldConfig(sets: [
	new FieldSet('seo', [new TextField('meta_title'), new BoolField('noindex')], ['type:post', 'type:page'], 'SEO')
]);
```

Plugins can add sets too
([Field sets from a plugin](extending.md#field-sets-from-a-plugin)).
A set in `config/fields.php` replaces a plugin's set of the same
name, and a set in `user/data/fields/` replaces either. Set `dataSets:
false` in `config/fields.php` to ignore `user/data/fields/`.

Editors that read JSON Schema can check a set's file: start it with
`"$schema": "../../../vendor/blush-dev/framework/resources/schemas/field-set.schema.json"`.
A relation's file in `user/data/relations` can name
`relation.schema.json` the same way; the admin keeps a file's
`"$schema"` when it saves the relation.

## Listing entries

A type's `listing` option sets how its listing page lists entries:

```php
new Listing(orderBy: 'published', order: Order::Desc, perPage: 20)
```

| Option    | Default         | What it does                                                           |
|-----------|-----------------|------------------------------------------------------------------------|
| `type`    | The type itself | Which type to list                                                     |
| `orderBy` | Its type's order | `published`, `updated`, `title`, `author`, `position` (a tree's, or a collection's that [nests or is ordered by position](#nesting-and-order), then title), or any field. A collection lists by its `order`: newest published first, or by `position`, then title |
| `order`   | Its type's order | `asc` or `desc` (`Order::Asc` or `Order::Desc` in PHP)                 |
| `perPage` | `10`            | How many per page; `0` (`Listing::ALL`) for all of them                |
| `query`   |                 | Any other option from the table below, such as `{terms: {tag: [php]}}` |

Entries are never sorted by file name, so the order holds when file
names change or content moves to a database. Text sorts without regard
to case; entries without the value come last, whichever way; and ties
go by when each entry was made (its id), earliest first. 1.x's
`orderby: filename` is read as `published`, and `content:lint` warns
where a page still says it.

To list entries on any page, use `collection` in its front matter:

```yaml
---
title: "Latest recipes"
collection:
  type: recipe
  number: 5
  orderby: published
  order: desc
---
```

| Option                    | What it does                                                                  |
|---------------------------|-------------------------------------------------------------------------------|
| `type`                    | Which type(s) to list                                                         |
| `number`                  | How many per page (default 10; `0` or less for all)                           |
| `offset`                  | Skip this many                                                                |
| `orderby`                 | `published` (default), `updated`, `title`, `author`, `position`, or any field |
| `order`                   | `desc` with no `orderby`, else `asc` (default), or `desc`                     |
| `terms`                   | Only entries in these terms, such as `{tag: [php]}`                           |
| `author`                  | Only entries crediting these profiles, through any credit relation            |
| `names` / `names_exclude` | Only, or never, these slugs                                                   |
| `meta_key` / `meta_value` | Only entries whose field has this value                                       |
| `year` … `second`         | Only entries published in this period: `year`, then `month`, down to `second`, each with the ones before it |

## All type options

No type takes a folder: each is kept in `_` and its name (`page`, at
the content root). An older definition naming one (`folder`, or 1.x's
`path`) is refused from a plugin; one in `user/data/types/` is read in
`_` and its name until it's moved (see
[Moving a type into its folder](#moving-a-type-into-its-folder)).

Every kind takes these:

| Option              | Default                                                        | What it does                                                     |
|---------------------|----------------------------------------------------------------|------------------------------------------------------------------|
| `labels`            | Made from the name                                             | [Names in the admin](#names-descriptions-and-icons-in-the-admin) |
| `description`       |                                                                | What the type is for, in a sentence                              |
| `icon`              | Its kind's                                                     | An icon for the admin, by name                                   |
| `public`            | `true`                                                         | Whether the type is visible on the site at all                   |
| `sitemap`           | `true`                                                         | Whether entries appear in the sitemap                            |
| `llms`              | `true` for collections and trees, `false` for profiles         | Whether entries are listed in [`llms.txt`](configuration.md#markdown-pages-and-llmstxt). Their Markdown copies stay either way |
| `fields` / `closed` |                                                                | [Custom fields](#custom-fields)                                  |
| `filename`          | `{slug}`                                                       | How new entries' files are named ([below](#naming-new-files))    |

Collections and trees also take this:

| Option   | Default                    | What it does                                                                            |
|----------|----------------------------|-----------------------------------------------------------------------------------------|
| `byline` | Its only credit relation   | The [credit relation](#crediting-people) its byline uses, by name, when it has several |

Trees also take this:

| Option   | Default   | What it does                                                          |
|----------|-----------|-----------------------------------------------------------------------|
| `prefix` | Its name  | The path its pages are served under ([Trees](#trees)); not for `page` |

Collections and the profiles type also take these:

| Option    | Default       | What it does                                                                                                                      |
|-----------|---------------|-----------------------------------------------------------------------------------------------------------------------------------|
| `urls`    | Standard URLs | `false` for no URLs of its own, or [custom URLs](#custom-urls)                                                                    |
| `listing` |               | How the listing page lists entries ([above](#listing-entries))                                                                    |
| `feed`    | `false`       | RSS, Atom, and JSON feeds: `true`, or a `TypeFeed` with `categories` (the [type of terms](#terms-and-relationships) used for each item's categories) and `listing` |
| `folders` |               | The [folders its files are kept in](#folders-for-many-files) inside its own: `{year}`, `{year}/{month}`, or `{initial}`             |

Only collections take:

| Option         | Default     | What it does                                                                                     |
|----------------|-------------|--------------------------------------------------------------------------------------------------|
| `dateArchives` | `none`      | Date archives: `year`, `month`, `day`, `hour`, `minute`, or `second`                             |
| `hierarchical` | `false`     | Whether an entry may name a `parent` entry ([Nesting and order](#nesting-and-order))             |
| `order`        | `published` | `published` (newest first) or `position` (lowest first, then title) ([Nesting and order](#nesting-and-order)) |

### Naming new files

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
or `.`. Dates are the entry's publish date; every new entry gets one.

A pattern names files, never folders (a collection's folders have
[their own pattern](#folders-for-many-files)). A page's subpages go in a folder
named for its slug (`about/team.md` under `2026-10-05.about.md`), and a
page kept as a folder (`about/index.md`) keeps its folder's name.

Changing the pattern renames nothing. An entry's address comes from the
part of its file name after the last `.`, so files named by an older
pattern keep working beside new ones. Renaming an entry keeps whatever
comes before its slug. Listings never sort by file name, so mixed
names list in order.

When a type's own pattern has a date in it, a new publish date renames
the entry's file in the admin: changing the date and saving, or
publishing an entry that had no date yet. The file is named by the
pattern, with its translations named after it, the way
`content:filenames` names it. Trashing, restoring, and switching to
draft and back never change an entry's date, so they never rename it.
No address changes.

To rename older files to the pattern, run `bin/blush content:filenames`
to see what would change, then `bin/blush content:filenames --write`
(add `--type=post` for one type), or use **File Names** on Content
health in the admin. Each entry's file is renamed, with its translations
named after it. Dates are each entry's publish date as its file writes
it (`2013-02-09 00:00:00 -5` names the 9th, wherever your site is), or
its `updated` date, or when the file last changed. It leaves alone
hidden files (`_`-prefixed), entries kept as folders, entries with a
translation kept as one, and entries whose date isn't a real date
(such as a `2007-00-00` placeholder, which `content:lint` warns of).
No address changes.

It renames only types that set `filename` themselves. Renaming replaces
everything before the slug, so check the list first: a same-day counter
(`2026-10-05-2.hello.md`) is dropped, and a file whose date differs
from its publish date takes the publish date.

In the admin, choose it under **File names** on the type's screen.

### Collections are flat

A collection's entries are files, never folders of their own:
`_post/hello.md`, not `_post/hello/index.md`. Unless it sets
[folders for many files](#folders-for-many-files), they're directly in
its folder, not in `_post/2024/hello.md`. Only `_` folders
(`_post/_drafts`) may sit inside one, and they can hold its files. `content:lint` reports an entry kept as a folder, or in a
folder the collection doesn't keep files in, as an error. To move
them, run `bin/blush content:folders` to see what would change, then
`bin/blush content:folders --write`, or use **Collection Folders** on
Site Health in the admin. Each moves into its folder under its own name
(`_post/hello/index.md` becomes `_post/hello.md`), and folders left
empty are removed.

A [nesting collection](#nesting-and-order) is flat too: its entries
name their parents in front matter rather than sitting in their folders.

### Folders for many files

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
[database](going-live.md#large-sites-sqlite) has no folders to fill, so
the admin doesn't offer either there.

These folders are only where files are kept. They're never part of an
entry's address or how it's found: `_post/2026/hello.md` is the post
`hello`, at the same URL as `_post/hello.md`. Use `slug:` or a
[file name pattern](#naming-new-files) to tell apart posts with the same
slug in different years.

- **New entries** go in the folder their publish date and slug give
  them, and so do copies.
- **A new date or slug moves the file**, as a new date renames a file
  [named by date](#naming-new-files): changing a post's publish date
  from 2025 to 2026 moves it from `_post/2025` to `_post/2026`.
- **Changing the pattern moves nothing.** Older files keep working
  where they are; `content:lint` warns of each file outside the
  pattern's folders. `bin/blush content:folders --write`, or
  **Collection Folders** on Site Health, moves them, by the publish date
  as the file writes it (else its updated date).
- **Hidden files stay where they are.** A `_` file (`_post/_authors.md`)
  or a file in a `_` folder (`_post/_drafts/idea.md`) is kept outside
  the pattern's folders, and isn't moved into them.

In the admin, choose it under **Folders** on the type's screen: None,
By year, By year and month, or By first letter.

For the profiles type, `urls` sets where profiles' pages are (its
`prefix`, `profiles` by default), `listing` how a profile's page lists
the entries crediting them, and `feed` whether each profile has a feed.
It doesn't take `dateArchives`, and nothing answers at
`/profiles` itself.

### Custom URLs

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
[tree](#trees) takes `prefix` on its own, since it has no other URL
settings.) `single` (an
entry) and `collection` (the listing page) set the rest; `paths` sets any
other route key that `routes:list` shows, such as
`['collection.paged' => 'p/{page}']`.

Each [relation with an archive word](#linking-entries-to-other-entries)
from the type, [credits](#people-archives) included, adds route keys of
its own, named after the relation: `authors.collection`,
`authors.single`, `authors.single.paged`, and the `authors.single.feed`
keys. `paths` can move those too. Their paths hold `{target}`, the
linked entry's slug. The admin lists them with the type's other
addresses.

Single-entry paths can use `{name}`, `{year}`, `{month}`, `{day}`,
`{hour}`, `{minute}`, `{second}`, `{profile}` (the first person
credited), and the name of any [classify relation](#terms-and-relationships)
(the first term filed under, such as `{category}`). If
someone reaches a post by a wrong date, they're redirected to the right
one.

Run `bin/blush routes:list` to see every URL your types create. The
admin's **Addresses** panel on a type's screen edits these paths too
(see [Editing a type](admin.md#editing-a-type)).

## Giving pages fields

To declare fields for pages, redefine the built-in `page` type in
`user/data/types/page.json`:

```json
{
	"kind": "tree",
	"fields": [{ "name": "subtitle", "type": "text" }]
}
```

A plugin can do the same with `new Tree(fields: [new TextField('subtitle')])`.

## Crediting people

A type credits people through a **credit relation**: a
[relation](#terms-and-relationships) of kind `credit`, from the types
that credit, to the [profiles](#built-in-types) type. Each names its
front matter key in the types' own words: a blog credits its authors,
and a recipe box its cooks and photographers. Every credit points at the
same profiles, so Jane is one profile whether she wrote a post or
cooked a recipe.

Nothing credits anyone until a relation says so. The skeleton ships
`user/data/relations/authors.json`, crediting posts' authors:

```json
{
	"kind": "credit",
	"from": ["post"],
	"to": ["profile"],
	"aliases": ["author"],
	"label": "Authors"
}
```

To credit authors on another type, add it to `from`. The admin's
new-type form does that when you ask for authors. More credits are more
relations:

```json
{
	"kind": "credit",
	"from": ["recipe"],
	"to": ["profile"],
	"label": "Cooks",
	"required": true
}
```

In PHP, `Relation::authors(['post', 'recipe'], 'profile')` is the
`authors` relation above, and other credits are a `Relation` of
`RelationKind::Credit`. A credit takes the [relation
options](#relation-options), with these differences: `to` is the
profiles type and nothing else, it's always `ordered` (the order names
are written in is the byline's), a translation adds its own names to
its original's (`translations: add`), and its `inverse` has a `page`
and an `archive` by default ([below](#people-archives)). `singular`
names one ("Cook"), made from the label when it's left out.

A type with one credit uses it for the byline. A type with several
names its byline with the type's `byline` option. In
`user/data/types/recipe.json`:

```json
{
	"byline": "cooks"
}
```

The default theme's byline uses that relation ("By Jane") and labels
the rest ("Photographer: Sam").

### People archives

A credit's `inverse.archive` is a word (its name, by default), so every
type it credits gets two kinds of page under its own prefix, as [any
relation with an archive word](#linking-entries-to-other-entries) does:

- `/recipes/cooks` lists the people at least one published recipe
  credits as a cook, by name, each with their bio's start.
- `/recipes/cooks/jane` is Jane's archive there: her bio, then the
  recipes crediting her as a cook, listed and paged as the type lists
  them, with feeds at `/recipes/cooks/jane/feed` (and `/feed/atom`,
  `/feed/json`) when the type has a feed. Someone no recipe credits as a
  cook has no archive there.

`"inverse": {"archive": false}` turns them off. Bylines link to the
archive under the entry's own type, or to the profile's page when the
credit has no archives. The sitemap includes the archives.

To give the list a title and an introduction, add a page named after
the word to the type's folder: `user/content/_recipe/_cooks.md`. Its
title replaces "Cooks" and its body introduces the list.

To write something for one person's archive instead of their bio, add
`user/content/_recipe/_cooks/jane.md`. While it's published, its title
and body introduce Jane's cook archive; the profile's bio is used
otherwise.

The leading underscore keeps both kinds of page out of the type's
listings and feeds, so they have no address of their own. Every
relation with an archive word takes the same two pages.

### Profile pages

Each profile has a page of its own at `/profiles/jane`: the profile,
then every published entry of any type crediting them, newest first
unless the profiles type's `listing` says otherwise. It's a credit's
`inverse.page`, on by default. A profile has a page before anything
credits them.

They're at `/profiles/{name}`, with their files in `_profile/`. To move
their pages, redefine the `profile` type. For example, a 1.x site with
its author pages at `/authors/jane`, `user/data/types/profile.json`:

```json
{
	"kind": "profiles",
	"urls": { "prefix": "authors" }
}
```

Its author files move from `authors/` to `_profile/`.

## Turning off a built-in type

If you don't want profiles, in `config/content.php`:

```php
return new ContentConfig(disabled: ['profile']);
```
