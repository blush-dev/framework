# Menus and regions

A **menu** is a list of links, such as your site's main navigation or
your social profiles. A **region** is an area of the page, such as a
sidebar or the footer, filled with directives, components, text, or template parts.

Both are files in `user/data/`, one file per menu or region. Your theme
decides where they appear: it declares **locations** (`primary`, `social`,
`footer`), and each one shows your menu or region with the same name.

## Menus

Add a file named for the location to `user/data/menus/`. The default
theme shows a `primary` menu in its header:

```yaml
# user/data/menus/primary.yaml
items:
  - entry: page/about
  - entry: page/contact
  - collection: post
    label: Blog
  - route: home.feed
    label: Feed
  - url: https://github.com/example
    label: GitHub
```

JSON works too (`primary.json`); if both exist, JSON wins. A file can
also be the list of items on its own, without `items:`.

### Links

Each item links to one thing:

| Key | Links to | Example |
|---|---|---|
| `entry` | An entry, as `{type}/{key}`. Its title is the label. | `entry: page/about`, `entry: post/hello-world` |
| `term` | A term's page, as `{type}/{slug}`, for a [type of terms](content-types.md#terms-and-relationships) with pages. Its title is the label. | `term: category/art` |
| `collection` | A content type's listing. Its landing page's title is the label, if it has one. | `collection: post` |
| `route` | A named route, with any `params` it needs. Run `bin/blush routes:list` for names. | `route: home.feed` |
| `url` | Any URL, as written | `url: https://example.org/` |

Linking to an entry rather than typing its URL means the menu follows
along when a slug or permalink changes. Drafts, scheduled entries, and
entries that don't exist are left out, so a menu never links to a page
that isn't there. A scheduled entry appears on its own once it goes live.

