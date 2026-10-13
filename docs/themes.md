# Themes

A theme controls how your site looks. It never owns your content or your
URLs, so you can switch themes at any time without breaking anything.

Blush ships with a **default theme**: plain, accessible, light and dark,
with no build step. A theme is expected to include its own templates;
anything it leaves out comes from Blush's own plain versions, not from
the default theme. To build on the default theme, name it as your
theme's `parent`.

There are three ways to change how your site looks, from least to most
work:

1. [Adjust the active theme's settings](#settings)
   with a data file.
2. [Override a few templates](#overriding-templates) with a child theme.
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
saved in `user/data/settings/theme.json` instead, and wins over
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

To adjust a theme without touching its files, set the options it offers,
if any, in its own group of settings, a file named for the theme with
`__` for its `/`. For `acme/notebook`, `user/data/settings/acme__notebook.json`:

```json
{
	"layout": "grid"
}
```

Each theme keeps its own, so switching themes and back keeps what you
set. The default theme has none.

A single entry can change its own look too, with front matter:

```yaml
class: wide-page             # extra classes on <body>
stylesheet: /media/zine.css  # an extra stylesheet
```

## Your own settings

A site can add settings to the admin's **Settings** screens with a
[field set](content-types.md#field-sets) aimed at one: `settings:general`, `settings:reading`,
`settings:writing`, `settings:media`, `settings:search`, `settings:ai`,
or `settings:api`.

`user/data/fields/brand.json`:

```json
{
	"label": "Brand",
	"targets": ["settings:general"],
	"fields": {
		"tagline": { "label": "Tagline" },
		"accent": { "type": "enum", "options": ["red", "blue"], "default": "blue" }
	}
}
```

The set's fields show as a panel of their own on that screen, and what's
saved goes in `user/data/settings/site.json`. Read them in a
template with `$site->setting()`:

```php
<p class="tagline"><?= e((string) $site->setting('tagline')) ?></p>
```

It returns the saved value, read through its field (a date is a date),
or the field's `default`, or the second argument when there's neither:
`$site->setting('accent', 'red')`. Settings share one name space across
the screens, so two sets can't both add a `tagline`; `bin/blush
content:lint` says so.

## Overriding templates

Templates belong to themes; your site has none of its own. To change
one piece of a theme, make a **child theme**: a theme whose `parent` is
the one you're changing. Anything the child doesn't include comes from
its parent, so it holds only the templates you change.

```sh
bin/blush theme:new acme/notebook-child --parent=acme/notebook
bin/blush theme:activate acme/notebook-child
```

Then copy the template into the child's `views/` folder and edit the
copy. The child's copy wins. For example, to change the site footer,
copy the default theme's `views/partials/footer.php` to
`extensions/acme/notebook-child/views/partials/footer.php`.

Only the active theme's `styles` are loaded, but a file the child
doesn't have is found in its parent. `theme:new` gives a child no
`style.css` and no `styles`, which means `["style.css"]`, so the
parent's `style.css` loads. To add styles on top, add your own file and
list both: `"styles": ["style.css", "child.css"]`. If the parent's
stylesheets have other names, list them the same way. Icons work the same way
(the child's `icons/`), and a child's catalogs reword its parent's text
(see [Translations](#translations)).

The default footer ends with "Powered by Blush." To reword it, you don't
need to copy the template: its key is `powered_by` (see
[Your own wording](#your-own-wording)).

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

In a terminal, `bin/blush theme:new` on its own asks for the name, a
label, a parent theme to build on (or none), a description, and whether
to activate it now.

That makes the smallest valid theme, in a folder at its name:

```
extensions/acme/notebook/
  theme.json    {"$schema": "…", "name": "acme/notebook", "label": "Notebook", "namespace": "acme-notebook", "version": "1.0.0", "styles": ["style.css"]}
  style.css
  lang/en.json  {"@@locale": "en", "@@domain": "acme/notebook"}, for your theme's text
```

`--label`, `--namespace`, and `--parent` (another theme's name) set those
instead of the defaults.

Every template your theme doesn't include comes from its parent, if it
has one, and last from Blush's own templates. Those are plain on
purpose:

- **`layouts/base`** is the page skeleton: the `<head>`
  (`$template->head()`), a skip link, the page in `<main id="main">`,
  and the end of the body (`$template->foot()`). It has no site header
  or footer, so most themes include their own `layouts/base.php`.
  `bin/blush theme:check --strict` notes a theme without one.
- **`single`, `collection`, and `error`**, with the parts `entries`,
  `entry-summary`, `entry-meta`, and `pagination`, are bare HTML, so a
  page still shows when your theme doesn't have a template for it.
- **The welcome page, feeds, and sitemaps** are Blush's, and your theme
  can replace any of them.

To start from the default theme's design instead, make it your theme's
parent (`bin/blush theme:new acme/notebook --parent=blush/default`).
Its **stylesheet** still isn't loaded: only the active theme's `styles`
are, so your `style.css` starts from a blank page. Copy the default
theme's `style.css` in as a starting point if you like.

A full theme can have:

```
extensions/acme/notebook/
  theme.json      The manifest
  style.css       Styles (list more in "styles")
  views/
    layouts/      base.php, and any others
    partials/     header.php, footer.php, and other pieces
    blocks/   Your look for blocks, such as callout.php
    components/   Your theme's own components, for its templates
    single.php  collection.php  ...
  icons/          SVG icons, such as badge.svg (see Blocks)
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
  `core`, `theme`, and `default` are reserved, and no two
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
  service provider of its own (see [Blocks and components](#blocks-and-components)).
  `autoload` is Composer's shape: `psr-4` folders, and `files` loaded
  once when the theme is in use. It still can't add content types,
  routes, or commands; those belong to the site or a
  [plugin](extending.md#plugins).
- **`preload`:** files every page should fetch early, relative to the
  theme, such as the fonts your stylesheet uses, so text doesn't wait
  on them. What each file is comes from its extension: a font is
  preloaded as a font (and fetched `crossorigin`, as browsers require),
  `.css` as a style, `.js` as a script, and images as images. A file
  also in `styles` or `scripts` is preloaded with its `?v=` version, as
  the page loads it; any other file, like a font, is preloaded without
  one, matching how your stylesheet asks for it. For a file only some
  pages need, use `$template->head()->preload()` instead.
- **`assets`:** styles and scripts your theme registers by name, which
  pages load only when they ask for them. A theme with a `provider`
  registers them there instead. See [Scripts and styles](#scripts-and-styles).
- **`settings`:** options site owners set in your theme's own group of
  settings (`user/data/settings/{vendor}__{name}.json`). They
  use the same field types as [custom fields](content-types.md#custom-fields).
  None may be named `menus`, which keeps the site's menu assignments.
- **`menus`:** the places your theme shows the site's menus, with a
  default menu for each if you like. See [Menus](menus.md#for-theme-authors).
- **`bleed`:** the classes that widen an element past the text column;
  see [Bleed](#bleed).
- **`variants`:** styles your theme adds to blocks, by block,
  such as `{"callout": ["bordered"]}`. See
  [Variants](blocks.md#variants). Under `image`, the classes it
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

The path is relative to `theme.json`. A new site's `.vscode/settings.json` also maps every
`extensions/*/*/theme.json` to the schema, so VS Code
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

	<?php if ($theme->setting('showDate')) : ?>
		<p><?= e($template->date($entry->published)) ?></p>
	<?php endif ?>

	<?= raw($entry->content()) ?>
</article>
```

`$template` is the template itself: it's how a template reaches the calls
below. Its data (such as `$entry`, `$page`, `$site`, and `$messages`)
are plain variables. For editor autocomplete, list the ones a file uses
at the top of it:

```php
/**
 * @var Blush\View\Template        $template
 * @var Blush\Translation\Messages $messages
 * @var Blush\Content\Entry\Entry  $entry
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
| `$template->layout('base')` | Wrap this template in `layouts/base.php`; a name with a folder is in `layouts/` too (`layout('shells/wide')` is `layouts/shells/wide.php`) |
| `$template->section('content')` | In a layout, print the wrapped template |
| `$template->start('name')` … `$template->stop()` | Capture a named section |
| `$template->include('partials/header', key: $value)` | Include another template |
| `$template->include(['partials/card-post', 'partials/card'])` | Include the first of these that exists |
| `$template->includeIf('partials/sidebar')` | Include it only if it exists |
| `$template->includeWhen($condition, 'partials/x')` | Include it only when the condition is true |
| `$template->includeUnless($condition, 'partials/x')` | Include it unless the condition is true |
| `$template->each('partials/card', $entries, as: 'entry', empty: 'partials/none')` | Include a template once per item (see below) |
| `$template->component('notebook/card', title: '...')` | Render a component (see [Components](components.md)) |
| `$template->block('callout', variant: 'tip')` | Render a block, as content would (see [Blocks](blocks.md#using-blocks-in-templates)) |
| `$template->icon('house', 'Home')` | An icon, decorative or labeled (see [Icons](blocks.md#icons)) |
| `$template->route('home')` | A named route's URL (`bin/blush routes:list`), with any parameters: `route('post.collection.month', ['year' => 2026, 'month' => '05'])`. On a [translated](content.md#translations) page, it's that language's version when there is one (`/es` for `home`) |
| `$template->date($entry->published)` | A date in the site's date format and the page's language; or give an [ICU pattern](https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax), such as `'MMMM y'` |
| `$template->time($entry->published)` | A time in the site's time format |
| `$template->datetime($entry->published)` | Both, joined as the site's language joins them (`October 4, 2026 at 2:30 PM`) |
| `$template->widont($title)` | Escaped text whose last two words won't split across lines, so a title never ends with one word alone |
| `$template->cache('key', fn () => ...)` | Keep a piece of HTML that's slow to build (see below) |
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

Every template gets four objects besides `$template`:

| Variable | What it is |
|---|---|
| `$site` | The site: `name`, `url`, `description`, `locale`, `lang`, and `dir`, and `$site->setting('name')` for a site setting a field set adds ([Your own settings](#your-own-settings)) |
| `$theme` | Your theme and its parents: `$theme->setting('name')` for a theme setting, `$theme->asset('app.js')` for a file's URL (versioned), `$theme->inline('svg/logo.svg')` for a file's contents (such as an SVG to print with `raw()`), each found in a child theme first; and its `name` |
| `$media` | The media library: `$media->find($value)` (see [Media](#media)) |
| `$messages` | Your text: `$messages->t('key', 'Text', ...)`, with its text for when no catalog has it ([Translations](#translations)) |

Content pages also
get `$page`, `$entry` (the entry), `$entries` (a listing, when there is
one), `$type`, and `$title`, and so do their layouts, partials,
blocks, and components, so you don't have to pass them along. Anything you pass to
`include()` wins, so `$template->include('partials/summary', entry: $item)` shows
that entry instead.

`each()` saves writing a loop around `include()`. Each item reaches the
partial as the variable you name with `as` (`$item` if you don't), along
with `$index` (its position, from `0`) and anything else you pass. With no
items, it includes the `empty` template, if you gave one:

```php
<?= $template->each('partials/entry-summary', $entries, as: 'entry', empty: 'partials/no-entries') ?>
```

An entry offers `title`, `slug`, `published`, `updated`, `content()`
(its rendered Markdown; `raw()` is the Markdown as written),
`summary()`, `excerpt()`, `subtitle()`, `wordCount()`, `readingTime()`
(in minutes), and `field('name')` for any declared field in its front
matter (a key nothing declares reads as `null`; a theme registers the
keys its templates read, as a plugin [does](extending.md#fields-from-a-plugin)).

An entry also leads to the entries and files it's linked to:

| Call | What it does |
|---|---|
| `$entry->url()` | Its URL, or `''` when it has no page |
| `$profile->avatar(48)` | A profile's avatar, to print: its picture, else a square box with its initials (see [Avatars](#avatars)). Empty for an entry that isn't a profile |
| `$entry->link()` | A link to it: `<a href="…">Its title</a>`, escaped and ready to print. Attributes go by name: `link(class: 'tag', ariaCurrent: 'page')` (a camelCase name is dashed, so `ariaCurrent` is `aria-current`; `true` prints the name alone, and `false` or `null` leaves it out). An entry without a title reads "Untitled", and one without a page prints its title alone |
| `$entry->related('tag')` | The entries it [links to](content-types.md#linking-entries-to-other-entries) through one of its type's relations, such as its tags or a movie's `actors`, in order, each in its language when it's translated |
| `$entry->referencedBy('actors')` | The entries linking to it through a relation, newest first: by its name, or `'movie.actors'` for one type's |
| `$profile->archiveUrl($entry->type)` | Its [archive](content-types.md#linking-entries-to-other-entries) under a type: that type's entries crediting it through the byline, or through a relation you name (`$cook->archiveUrl('recipe', 'cooks')` is `/recipes/cooks/jane`). `''` when there's none, so a byline links with `$profile->archiveUrl($entry->type) ?: $profile->url()` |
| `$entry->creators()` | Who made it: the profiles its type's [byline relation](content-types.md#crediting-people) credits, such as a post's `authors` or a recipe's `cooks`, in order, as `related()` gives them; none when the type has no byline |
| `$entry->classifications()` | What it's filed under: the entries every one of its type's classify relations links (its categories and tags), merged, each once |
| `$entry->credits()` | The profiles it credits through every credit relation (its authors, photographers), the byline first, merged, each once |
| `$entry->references()` | The other entries it refers to through every reference relation (a movie's actors, related posts), merged, each once |
| `$entry->translations()` | It in every language it's written in, by language code, the default first, itself included |
| `$entry->relations()` | Its type's relations by name, or those of one kind: `relations(RelationKind::Credit)` for the people it can credit, its [byline relation](content-types.md#crediting-people) first, or `RelationKind::Classify` for the kinds of terms it's filed under |
| `$entry->linksTo('tag', $id)` | Whether it links to an entry through a relation, by that entry's id |
| `$entry->parent()` | A page's parent page (from its folder), or the parent of an entry in a [nesting collection](content-types.md#nesting-and-order), such as a category, or `null` |
| `$entry->ancestors()` | Its parents from the top down, for breadcrumbs |
| `$entry->children()` | A page's subpages or a nesting collection's child entries, by `position` and then title |
| `$entry->media('image')` | The media file a media field names, such as its `image`, or `null`; the same as `$media->find()` gives (see [Media](#media)) |

`related()` and `referencedBy()` give a list to loop over and count,
with a little more:

| Call | What it does |
|---|---|
| `->published()` | Only the ones that are published and have a page of their own |
| `->first()` | The first one, such as a post's author, or `null` |
| `->isEmpty()` | Whether there are none |
| `->all()` | Them as an array |
| `->relation` | The relation, whose `label` and `singular` name it ("Photographers", "Photographer"), or `null` when the entry's type has no relation by that name |
| `->byline` | Whether it's the entry type's byline relation, such as a post's `authors` |

Each is looked up once per request and kept, so call them as often as
you like (`$entry->creators()` in a condition and again in a loop is one
lookup); there's no need to save them in a variable first. An entry's
`excerpt()`, `wordCount()`, and `readingTime()` are kept the same way.

These come back whatever their status: a draft or a hidden entry is
there too, so keep to `published()` for what you show or link:

```php
<?php foreach ($entry->related('tag')->published() as $tag) : ?>
	<?= $tag->link(class: 'tag') ?>
<?php endforeach ?>
```

To list everyone an entry credits, byline first, under each relation's
name:

```php
<?php foreach ($entry->relations(Blush\Content\Relation\RelationKind::Credit) as $relation) : ?>
	<?php $profiles = $entry->related($relation->name)->published() ?>
	<?php if (! $profiles->isEmpty()) : ?>
		<p>
			<?= e($profiles->byline ? 'By' : ($relation->label ?: ucfirst($relation->name)) . ':') ?>
			<?= raw(implode(', ', array_map(static fn ($profile): string => (string) $profile->link(), $profiles->all()))) ?>
		</p>
	<?php endif ?>
<?php endforeach ?>
```
`excerpt(50, $more)` takes the number of words and HTML to end with when
the content is cut short, such as a "Continue reading" link. Without a
summary, the excerpt is the content's prose: the words of its paragraphs
and list items. Headings, images, audio, video, embeds, captions,
callouts, tables, code, footnotes, buttons, and menus are left out.

```php
<?= raw($entry->excerpt(40, ' <a href="' . url($entry->url()) . '">Continue reading</a>')) ?>
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
`$template->head()->remove('style:' . $theme->asset('style.css'))`.
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
`$theme->asset(...)`) as full URLs on your site's `url`, so pass
paths as they are. Meta tag values print as given, so give `og:image`
and the like a full URL.

### Media

A media field, such as an entry's `image`, holds the file's id. Get the
file with `$entry->media('image')`. For any other value, such as a site
setting's, every template has the media library as `$media`:
`$media->find($value)`. Both read an older value written as a path too,
return `null` for none, and give the same file, looked up once a
request.

A file has its `url`, `width`, `height`, `mime`, `kind()`, and details
(`metadata()`, with its `alt` text and `caption`). An image prints
itself with `img()`: its size, the library's alt text, and a `srcset`
of the sizes the library keeps of it, lazy-loaded. Pass attributes by
name, as `link()` takes them, to add or change any:

```php
<?php if ($image = $entry->media('image')) : ?>
	<?= $image->img(class: 'hero', loading: 'eager', sizes: '100vw') ?>
<?php endif ?>

<?php if ($logo = $media->find($site->setting('logo'))) : ?>
	<?= $logo->img(class: 'site-logo', alt: $site->name) ?>
<?php endif ?>
```

Give `alt: ''` for a decorative image. Sounds, videos, and other files
print nothing from `img()`. Your theme's own images aren't in the
library: link them with `$theme->asset('img/logo.png')`.

### Scripts and styles

Besides your theme's own `styles` and `scripts`, Blush, plugins, and
themes register scripts and styles by name, a handle such as
`core/player`, and pages load them only when they need them. Ask for
one in a template with `$template->enqueue('acme/gallery')`; it prints
in the `<head>` once, after anything it needs, however many times it's
asked for. Blocks and components ask for their own, so the audio
and video player (`core/player`) loads only on pages that play
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
		"requires": ["core/player"]
	}
}
```

A file is a path inside your theme or a full URL, or an object with
that `path`, its `attributes`, and, for a script, `footer`. Themes
register theirs after plugins, a parent's before its child's, and a
theme's provider after its `theme.json`, so a provider's handle wins;
registrations from your site's own providers (in `src/`) win over any
theme's. `theme.json`'s `styles`
and `scripts` stay as they are: files every page loads.

The player draws its colors from `--player-*` properties, set to your
page's own text and background colors. Set any of them on `:root` or
`.player` (and `.audio-card`, for audio drawn as a card, or
`.playlist`, for a playlist) in your
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
	--player-line: #e5e5e5;                 /* a card's border, the volume slider's, a playlist's rules */
	--player-card-radius: 12px;             /* a card's corners, and the volume slider's */
	--player-title-font: var(--font-heading); /* a card's title */
}
```

`--player-shadow`, `--player-time-size`, `--player-overlay`, and
`--player-overlay-fg` (a video's controls over the picture) are there
too, and for cards, `--player-fg`, `--player-font`,
`--player-title-size`, `--player-by-size`, `--player-card-pad`,
`--player-art-size`, `--player-art-radius`, `--player-art-shadow`
(none unless you set one), and `--player-pop-shadow` (what lifts the
volume slider off the page). A card's text and fonts
are its surroundings' unless you set them. To draw the player yourself, register your own `core/player`,
or an empty one to load nothing: `new Asset('core/player')` in your
provider, or `"core/player": {}` under `assets`.

### Translations

Write your text where it's used, with a key that names it:

```php
<?= e($messages->t('entry.by', 'By {author}', author: $name)) ?>
```

The second argument is the text, in whatever language you write your
theme in. It's shown wherever no catalog has the key, so your theme
reads well before anyone translates it. Values are named (`author:`),
and every value is named, never positional. Leave the text out
(`$messages->t('read_more', title: $title)`) to read a key from your
catalogs alone; a key no catalog has then shows as itself.

Text is an [ICU message](https://unicode-org.github.io/icu/userguide/format_parse/messages/),
so one message can cover what would otherwise be several:

```php
// Plurals: # is the number; other is required.
$messages->t('comments', '{count, plural, =0 {No comments} one {# comment} other {# comments}}', count: $n);

// Choosing by a value, instead of building keys from it.
$messages->t('status', '{status, select, draft {Draft} scheduled {Scheduled} other {Published}}', status: $status);

// Numbers and dates, in the page's language.
$messages->t('since', '{views, number} views since {date, date, long}', views: $views, date: $since);
```

Keep a sentence as one message, so a translator can reorder it. A note
for translators goes in `context:`, which is never a value:

```php
$messages->t('actions.post', 'Post', context: 'A button that publishes, not a blog post');
```

Classes write text the same way, with the same `t()`. A
[component or block class](#blocks-and-components) and your
theme's provider have `$this->messages->t()`; any other class asks for
`Messages` in its constructor and calls `$this->messages->t()`. Core and
plugins write theirs the same way.

`t()` gives back the message, not yet translated: it's translated when
it's printed, in the page's language. So a component can make all the
lines it might show and print only one, and only that one is looked
up. A message you hand to something else to show later, such as a
[template's label](#templates-for-single-entries), is translated where
it's shown, in that page's or the admin's language. Where you need the
text as a string right away, cast it: `(string) $text`.

Text is looked up in your theme's catalogs (a child theme's first),
then Blush's. A plugin's text is the plugin's, and Blush's is its own,
so a key your theme happens to share with them never rewords them; to
reword them, use [your own wording](#your-own-wording).

#### Catalogs

Translations live in your theme's `lang/` folder, one catalog per
language (`en.json`, `fr.json`, `fr_CA.json`). Start each catalog with
`@@locale` and `@@domain`, which say what it translates: the language,
and your theme's name.

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

`bin/blush lang:extract acme/notebook` writes the text in your code to
`lang/en.json` for translators to work from: it adds new keys, updates
text you've changed (translators should recheck those), keeps
everything else, and writes each `context:` beside its message as
`@{key}`. Give `--locale=fr` when your code's text is French, and
`--dry-run` to see what would change. You don't need the file for your
text to show; it's for translators. `theme:check` warns when
`lang/en.json` reads otherwise than your code (its text would win),
when one key has two texts, when text isn't a valid ICU message, and
when a key or text isn't plain text it can collect (such as
`"status.{$status}"`; use a `select`), and when a call names a parameter
`key:` or `text:` beside its key and text, which PHP refuses (name it
something else). Templates in other languages,
such as Blade, aren't read.

Keys that start with `@` aren't messages, so they never show on a page.
They let a catalog be recognized on its own, away from its folder, such
as when it's sent to a translator and back. Every catalog Blush ships
has them, and plugins and icon packs use them the same way, with their
own names. `theme:check` warns
about a catalog whose `@@locale` or `@@domain` doesn't match its file or
your theme (and, with `--strict`, notes one without them).

Values in a message are text, and escaped with it. When one is HTML,
such as a list of linked names, mark it with `raw()`: it goes in as it
is, and the message's own words and every other value are still
escaped.

```php
<?= e($messages->t('posted_by', names: raw($links), date: $date)) ?>
```

`attr()` escapes it all, so the same message can go in a `title` too.

A child theme's catalogs come before its parent's, message by message,
so a child rewords only what it needs to. A message given as a value
prints in the page's language too: `<?= e($label) ?>` prints a label a
provider registered.

#### Your own wording

To reword or translate any text without touching the theme or plugin
it comes from, put your own catalog in `user/lang/`, one folder per
language:

```
user/lang/
  en/
    core.json                           Blush's own text
    extensions/acme/notebook.json       a theme's, by its name
    extensions/acme/hello.json          a plugin's or icon pack's
  fr/
    extensions/acme/notebook.json
```

Your catalog wins over the package's, message by message, so it only
needs what you change, and the rest still comes from the package. The
language is checked first, so the package's own `fr.json` still beats
your `en` folder on a French page.

```json
{
	"@@locale": "en",
	"@@domain": "blush/default",
	"skip_to_content": "Jump to the post",
	"powered_by": "Powered by tea."
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

A block used in an entry's Markdown (`::acme/post-list{limit=5}`)
needs no `cache()`: it's kept with the entry's rendered content, on the
same terms.

### Avatars

`$profile->avatar()` draws a profile's avatar the way the admin does:
its picture, from the profile's `avatar` field, or, without one, a
square box with up to two initials of its name ("Jane Doe" is JD), as an
inline SVG. Give a size in pixels (48 by default):

```php
<?php if ($author = $entry->creators()->published()->first()) : ?>
	<p class="byline">
		<?= $author->avatar(32) ?>
		<?= e($author->title) ?>
	</p>
<?php endif ?>
```

Beside a printed name, it's hidden from screen readers. Alone, give it a
label so it's read as an image: `$profile->avatar(label:
$profile->title)`. Only profiles have avatars: on any other entry, it
prints nothing.

A picture is an `<img>` cropped square at the size you give. Initials
are gray until your theme styles them. Both have the class `avatar`;
two custom properties set the initials' colors, and the letters use the
page's font:

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
| What a type's relation links to (such as `/recipes/cooks` or `/movies/directors`) | `related-list-{type}-{relation}`, `related-list-{relation}`, `related-list`, `collection` |
| An entry's archive under a relation (such as `/recipes/cooks/jane` or `/movies/directors/penny`) | `related-{type}-{relation}`, `related-{relation}`, `related`, then `profile` for a profile or `term` otherwise, `collection` |
| A profile's page (such as `/profiles/jane`) | `profile-{slug}`, `profile`, `collection` |
| The homepage | `home`, then whatever it shows |
| An error | `error-{status}`, `error` |
| A site with no homepage yet | `welcome` |

On a relation's list (`/recipes/cooks`), `$entries` holds what it links
to (link each with `$target->archiveUrl($type, $page->relation->name)`), `$page->relation` is the relation, and `$entry` is its
list page (`_cooks`), when it has one. On one target's archive,
`$entry` is the page written for it (`_cooks/jane`) or else the target,
`$page->target` is always the target (a profile, for a credit),
`$page->relation` the relation, and `$entries` the entries of that type
linking to it. On a profile's page, `$entry` is the profile and
`$entries` everything crediting them.

`{kind}` is the kind of the entry's type: `collection` (such as posts),
`tree` (pages, and any other tree), or `profiles`. So
`single-tree.php` draws every tree's pages and `single-collection.php`
every collection's entries, whatever a site names its types. A
[type of terms](content-types.md#terms-and-relationships) (one a
classify relation files entries under, such as tags) tries `-terms`
first: `collection-terms.php` lists any such type's terms, and
`single-terms.php` draws a term served as a single entry (one whose
type has no term pages).

The welcome page shows until `user/content/index.md` exists. Its
content (the next steps, and, outside production, any problems
`bin/blush doctor` would report) is Blush's own
`partials/welcome` part, so a theme's own `welcome` view can wrap it in
its markup with `$template->include('partials/welcome')`.

An entry's `template` front matter is always tried first. Feeds
(`feed-rss`, `feed-atom`, `feed-json`) and sitemaps (`sitemap`,
`sitemap-index`) are templates too, Blush's own unless your theme has
them, if you need to change them.

### Templates for single entries

An entry can name a template of your theme in its `template` front
matter, which comes before the [template hierarchy](#which-template-is-used).
To offer one in the admin's **Template** row, declare it from your
theme's provider (`provider` in [`theme.json`](#themejson)), in
`boot()`:

```php
use Blush\View\EntryTemplate;
use Blush\View\EntryTemplateRegistry;

$this->container->get(EntryTemplateRegistry::class)->register(new EntryTemplate(
	'template-compact',
	label: $this->t('templates.compact.label', 'Compact List'),
	description: $this->t('templates.compact.description', 'Every post on a line of its own, its title and date.'),
	types: ['page']
));
```

- **The name** is the view's path under `views/` without `.php`, so
  `template-compact` is `views/template-compact.php`.
- **The label and description** are what the row's menu shows, in the
  site's language ([Translations](#translations)). Plain strings work
  too, shown as written.
- **The types** are the content types it's offered on, by name. Leave
  them out to offer it on every type.

The row lists only the templates with a view in your theme (or a theme
it builds on), and only on single entries: never on a type's index
page, an archive's page, or an error page. Choosing one writes its name
to `template`; **Default** takes `template` out. `bin/blush theme:check`
warns about a declared template with no view.

### Blocks and components

Your theme draws two kinds of pieces:

- **[Blocks](blocks.md)** are what content says: callouts,
  galleries, buttons. They come from Blush and plugins, never a
  theme, so content never depends on one theme. Your theme gives them
  their look.
- **[Components](components.md)** are your theme's own building blocks
  for its templates: a card, a post header, a list of archives. Content
  never names them.

**Giving blocks a look.** The built-in blocks work in every
theme, drawing themselves with Blush's templates: `callout`, `embed`,
`figure`, and `gallery`; the layouts `group`, `grid`, `row`, and
`stack`; the media blocks `audio`, `video`, `file`, and
`playlist`; the inline
ones `abbr`, `badge`, `button`, `cite`, `dfn`, `icon`, `ins`, `kbd`,
`samp`, `small`, `time`, and `var`; `toc`, a table of contents; `menu`;
and `progress` and `meter`. To change how one looks, style its classes
(`.block-callout`, `.block-callout__title`), or add your own
`views/blocks/callout.php` (or `core-callout.php`, and so on);
yours wins. A plugin's block is drawn the same way:
`views/blocks/{namespace}-{name}.php`.

To give a block a style of your own, add a
[variant](blocks.md#variants) in `theme.json` and style its class
(`.block-callout--bordered`), or give it a template of its own
(`views/blocks/callout-bordered.php`). A template in `blocks/`
for a block no one registered is never drawn: a theme can't add
blocks, so make it a component instead.

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
it, so the class needs no `render()`; a child theme can override the
template with its own:

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

`bin/blush block:list` lists every block and the file that draws
each, and `bin/blush component:list` your theme's components.
`bin/blush theme:why blocks/callout` shows what a file overrides.
`theme:check` warns about a block or component with a PHP class but
no template, about a file in `blocks/` for no registered block
or in `components/` not named for a component, about variants in
`theme.json` for blocks that don't exist or with names that aren't
valid, and about a variant's template that's also another block's.
With `--strict`, it also notes variants without a translated label (see
[Labels and translations](blocks.md#labels-and-translations)).

### BEM classes anywhere

Blush names classes the BEM way: a block, its elements (`__`), and
modifiers (`--`). Blocks and components build theirs for you
(`$block->elementClass('title')`). For your own partials, or anything
else that prints HTML, make a `Bem` for the block's name:

```php
<?php

use Blush\Support\Bem;

$bem = new Bem('pagination');

?>
<nav class="<?= attr($bem->block()) ?>">
	<a class="<?= attr($bem->element('link')) ?>" href="/page/2/">2</a>
</nav>
```

| Method | Classes |
|---|---|
| `$bem->block()` | `pagination` |
| `$bem->modifier('next')` | `pagination pagination--next` |
| `$bem->element('item')` | `pagination__item` |
| `$bem->element('item')->modifier('current')` | `pagination__item pagination__item--current` |
| `$bem->element('item')->modifier('current', 'last')` | `pagination__item pagination__item--current pagination__item--last` |
| `$bem->element('item')->when($isCurrent, 'current')` | adds `pagination__item--current` only when `$isCurrent` |
| `$bem->block()->with('alignwide')` | `pagination alignwide` |
| `$card->block()->with($list->element('card'))` | `card list__card`, two blocks on one element |

Every method returns a class you can keep chaining, without changing
the one you started from, and it prints as the full list wherever a
string goes, such as `attr()`. `with()` adds utility classes (a string
or a list) that aren't the block's own, and an empty modifier is
skipped. Names are letters, digits, hyphens, and underscores, starting
with a letter or digit; anything else throws an `InvalidClassName`.

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
(Float Right), and styles them. They're only offered while it's the active theme, not
when it's a parent, since another theme's stylesheet may not style them;
list the ones yours does.
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
links the built files, and `$theme->asset('resources/fonts/body.woff2')`
finds a font your CSS uses the same way. Copied files are reached by
their path: `$theme->asset('public/img/icon.png')`.

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

It checks the manifest and settings, the site's menus, and
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
