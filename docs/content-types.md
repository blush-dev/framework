# Content types

Out of the box, every entry is a **page**. That's enough for a simple
site. When you want a blog, a portfolio, or a recipe box, where entries
are listed, dated, tagged, or given their own fields, define a **content
type**.

Each content type owns a folder in `user/content/`. Entries in that folder
belong to it, and its `index.md` becomes the listing page.

## Built-in types

- **`page`:** every entry that isn't in another type's folder.
- **`author`:** people, in `user/content/authors/`. Entries list their
  authors with `authors: jane`, and each author gets an archive at
  `/authors/jane`. You don't need an author file; Blush makes a stand-in
  from the slug until you write one.

## Two ways to define a type

**In YAML or JSON** (no PHP needed): create a file in `user/data/types/`
named after the type.

```yaml
# user/data/types/recipe.yaml
path: recipes
```

**In PHP**, in `config/content.php`, which gives you editor autocomplete
and type checking:

```php
<?php

declare(strict_types=1);

use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\ContentType;

return new ContentConfig(
	types: [
		new ContentType('recipe', path: 'recipes')
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

use Blush\Content\Type\ArchiveGranularity;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\TypeFeed;

return new ContentConfig(
	types: [
		// Posts live in user/content/blog/ and are listed at /blog.
		new ContentType(
			'post',
			path: 'blog',
			collection: ['orderby' => 'published', 'order' => 'desc'],
			feed: new TypeFeed(),
			archives: ArchiveGranularity::Month
		),

		// Tags live in user/content/blog/tags/.
		new ContentType(
			'tag',
			path: 'blog/tags',
			taxonomy: true,
			termCollect: 'post'
		)
	]
);
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

A taxonomy is a type whose entries group other entries: tags, categories,
series. Set `taxonomy: true`, and `termCollect` to the type it groups.

- Entries join a term with a front matter key named after the taxonomy
  (`tag: php`, or a list). Use `field` to pick another key, and
  `fieldAliases` to accept more than one.
- Each term has a page listing its entries, at `/{path}/{slug}`.
- You don't have to create a file for every term. A term without one gets
  a stand-in page titled after its slug. Add a file (such as
  `blog/tags/php.md`) to give it a proper title and description.

## Custom fields

Declare the fields a type's entries use, and Blush checks them when it
indexes your content and in `bin/blush content:lint`:

```yaml
# user/data/types/recipe.yaml
path: recipes
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

A type's `collection` option controls its listing page. The same options
work in any page's front matter, to list entries on that page:

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

| Option | Default | What it does |
|---|---|---|
| `path` | The name | The folder under `user/content/` |
| `public` | `true` | Whether the type is visible on the site at all |
| `routing` | Standard URLs | `false` for no URLs of its own, or a `prefix` and `paths` (below) |
| `collection` | | How the listing page lists entries (see above) |
| `taxonomy` | `false` | Whether entries are terms that group other entries |
| `field` / `fieldAliases` | The name | A taxonomy's front matter key, and other keys it's read from |
| `termCollect` | Every type | The type a taxonomy's term pages list |
| `termCollection` | | How term pages list entries |
| `collect` | The type itself | The type the listing page lists, or `false` for none |
| `feed` | `false` | RSS, Atom, and JSON feeds for the listing (`true`, or `taxonomy` and `collection` settings) |
| `sitemap` | `true` | Whether entries appear in the sitemap |
| `archives` | `none` | Date archives: `year`, `month`, `day`, `hour`, `minute`, or `second` |
| `fields` / `closed` | | [Custom fields](#custom-fields) |

### Custom URLs

`routing` moves a type's URLs. For example, to serve posts from `blog/`
at `/archives/2026/09/26/hello`:

```php
use Blush\Content\Type\TypeRouting;

new ContentType(
	'post',
	path: 'blog',
	routing: new TypeRouting(
		prefix: 'archives',
		paths: ['single' => '{year}/{month}/{day}/{name}']
	),
	archives: ArchiveGranularity::Day
);
```

Single-entry paths can use `{name}`, `{year}`, `{month}`, `{day}`,
`{hour}`, `{minute}`, `{second}`, `{author}`, and any taxonomy's name. If
someone reaches a post by a wrong date, they're redirected to the right
one.

Run `bin/blush routes:list` to see every URL your types create.

## Turning off a built-in type

If you don't want author archives:

```php
return new ContentConfig(disabled: ['author']);
```
