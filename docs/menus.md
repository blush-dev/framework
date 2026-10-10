# Menus

A **menu** is a list of links, such as your site's main navigation or
your social profiles. You can make as many menus as you like. Your theme
decides where menus can appear: it declares **locations** (`primary`,
`social`, `footer`), and you **assign** a menu to each location you want
filled. One menu can fill several locations.

## Making a menu

Make and edit menus in the admin, under **Config → Menus** (see
[the admin](admin.md#menus)), or as files. Each menu is a file in
`user/data/menus/`, named for the menu:

`user/data/menus/main.json`:

```json
{
	"items": [
		{ "entry": "page/about" },
		{ "entry": "page/contact" },
		{ "collection": "post", "label": "Blog" },
		{ "route": "home.feed", "label": "Feed" },
		{ "url": "https://github.com/example", "label": "GitHub" }
	]
}
```

A menu's name is its file name. Locations and content use it to find the
menu: renaming one in the admin moves its locations along, but an entry
that shows it with `::menu{name=…}` needs the new name. Use lowercase
letters, digits, hyphens, and underscores. A site [kept in a database](going-live.md#large-sites-sqlite)
keeps its menus there instead of in files.

## Showing a menu

Assign a menu to one of your theme's locations, in the admin (the
**Locations** under **Config → Menus**, or a menu's **Settings**) or with
the CLI:

```sh
bin/blush menu:assign primary main      # the primary location shows the main menu
bin/blush menu:assign primary --clear   # take it away again
```

Each theme keeps its own assignments, so switching themes and back
again keeps them. Add `--theme=vendor/name` to assign menus for a theme
that isn't active.

Until you assign a menu to a location, it shows the theme's default
menu for it, if the theme has one, and nothing otherwise. The default
theme has a `primary` location in its header, with no default.

To show a menu inside a page's content, name it in a
[`menu` directive](directives.md#menus):

```md
::menu{name=social}
```

## Links

Each item links to one thing:

| Key | Links to | Example |
|---|---|---|
| `entry` | An entry, as `{type}/{key}`. Its title is the label. | `"entry": "page/about"`, `"entry": "post/hello-world"` |
| `term` | A term's page, as `{type}/{slug}`, for a [type of terms](content-types.md#terms-and-relationships) with pages. Its title is the label. | `"term": "category/art"` |
| `collection` | A content type's listing. Its landing page's title is the label, if it has one. | `"collection": "post"` |
| `route` | A named route, with any `params` it needs. Run `bin/blush routes:list` for names. | `"route": "home.feed"` |
| `url` | Any URL, as written | `"url": "https://example.org/"` |

Linking to an entry rather than typing its URL means the menu follows
along when a slug or permalink changes. Drafts, scheduled entries, and
entries that don't exist are left out, so a menu never links to a page
that isn't there. A scheduled entry appears on its own once it goes live.

An entry's `key` is its file name without the extension, with any folders
below its type's folder: `user/content/about.md` is `page/about`, and
`user/content/about/team.md` is `page/about/team`. A landing page
(`index.md` in a type's folder) is `{type}/`.

### Links that survive renaming

An `entry` or `term` link can also hold the entry's id, in `ref`:

```json
{ "entry": "page/about", "ref": "0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1c01" }
```

When `ref` finds the entry, it wins, so the link still works after the
entry is renamed or moved. You don't need to write ids yourself:

```sh
bin/blush menu:refs           # lists menus with links not filed with their ids
bin/blush menu:refs --write   # adds each id, and updates links to renamed entries
```

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

```json
{
	"label": "Main",
	"items": [
		{
			"label": "Services",
			"children": [
				{ "entry": "page/services/design", "description": "Websites and brands" },
				{ "entry": "page/services/writing", "badge": "New" }
			]
		}
	]
}
```

Themes may accept more options for their menus, such as a number of
columns for a large dropdown. Their documentation lists them.

### Checking your menus

```sh
bin/blush menu:list          # each location, the menu it shows, and any problems
bin/blush menu:show primary  # what a location shows, with every URL
```

An item that can't be shown (a missing entry, a typo in a key, no label)
is left out of the page and reported by both commands, by
`bin/blush theme:check`, and in the log. So is a location assigned a
menu you don't have.

## Autocomplete in your editor

Blush ships a JSON Schema for menu files, so editors such as VS Code
and PhpStorm can suggest keys and flag mistakes, like a typo in a key
or an entry written as a URL. A new site's `.vscode/settings.json`
already maps `user/data/menus/` to it. For other editors, point a file
at the schema with a `$schema` key:

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/menu.schema.json",
	"items": [{ "entry": "page/about" }]
}
```

Options a theme adds to its menu items aren't in the schema, so the
editor won't suggest them.

## More than one language

Any text can be written once per language:

```json
{ "entry": "page/about", "label": { "en": "About", "fr": "À propos" } }
```

The page's language picks the text: an entry's `locale`, or your site's
(`APP_LOCALE`). If there's no text for it, the language without its
region is tried (`fr` for `fr_CA`), then your site's, then English, then
the first one written.

On a site with [translations](content.md#translations), links follow the
page's language too, so write each link once. On a French page,
`"entry": "page/about"` links to `about.fr.md` with its French title,
`term` links to the French topic, and `collection` links to the French
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
	"social": {
		"label": "Social",
		"items": [
			{ "url": "https://github.com/example", "label": "GitHub", "icon": "mytheme/github" }
		]
	}
}
```

- A location's label also names its `<nav>` for screen readers, so
  keep it short and leave out "navigation" or "menu" ("Primary", not
  "Primary navigation").
- `depth` is how many levels deep the location shows (1 is the top
  level); deeper items are left out.
- `fields` are extra options for each item, with the same field types as
  [custom fields](content-types.md#custom-fields). Read one with
  `$item->field('columns')`. A key that isn't a built-in option or a
  declared field is reported as a problem.
- `items` are the location's default menu, written as a menu's items
  are, shown until the site assigns the location a menu. Leave it out to
  show nothing. Link to entries by `{type}/{key}`; your theme can't know
  a site's ids.

A child theme inherits its parent's locations, and can redeclare one to
change it.

The site's assignments are kept in your theme's settings under `menus`,
so none of your [settings](themes.md#settings) can be named `menus`.

### Printing a menu

The `menu` directive prints what a location shows with accessible
markup: a `<nav>` named by the menu's label, nested lists, and
`aria-current="page"` on the link to the current page.

```php
<?= $template->directive('menu', location: 'primary') ?>
```

It prints nothing when the location has nothing to show. Its classes
start with `directive-menu` (`directive-menu--primary`,
`directive-menu__item--current`, `directive-menu__link`). Items with
children get a toggle button, hidden, for a dropdown: your script can
show it and open and close the submenu it controls (`aria-controls`),
updating `aria-expanded`. Without a script, submenus stay open.

For markup of your own, get what a location shows with
`$template->menu()`:

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
The menu's `name` is the site menu's, and `''` for your default.

For a social menu, ship the brand icons you need with your theme, in its
`icons/` folder (`icons/github.svg` is `mytheme/github`); Blush doesn't
include brand logos.
