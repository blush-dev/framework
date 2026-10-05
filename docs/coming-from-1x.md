# Coming from Blush 1.x

Blush 2 is a rewrite, but **your content doesn't change**. Every file,
folder, and front matter convention 1.x understood still works, and your
URLs stay the same. What changes is the code around it: config, themes,
and the command line.

## Your content

Copy your `user/` folder across as it is. In particular:

- File names like `01.intro.md` and `2003-04-15.welcome.md` still sort and
  still drop the prefix from the URL in collections (such as your posts)
  and taxonomies. Pages still work with one, but `content:lint` reports
  it: rename `01.about.md` to `about.md`.
- `index.md` landing pages, `_`-prefixed hidden files, and `_drafts/`
  folders work as before.
- The 1.x front matter names still work: `date` (now `published`),
  `author` (now `authors`), `excerpt` (now `summary`), and `view` (now
  `template`). You don't need to rename anything.
- `_error/404.md` still works as your error page (2.x also reads
  `_errors/`).
- Media links to `/user/media/...` still resolve.

Run `bin/blush content:lint --strict` to see which old names your content
uses. They're reported as notices, not problems.

## Config

1.x config files returned arrays. In 2.x they return objects, but the 1.x
arrays can be passed straight in.

**Content types** (`config/content.php`): wrap your 1.x array, and move
`home_alias` from `config/app.php` into it as `home`:

```php
<?php

declare(strict_types=1);

use Blush\Content\Type\ContentConfig;

return ContentConfig::fromArray([
	'home'  => 'post',
	'types' => [
		// Your 1.x types, unchanged: date_archives, term_collect,
		// term_collection, routing, feed, and the rest.
		'post' => [
			'path'          => '_posts',
			'collection'    => ['order' => 'desc'],
			'date_archives' => true
		]
	]
]);
```

One default changed: a type without a `path` (or `folder`) now lives in
`_` and its name (`_recipe/`), not a folder named after it. If a 1.x type
leaves `path` out, add `'path' => 'recipe'` to keep its folder. Its URLs
don't change either way.

In 2.x, each kind of type is its own class, and some options have new
names. When you're ready, you can move to them (see
[Content types](content-types.md)):

| 1.x | 2.x |
|---|---|
| `taxonomy: true` | `new Taxonomy(...)`, or `kind: taxonomy` in YAML |
| `path` | `folder` |
| `routing` (`prefix`, `paths`) | `urls` (`prefix`, `single`, `collection`, `paths`) |
| `collection` and `collect` | `listing` (`type`, `orderBy`, `order`, `perPage`, `query`) |
| `term_collect` | `types` (a list) |
| `term_collection` | `termListing` |
| `field_aliases` | `aliases` |
| `date_archives`, `time_archives` | `dateArchives` (`day`, `second`, and others) |
| `feed` `taxonomy` and `collection` | `feed` `categories` and `listing` |

**Markdown** (`config/markdown.php`) is rewritten: 2.x doesn't take
CommonMark's options or extension classes. Its Markdown already has
attributes (`{.alignwide}`), definition lists, footnotes, smart
punctuation, heading anchors, and line breaks as `<br>`, and
`MarkdownConfig` takes the rest by name: `html: RawHtml::Filter` for
disallowed raw HTML, and `anchors` and `footnotes` for their classes
(see [Configuration](configuration.md#markdown)). A table of contents
placeholder becomes the `::toc` [component](components.md).

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
