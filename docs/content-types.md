# Content types

Out of the box, every entry is a **page**. That's enough for a simple
site. When you want a blog, a portfolio, or a recipe box, where entries
are listed, dated, tagged, or given their own fields, define a **content
type**.

Each content type owns a folder in `user/content/`. Entries in that folder
belong to it, and its `index.md` becomes the listing page. The folder is
the type's name after an underscore (`_recipe` for `recipe`) unless you
choose one. The underscore keeps type folders apart from your page
folders, and it's left out of URLs: `_recipe/` is served at `/recipe`.

## Four kinds of type

Every content type is one of four kinds:

- **Collection:** entries that are listed, such as posts, recipes, or
  projects. A collection has a listing page, and it can have a feed and
  date archives.
- **Taxonomy:** entries that group other entries, such as tags,
  categories, or series. Each entry in a taxonomy is a **term**, and each
  term gets a page listing the entries in it.
- **Tree:** entries that nest by folder, each served at its file path.
  The built-in `page` type is a tree: it holds every entry that isn't in
  another type's folder, and `about/team.md` is a subpage of `about.md`
  (or `about/index.md`). You can add [trees of your own](#trees), such as
  docs.
- **Profiles:** the built-in `profile` type, the people your entries
  credit. A site has one.

## Built-in types

- **`page`** (tree): every entry that isn't in another type's folder.
- **`profile`** (profiles): people, in `user/content/profiles/`. Each
  file is one person: the title is their public name, `subtitle` a line
  under it (such as "Food editor"), `avatar` a portrait from
  `user/media`, and the body their bio. Each profile has a page at
  `/profiles/jane`. Entries credit them through the
  [people fields](#crediting-people) of their type, such as
  `authors: jane`. You don't need a profile file; Blush uses the name as
  the entry writes it until you add one, and `content:lint` warns about
  it. An [account](accounts.md#profiles) can be linked to a profile.

## Three ways to define a type

**In YAML or JSON** (no PHP needed): create a file in `user/data/types/`
named after the type. Use `kind` to pick the kind; it's `collection` if
you leave it out.

```yaml
# user/data/types/recipe.yaml
folder: recipes
```

**In PHP**, in `config/content.php`, which gives you editor autocomplete
and type checking. Each kind is its own class: `Collection`, `Taxonomy`,
`Tree`, or `Profiles`.

```php
<?php

declare(strict_types=1);

use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentConfig;

return new ContentConfig(
	types: [
		new Collection('recipe', folder: 'recipes')
	]
);
```

**In a plugin**, with the same classes, when the types belong with
code you install, like a plugin registering post types in WordPress. See
[Content types from a plugin](extending.md#content-types-from-a-plugin).

### Changing a type from code

A file in `user/data/types/` named after a collection, taxonomy, or
[tree](#trees) from
`config/content.php` or a plugin changes that type rather than
defining a new one. Each option it sets replaces the code's, and the
rest stay as the code has them:

```yaml
# user/data/types/post.yaml: posts are defined in config/content.php
description: Writing, mostly.
feed: false
```

This is what the admin writes when you edit such a type, and it keeps
only what differs from the code. A file like this can't change the
type's kind or folder, and the `page` type and the profiles type from
code can't be changed this way. Delete the file to go back to the code's
definition. With `dataTypes` off, these files aren't read either.

All three do the same thing, and every option below works in each. (In
YAML, use the option names as keys.) A type's name uses lowercase letters,
digits, and underscores.

If two places define the same type, `config/content.php` replaces a
plugin's type, and both replace a built-in one. A YAML type may
replace a built-in type, but not one from a plugin or
`config/content.php`; that's an error. Two plugins can't define the
same type.

Which to pick:

- **YAML** keeps a type with your content, so a copy of `user/` carries
  it along. It's the only kind the [admin](admin.md#content-types) can
  create and edit.
- **`config/content.php`** keeps it with your site's code.
- **A plugin** keeps it with a feature you can reuse or version on its
  own.

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
| `newItem`     | `item`     | New literary form     | The New button and screen                      |
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

```yaml
# user/data/types/person.yaml
folder: people
labels:
  plural: People
  newItem: Add someone
```

```yaml
# user/data/types/literary_form.yaml
kind: taxonomy
labels:
  menu: Forms
```

In PHP, `TypeLabels` takes `singular` first and the rest by name:

```php
use Blush\Content\Type\TypeLabels;

new Collection('person', folder: 'people', labels: new TypeLabels('Person', plural: 'People'))
```

`description` says what the type is for, in a sentence. The admin shows it
on the type's screen and on its list while it's empty. `icon` names an
icon to show the type with in the admin's menu, from the icons the `icon`
component offers (`bin/blush icon:list`), such as `film` or
`book-open`. Without one, the type gets its kind's icon.

```yaml
# user/data/types/recipe.yaml
folder: recipes
description: Dishes we cook at home, with what goes in them.
icon: notebook-pen
```

## Example: a blog

```php
<?php

declare(strict_types=1);

use Blush\Content\Query\Order;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\Listing;
use Blush\Content\Type\Taxonomy;
use Blush\Content\Type\TypeFeed;

return new ContentConfig(
	types: [
		// Posts live in user/content/_blog/ and are listed at /blog.
		new Collection(
			'post',
			folder: '_blog',
			listing: new Listing(orderBy: 'published', order: Order::Desc),
			feed: new TypeFeed(),
			dateArchives: DateArchives::Month
		),

		// Tags live in user/content/_blog/tags/.
		new Taxonomy(
			'tag',
			folder: '_blog/tags',
			types: ['post']
		)
	]
);
```

The same types in YAML:

```yaml
# user/data/types/post.yaml
folder: _blog
listing:
  orderBy: published
  order: desc
feed: true
dateArchives: month
```

```yaml
# user/data/types/tag.yaml
kind: taxonomy
folder: _blog/tags
types: [post]
```

Now:

| File                        | URL                                                |
|-----------------------------|----------------------------------------------------|
| `_blog/index.md`            | `/blog` (the post listing, newest first)           |
| `_blog/2026-09-26.hello.md` | `/blog/hello`                                      |
|                             | `/blog/2026` and `/blog/2026/09` (date archives)   |
|                             | `/blog/feed`, `/blog/feed/atom`, `/blog/feed/json` |
| `_blog/tags/php.md`         | `/blog/tags/php` (every post tagged `php`)         |

Create a post with:

```sh
bin/blush content:new post "Hello world"
```

Tag it in its front matter:

```yaml
tag: [php, news]
```

### Making the blog the home page

To show the latest posts on the home page, name the type as `home`:

```php
return new ContentConfig(
	home: 'post',
	types: [ /* ... */ ]
);
```

The home page then lists posts, with `/page/2` and so on, and the feed
moves to `/feed`. It takes the place of `user/content/index.md`, which is
no longer shown.

## Taxonomies

A taxonomy's entries are terms that group other entries. Set `types` to
the types it groups.

- Entries join a term with a front matter key named after the taxonomy
  (`tag: php`, or a list). Use `field` to pick another key, and `aliases`
  to accept more than one.
- Each term has a page listing its entries, at `/{folder}/{slug}` (without
  the folder's underscores). It
  lists the entries of `types` (every type if you leave it empty), and
  `termListing` sets how.
- The taxonomy's own `listing` sets how its listing page (such as
  `/blog/tags`) lists the terms.
- You don't have to create a file for every term. A term without one gets
  a stand-in page titled after its slug. Add a file (such as
  `_blog/tags/php.md`) to give it a proper title and description.

### Hierarchical taxonomies

Set `hierarchical: true` to let a term sit under another, like categories
with subcategories. A term names its parent by slug in its front matter:

```yaml
---
title: CSS
parent: web-design
---
```

Term files stay side by side in the taxonomy's folder, so moving a term
changes one line. A term's URL follows the tree: with `css` under
`web-design` under `web`, it's `/topics/web/web-design/css`, and its
later pages and feeds are under that (`…/css/page/2`, `…/css/feed`).
Any other path to the term, such as `/topics/css` or its address before
it moved, redirects there. Slugs are unique across the whole taxonomy, as
they are for any taxonomy. A term below the top can't be slugged `page`
or `feed`, since those words start its paged and feed URLs.

A term's page still lists only the entries in that term, not those in its
child terms. Themes show the tree with `$template->parent()`,
`$template->ancestors()`, and `$template->children()` (see
[Themes](themes.md)), and the admin shows each term's parents before its
title. `bin/blush content:lint` reports a parent with no file (the term
is shown at the top level) and a term that's its own ancestor.

## Trees

A tree is a type whose entries nest by folder, as pages do, in a folder
of its own. Use one for docs, a manual, or a handbook:

```php
use Blush\Content\Type\Tree;

new Tree('doc', folder: '_docs', icon: 'book')
```

In YAML, that's `user/data/types/doc.yaml` with `kind: tree`, which the
admin's **New Content Type** writes too. A tree from `config/content.php`
can be [changed from the admin](#changing-a-type-from-code), as
collections and taxonomies can; the `page` type can't.

- Each entry is served at its path in the folder, without the folder's
  underscores: `_docs/install/requirements.md` is at
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

A tree's folder defaults to `_` and its name, like other types; only the
`page` type sits at the content root.

## Custom fields

Declare the fields a type's entries use, and Blush checks them when it
indexes your content and in `bin/blush content:lint`:

```yaml
# user/data/types/recipe.yaml
folder: recipes
fields:
  - name: servings
    type: number
    integer: true
    required: true
  - name: difficulty
    type: enum
    options: [easy, medium, hard]
    default: easy
  - name: ingredients
    type: list
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
| `reference` | Other entries, by slug | `to` (the type), `multiple` (default `true`)                                                                        |
| `media`     | A media file           | `kind`: `image`, `video`, `audio`, or `file`, so the admin's picker offers only those (any file when it's left out) |
| `slug`      | A URL-safe name        |                                                                                                                     |
| `object`    | A group of fields      | `fields`, `closed`                                                                                                  |

Every field also takes `required`, `default`, `aliases` (other keys it's
read from), `label`, `description`, and `control`.

`fields` can also be a map of names to definitions, which some people
find easier to read:

```yaml
fields:
  servings:
    type: number
    integer: true
  difficulty:
    type: enum
    options: [easy, medium, hard]
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

```yaml
fields:
  - name: difficulty
    type: enum
    options: [easy, medium, hard]
    control: radios
  - name: diets
    type: list
    control: checks
    item:
      type: enum
      options: [vegetarian, vegan, gluten-free]
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
`targets`, as `type:` and the type's name:

```yaml
# user/data/fields/seo.yaml
label: SEO
description: How the entry appears in search results.
targets: [type:post, type:page]
fields:
  meta_title:
    type: text
    label: Title for search engines
  noindex:
    type: bool
    label: Hide from search engines
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
`media:video`, `media:audio`, or `media:file` (see
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
`# yaml-language-server: $schema=../../../vendor/blush-dev/framework/resources/schemas/field-set.schema.json`
(or a `"$schema"` key in JSON).

## Listing entries

A type's `listing` option sets how its listing page lists entries:

```php
new Listing(orderBy: 'published', order: Order::Desc, perPage: 20)
```

| Option    | Default         | What it does                                                           |
|-----------|-----------------|------------------------------------------------------------------------|
| `type`    | The type itself | Which type to list                                                     |
| `orderBy` | `filename`      | `filename`, `published`, `updated`, `title`, `author`, or any field    |
| `order`   | `asc`           | `asc` or `desc` (`Order::Asc` or `Order::Desc` in PHP)                 |
| `perPage` | `10`            | How many per page; `0` (`Listing::ALL`) for all of them                |
| `query`   |                 | Any other option from the table below, such as `{terms: {tag: [php]}}` |

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
| `orderby`                 | `filename` (default), `published`, `updated`, `title`, `author`, or any field |
| `order`                   | `asc` (default) or `desc`                                                     |
| `terms`                   | Only entries in these terms, such as `{tag: [php]}`                           |
| `author`                  | Only entries crediting these profiles, through any people field               |
| `names` / `names_exclude` | Only, or never, these slugs                                                   |
| `meta_key` / `meta_value` | Only entries whose field has this value                                       |
| `year` … `second`         | Only entries published in this period                                         |

## All type options

Every kind takes these:

| Option              | Default                                                        | What it does                                                     |
|---------------------|----------------------------------------------------------------|------------------------------------------------------------------|
| `folder`            | `_` and the name (`''`, the content root, for the `page` type) | The folder under `user/content/`                                 |
| `labels`            | Made from the name                                             | [Names in the admin](#names-descriptions-and-icons-in-the-admin) |
| `description`       |                                                                | What the type is for, in a sentence                              |
| `icon`              | Its kind's                                                     | An icon for the admin, by name                                   |
| `public`            | `true`                                                         | Whether the type is visible on the site at all                   |
| `sitemap`           | `true`                                                         | Whether entries appear in the sitemap                            |
| `fields` / `closed` |                                                                | [Custom fields](#custom-fields)                                  |

Collections, taxonomies, and trees also take this:

| Option   | Default                                   | What it does                                                                                                                                |
|----------|-------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------|
| `people` | `authors` for collections, none otherwise | How entries credit people ([below](#crediting-people)). `authors: true` or `authors: false` is short for the `authors` field alone, or none |

Collections, taxonomies, and the profiles type also take these:

| Option    | Default       | What it does                                                                                                                      |
|-----------|---------------|-----------------------------------------------------------------------------------------------------------------------------------|
| `urls`    | Standard URLs | `false` for no URLs of its own, or [custom URLs](#custom-urls)                                                                    |
| `listing` |               | How the listing page lists entries ([above](#listing-entries))                                                                    |
| `feed`    | `false`       | RSS, Atom, and JSON feeds: `true`, or a `TypeFeed` with `categories` (the taxonomy used for each item's categories) and `listing` |

Only collections take:

| Option         | Default | What it does                                                         |
|----------------|---------|----------------------------------------------------------------------|
| `dateArchives` | `none`  | Date archives: `year`, `month`, `day`, `hour`, `minute`, or `second` |

Only taxonomies take:

| Option              | Default    | What it does                                                                   |
|---------------------|------------|--------------------------------------------------------------------------------|
| `types`             | Every type | The types a term's page lists                                                  |
| `field` / `aliases` | The name   | The front matter key entries use to join a term, and other keys it's read from |
| `termListing`       |            | How a term's page lists entries                                                |
| `hierarchical`      | `false`    | Whether a term may have a `parent` ([above](#hierarchical-taxonomies))         |

For the profiles type, `urls` sets where profiles' pages are (its
`prefix`, `profiles` by default, whatever the folder), `listing` how a profile's page lists
the entries crediting them, and `feed` whether each profile has a feed.
It doesn't take `people` or `dateArchives`, and nothing answers at
`/profiles` itself.

### Custom URLs

`urls` moves a type's URLs. For example, to serve posts from `blog/` at
`/archives/2026/09/26/hello`:

```php
use Blush\Content\Type\TypeUrls;

new Collection(
	'post',
	folder: 'blog',
	urls: new TypeUrls(prefix: 'archives', single: '{year}/{month}/{day}/{name}'),
	dateArchives: DateArchives::Day
);
```

`prefix` replaces the folder (less its underscores) at the start of every
URL. `single` (an
entry) and `collection` (the listing page) set the rest; `paths` sets any
other route key that `routes:list` shows, such as
`['collection.paged' => 'p/{page}']`.

Each [people field](#crediting-people) with archives adds route keys
of its own, such as `authors.collection`, `authors.single`,
`authors.single.paged`, and the `authors.single.feed` keys; `paths` can
move those too. Their paths hold `{profile}`.

Single-entry paths can use `{name}`, `{year}`, `{month}`, `{day}`,
`{hour}`, `{minute}`, `{second}`, `{profile}` (the first person
credited), and any taxonomy's name. If
someone reaches a post by a wrong date, they're redirected to the right
one.

Run `bin/blush routes:list` to see every URL your types create. The
admin's **Addresses** panel on a type's screen edits these paths too
(see [Editing a type](admin.md#editing-a-type)).

## Giving pages fields

To declare fields for pages, redefine the built-in `page` type:

```php
use Blush\Field\Fields\TextField;
use Blush\Content\Type\Tree;

return new ContentConfig(
	types: [
		new Tree(fields: [new TextField('subtitle')])
	]
);
```

In YAML, that's `user/data/types/page.yaml` with `kind: tree` and
`fields`.

## Crediting people

A type credits people through its **people fields**. Each is a front
matter key that names [profiles](#built-in-types), in the type's own
words: a blog credits its authors, and a recipe box its cooks and
photographers. Every field points at the same profiles, so Jane is one
profile whether she wrote a post or cooked a recipe.

Collections have one people field, `authors` (it reads `author` too),
unless you change it. Pages and taxonomies have none unless you add
them:

```yaml
# user/data/types/recipe.yaml
folder: recipes
people:
  cooks:
    required: true
  photographers:
    multiple: false
    aliases: [photographer]
```

```yaml
# user/data/types/page.yaml: pages credit authors too
kind: tree
authors: true
```

```yaml
# A collection that credits no one
folder: notes
authors: false
```

Each field takes these, all optional:

| Option                | Default                         | What it does                                         |
|-----------------------|---------------------------------|------------------------------------------------------|
| `plural` / `singular` | Made from the field's name      | What it's called: "Cooks", "Cook"                    |
| `aliases`             | `[]` (`[author]` for `authors`) | Other front matter keys it's read from               |
| `archive`             | The field's name                | The word its archives sit under, or `false` for none |
| `multiple`            | `true`                          | Whether an entry may credit several people           |
| `required`            | `false`                         | Whether an entry needs one before it's published     |

In PHP, `people` is a list of `PeopleField`s:

```php
use Blush\Content\Type\PeopleField;

new Collection('recipe', folder: 'recipes', people: [
	new PeopleField('cooks', required: true),
	new PeopleField('photographers', aliases: ['photographer'], multiple: false)
]);
```

`people: true` is the `authors` field alone, and `people: false` none. In
a type without a people field, its key in front matter is just an
undeclared key. A taxonomy's field wins over a people field reading the
same key, so a 1.x site with an `author` taxonomy keeps it.

The default theme's byline uses the first people field ("By Jane") and
labels the rest ("Photographer: Sam").

### People archives

A routed type gets two kinds of page under its own prefix for each
people field with archives, so a blog and a recipe box each have their
own:

- `/recipes/cooks` lists the people at least one published recipe
  credits as a cook, by name, each with their bio's start.
- `/recipes/cooks/jane` is Jane's archive there: her bio, then the
  recipes crediting her as a cook, listed and paged as the type lists
  them, with feeds at `/recipes/cooks/jane/feed` (and `/feed/atom`,
  `/feed/json`) when the type has a feed. Someone no recipe credits as a
  cook has no archive there.

Bylines link to the archive under the entry's own type and field, or to
the profile's page when that field has no archives. The sitemap and
static export include the archives.

To give a field's list a title and an introduction, add a page named
after the field to the type's folder: `user/content/recipes/_cooks.md`.
Its title replaces "Cooks" and its body introduces the list.

To write something for one person's archive instead of their bio, add
`user/content/recipes/_cooks/jane.md`. While it's published, its title
and body introduce Jane's cook archive; the profile's bio is used
otherwise.

The leading underscore keeps both kinds of page out of the type's
listings and feeds, so they have no address of their own.

### Profile pages

Each profile has a page of its own at `/profiles/jane`: the profile,
then every published entry of any type crediting them, newest file
first unless the profiles type's `listing` says otherwise. A profile
with a file has a page before anything credits them.

They're at `/profiles/{name}` wherever the files are. To keep them in
another folder, or move their pages, redefine the `profile` type. For
example, a 1.x site that keeps its author files in `authors/`, and its
author pages at `/authors/jane`:

```php
use Blush\Content\Type\Profiles;
use Blush\Content\Type\TypeUrls;

return new ContentConfig(types: [
	new Profiles(folder: 'authors', urls: new TypeUrls(prefix: 'authors'))
]);
```

Without `urls`, those profiles would still be at `/profiles/jane`.

## Turning off a built-in type

If you don't want profiles:

```php
return new ContentConfig(disabled: ['profile']);
```
