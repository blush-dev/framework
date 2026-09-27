# Content types

Out of the box, every entry is a **page**. That's enough for a simple
site. When you want a blog, a portfolio, or a recipe box, where entries
are listed, dated, tagged, or given their own fields, define a **content
type**.

Each content type owns a folder in `user/content/`. Entries in that folder
belong to it, and its `index.md` becomes the listing page.

## Three kinds of type

Every content type is one of three kinds:

- **Collection:** entries that are listed, such as posts, recipes, or
  projects. A collection has a listing page, and it can have a feed and
  date archives.
- **Taxonomy:** entries that group other entries, such as tags,
  categories, or series. Each entry in a taxonomy is a **term**, and each
  term gets a page listing the entries in it.
- **Pages:** the built-in `page` type. It holds every entry that isn't in
  another type's folder, and serves each one at its file path.

## Built-in types

- **`page`** (pages): every entry that isn't in another type's folder.
- **`author`** (a taxonomy): people, in `user/content/authors/`. Entries
  list their authors with `authors: jane`, and each author gets an archive
  at `/authors/jane`. You don't need an author file; Blush makes a
  stand-in from the slug until you write one.

## Two ways to define a type

**In YAML or JSON** (no PHP needed): create a file in `user/data/types/`
named after the type. Use `kind` to pick the kind; it's `collection` if
you leave it out.

```yaml
# user/data/types/recipe.yaml
folder: recipes
```

**In PHP**, in `config/content.php`, which gives you editor autocomplete
and type checking. Each kind is its own class: `Collection`, `Taxonomy`,
or `Pages`.

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

Both do the same thing, and every option below works in either one. (In
YAML, use the option names as keys.) A type's name uses lowercase letters,
digits, and underscores.

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
		// Posts live in user/content/blog/ and are listed at /blog.
		new Collection(
			'post',
			folder: 'blog',
			listing: new Listing(orderBy: 'published', order: Order::Desc),
			feed: new TypeFeed(),
			dateArchives: DateArchives::Month
		),

		// Tags live in user/content/blog/tags/.
		new Taxonomy(
			'tag',
			folder: 'blog/tags',
			types: ['post']
		)
	]
);
```

The same types in YAML:

```yaml
# user/data/types/post.yaml
folder: blog
listing:
  orderBy: published
  order: desc
feed: true
dateArchives: month
```

```yaml
# user/data/types/tag.yaml
kind: taxonomy
folder: blog/tags
types: [post]
```

Now:

| File | URL |
|---|---|
| `blog/index.md` | `/blog` (the post listing, newest first) |
| `blog/2026-09-26.hello.md` | `/blog/hello` |
| | `/blog/2026` and `/blog/2026/09` (date archives) |
| | `/blog/feed`, `/blog/feed/atom`, `/blog/feed/json` |
| `blog/tags/php.md` | `/blog/tags/php` (every post tagged `php`) |

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
- Each term has a page listing its entries, at `/{folder}/{slug}`. It
  lists the entries of `types` (every type if you leave it empty), and
  `termListing` sets how.
- The taxonomy's own `listing` sets how its listing page (such as
  `/blog/tags`) lists the terms.
- You don't have to create a file for every term. A term without one gets
  a stand-in page titled after its slug. Add a file (such as
  `blog/tags/php.md`) to give it a proper title and description.

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

| Type | Holds | Extra options |
|---|---|---|
| `text` | A line of text | |
| `markdown` | Formatted text | |
| `date` | A date and time | |
| `bool` | `true` or `false` | |
| `number` | A number | `integer`, `min`, `max` |
| `enum` | One of a set of values | `options` |
| `list` | Several values | `item` (a field definition for each value; text by default) |
| `reference` | Other entries, by slug | `to` (the type), `multiple` (default `true`) |
| `media` | A media file | |
| `slug` | A URL-safe name | |
| `object` | A group of fields | `fields`, `closed` |

Every field also takes `required`, `default`, `aliases` (other keys it's
read from), `label`, and `description`.

Keys you don't declare are still kept, and `content:lint --strict` lists
them. Set `closed: true` on the type to make them errors instead.

## Listing entries

A type's `listing` option sets how its listing page lists entries:

```php
new Listing(orderBy: 'published', order: Order::Desc, perPage: 20)
```

| Option | Default | What it does |
|---|---|---|
| `type` | The type itself | Which type to list |
| `orderBy` | `filename` | `filename`, `published`, `updated`, `title`, `author`, or any field |
| `order` | `asc` | `asc` or `desc` (`Order::Asc` or `Order::Desc` in PHP) |
| `perPage` | `10` | How many per page; `0` (`Listing::ALL`) for all of them |
| `query` | | Any other option from the table below, such as `{terms: {tag: [php]}}` |

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

| Option | What it does |
|---|---|
| `type` | Which type(s) to list |
| `number` | How many per page (default 10; `0` or less for all) |
| `offset` | Skip this many |
| `orderby` | `filename` (default), `published`, `updated`, `title`, `author`, or any field |
| `order` | `asc` (default) or `desc` |
| `terms` | Only entries in these terms, such as `{tag: [php]}` |
| `author` | Only entries by these authors |
| `names` / `names_exclude` | Only, or never, these slugs |
| `meta_key` / `meta_value` | Only entries whose field has this value |
| `year` … `second` | Only entries published in this period |

## All type options

Every kind takes these:

| Option | Default | What it does |
|---|---|---|
| `folder` | The name (`''`, the content root, for pages) | The folder under `user/content/` |
| `public` | `true` | Whether the type is visible on the site at all |
| `sitemap` | `true` | Whether entries appear in the sitemap |
| `fields` / `closed` | | [Custom fields](#custom-fields) |

Collections and taxonomies also take these:

| Option | Default | What it does |
|---|---|---|
| `urls` | Standard URLs | `false` for no URLs of its own, or [custom URLs](#custom-urls) |
| `listing` | | How the listing page lists entries ([above](#listing-entries)) |
| `feed` | `false` | RSS, Atom, and JSON feeds: `true`, or a `TypeFeed` with `categories` (the taxonomy used for each item's categories) and `listing` |

Only collections take:

| Option | Default | What it does |
|---|---|---|
| `dateArchives` | `none` | Date archives: `year`, `month`, `day`, `hour`, `minute`, or `second` |

Only taxonomies take:

| Option | Default | What it does |
|---|---|---|
| `types` | Every type | The types a term's page lists |
| `field` / `aliases` | The name | The front matter key entries use to join a term, and other keys it's read from |
| `termListing` | | How a term's page lists entries |

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

`prefix` replaces the folder at the start of every URL. `single` (an
entry) and `collection` (the listing page) set the rest; `paths` sets any
other route key that `routes:list` shows, such as
`['collection.paged' => 'p/{page}']`.

Single-entry paths can use `{name}`, `{year}`, `{month}`, `{day}`,
`{hour}`, `{minute}`, `{second}`, `{author}`, and any taxonomy's name. If
someone reaches a post by a wrong date, they're redirected to the right
one.

Run `bin/blush routes:list` to see every URL your types create.

## Giving pages fields

To declare fields for pages, redefine the built-in `page` type:

```php
use Blush\Content\Schema\Fields\TextField;
use Blush\Content\Type\Pages;

return new ContentConfig(
	types: [
		new Pages(fields: [new TextField('subtitle')])
	]
);
```

In YAML, that's `user/data/types/page.yaml` with `kind: pages` and
`fields`.

## Turning off a built-in type

If you don't want author archives:

```php
return new ContentConfig(disabled: ['author']);
```
