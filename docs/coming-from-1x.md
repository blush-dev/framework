# Coming from Blush 1.x

Blush 2 is a rewrite, but **your content doesn't change**. Every file,
folder, and front matter convention 1.x understood still works (terms
and authors need files, which one command writes, and taxonomy types
are written a new way; see below), and your URLs stay the same. What changes is the code around it: config, themes,
and the command line.

## Your content

Copy your `user/` folder across as it is. In particular:

- File names like `01.intro.md` and `2003-04-15.welcome.md` still drop
  the prefix from the URL in collections (such as your posts and your
  tags), but they no longer set the order: collections list newest
  published first, and terms and pages by `position`, then title. Give
  terms a `position` to keep an order such as `01.` to `07.`.
  `orderby: filename` is read as `published`. Pages still work with a
  prefix, but `content:lint` reports it: rename `01.about.md` to
  `about.md`.
- `index.md` landing pages, `_`-prefixed hidden files, and `_drafts/`
  folders work as before.
- The 1.x front matter names still work: `date` (now `published`),
  `author` (now `authors`), `excerpt` (now `summary`), and `view` (now
  `template`). You don't need to rename anything. Authors are credited
  through the `authors` [credit relation](content-types.md#crediting-people)
  (the skeleton's `user/data/relations/authors.json`); add each type
  whose entries name authors to its `from`.
- `_error/404.md` still works as your error page (2.x also reads
  `_errors/`).
- Media links to `/user/media/...` still resolve.
- 1.x showed a term or author your entries named even without a file.
  2.x needs the file, and leaves one without it out of the site. Run
  `bin/blush content:terms --write` once to write them all (or choose
  **Write Files** under **Terms and Profiles** in Site Health), titled
  as your entries wrote them.

Run `bin/blush content:lint --strict` to see which old names your content
uses. They're reported as notices, not problems.

## Config

1.x config files returned arrays. In 2.x they return objects, but the 1.x
arrays can be passed straight in.

**Content types** move out of config. Put each 1.x type in a data file
in `user/data/types/`, named after it, with the same keys (or define it
in a [plugin](extending.md#content-types-from-a-plugin)):

```yaml
# user/data/types/post.yaml
# Your 1.x type, unchanged: date_archives, routing, feed, and the rest
# (taxonomies change; see below).
path: _posts
collection:
  order: desc
date_archives: true
```

Move `home_alias` from `config/app.php` into `config/content.php` as
`home`:

```php
<?php

declare(strict_types=1);

use Blush\Content\ContentConfig;

return new ContentConfig(home: 'post');
```

One default changed: a type without a `path` (or `folder`) now lives in
`_` and its name (`_recipe/`), not a folder named after it. If a 1.x type
leaves `path` out, add `path: recipe` to keep its folder. Its URLs
don't change either way.

### Taxonomies

2.x has no taxonomy kind. A type of terms, such as tags or categories,
is a plain collection, and what files entries under its terms is a
**relation** defined on its own (see
[Terms and relationships](content-types.md#terms-and-relationships)).
So a 1.x type with `taxonomy: true` needs moving:

- **In `user/data/types/`**, it keeps working as it is until you
  migrate it. Run `bin/blush content:taxonomies` to list them, then
  `bin/blush content:taxonomies --write` (or **Migrate Types** under
  **Taxonomies** in Site Health) to rewrite each: the type's file is
  edited in place, and its relation is written to
  `user/data/relations/{name}.json`.
- **In a plugin**, it stops Blush from loading, with a message saying
  what to change. Rewrite it by hand.

For example, a 1.x category taxonomy for posts:

```yaml
# user/data/types/category.yaml
taxonomy: true
path: _posts/categories
term_collect: post
hierarchical: true
```

becomes a collection ordered by `position`, out of
`llms.txt`, and a classify relation named after it:

```yaml
# user/data/types/category.yaml
path: _posts/categories
hierarchical: true
order: position
llms: false
```

And in `user/data/relations/category.json`:

```json
{"kind": "classify", "from": ["post"], "to": ["category"], "create": true}
```

A taxonomy's `field` and `field_aliases` become the relation's `field`
and `aliases`, and its `term_collection` the relation's
`inverse.listing` (as `"inverse": {"listing": {...}}`). Entries' front matter (`category: news`) doesn't
change, and neither do the terms' URLs.

### New option names

In 2.x, each kind of type is its own class, and some options have new
names. When you're ready, you can move to them (see
[Content types](content-types.md)):

| 1.x | 2.x |
|---|---|
| `taxonomy: true` | A collection and a classify relation ([above](#taxonomies)) |
| `path` | `folder` |
| `routing` (`prefix`, `paths`) | `urls` (`prefix`, `single`, `collection`, `paths`) |
| `collection` and `collect` | `listing` (`type`, `orderBy`, `order`, `perPage`, `query`) |
| `term_collect` | The relation's `from` (a list) |
| `term_collection` | The relation's `inverse.listing` |
| A taxonomy's `field_aliases` | The relation's `aliases` |
| `date_archives`, `time_archives` | `dateArchives` (`day`, `second`, and others) |
| `feed` `taxonomy` and `collection` | `feed` `categories` (a term type) and `listing` |

**Markdown** (`config/markdown.php`) is rewritten: 2.x doesn't take
CommonMark's options or extension classes. Its Markdown already has
attributes (`{.alignwide}`), definition lists, footnotes, smart
punctuation, heading anchors, and line breaks as `<br>`, and
`MarkdownConfig` takes the rest by name: `html: RawHtml::Filter` for
disallowed raw HTML, and `anchors` and `footnotes` for their classes
(see [Configuration](configuration.md#markdown)). A table of contents
placeholder becomes the `::toc` [directive](directives.md).

**Media URLs:** 2.x serves media from `/media`. To keep 1.x's
`/user/media/...` URLs working, add `config/media.php`:

```php
<?php

declare(strict_types=1);

use Blush\Media\MediaConfig;

return new MediaConfig(url: '/user/media');
```

The rest of `config/app.php` moves to `.env` and the new
`AppConfig`; see [Configuration](configuration.md).

## Themes

Themes are new in 2.x, so a 1.x theme needs rebuilding. Start from the
default theme (`bin/blush theme:new mytheme`) and bring your styles
across. See [Themes](themes.md). Your entries' `view` front matter still
picks a template, but by the 2.x template's name.

1.x's `$pagination->display([...])` becomes `$page->pageLinks()`, which
gives the same numbered links (first and last pages, the pages around
the current one, and dots) for your template to mark up; see
[Pagination](themes.md#pagination). Later pages of a listing still get
"Page 2" in their title.

## Small behavior changes

- Only an image **on its own line** becomes a `<figure>`. Images inside a
  paragraph stay plain images (1.x wrapped every image).
- A collection no longer needs an `index.md` to have a listing page, and
  an empty collection shows an empty listing instead of a 404.
- An entry in a type with its own URLs is served only at those URLs, not
  also at its folder path.
- Feed and sitemap XSL stylesheets aren't supported.
- Blush 2 needs PHP 8.5.
