# `theme.json`

`theme.json` is a theme's manifest: its name, what it builds on, the
files every page loads, and the options it offers.

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

## Keys

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
  [Creating a plugin](../plugins/creating-a-plugin.md)). The admin shows
  them on the theme's details.
- **`abandoned`:** `true` when the theme is no longer maintained, or the
  name of the package to use instead, as a plugin has it (see
  [Creating a plugin](../plugins/creating-a-plugin.md)). It only warns:
  an abandoned theme can still be activated.
- **`suggest`:** packages that work well with the theme, each with why,
  as a plugin has them (see [Creating a plugin](../plugins/creating-a-plugin.md)).
  They're only shown, on the theme's details.
- **`parent`:** the name of the theme this one builds on, instead of
  starting from the default. Anything this theme doesn't include comes
  from its parent. See [Child themes](child-themes.md).
- **`require`:** what the theme needs, with version constraints, as a
  plugin's [requirements](../plugins/requirements.md) work: Blush
  (`blush-dev/framework`), `php`, PHP extensions, other plugins,
  themes, or icon packs by name (`"acme/shop": "^2.0"` needs the Shop
  plugin turned on), and libraries Composer installed. A theme whose requirements aren't met can't be
  activated. When the active theme's requirements stop being met, or
  those of a theme it falls back to, visitors see the default theme
  until that's fixed; the Themes screen, `theme:check`, and `doctor` say
  why.
- **`conflict`:** what the theme can't run with, as a plugin's
  [conflicts](../plugins/conflicts.md) work. A theme that conflicts with
  something that's on can't be activated, and when the active theme (or
  one it falls back to) comes to conflict, visitors see the default
  theme until that's fixed. The extension it names carries on.
- **`replace`:** the packages the theme stands in for, as a plugin's
  [`replace`](../plugins/replacing-extensions.md) works: a
  requirement of one is met by the theme while it's active, and it can't
  be activated while one it replaces is on.
- **`provide`:** the packages the theme implements, as a plugin's
  [`provide`](../plugins/composer-packages.md) works: a requirement of
  one is met by the theme while it's active.
- **`provider`** and **`autoload`:** a theme can run PHP, through a
  service provider of its own (see [Blocks and components](blocks-and-components.md)).
  `autoload` is Composer's shape: `psr-4` folders, and `files` loaded
  once when the theme is in use. It still can't add content types,
  routes, or commands; those belong to a
  [plugin](../plugins/README.md).
- **`preload`:** files every page should fetch early, relative to the
  theme, such as the fonts your stylesheet uses, so text doesn't wait
  on them. What each file is comes from its extension: a font is
  preloaded as a font (and fetched `crossorigin`, as browsers require),
  `.css` as a style, `.js` as a script, and images as images. A file
  also in `styles` or `scripts` is preloaded with its `?v=` version, as
  the page loads it; any other file, like a font, is preloaded without
  one, matching how your stylesheet asks for it. For a file only some
  pages need, use `$template->head()->preload()` instead (see
  [The head and the foot](head-and-foot.md)).
- **`assets`:** styles and scripts your theme registers by name, which
  pages load only when they ask for them. A theme with a `provider`
  registers them there instead. See [Scripts and styles](scripts-and-styles.md).
- **`settings`:** options site owners set in your theme's own group of
  settings (`user/data/settings/{vendor}__{name}.json`; see
  [Theme settings](settings.md)). They use the same field types as
  [custom fields](../concepts/custom-fields.md). None may be named
  `menus`, which keeps the site's menu assignments.
- **`menus`:** the places your theme shows the site's menus, with a
  default menu for each if you like. See [Menus in templates](../features/menus.md).
- **`bleed`:** the classes that widen an element past the text column;
  see [Bleed](bleed.md).
- **`variants`:** styles your theme adds to blocks, by block,
  such as `{"callout": ["bordered"]}`. See
  [Blocks](../features/blocks/README.md) for variants. Under `image`, the
  classes it offers images; see [Image variants](image-variants.md).
- **`preview`:** what the admin's Themes screen draws your theme's
  preview from; see [The admin's preview](#the-admins-preview).

## The admin's preview

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

## Autocomplete in your editor

Blush ships a JSON Schema for `theme.json`, so editors such as VS Code and
PhpStorm can suggest keys, show what each one does, and flag mistakes.
`theme:new` points the manifest at it with a `$schema` key, beside the
[schema version](../concepts/extensions.md) the file is written to:

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/theme.v1.schema.json",
	"schemaVersion": 1,
	"name": "acme/notebook"
}
```

The path is relative to `theme.json`. A new site's `.vscode/settings.json` also maps every
`extensions/*/*/theme.json` to the schema, so VS Code
finds it even without `$schema`.

The schema covers the built-in field types. A field type from a plugin
is allowed, but the editor can't suggest its options.
