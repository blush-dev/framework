# Menus

A **menu** is a list of links, such as your site's main navigation or
your social profiles. You can make as many menus as you like. Your theme
decides where menus can appear: it declares **locations** (`primary`,
`social`, `footer`), and you **assign** a menu to each location you want
filled. One menu can fill several locations.

Theme authors: see [Menus in themes](../features/menus.md) for declaring
locations and printing menus.

## Making a menu

Make and edit menus in the admin, under **Structure → Menus** (see
[Menus in the admin](../admin/menus.md)), or as files. Each menu is a file in
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
letters, digits, hyphens, and underscores. A site [kept in a database](../running/sqlite.md)
keeps its menus there instead of in files.

## Showing a menu

Assign a menu to one of your theme's locations, in the admin (the
**Locations** under **Structure → Menus**, or a menu's **Settings**) or with
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
[`menu` block](blocks/menus.md):

```md
::menu{name=social}
```

## Links

Each item links to one thing:

| Key | Links to | Example |
|---|---|---|
| `entry` | An entry, as `{type}/{key}`. Its title is the label. | `"entry": "page/about"`, `"entry": "post/hello-world"` |
| `term` | A term's page, as `{type}/{slug}`, for a [type of terms](relationships.md) with pages. Its title is the label. | `"term": "category/art"` |
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

## Item options

| Key | What it does |
|---|---|
| `label` | The text shown. Needed for `route` and `url` links; for the others it replaces the title. |
| `children` | A list of items shown under this one. An item with children and no link is a heading for them. |
| `icon` | An [icon](blocks/icons.md) shown with the label, such as `house` or `mytheme/github`. |
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

## Checking your menus

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

The page's language picks the text: an entry's language's locale (see
[Translations](translations.md)), or your site's (`APP_LOCALE`). If there's no text for it, the language without its
region is tried (`fr` for `fr_CA`), then your site's, then English, then
the first one written.

On a site with [translations](translations.md), links follow the
page's language too, so write each link once. On a French page,
`"entry": "page/about"` links to `about.fr.md` with its French title,
`term` links to the French topic, and `collection` links to the French
listing when there are French entries. Anything that isn't translated
yet links to the original. Leave `label` off entry, term, and collection
links so the translated title shows.
