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
its `theme.json` gives. Themes live in `extensions/{vendor}/{name}/`, a
folder at their name (`extensions/acme/notebook/`), and each can be its
own git repository. They can also be installed with Composer (package
type `blush-theme`), where the theme's name is the package's. A theme
can do without `theme.json` when its `composer.json` has the type
`blush-theme`: its keys go under `extra.blush` there (see
[Extensions](extending.md#extensions)). If two
share a name, the one in `extensions/` wins. The framework's default theme is `blush/default`. The active theme
is set in `config/theme.php`, which `theme:activate` writes for you:

```php
return new ThemeConfig(active: 'acme/notebook');
```

A theme activated in the admin's [Themes screen](admin.md#themes) is
saved in `user/data/settings.json` instead, and wins over
`config/theme.php`. `theme:activate` clears that saved theme, so the
command always takes effect.

A theme whose manifest is broken is listed by `theme:list` (and the
admin's Themes screen) by where it was found, such as
`extensions/acme/notebook`, with the reason. A theme in a folder that
isn't its name is broken too.

In development, add `?theme=acme/notebook` to any URL to preview another
theme. A previewed theme's provider runs as it would if the theme were
active, as long as its requirements are met. The active theme's provider
has run too, so what it registers stays, and routes the previewed
theme's provider adds don't take effect until it's activated.

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
`views/partials/footer.php` to `resources/views/partials/footer.php`.

The default footer ends with a "Powered by" line ("Powered by coffee.",
"Powered by sleepless nights.", and others), picked at random
from `$template->tGroup('powered_by')`. To use your own lines, you don't
need to copy the template: see [Your own wording](#your-own-wording). Each line has its own key
under `powered_by` in the theme's `lang/en.json` (`powered_by.coffee`).
A translation's `powered_by` replaces the whole group, so it can have
more lines or fewer, and none of the English ones show through.

To override a template for one theme only, use
`resources/views/themes/{vendor}/{name}/` instead, by the theme's name
(`resources/views/themes/acme/notebook/`).

Not sure which file is in charge of a view? Ask:

```sh
bin/blush theme:why partials/footer
```

## Building a theme

Create one:

```sh
bin/blush theme:new acme/notebook
bin/blush theme:activate acme/notebook
```

That makes the smallest valid theme, in a folder at its name:

```
extensions/acme/notebook/
  theme.json    {"$schema": "…", "name": "acme/notebook", "label": "Notebook", "namespace": "acme-notebook", "version": "1.0.0", "styles": ["style.css"]}
  style.css
  lang/en.json  {"@@locale": "en", "@@domain": "acme/notebook"}, for your theme's text
```

`--label`, `--namespace`, and `--parent` (another theme's name) set those
instead of the defaults.

Every template your theme doesn't include comes from the default theme.
Its **stylesheet** doesn't, though: only the active theme's `styles` are
loaded, so your `style.css` starts from a blank page. Copy the default
theme's `style.css` in as a starting point if you like.

A full theme can have:

```
extensions/acme/notebook/
  theme.json      The manifest
  style.css       Styles (list more in "styles")
  views/
    layouts/      base.php, and any others
    partials/     header.php, footer.php, and other pieces
    directives/   Your look for directives, such as callout.php
    components/   Your theme's own components, for its templates
    single.php  collection.php  ...
  icons/          SVG icons, such as badge.svg (see Directives)
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
	"authors": [
		{ "name": "Jane Doe", "email": "jane@example.com", "homepage": "https://example.com", "role": "Designer" }
	],
	"parent": "blush/default",
	"styles": ["style.css"],
	"scripts": ["app.js"],
	"preload": ["fonts/body.woff2"],
	"menus": { "primary": "Primary", "social": "Social" },
	"regions": { "sidebar": "Sidebar" },
	"preview": {
		"layout": "centered",
		"type": "Serif headings · sans body",
		"palette": {
			"background": ["#fdfcfb", "#141312"],
			"surface": ["#f3f1ee", "#1f1d1b"],
			"text": ["#1d1b19", "#ece9e5"],
			"muted": ["#5c5752", "#aaa39c"],
			"accent": ["#a3285b", "#f08bb4"],
			"border": ["#dcd8d3", "#3a3633"]
		}
	},
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

- **`name`:** the key the theme is known by, `vendor/name` in lowercase
  letters, digits, `-`, `_`, and `.`. Config, `parent`, `?theme=`, and
  asset URLs all use it, and it's the theme's folder in `extensions/`.
  Leave it out and the `composer.json` beside `theme.json` gives it; a
  Composer theme's name is its package's. `description`, `version`,
  `license`, `autoload`, `require`, `conflict`, `replace`, `provide`, `homepage`,
  `support`, `funding`, `abandoned`, and `suggest` also come from that
  `composer.json` when `theme.json` leaves them out.
- **`label`:** the theme's title, as people read it. Leave it out and
  the theme is shown by its `name`.
- **`namespace`:** what your theme's components, icons, and translations
  go by (`notebook/badge`). Lowercase letters, digits, `-`, and `_`.
  Optional, and best left out: without one, it's the name with a hyphen
  for the `/` (and any `.`), so `acme/notebook` goes by `acme-notebook`.
  `theme:new` writes that form. Give one only when you want something
  shorter.
  `blush`, `app`, `theme`, and `default` are reserved, and no two
  installed extensions (plugins, themes, icon packs) may share one: two
  themes that do are both broken, and a theme whose namespace a plugin
  has is broken too.
- **`authors`:** who made the theme, in the same shape as
  `composer.json`'s `authors`: each has a `name`, and may have an
  `email`, a `homepage` (an `http` or `https` address), and a `role`.
  Leave it out and the authors in the `composer.json` beside
  `theme.json` are used, so a theme that's also a Composer package lists
  them once. The admin shows them on the theme's details.
- **`license`:** how the theme may be used, as `composer.json` has it:
  an [SPDX identifier](https://spdx.org/licenses/) such as `MIT`, a list
  any of which applies, `(MIT and OFL-1.1)` when all apply (a theme with a bundled font, say),
  or `proprietary`. Leave it out and the `composer.json` beside
  `theme.json` gives it. The admin shows it on the theme's details,
  linking common open source licenses to their text.
- **`homepage`, `support`, and `funding`:** where to learn about, get
  help with, and fund the theme, as a plugin has them (see
  [Plugins](extending.md#plugins)). The admin shows them on the theme's
  details.
- **`abandoned`:** `true` when the theme is no longer maintained, or the
  name of the package to use instead, as a plugin has it (see
  [Plugins](extending.md#plugins)). It only warns: an abandoned theme
  can still be activated.
- **`suggest`:** packages that work well with the theme, each with why,
  as a plugin has them (see [Plugins](extending.md#plugins)). They're
  only shown, on the theme's details.
- **`parent`:** the name of the theme this one builds on, instead of
  starting from the default. Anything this theme doesn't include comes
  from its parent.
- **`require`:** what the theme needs, with version constraints, as a
  plugin's [requirements](extending.md#requirements) work: Blush
  (`blush-dev/framework`), `php`, PHP extensions, other plugins,
  themes, or icon packs by name (`"acme/shop": "^2.0"` needs the Shop
  plugin turned on), and libraries Composer installed. A theme whose requirements aren't met can't be
  activated. When the active theme's requirements stop being met, or
  those of a theme it falls back to, visitors see the default theme
  until that's fixed; the Themes screen, `theme:check`, and `doctor` say
  why.
- **`conflict`:** what the theme can't run with, as a plugin's
  [conflicts](extending.md#conflicts) work. A theme that conflicts with
  something that's on can't be activated, and when the active theme (or
  one it falls back to) comes to conflict, visitors see the default
  theme until that's fixed. The extension it names carries on.
- **`replace`:** the packages the theme stands in for, as a plugin's
  [`replace`](extending.md#replacing-another-extension) works: a
  requirement of one is met by the theme while it's active, and it can't
  be activated while one it replaces is on.
- **`provide`:** the packages the theme implements, as a plugin's
  [`provide`](extending.md#providing-a-package) works: a requirement of
  one is met by the theme while it's active.
- **`provider`** and **`autoload`:** a theme can run PHP, through a
  service provider of its own (see [Directives and components](#directives-and-components)).
  `autoload` is Composer's shape: `psr-4` folders, and `files` loaded
  once when the theme is in use. It still can't add content types,
  routes, or commands; those belong to the site or a
  [plugin](extending.md#plugins).
- **`preload`:** files every page should fetch early, relative to the
  theme, such as the fonts your stylesheet uses, so text doesn't wait
  on them. What each file is comes from its extension: a font is
  preloaded as a font (and fetched `crossorigin`, as browsers require),
  `.css` as a style, `.js` as a script, and images as images. For a file
  only some pages need, use `$template->head()->preload()` instead.
- **`assets`:** styles and scripts your theme registers by name, which
  pages load only when they ask for them. A theme with a `provider`
  registers them there instead. See [Scripts and styles](#scripts-and-styles).
- **`settings`:** options site owners set in `user/data/theme.json`. They
  use the same field types as [custom fields](content-types.md#custom-fields).
- **`menus` and `regions`:** the places your theme shows the site's menus
  and regions. See [Menus and regions](menus.md#for-theme-authors).
- **`bleed`:** the classes that widen an element past the text column;
  see [Bleed](#bleed).
- **`variants`:** styles your theme adds to directives, by directive,
  such as `{"callout": ["bordered"]}`. See
  [Variants](directives.md#variants). Under `image`, the classes it
  offers images; see [Image variants](#image-variants).
- **`preview`:** what the admin's Themes screen draws your theme's
  preview from; see [The admin's preview](#the-admins-preview).

#### The admin's preview

The admin's Themes screen shows each theme as a small sketch of a page,
drawn from colors the theme declares rather than a screenshot, so it
never goes stale. Give it a `preview`:

- **`layout`:** the page's shape: `centered` (one column, the default),
  `sidebar` (text beside a sidebar), or `wide` (a wide banner over a row
  of cards).
- **`type`:** a short line about your type, such as `Serif headings ·
  sans body`. It's shown as text; the admin doesn't load your fonts.
- **`palette`:** six colors: `background`, `surface` (cards and the
  header), `text`, `muted` (secondary text), `accent` (links and
  buttons), and `border`. Each is a hex color, or a `["light", "dark"]`
  pair; one color is used for both. All six are needed. The sketch
  shows the light colors when the admin is light and the dark ones when
  it's dark.

A theme without a `preview`, or without a `palette`, is sketched in the
admin's own gray. A `preview` that doesn't fit these rules makes the
manifest broken, like any other key.

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

A new site's `.vscode/settings.json` also maps every
`extensions/*/*/theme.json` (and `.yaml`) to the schema, so VS Code
finds it even without `$schema`.

The schema covers the built-in field types. A field type from a plugin
is allowed, but the editor can't suggest its options.

### Templates

Templates are plain PHP, unless a plugin adds another template language
([Template engines from a plugin](extending.md#template-engines-from-a-plugin)).
Here's a simplified `single.php`:

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
entry body. `raw()` marks the HTML as trusted, and the mark travels with
it: `e()` leaves trusted HTML (and a component) as it is, while `attr()`
escapes everything, since an attribute never holds markup. Never `raw()`
text a visitor or a writer typed, such as a title.

What a template can use:

| Call | What it does |
|---|---|
| `$template->layout('base')` | Wrap this template in `layouts/base.php` |
| `$template->section('content')` | In a layout, print the wrapped template |
| `$template->start('name')` … `$template->stop()` | Capture a named section |
| `$template->include('partials/header', key: $value)` | Include another template |
| `$template->include(['partials/card-post', 'partials/card'])` | Include the first of these that exists |
| `$template->includeIf('partials/sidebar')` | Include it only if it exists |
| `$template->includeWhen($condition, 'partials/x')` | Include it only when the condition is true |
| `$template->includeUnless($condition, 'partials/x')` | Include it unless the condition is true |
| `$template->each('partials/card', $entries, as: 'entry', empty: 'partials/none')` | Include a template once per item (see below) |
| `$template->component('notebook/card', title: '...')` | Render a component (see [Components](components.md)) |
| `$template->directive('callout', variant: 'tip')` | Render a directive, as content would (see [Directives](directives.md#using-directives-in-templates)) |
| `$template->icon('house', 'Home')` | An icon, decorative or labeled (see [Icons](directives.md#icons)) |
| `$template->permalink($entry)` | An entry's URL |
| `$template->route('home')` | A named route's URL (`bin/blush routes:list`), with any parameters: `route('post.collection.month', ['year' => 2026, 'month' => '05'])`. On a [translated](content.md#translations) page, it's that language's version when there is one (`/es` for `home`) |
| `$template->terms($entry, 'tag')` | An entry's terms of a [type of terms](content-types.md#terms-and-relationships), such as its tags |
| `$template->related($entry, 'actors')` | The published entries an entry [links to](content-types.md#linking-entries-to-other-entries) through a relation, in order, each in the entry's language when it's translated |
| `$template->referencedBy($entry, 'actors')` | The published entries linking to an entry through a relation, newest first: by its name, or `'movie.actors'` for one type's |
| `$template->people($entry, $field)` | The profiles an entry credits through a [people field](content-types.md#crediting-people), in order; the type's first people field (its byline) when `$field` is left out |
| `$template->author($entry)` | The first person an entry's byline credits, such as a post's author, or `null` |
| `$template->profile('jane')` | A published profile by its slug, or `null` |
| `$template->avatar($profile, 48)` | A profile's avatar, to print: a square box with its initials, as an inline SVG (see [Avatars](#avatars)) |
| `$template->bylineUrl($profile, $entry, $field)` | Where a byline links: the person's archive under the entry's type and field, such as `/blog/authors/jane`, else their profile's page, else `''` |
| `$template->personUrl($profile, $type, $field)` | A person's archive under a type's people field, such as `/recipes/cooks/jane` (`''` when it has none) |
| `$template->peopleUrl($type, $field)` | The list of people a type's field credits, such as `/recipes/cooks` |
| `$template->permalink($profile)` | A profile's own page, such as `/profiles/jane` |
| `$template->parent($entry)` | A page's parent page (from its folder), or the parent of an entry in a [nesting collection](content-types.md#nesting-and-order), such as a category, if published |
| `$template->ancestors($entry)` | Its parents from the top down, for breadcrumbs |
| `$template->children($entry)` | A page's subpages or a nesting collection's child entries, published, by `position` and then title |
| `$template->date($entry->published)` | A date in the site's date format and the page's language; or give an [ICU pattern](https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax), such as `'MMMM y'` |
| `$template->time($entry->published)` | A time in the site's time format |
| `$template->datetime($entry->published)` | Both, joined as the site's language joins them (`October 4, 2026 at 2:30 PM`) |
| `$template->setting('name')` | A theme setting |
| `$template->site('name')` | A site setting a field set adds ([Your own settings](#your-own-settings)) |
| `$template->asset('app.js')` | A theme file's URL, versioned |
| `$template->inline('svg/logo.svg')` | A theme file's contents, such as an SVG icon to print with `raw()` |
| `$template->widont($title)` | Escaped text whose last two words won't split across lines, so a title never ends with one word alone |
| `$template->cache('key', fn () => ...)` | Keep a piece of HTML that's slow to build (see below) |
| `$template->t('key')` | A translated string from `lang/` |
| `$template->tGroup('group')` | The translated strings of a group in `lang/` (the keys under `group`), keyed by name |
| `$template->head()` | Add to the `<head>`: title, meta tags, styles, scripts |
| `$template->foot()` | Add scripts to the end of the page, and print them just before `</body>` in the base layout |
| `$template->bodyClass()` | The `<body>` classes |

Dates and times use the formats the site owner picks on **Settings →
General** (`dateFormat` and `timeFormat` in `config/app.php`), so use
them without a format wherever a date is just a date. Pass one only
where your design needs it: a style (`full`, `long`, `medium`, `short`)
or an [ICU pattern](https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax),
such as `$template->date($date, 'MMMM y')` for an archive's heading, or
`$template->datetime($date, 'short', 'HH:mm')`. A style can also be
given as `Blush\Clock\DateStyle` (`Full`, `Long`, `Medium`, `Short`), so
your editor completes it and a typo is an error rather than a pattern:

```php
<?php use Blush\Clock\DateStyle; ?>
<?= e($template->date($entry->published, DateStyle::Short)) ?>
```

ICU patterns aren't PHP's
`date()` letters: `MMMM d, y` is PHP's `F j, Y`, and `h:mm a` is `g:i A`.
For a machine-readable date, such as a `<time datetime="">`, use
`$date->format(DATE_ATOM)`.

Every template gets `$site` (name, URL, and language). Content pages also
get `$page`, `$entry` (the entry), `$entries` (a listing, when there is
one), `$type`, and `$title`, and so do their layouts, partials,
directives, and components, so you don't have to pass them along. Anything you pass to
`include()` wins, so `$template->include('partials/summary', entry: $item)` shows
that entry instead.

`each()` saves writing a loop around `include()`. Each item reaches the
partial as the variable you name with `as` (`$item` if you don't), along
with `$index` (its position, from `0`) and anything else you pass. With no
items, it includes the `empty` template, if you gave one:

```php
<?= $template->each('partials/entry-summary', $entries, as: 'entry', empty: 'partials/no-entries') ?>
```

An entry offers `title`, `slug`, `published`, `updated`, `body()`,
`summary()`, `excerpt()`, `subtitle()`, `wordCount()`, `readingTime()`
(in minutes), and `field('name')` for anything in its front matter.
`excerpt(50, $more)` takes the number of words and HTML to end with when
the body is cut short, such as a "Continue reading" link:

```php
<?= raw($entry->excerpt(40, ' <a href="' . url($template->permalink($entry)) . '">Continue reading</a>')) ?>
```

To preload a file on one page, such as the picture at the top of a
post, use `$template->head()->preload($url)`; it works out what the
file is the same way the `preload` list does, and attributes you give
win: `->preload($url, ['fetchpriority' => 'high'])`.

A few lines of JavaScript that must run before the page paints, such as
setting a color scheme a visitor picked, go in with
`$template->head()->inlineScript('scheme', $js)`, once per id; they run
where they stand, unlike your theme's deferred `scripts`. Like
`inlineStyle()`, the code isn't escaped, so it must be yours.

Blush fills in the `<head>` for you: the title, the canonical URL,
OpenGraph tags, feed links, `hreflang` links to the page in your
site's other [languages](content.md#translations), and on an entry's page, a description (its
`summary`, or the start of its text) and, when the entry has an `image`
field, `og:image` and a Twitter card. Add or replace any tag with
`$template->head()`, or drop one with `remove()`, such as your stylesheet on
a page that stands alone:
`$template->head()->remove('style:' . $template->asset('style.css'))`.
`$template->head()` prints everything inside `<head>`, and
`$template->foot()` prints the scripts that load at the end of the page,
so your base layout needs only:

```php
<head>
<?= $template->head() ?>

</head>
<body>
…
<?= $template->foot() ?>
</body>
```

Print `$template->foot()` just before `</body>`, in every layout that
writes its own `<body>`. Blush, plugins, and themes put scripts there,
and a layout without it gets none of them: `bin/blush theme:check`
reports it as an error, and in development, the log names the scripts
a page lost.

It starts with `<meta charset="utf-8">` and the `<title>`, then prints
its tags grouped by kind: meta tags (including `viewport` and
`generator`, which you can replace or remove like any other), OpenGraph
tags, links, preloads and other resource hints, styles, scripts, then
inline scripts. Each group keeps the order its tags were added in, so
stylesheets and inline styles stay in your cascade order. The foot
prints its scripts before its inline scripts too. An inline script runs
where it stands, so it still runs before any `defer` or module script,
wherever it prints. Every line is indented by one
tab. `bin/blush theme:check` warns if your layout prints the charset,
`viewport`, `generator`, or `<title>` itself as well.
The head prints root-relative links and scripts (`/feed`,
`$template->asset(...)`) as full URLs on your site's `url`, so pass
paths as they are. Meta tag values print as given, so give `og:image`
and the like a full URL.

### Scripts and styles

Besides your theme's own `styles` and `scripts`, Blush, plugins, and
themes register scripts and styles by name, a handle such as
`blush/player`, and pages load them only when they need them. Ask for
one in a template with `$template->enqueue('acme/gallery')`; it prints
in the `<head>` once, after anything it needs, however many times it's
asked for. Directives and components ask for their own, so the audio
and video player (`blush/player`) loads only on pages that play
something.

The head is printed once the whole page has rendered, so a template
can add to it anywhere, even in your footer partial after the layout
has printed `$template->head()`. Scripts that should load after the
page's markup go in the foot instead, where your layout prints
`$template->foot()`: `$template->foot()->script($url)`, or
`$template->foot()->inlineScript('id', $js)`. The head and the foot
share their tags, so a script asked for in both prints once, in the
head, and `has()` and `remove()` on either find tags in both.
Inline code prints between its tags as written, trimmed, so a line of
code prints as `<script id="scheme">…</script>`; code over several
lines keeps its line breaks. Code and data that belong to a registered
script can be tied to it, as
[Inline code and data](extending.md#inline-code-and-data) shows.

Your theme registers its own the same way plugins do: in its
provider's `boot()`, with `from` set to your theme's name, as
[Scripts and styles](extending.md#scripts-and-styles) shows. That's the
place for them when your theme has a provider. A theme without one can
list them under `assets` in `theme.json` instead, each handle with its
`styles`, `scripts`, and the handles it `requires`:

```json
"assets": {
	"acme/lightbox": {
		"styles": ["css/lightbox.css"],
		"scripts": [
			{ "path": "js/lightbox.js", "footer": true, "attributes": { "type": "module" } },
			"https://cdn.example.com/zoom.js"
		],
		"requires": ["blush/player"]
	}
}
```

A file is a path inside your theme or a full URL, or an object with
that `path`, its `attributes`, and, for a script, `footer`. Themes
register theirs after plugins, a parent's before its child's, and a
theme's provider after its `theme.json`, so a provider's handle wins;
your site's registrations win over any theme's. `theme.json`'s `styles`
and `scripts` stay as they are: files every page loads.

The player draws its colors from `--player-*` properties, set to your
page's own text and background colors. Set any of them on `:root` or
`.player` (and `.audio-card`, for audio drawn as a card) in your
stylesheet to make it yours:

```css
.player {
	--player-accent: var(--color-accent);   /* the play button, what's played */
	--player-accent-fg: white;              /* the play button's glyph */
	--player-track: #ddd;                   /* what's left to play */
	--player-muted: #666;                   /* the time and other buttons */
	--player-font-mono: var(--font-mono);   /* the time */
	--player-radius: 8px;                   /* a video's corners */
	--player-surface: #fff;                 /* a card's background, and the volume slider's */
	--player-line: #e5e5e5;                 /* a card's border, and the volume slider's */
	--player-card-radius: 12px;             /* a card's corners, and the volume slider's */
	--player-title-font: var(--font-heading); /* a card's title */
}
```

`--player-shadow`, `--player-time-size`, `--player-overlay`, and
`--player-overlay-fg` (a video's controls over the picture) are there
too, and for cards, `--player-fg`, `--player-font`,
`--player-title-size`, `--player-by-size`, `--player-card-pad`,
`--player-art-size`, `--player-art-radius`, and `--player-pop-shadow`
(what lifts the volume slider off the page). A card's text and fonts
are its surroundings' unless you set them. To draw the player yourself, register your own `blush/player`,
or an empty one to load nothing: `new Asset('blush/player')` in your
provider, or `"blush/player": {}` under `assets`.

### Translations

A theme's text lives in its `lang/` folder, one catalog per language
(`en.json`, `fr.json`, `fr_CA.json`; YAML works too), read with
`$template->t('key')`. Start each catalog with `@@locale` and `@@domain`,
which say what it translates: the language, and your theme's name.

```json
{
	"@@locale": "fr",
	"@@domain": "acme/notebook",
	"read_more": "Lire la suite de {title}"
}
```

A page looks for each message in its own language (`fr_CA`), then
without the region (`fr`), then your site's language, and last English.
So `en.json` is the one catalog every theme should have: a site in a
language you haven't translated shows your English text.

Keys that start with `@@` aren't messages, so they never show on a page.
They let a catalog be recognized on its own, away from its folder, such
as when it's sent to a translator and back. Every catalog Blush ships
has them, and plugins and icon packs use them the same way, with their
own names. In YAML, quote them: `'@@locale': fr`. `theme:check` warns
about a catalog whose `@@locale` or `@@domain` doesn't match its file or
your theme (and, with `--strict`, notes one without them).

Values in a message are text, and escaped with it. When one is HTML,
such as a list of linked names, mark it with `raw()`: it goes in as it
is, and the message's own words and every other value are still
escaped.

```php
<?= e($template->t('posted_by', names: raw($links), date: $date)) ?>
```

`attr()` escapes it all, so the same message can go in a `title` too.

A child theme's catalogs come before its parent's, message by message,
so a child rewords only what it needs to.

#### Your own wording

To reword or translate any text without touching the theme or plugin
it comes from, put your own catalog in `user/lang/`, one folder per
language:

```
user/lang/
  en/
    blush.json                          Blush's own text
    app.json                            your site's (resources/lang)
    extensions/acme/notebook.json       a theme's, by its name
    extensions/acme/hello.json          a plugin's or icon pack's
  fr/
    extensions/acme/notebook.json
```

Your catalog wins over the package's, message by message, so it only
needs what you change, and the rest still comes from the package. The
language is checked first, so the package's own `fr.json` still beats
your `en` folder on a French page. For a group such as the default
footer's `powered_by` lines, your group replaces the theme's: list only
the lines you want.

```json
{
	"@@locale": "en",
	"@@domain": "blush/default",
	"skip_to_content": "Jump to the post",
	"powered_by": {
		"tea": "Powered by tea."
	}
}
```

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
their URLs. The default theme's `partials/pagination.php` is a full
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

A directive used in an entry's Markdown (`::app/post-list{limit=5}`)
needs no `cache()`: it's kept with the entry's rendered content, on the
same terms.

### Avatars

`$template->avatar($profile)` draws a person's avatar the way the admin
does: a square box with up to two initials of their name ("Jane Doe" is
JD), as an inline SVG. Give a size in pixels as the second argument (48
by default):

```php
<?php if ($author = $template->author($entry)) : ?>
	<p class="byline">
		<?= $template->avatar($author, 32) ?>
		<?= e($author->title) ?>
	</p>
<?php endif ?>
```

Beside a printed name, it's hidden from screen readers. Alone, give it a
label so it's read as an image: `$template->avatar($profile, label:
$profile->title)`.

It's gray until your theme styles it. Its class is `avatar`, and two
custom properties set its colors; the letters use the page's font:

```css
.avatar {
	--avatar-background: #fde2e4;
	--avatar-color: #9d174d;
	border-radius: 50%; /* a circle; leave it out for a square */
}
```

### Which template is used

Blush picks the most specific template your theme (or its parents) has:

| Page | Templates tried, in order |
|---|---|
| An entry | `single-{type}-{slug}`, `single-{type}`, (`single-terms`), `single-{kind}`, `single` |
| A listing | `collection-{type}`, (`collection-terms`), `collection-{kind}`, `collection` |
| A term's page, or any entry's page listing what links to it | `term-{type}-{slug}`, `term-{type}`, `term`, `collection` |
| A date archive | `archive-date-{type}`, `archive-date`, `collection` |
| A type's people (such as `/recipes/cooks`) | `people-{type}-{field}`, `people-{field}`, `people`, `collection` |
| A person's archive (such as `/recipes/cooks/jane`) | `person-{type}-{field}`, `person-{field}`, `person`, `profile`, `collection` |
| What a type's relation links to (such as `/movies/directors`) | `related-list-{type}-{relation}`, `related-list-{relation}`, `related-list`, `collection` |
| An entry's archive under a relation (such as `/movies/directors/penny`) | `related-{type}-{relation}`, `related-{relation}`, `related`, `term`, `collection` |
| A profile's page (such as `/profiles/jane`) | `profile-{slug}`, `profile`, `collection` |
| The homepage | `home`, then whatever it shows |
| An error | `error-{status}`, `error` |
| A site with no homepage yet | `welcome` |

On a type's people page, `$entries` holds the profiles (link each with
`$template->personUrl($profile, $type, $page->people->field)`) and
`$entry` is the field's list page (`_cooks`), when it has one. On a
person's archive, `$entry` is the page written for it
(`_cooks/jane`) or else the profile, `$page->profile` is always the
profile, and `$entries` their entries of that type. On a profile's
page, `$entry` is the profile and `$entries` everything crediting
them.

`{kind}` is the kind of the entry's type: `collection` (such as posts),
`tree` (pages, and any tree of your site's own), or `profiles`. So
`single-tree.php` draws every tree's pages and `single-collection.php`
every collection's entries, whatever a site names its types. A
[type of terms](content-types.md#terms-and-relationships) (one a
classify relation files entries under, such as tags) tries `-terms`
first: `collection-terms.php` lists any such type's terms, and
`single-terms.php` draws a term served as a single entry (one whose
type has no term pages).

The welcome page shows until `user/content/index.md` exists. Its
content (the next steps, and, outside production, any problems
`bin/blush doctor` would report) is the default theme's
`partials/welcome` part, so a theme's own `welcome` view can wrap it in
its markup with `$template->include('partials/welcome')`.

An entry's `template` front matter is always tried first. Feeds
(`feed-rss`, `feed-atom`, `feed-json`) and sitemaps (`sitemap`,
`sitemap-index`) are templates too, if you need to change them.

### Directives and components

Your theme draws two kinds of pieces:

- **[Directives](directives.md)** are what content says: callouts,
  galleries, buttons. They come from Blush, plugins, and the site, never
  a theme, so content never depends on one theme. Your theme gives them
  their look.
- **[Components](components.md)** are your theme's own building blocks
  for its templates: a card, a post header, a list of archives. Content
  never names them.

**Giving directives a look.** The built-in directives work in every
theme, drawing themselves with Blush's templates: `callout`, `embed`,
`figure`, and `gallery`; the layouts `group`, `grid`, `row`, and
`stack`; the media directives `audio`, `video`, and `file`; the inline
ones `abbr`, `badge`, `button`, `cite`, `dfn`, `icon`, `ins`, `kbd`,
`samp`, `small`, `time`, and `var`; `toc`, a table of contents; `menu`;
and `progress` and `meter`. To change how one looks, style its classes
(`.directive-callout`, `.directive-callout__title`), or add your own
`views/directives/callout.php` (or `blush-callout.php`, and so on);
yours wins. A plugin's directive is drawn the same way:
`views/directives/{namespace}-{name}.php`.

To give a directive a style of your own, add a
[variant](directives.md#variants) in `theme.json` and style its class
(`.directive-callout--bordered`), or give it a template of its own
(`views/directives/callout-bordered.php`). A template in `directives/`
for a directive no one registered is never drawn: a theme can't add
directives, so make it a component instead.

**Your own components.** A theme's components are in its namespace,
which its `theme.json` declares: the `notebook` namespace's card is
`notebook/card`. A template-only component is a file in
`views/components/` named `{namespace}-{name}.php`, such as
`views/components/notebook-card.php`. Use it in a template with
`<?= $template->component('notebook/card', entry: $entry) ?>`.

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
it, so the class needs no `render()`; a site can override the template
with its own:

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

See [A component with a class](components.md#a-component-with-a-class)
for writing it.

`bin/blush directive:list` lists every directive and the file that draws
each, and `bin/blush component:list` your theme's components.
`bin/blush theme:why directives/callout` shows what a file overrides.
`theme:check` warns about a directive or component with a PHP class but
no template, about a file in `directives/` for no registered directive
or in `components/` not named for a component, about variants in
`theme.json` for directives that don't exist or with names that aren't
valid, and about a variant's template that's also another directive's.
With `--strict`, it also notes variants without a translated label (see
[Labels and translations](directives.md#labels-and-translations)).

### Bleed

The admin's editor can widen any element at the top of an entry (an
image, a paragraph, a block) past the text column: **Wide**, into
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
extensions/acme/notebook/
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
users rely on, and prints `$template->foot()`. Name a theme to check one that isn't active
(`bin/blush theme:check acme/notebook`): its code runs as it would if it
were active, provider and all, when its requirements are met, so its
components with a class render in the check. With `--strict`, it also notes a base layout whose
`<html>` has no `dir`: write `<html lang="<?= attr($site->lang) ?>"
dir="<?= attr($site->dir) ?>">`, so a page in a right-to-left language
(`$site->dir` is `rtl`) reads right to left.

### Going live with a theme

Theme files are served straight from the theme folder by default. On a
live site, copy them into `public/` so the web server can hand them out:

```sh
bin/blush theme:publish
```

Run it again after changing theme files.
