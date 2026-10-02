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
bin/blush theme:list                   # installed themes, and which is active
bin/blush theme:activate acme/notebook # switch themes
```

A theme is known by its **name**, `vendor/name` (`acme/notebook`), which
its `theme.json` gives. Themes live in `user/themes/{folder}/`, and each
can be its own git repository; the folder is only where it lives. They
can also be installed with Composer (package type `blush-theme`), where
the theme's name is the package's. If two share a name, `user/themes/`
wins. The framework's default theme is `blush/default`. The active theme
is set in `config/theme.php`, which `theme:activate` writes for you:

```php
return new ThemeConfig(active: 'acme/notebook');
```

A theme whose manifest is broken is listed by `theme:list` (and the
admin's Themes screen) by where it was found, such as
`user/themes/notebook`, with the reason.

In development, add `?theme=acme/notebook` to any URL to preview another
theme.

## Settings

Create `user/data/theme.json` to adjust the active theme without touching
its files:

```json
{
	"settings": {
		"layout": "grid"
	}
}
```

**`settings`** are the options a theme offers, if any. The default theme
has none.

A single entry can change its own look too, with front matter:

```yaml
class: wide-page             # extra classes on <body>
stylesheet: /media/zine.css  # an extra stylesheet
```

## Your own settings

A site, a theme's author, or a plugin can add settings to the
admin's **Settings** screens with a [field set](content-types.md#field-sets)
aimed at one: `settings:general`, `settings:reading`, or
`settings:search`.

```yaml
# user/data/fields/brand.yaml
label: Brand
targets: [settings:general]
fields:
  tagline:
    label: Tagline
  accent:
    type: enum
    options: [red, blue]
    default: blue
```

The set's fields show as a panel of their own on that screen, and what's
saved goes in `user/data/settings.json` under `site`. Read them in a
template with `$template->site()`:

```php
<p class="tagline"><?= e((string) $template->site('tagline')) ?></p>
```

It returns the saved value, read through its field (a date is a date),
or the field's `default`, or the second argument when there's neither:
`$template->site('accent', 'red')`. Settings share one name space across
the screens, so two sets can't both add a `tagline`; `bin/blush
content:lint` says so.

## Overriding templates

To change one piece of the active theme, copy its template into your site's
`resources/views/` folder and edit the copy. Your copy wins.

For example, to change the site footer, copy the default theme's
`views/parts/footer.php` to `resources/views/parts/footer.php`.

To override a template for one theme only, use
`resources/views/themes/{vendor}/{name}/` instead, by the theme's name
(`resources/views/themes/acme/notebook/`).

Not sure which file is in charge of a view? Ask:

```sh
bin/blush theme:why parts/footer
```

## Building a theme

Create one:

```sh
bin/blush theme:new acme/notebook
bin/blush theme:activate acme/notebook
```

That makes the smallest valid theme, in a folder named for the part
after the `/`:

```
user/themes/notebook/
  theme.json    {"$schema": "…", "name": "acme/notebook", "label": "Notebook", "namespace": "notebook", "version": "1.0.0", "styles": ["style.css"]}
  style.css
```

`--label`, `--namespace`, and `--parent` (another theme's name) set those
instead of the defaults.

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
  icons/          SVG icons, such as badge.svg (see Components)
  lang/           Translations, such as en.json
  src/            Optional PHP classes
```

### `theme.json`

```json
{
	"name": "acme/notebook",
	"label": "Notebook",
	"namespace": "notebook",
	"version": "1.0.0",
	"description": "A theme for writers.",
	"parent": "blush/default",
	"styles": ["style.css"],
	"scripts": ["app.js"],
	"menus": { "primary": "Primary", "social": "Social" },
	"regions": { "sidebar": "Sidebar" },
	"settings": {
		"showDate": {
			"type": "bool",
			"default": true,
			"label": "Show the date on posts"
		}
	}
}
```

`name`, `label`, and `namespace` are required.

- **`name`:** the key the theme is known by, `vendor/name` in lowercase
  letters, digits, `-`, `_`, and `.`. Config, `parent`, `?theme=`, and
  asset URLs all use it. A Composer theme's name is its package's; leave
  it out and the package's is used.