An entry's `key` is its file name without the extension, with any folders
below its type's folder: `user/content/about.md` is `page/about`, and
`user/content/about/team.md` is `page/about/team`. A landing page
(`index.md` in a type's folder) is `{type}/`.

### Item options

| Key | What it does |
|---|---|
| `label` | The text shown. Needed for `route` and `url` links; for the others it replaces the title. |
| `children` | A list of items shown under this one. An item with children and no link is a heading for them. |
| `icon` | An [icon](directives.md#icons) shown with the label, such as `house` or `mytheme/github`. |
| `description` | A short line of text under the label, for larger dropdown menus. |
| `image` | The URL of a small image shown with the label. |
| `badge` | A short tag, such as `New`. |
| `class` | A CSS class for the item. |
| `rel` | The link's `rel`, such as `me` for your own profiles. |

A menu can have its own `label`, which names the navigation for screen
readers. Without one, the theme's name for the location is used.

```yaml
label: Main
items:
  - label: Services
    children:
      - entry: page/services/design
        description: Websites and brands
      - entry: page/services/writing
        badge: New
```

Themes may accept more options for their menus, such as a number of
columns for a large dropdown. Their documentation lists them.

### Checking your menus

```sh
bin/blush menu:list          # each location, the menu it shows, and any problems
bin/blush menu:show primary  # the menu as a page shows it, with every URL
```

An item that can't be shown (a missing entry, a typo in a key, no label)
is left out of the page and reported by both commands, by
`bin/blush theme:check`, and in the log.

## Regions

Add a file named for the location to `user/data/regions/`. The default
theme shows a `footer` region above its credit line:

```yaml
# user/data/regions/footer.yaml
items:
  - directive: menu
    name: social
  - markdown: "Thanks for reading. **Subscribe** to the [feed](/feed)."
  - entry: page/_regions/newsletter
  - view: partials/newsletter
    heading: Get new posts by email
```

Each item is one of:

| Key | What it shows |
|---|---|
| `directive` | A [directive](directives.md), such as `menu`. The item's other keys are its props. |
| `component` | A [component](components.md). The item's other keys are its props. |
| `markdown` | Markdown text, with directives. |
| `entry` | An entry's content, as `{type}/{key}`. |
| `view` | A template part from your site or theme. The item's other keys are its data. |

For longer text, write a page in a folder whose name starts with `_`,
such as `user/content/_regions/newsletter.md`. It never gets a URL of its
own, but a region can show it with `entry: page/_regions/newsletter`.

A theme can fill a region with defaults, such as a search box in its
sidebar. Your file replaces the theme's items, so an empty
`items: []` clears them.

## Autocomplete in your editor

Blush ships JSON Schemas for menu and region files, so editors such as
VS Code and PhpStorm can suggest keys and flag mistakes, like a typo in a
key or an entry written as a URL. A new site's `.vscode/settings.json`
already maps `user/data/menus/` and `user/data/regions/` to them. For
other editors, point a file at its schema. In YAML, that's a comment on
the first line:

```yaml
# yaml-language-server: $schema=../../../vendor/blush-dev/framework/resources/schemas/menu.schema.json
items:
  - entry: page/about
```

In JSON, it's a `$schema` key (`region.schema.json` for regions):

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/menu.schema.json",
	"items": [{ "entry": "page/about" }]
}
```

Options a theme adds to its menu items, and a directive's or component's props, aren't
in the schema, so the editor won't suggest them.

## Using another name

If you switch to a theme that calls its locations something else, point
them at your files in `user/data/theme.json` instead of renaming the
files:

```json
{
	"menus": { "main": "primary" },
	"regions": { "aside": "sidebar" }
}
```

Here the theme's `main` location shows `menus/primary.yaml`.

## More than one language

Any text can be written once per language:

```yaml
- entry: page/about
  label:
    en: About
    fr: À propos
```

The page's language picks the text: an entry's `locale`, or your site's
(`APP_LOCALE`). If there's no text for it, the language without its
region is tried (`fr` for `fr_CA`), then your site's, then English, then
the first one written.

On a site with [translations](content.md#translations), links follow the
page's language too, so write each link once. On a French page,
`entry: page/about` links to `about.fr.md` with its French title,
`term:` links to the French topic, and `collection:` links to the French
listing when there are French entries. Anything that isn't translated
yet links to the original. Leave `label` off entry, term, and collection
links so the translated title shows.

## For theme authors

Declare the locations your theme shows in `theme.json`. Each is a label,
or an object:

```json
"menus": {
	"primary": "Primary",
	"mega": {
		"label": "Main",
		"depth": 2,
		"fields": {
			"columns": { "type": "number", "integer": true, "default": 1, "label": "Columns" }
		}
	},
	"social": "Social"
},
"regions": {
	"sidebar": {
		"label": "Sidebar",
		"items": [{ "directive": "menu", "name": "social" }]
	},
	"footer": "Footer"
}
```

- A menu location's label also names its `<nav>` for screen readers, so
  keep it short and leave out "navigation" or "menu" ("Primary", not
  "Primary navigation").
- `depth` is how many levels deep the location shows (1 is the top
  level); deeper items are left out.
- `fields` are extra options for each item, with the same field types as
  [custom fields](content-types.md#custom-fields). Read one with
  `$item->field('columns')`. A key that isn't a built-in option or a
  declared field is reported as a problem.
- A region location's `items` are the defaults it shows until the site
  has its own file.

A child theme inherits its parent's locations, and can redeclare one to
change it.

### Printing a menu

The `menu` directive prints a menu with accessible markup: a `<nav>` named
by the menu's label, nested lists, and `aria-current="page"` on the link
to the current page.

```php
<?= $template->directive('menu', name: 'primary') ?>
```

It prints nothing when the site has no menu for the location. Its
classes start with `directive-menu` (`directive-menu--primary`,
`directive-menu__item--current`, `directive-menu__link`). Items with
children get a toggle button, hidden, for a dropdown: your script can
show it and open and close the submenu it controls (`aria-controls`),
updating `aria-expanded`. Without a script, submenus stay open.

For markup of your own, get the menu with `$template->menu()`:

```php
<?php if ($menu = $template->menu('social')) : ?>
	<ul class="social">
		<?php foreach ($menu->items as $item) : ?>
			<li class="<?= attr($item->class) ?>">
				<a href="<?= url($item->url) ?>" <?= $item->ariaCurrent() ?>>
					<?= $template->icon($item->icon) ?>
					<span><?= e($item->label) ?></span>
				</a>
			</li>
		<?php endforeach ?>
	</ul>
<?php endif ?>
```

Each item has `label`, `url` (`null` for a heading), `icon`,
`description`, `image`, `badge`, `class`, `rel`, `children`, and
`field($name)`. `current` is true for the current page's item, and
`ancestor` for the items above it. `ariaCurrent()` prints the attribute
that says so: `aria-current="page"` on the current page's item,
`aria-current="true"` on the items above it, and nothing on the rest.

For a social menu, ship the brand icons you need with your theme, in its
`icons/` folder (`icons/github.svg` is `mytheme/github`); Blush doesn't
include brand logos.

### Printing a region

```php
<?php if ($template->hasRegion('sidebar')) : ?>
	<aside class="sidebar">
		<?= $template->region('sidebar') ?>
	</aside>
<?php endif ?>
```

`region()` returns the items' HTML in order, and `''` when there are
none. An item that can't be shown is left out and logged.
