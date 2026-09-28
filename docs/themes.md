# Themes

A theme controls how your site looks. It never owns your content or your
URLs, so you can switch themes at any time without breaking anything.

Blush ships with a **default theme**: plain, accessible, light and dark,
with no build step. Every other theme builds on it, so a theme only has to
include what it changes.

There are three ways to change how your site looks, from least to most
work:

1. [Adjust the active theme's settings](#settings)
   with a data file.
2. [Override a few templates](#overriding-templates) from your site.
3. [Build your own theme](#building-a-theme).

## Managing themes

```sh
bin/blush theme:list              # installed themes, and which is active
bin/blush theme:activate notebook # switch themes
```

Themes live in `user/themes/{slug}/`, and each can be its own git
repository. They can also be installed with Composer (package type
`blush-theme`). If two share a slug, `user/themes/` wins. The active theme
is set in
`config/theme.php`, which `theme:activate` writes for you.

In development, add `?theme=notebook` to any URL to preview another theme.

## Settings

Create `user/data/theme.json` to adjust the active theme without touching
its files:

```json
{
	"settings": {
		"excerpts": false
	}
}
```

**`settings`** are the options a theme offers. The default theme has one:
`excerpts` (show summaries in listings; on by default).

A single entry can change its own look too, with front matter:

```yaml
class: wide-page             # extra classes on <body>
stylesheet: /media/zine.css  # an extra stylesheet
```

## Overriding templates

To change one piece of the active theme, copy its template into your site's
`resources/views/` folder and edit the copy. Your copy wins.

For example, to change the site footer, copy the default theme's
`views/parts/footer.php` to `resources/views/parts/footer.php`.

To override a template for one theme only, use
`resources/views/themes/{slug}/` instead.

Not sure which file is in charge of a view? Ask:

```sh
bin/blush theme:why parts/footer
```

## Building a theme

Create one:

```sh
bin/blush theme:new notebook
bin/blush theme:activate notebook
```

That makes the smallest valid theme:

```
user/themes/notebook/
  theme.json    {"name": "Notebook", "version": "1.0.0", "styles": ["style.css"]}
  style.css
```

Every template your theme doesn't include comes from the default theme.
Its **stylesheet** doesn't, though: only the active theme's `styles` are
loaded, so your `style.css` starts from a blank page. Copy the default
theme's `style.css` in as a starting point if you like.

A full theme can have:

```
user/themes/notebook/
  theme.json      The manifest
  style.css       Styles (list more in "styles")
  views/
    layouts/      base.php, and any others
    parts/        header.php, footer.php, and other pieces
    components/   Components for templates and Markdown
    single.php  collection.php  ...
  lang/           Translations, such as en.json
  src/            Optional PHP classes
```

### `theme.json`

```json
{
	"name": "Notebook",
	"version": "1.0.0",
	"description": "A theme for writers.",
	"parent": "default",
	"styles": ["style.css"],
	"scripts": ["app.js"],
	"settings": {
		"showDate": {
			"type": "bool",
			"default": true,
			"label": "Show the date on posts"
		}
	}
}
```

Only `name` is required.

- **`parent`:** build on another theme instead of starting from the
  default. Anything this theme doesn't include comes from its parent.
- **`settings`:** options site owners set in `user/data/theme.json`. They
  use the same field types as [custom fields](content-types.md#custom-fields).

### Templates

Templates are plain PHP. Here's a simplified `single.php`:

```php
<?php

declare(strict_types=1);

$template->layout('base');

?>
<article class="entry">
	<h1><?= e($entry->title) ?></h1>

	<?php if ($template->setting('showDate')) : ?>
		<p><?= e($template->date($entry->published)) ?></p>
	<?php endif ?>

	<?= raw($entry->body()) ?>
</article>
```

`$template` is the template itself: it's how a template reaches the calls
below. Its data (such as `$entry`, `$page`, and `$site`) are plain
variables. For editor autocomplete, list them at the top of the file:

```php
/**
 * @var Blush\View\Template       $template
 * @var Blush\Content\Entry\Entry $entry
 */
```

**Always escape output.** Use `e()` for text, `attr()` for attributes,
`url()` for links, and `raw()` only for HTML you trust, such as a rendered
entry body.

What a template can use:

| Call | What it does |
|---|---|
| `$template->layout('base')` | Wrap this template in `layouts/base.php` |
| `$template->section('content')` | In a layout, print the wrapped template |
| `$template->start('name')` … `$template->stop()` | Capture a named section |
| `$template->include('parts/header', key: $value)` | Include another template |
| `$template->include(['parts/card-post', 'parts/card'])` | Include the first of these that exists |
| `$template->includeIf('parts/sidebar')` | Include it only if it exists |
| `$template->includeWhen($condition, 'parts/x')` | Include it only when the condition is true |
| `$template->includeUnless($condition, 'parts/x')` | Include it unless the condition is true |
| `$template->each('parts/card', $entries, as: 'entry', empty: 'parts/none')` | Include a template once per item (see below) |
| `$template->component('notebook/card', title: '...')` | Render a component (see [Components](components.md)) |
| `$template->permalink($entry)` | An entry's URL |
| `$template->terms($entry, 'tag')` | An entry's terms in a taxonomy |
| `$template->date($entry->published)` | A date, formatted for the site's locale |
| `$template->setting('name')` | A theme setting |
| `$template->asset('app.js')` | A theme file's URL, versioned |
| `$template->inline('svg/logo.svg')` | A theme file's contents, such as an SVG icon to print with `raw()` |
| `$template->widont($title)` | Escaped text whose last two words won't split across lines, so a title never ends with one word alone |
| `$template->cache('key', fn () => ...)` | Keep a piece of HTML that's slow to build (see below) |
| `$template->t('key')` | A translated string from `lang/` |
| `$template->head()` | Add to the `<head>`: title, meta tags, styles, scripts |
| `$template->bodyClass()` | The `<body>` classes |

Every template gets `$site` (name, URL, and language). Content pages also
get `$page`, `$entry` (the entry), `$entries` (a listing, when there is
one), `$type`, and `$title`, and so do their layouts, parts, and
components, so you don't have to pass them along. Anything you pass to
`include()` wins, so `$template->include('parts/summary', entry: $item)` shows
that entry instead.

`each()` saves writing a loop around `include()`. Each item reaches the
partial as the variable you name with `as` (`$item` if you don't), along
with `$index` (its position, from `0`) and anything else you pass. With no
items, it includes the `empty` template, if you gave one:

```php
<?= $template->each('parts/entry-summary', $entries, as: 'entry', empty: 'parts/no-entries') ?>
```

An entry offers `title`, `slug`, `published`, `updated`, `body()`,
`summary()`, `excerpt()`, `subtitle()`, `wordCount()`, `readingTime()`
(in minutes), and `field('name')` for anything in its front matter.
`excerpt(50, $more)` takes the number of words and HTML to end with when
the body is cut short, such as a "Continue reading" link:

```php
<?= raw($entry->excerpt(40, ' <a href="' . url($template->permalink($entry)) . '">Continue reading</a>')) ?>
```

Blush fills in the `<head>` for you: the title, the canonical URL,
OpenGraph tags, feed links, and on an entry's page, a description (its
`summary`, or the start of its text) and, when the entry has an `image`
field, `og:image` and a Twitter card. Add or replace any tag with
`$template->head()`, or drop one with `remove()`, such as your stylesheet on
a page that stands alone:
`$template->head()->remove('style:' . $template->asset('style.css'))`.

On later pages of a listing, the title gets the page number: "Blog:
Page 2", or "Page 2" on the front page. To word it differently, add
`document_title.paged` (`"{title}: Page {page}"`) and
`document_title.page` (`"Page {page}"`) to your theme's `lang/` files.

### Pagination

`$page->pageLinks()` gives a listing's numbered page links, ready to
loop over: previous, the page numbers, and next. It shows the first and
last pages and one on each side of the current page, with dots for the
rest, so a long listing reads `← 1 … 4 5 6 … 20 →`. A listing with one
page has no links.

```php
<?php

use Blush\Content\Query\PageLinkKind;

?>
<?php if ($links = $page->pageLinks()) : ?>
	<nav class="pagination" aria-label="Pagination">
		<?php foreach ($links as $link) : ?>
			<?php if ($link->url !== null) : ?>
				<a href="<?= url($link->url) ?>"><?= e(match ($link->kind) {
					PageLinkKind::Previous => 'Previous',
					PageLinkKind::Next     => 'Next',
					default                => (string) $link->number
				}) ?></a>
			<?php else : ?>
				<span<?= $link->isCurrent() ? ' aria-current="page"' : '' ?>><?= e($link->isCurrent() ? (string) $link->number : '…') ?></span>
			<?php endif ?>
		<?php endforeach ?>
	</nav>
<?php endif ?>
```

Each link has a `kind`, a `number` (none for the dots), and a `url`
(none for the current page or the dots). The kinds are `Previous`,
`Number`, `Current`, `Dots`, and `Next`; `$link->kind->value` (`prev`,
`number`, `current`, `dots`, `next`) works as a class name.
`$link->padded(2)` gives the number with leading zeros, such as `02`.

Change how many pages show with `$page->pageLinks(endSize: 2, midSize:
2)`, or leave out previous and next with `adjacent: false`. For just
"Previous" and "Next" links, `$entries->previous()` and
`$entries->next()` give the page numbers, and `$page->pageUrl($number)`
their URLs. The default theme's `parts/pagination.php` is a full
example.

### Caching slow parts

Some parts of a page are slow to build, such as a list of every post.
Wrap them in `$template->cache()` and they're built once, then reused until
your content changes (a publish) or you switch themes:

```php
<?= $template->cache('archives.years', fn () => $template->component('notebook/post-archives', by: 'year')) ?>
```

The key names the piece, so give each variation its own key. Only the
HTML is kept, so don't add to the head inside it. In development,
nothing is cached, so your changes always show.

A component used in an entry's Markdown (`::notebook/post-archives{by=year}`)
needs no `cache()`: it's kept with the entry's rendered content, on the
same terms.

### Which template is used

Blush picks the most specific template your theme (or its parents) has:

| Page | Templates tried, in order |
|---|---|
| An entry | `single-{type}-{slug}`, `single-{type}`, `single` |
| A listing | `collection-{type}`, `collection-taxonomy` (a taxonomy's listing), `collection` |
| A term | `term-{taxonomy}-{slug}`, `term-{taxonomy}`, `term`, `collection` |
| A date archive | `archive-date-{type}`, `archive-date`, `collection` |
| The home page | `home`, then whatever it shows |
| An error | `error-{status}`, `error` |

An entry's `template` front matter is always tried first. Feeds
(`feed-rss`, `feed-atom`, `feed-json`) and sitemaps (`sitemap`,
`sitemap-index`) are templates too, if you need to change them.

### Components

A component is a reusable piece of a template, and every component your
theme has can also be used in Markdown, by the same name.
[Components](components.md) covers writing them; this section covers
where they go in a theme.

A theme's components are in its namespace, which is its slug: the
`notebook` theme's badge is `notebook/badge`. A template-only component is
a file in `views/components/` named `{slug}-{name}.php`, such as
`views/components/notebook-badge.php`. Use it in a template with
`<?= $template->component('notebook/badge', tone: 'new')->content('New') ?>`,
or in Markdown with `:notebook/badge[New]{tone=new}`.

Four **core components** work in every theme, because the default theme
provides them: `callout`, `embed`, `figure`, and `gallery`. They're the
only components with short names. To change how one looks, add your own
`views/components/callout.php` (or `blush-callout.php`, and so on); yours
wins.

Content that uses one of your theme's own components shows it as plain
text under any other theme. If a site's content depends on a component,
it may belong in the site rather than the theme (see
[Where components live](components.md#where-components-live)).

A component that needs data, such as a list of posts, can have a PHP
class. Keep it in the theme's `src/`, and name a provider and autoload
map in `theme.json`:

```json
{
	"name": "Notebook",
	"provider": "Notebook\\ThemeProvider",
	"autoload": { "psr-4": { "Notebook\\": "src/" } }
}
```

The provider registers the class in its `boot()` method, and the
template in `views/components/` (here, `notebook-recent-posts.php`) draws
it:

```php
<?php // src/ThemeProvider.php

declare(strict_types=1);

namespace Notebook;

use Blush\Core\ServiceProvider;
use Blush\View\Component\ComponentRegistry;

final class ThemeProvider extends ServiceProvider
{
	public function boot(): void
	{
		$this->container->get(ComponentRegistry::class)->register('notebook/recent-posts', View\RecentPosts::class);
	}
}
```

See [A component with a PHP class](components.md#a-component-with-a-php-class) for writing the component class.

To see every component your theme can use, and which file draws each,
run `bin/blush component:list`. `bin/blush theme:why components/callout`
shows what a file overrides. `theme:check` warns about a component with
a PHP class but no template, and about a file in `components/` that isn't
named for a component. With `--strict`, it also notes registered
components without a translated label (see
[Labels and translations](components.md#labels-and-translations)).

### Building assets with Vite

A theme that uses Sass, bundles JavaScript, or wants hashed file names can
build its assets with [Vite](https://vite.dev/). Keep sources in the
theme's `resources/` folder (Blush never serves it) and build into its
`public/` folder:

```
user/themes/notebook/
  theme.json      "styles": ["resources/scss/style.scss"], "scripts": ["resources/js/app.js"]
  package.json    vite, plus sass-embedded for Sass
  vite.config.js
  resources/
    scss/  js/  fonts/    sources
    static/               copied to public/ as is (favicons, icons)
  public/                 the build: hashed files and .vite/manifest.json
```

List your source files in `theme.json`. Blush reads Vite's manifest and
links the built files, and `$template->asset('resources/fonts/body.woff2')`
finds a built font the same way. Files copied from `static/` are
reached by their path: `$template->asset('public/img/icon.png')`.

The build lives in the theme, so it travels with the theme's repository.
Blush never serves `package.json`, `node_modules/`, or `*.config.js`
files. Its `vite.config.js`:

```js
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

const theme = import.meta.dirname;

export default defineConfig({
	root: theme,
	base: './',
	publicDir: 'resources/static',
	build: {
		outDir: 'public',
		emptyOutDir: true,
		manifest: true,
		rolldownOptions: {
			input: ['resources/scss/style.scss', 'resources/js/app.js'].map((file) => resolve(theme, file))
		}
	}
});
```

Run `npx vite build` in the theme's folder (or `vite build --watch` while
you work) and commit the built `public/` folder, so your server never needs Node.

### Check your theme

```sh
bin/blush theme:check
```

It checks the manifest and settings, and makes sure the base layout has
the landmarks and skip link screen reader users rely on.

### Going live with a theme

Theme files are served straight from the theme folder by default. On a
live site, copy them into `public/` so the web server can hand them out:

```sh
bin/blush theme:publish
```

Run it again after changing theme files. A [static
export](going-live.md#static-export) includes them automatically.