- **`label`:** the theme's title, as people read it.
- **`namespace`:** what your theme's components, icons, and translations
  go by (`notebook/badge`). Lowercase letters, digits, `-`, and `_`.
  `blush`, `app`, `theme`, and `default` are reserved, and no two
  installed extensions (plugins, themes, icon packs) may share one: two
  themes that do are both broken, and a theme whose namespace a plugin
  has is broken too.
- **`parent`:** the name of the theme this one builds on, instead of
  starting from the default. Anything this theme doesn't include comes
  from its parent.
- **`provider`** and **`autoload`:** a theme can run PHP, through a
  service provider of its own (see [Components](#components)). It still
  can't add content types, routes, or commands; those belong to the site
  or a [plugin](extending.md#plugins).
- **`settings`:** options site owners set in `user/data/theme.json`. They
  use the same field types as [custom fields](content-types.md#custom-fields).
- **`menus` and `regions`:** the places your theme shows the site's menus
  and regions. See [Menus and regions](menus.md#for-theme-authors).
- **`bleed`:** the classes that widen an element past the text column;
  see [Bleed](#bleed).
- **`variants`:** styles your theme adds to components, by component,
  such as `{"callout": ["bordered"]}`. See
  [Variants](components.md#variants). Under `image`, the classes it
  offers images; see [Image variants](#image-variants).

#### Autocomplete in your editor

Blush ships a JSON Schema for `theme.json`, so editors such as VS Code and
PhpStorm can suggest keys, show what each one does, and flag mistakes.
`theme:new` points the manifest at it with a `$schema` key:

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/theme.schema.json",
	"name": "acme/notebook"
}
```

The path is relative to `theme.json`. In a YAML manifest, put it in a
comment on the first line instead:

```yaml
# yaml-language-server: $schema=../../../vendor/blush-dev/framework/resources/schemas/theme.schema.json
name: acme/notebook
label: Notebook
namespace: notebook
```

A new site's `.vscode/settings.json` also maps every `user/themes/*/theme.json`
(and `.yaml`) to the schema, so VS Code finds it even without `$schema`.

The schema covers the built-in field types. A field type from a plugin
is allowed, but the editor can't suggest its options.

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
| `$template->icon('house', 'Home')` | An icon, decorative or labeled (see [Icons](components.md#icons)) |
| `$template->permalink($entry)` | An entry's URL |
| `$template->terms($entry, 'tag')` | An entry's terms in a taxonomy |
| `$template->people($entry, $field)` | The profiles an entry credits through a [people field](content-types.md#crediting-people), in order; the type's first people field (its byline) when `$field` is left out |
| `$template->bylineUrl($profile, $entry, $field)` | Where a byline links: the person's archive under the entry's type and field, such as `/blog/authors/jane`, else their profile's page, else `''` |
| `$template->personUrl($profile, $type, $field)` | A person's archive under a type's people field, such as `/recipes/cooks/jane` (`''` when it has none) |
| `$template->peopleUrl($type, $field)` | The list of people a type's field credits, such as `/recipes/cooks` |
| `$template->permalink($profile)` | A profile's own page, such as `/profiles/jane` |
| `$template->parent($entry)` | A page's parent page (from its folder) or a term's parent term, if published |
| `$template->ancestors($entry)` | Its parents from the top down, for breadcrumbs |
| `$template->children($entry)` | A page's subpages or a term's child terms, published, by title |
| `$template->date($entry->published)` | A date, formatted for the site's locale |
| `$template->setting('name')` | A theme setting |
| `$template->site('name')` | A site setting a field set adds ([Your own settings](#your-own-settings)) |
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
The head prints root-relative links and scripts (`/feed`,
`$template->asset(...)`) as full URLs on your site's `url`, so pass
paths as they are. Meta tag values print as given, so give `og:image`
and the like a full URL.

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
| A type's people (such as `/recipes/cooks`) | `people-{type}-{field}`, `people-{field}`, `people`, `collection` |
| A person's archive (such as `/recipes/cooks/jane`) | `person-{type}-{field}`, `person-{field}`, `person`, `profile`, `collection` |
| A profile's page (such as `/profiles/jane`) | `profile-{slug}`, `profile`, `collection` |
| The home page | `home`, then whatever it shows |
| An error | `error-{status}`, `error` |

On a type's people page, `$entries` holds the profiles (link each with
`$template->personUrl($profile, $type, $page->people->field)`) and
`$entry` is the field's list page (`_cooks`), when it has one. On a
person's archive, `$entry` is the page written for it
(`_cooks/jane`) or else the profile, `$page->profile` is always the
profile, and `$entries` their entries of that type. On a profile's
page, `$entry` is the profile and `$entries` everything crediting
them.

An entry's `template` front matter is always tried first. Feeds
(`feed-rss`, `feed-atom`, `feed-json`) and sitemaps (`sitemap`,
`sitemap-index`) are templates too, if you need to change them.

### Components

A component is a reusable piece of a template, and every component your
theme has can also be used in Markdown, by the same name.
[Components](components.md) covers writing them; this section covers
where they go in a theme.

A theme's components are in its namespace, which its `theme.json`
declares: the `notebook` namespace's badge is `notebook/badge`. A
template-only component is a file in `views/components/` named
`{namespace}-{name}.php`, such as
`views/components/notebook-badge.php`. Use it in a template with
`<?= $template->component('notebook/badge', tone: 'new')->content('New') ?>`,
or in Markdown with `:notebook/badge[New]{tone=new}`.

The **core components** work in every theme, because the default theme
provides them: `callout`, `embed`, `figure`, and `gallery`; the layout
components `group`, `grid`, and `row`; the media components `audio`,
`video`, and `file`; the inline components `abbr`, `badge`, `cite`, `dfn`, `ins`, `kbd`,
`samp`, `small`, `time`, and `var`; `toc`, a table of
contents; `icon`; `button`; and `progress` and `meter`. They're the
only components with short names. To change how one looks, add your own
`views/components/callout.php` (or `blush-callout.php`, and so on); yours
wins.

To give a component a style of your own, add a
[variant](components.md#variants) in `theme.json` and style its class
(`.component-callout--bordered`), or give it a template of its own
(`views/components/callout-bordered.php`).

Content that uses one of your theme's own components shows it as plain
text under any other theme. If a site's content depends on a component,
it may belong in the site rather than the theme (see
[Where components live](components.md#where-components-live)).

A component that needs data, such as a list of posts, can have a PHP
class. Keep it in the theme's `src/`, and name a provider and autoload
map in `theme.json`:

```json
{
	"name": "acme/notebook",
	"label": "Notebook",
	"namespace": "notebook",
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
use Blush\Component\ComponentRegistry;

final class ThemeProvider extends ServiceProvider
{
	public function boot(): void
	{
		$this->container->get(ComponentRegistry::class)->register('notebook/recent-posts', View\RecentPosts::class);
	}
}
```

See [The class](components.md#the-class) for writing the component class, and [The template](components.md#the-template) for what its template gets.

To see every component your theme can use, and which file draws each,
run `bin/blush component:list`. `bin/blush theme:why components/callout`
shows what a file overrides. `theme:check` warns about a component with
a PHP class but no template, about a file in `components/` that isn't
named for a component, about variants in `theme.json` for components
that don't exist or with names that aren't valid, and about a variant's
template that's also another component's. With `--strict`, it also notes
registered components and variants without a translated label (see
[Labels and translations](components.md#labels-and-translations)).

### Bleed

The admin's editor can widen any element at the top of an entry (an
image, a paragraph, a component) past the text column: **Wide**, into
the margin, or **Full**, edge to edge. Each is a class on the element,
`bleed-wide` and `bleed-full` unless your theme names its own; the
column's own width writes no class at all. Style both:

```css
.bleed-wide { /* wider than the text, centered on it */ }
.bleed-full { /* as wide as the page */ }
```

A theme whose content already uses other names says so in `theme.json`,
and the editor writes those instead (a child theme inherits them):

```json
{
	"bleed": {
		"wide": "stretch-wide",
		"full": "stretch-full"
	}
}
```

The default theme styles `bleed-wide` and `bleed-full`, and Blush 1.x's
`stretch-wide` and `stretch-full` the same way.

### Image variants

An image in Markdown takes classes, `![A lake](/media/lake.jpg){.inline-left}`,
and the site puts them on the image's figure. List the ones your
stylesheet styles under `variants.image` in `theme.json`, and the
editor offers them as the image's **Variant**. Widths aren't variants:
they're [bleed](#bleed).

```json
{
	"variants": {
		"image": ["inline-left", "polaroid"]
	}
}
```

Give each a label (and a description, if you like) in your theme's
`lang/` catalog:

```json
{
	"images": {
		"variants": {
			"polaroid": {
				"label": "Polaroid",
				"description": "A white border, wider at the bottom."
			}
		}
	}
}
```

The default theme offers `inline-left` (Float Left) and `inline-right`
(Float Right), and styles them. They're only offered while it's the active theme, since
another theme's stylesheet may not style them; list the ones yours does.
`theme:check` notes an image variant without a label.

### Building assets with Vite

A theme that uses Sass or bundles JavaScript can build its assets with
[Vite](https://vite.dev/). Keep sources in the theme's `resources/`
folder (Blush never serves it) and build into its `public/` folder:

```
user/themes/notebook/
  theme.json      "styles": ["resources/scss/style.scss"], "scripts": ["resources/js/app.js"]
  package.json    vite, plus sass-embedded for Sass
  vite.config.js
  resources/
    scss/  js/            sources Vite builds
    fonts/  img/  svg/    copied to public/ as they are
  public/                 the build: css/, js/, the copies, and .vite/manifest.json
```

List your source files in `theme.json`. Blush reads Vite's manifest and
links the built files, and `$template->asset('resources/fonts/body.woff2')`
finds a font your CSS uses the same way. Copied files are reached by
their path: `$template->asset('public/img/icon.png')`.

Every theme asset URL ends in `?v=` and a hash of the file's contents,
so browsers can cache files for as long as they like and still get a
changed file right away. Built files keep their plain names
(`public/css/style.css`), and the config below versions the fonts and
images your CSS points to the same way.

The build lives in the theme, so it travels with the theme's repository.
Blush never serves `package.json`, `node_modules/`, or `*.config.js`
files. Its `vite.config.js`:

```js
import { cpSync, readdirSync } from 'node:fs';
import { posix, relative, resolve } from 'node:path';
import { crc32 } from 'node:zlib';
import { defineConfig } from 'vite';

const theme = import.meta.dirname;
const resources = resolve(theme, 'resources');
const outDir = resolve(theme, 'public');

// Folders Vite builds from; everything else in resources/ is copied.
const sources = ['scss', 'js'];

// The same hash Blush adds to URLs.
const version = (source) => crc32(source).toString(16).padStart(8, '0');

const resourceFiles = () => ({
	name: 'resource-files',

	// Adds ?v= to the fonts and images the CSS points to.
	generateBundle(options, bundle) {
		for (const file of Object.values(bundle)) {
			if (file.type !== 'asset' || !file.fileName.endsWith('.css')) {
				continue;
			}

			file.source = String(file.source).replace(/url\(\s*(['"]?)([^'")?#]+)\1\s*\)/g, (match, quote, url) => {
				const target = bundle[posix.join(posix.dirname(file.fileName), url)];

				return target ? `url(${quote}${url}?v=${version(target.source ?? target.code)}${quote})` : match;
			});
		}
	},

	writeBundle() {
		for (const name of readdirSync(resources)) {
			if (!sources.includes(name)) {
				cpSync(resolve(resources, name), resolve(outDir, name), { recursive: true });
			}
		}
	}
});

export default defineConfig({
	root: theme,
	base: './',
	publicDir: false,
	plugins: [resourceFiles()],
	build: {
		outDir,
		emptyOutDir: true,
		manifest: true,
		rolldownOptions: {
			input: ['resources/scss/style.scss', 'resources/js/app.js'].map((file) => resolve(theme, file)),
			output: {
				entryFileNames: 'js/[name].js',
				chunkFileNames: 'js/[name].js',
				assetFileNames: ({ names, originalFileNames }) => {
					if (names[0]?.endsWith('.css')) {
						return 'css/[name][extname]';
					}

					const original = originalFileNames[0];

					return original ? relative(resources, resolve(theme, original)) : 'assets/[name][extname]';
				}
			}
		}
	}
});
```

It needs Node 22.2 or later. Run `npx vite build` in the theme's folder
(or `vite build --watch` while you work) and commit the built `public/`
folder, so your server never needs Node. A build that hashes file names
(Vite's default) works too; the URLs just carry both hashes.

### Check your theme

```sh
bin/blush theme:check
```

It checks the manifest and settings, the site's menus and regions, and
makes sure the base layout has the landmarks and skip link screen reader
users rely on.

### Going live with a theme

Theme files are served straight from the theme folder by default. On a
live site, copy them into `public/` so the web server can hand them out:

```sh
bin/blush theme:publish
```

Run it again after changing theme files. A [static
export](going-live.md#static-export) includes them automatically.
