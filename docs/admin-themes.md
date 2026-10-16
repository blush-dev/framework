# Admin themes

An admin theme changes how the admin looks: its colors, fonts, type
sizes, corner radii, and how roomy its rows are. It can't change the
admin's layout or what it does. It's a skin over the admin, not a new
admin.

Blush comes with six admin themes, all on the platform's own fonts:

- **Neutral** (`blush/neutral`): cool gray with a cobalt accent. This is
  the admin's own look, and what everyone sees until they choose another.
- **Library** (`blush/library`): cool parchment, a bottle-green accent,
  and titles in the system's serif.
- **Cyanotype** (`blush/cyanotype`): Prussian blue on sun-faded paper,
  and the print itself in the dark.
- **Dune** (`blush/dune`): sand in late sun, with the twilight indigo of
  the dunes' shadow side.
- **Observatory** (`blush/observatory`): a star chart on paper, the
  night sky in the dark, and a nebula magenta accent.
- **Ridgeline** (`blush/ridgeline`): sunset over the mountains, golden
  hour and the ridge in silhouette.

Each account chooses its own theme under **Theme and Color Scheme** on
[Your Account](admin.md#your-account). The site's default is what
everyone else sees, including the sign-in screen.

## Choosing the site's default

On **Extend → Admin Themes**, **Make Default** on a theme that's on makes
it the site's default (see [Admin themes](admin.md#admin-themes) in the
admin guide). Owners and administrators can, and any role with
`extensions.admin-themes.activate`. Or set `default` in
`config/admin-themes.php` to a theme's name:

```php
<?php

declare(strict_types=1);

return new Blush\Admin\Theme\AdminThemeConfig(
	default: 'blush/library'
);
```

An account that hasn't chosen a theme, or that chose the site's default,
sees whatever the default is, so changing the default changes their
admin too. An account that chose another theme keeps it. If an account's
theme is turned off or removed, it sees the default, and if the default
can't be shown, Neutral.

## Turning admin themes on

Neutral is always on. The others Blush comes with, and admin themes installed with
Composer, are on by default. An admin theme in your site's `extensions/`
folder is off until its switch on **Extend → Admin Themes** turns it on,
or `config/admin-themes.php` names it in `enabled`:

```php
return new Blush\Admin\Theme\AdminThemeConfig(
	enabled: ['acme/harbor']
);
```

This works the same way as [turning on](extending.md#turning-extensions-on)
plugins and icon packs: once a switch is used, the admin's saved list is
all of what's on. **Install Admin Theme** on the same screen installs one
from a `.zip`; it arrives turned off. Since an admin theme is only data,
administrators may install and delete them, not just owners. Accounts can only choose themes that are on and
whose [requirements](extending.md#requirements) are met.

## Making an admin theme

An admin theme is a folder with two files, plus any fonts it brings.
Local admin themes go in `extensions/{vendor}/{name}`, like every
[extension](extending.md):

```text
extensions/acme/harbor/
    admin-theme.json    its name and label
    tokens.json         what it changes
    fonts/              fonts it brings, if any
```

`admin-theme.json` says what the theme is:

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/admin-theme.schema.json",
	"name": "acme/harbor",
	"label": "Harbor",
	"description": "Sea blues and a serif for titles",
	"version": "1.0.0"
}
```

- **`name`** is required: `vendor/name`, matching the folder.
- **`label`** is the name people see. Without one, the theme is shown by
  its name.
- **`description`** is shown beside its swatch on Your Account. Keep it
  to a few words.

It takes `version`, `authors`, `license`, `require`, `conflict`,
`replace`, `provide`, `abandoned`, `suggest`, `keywords`, and links as
every [manifest](extending.md#extensions) does. An admin theme has no
`namespace`: it has no blocks, components, icons, or text of its own.

To publish a theme as a Composer package instead, give its
`composer.json` the type `blush-admin-theme`.

## tokens.json

`tokens.json` sets the admin's design tokens, grouped the way CSS
names things. Here is a theme that changes the accent, the corners, and
the UI font:

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/tokens.schema.json",
	"color": {
		"accent": {
			"default": "light-dark(#1d4e89, #8fb8e8)",
			"hover": "light-dark(#163d6c, #a7c8ee)"
		}
	},
	"border": {
		"radius": { "small": "4px", "medium": "8px", "large": "12px" }
	},
	"typography": {
		"fontFamily": { "ui": ["Inter", "sans-serif"] }
	}
}
```

Set every token, fonts included, even where you want Neutral's value.
Anything a theme leaves out stays as Neutral has it, and Neutral's values
can change in a later version, so a theme that leaves tokens out changes
with them. Neutral's own
[`tokens.json`](../resources/admin-themes/neutral/tokens.json) sets every
token but the font families and the chrome's colors (below), so it's a
good place to start from. For the
platform's own UI font, list its stack:

```json
"fontFamily": {
	"ui": ["-apple-system", "BlinkMacSystemFont", "Segoe UI Variable Text", "Segoe UI", "system-ui", "Roboto", "Noto Sans", "Ubuntu", "Cantarell", "Helvetica Neue", "Arial", "sans-serif"]
}
```

Blush reads `tokens.json`, checks every value, and writes the theme's
stylesheet itself. A theme never ships CSS. That's what keeps a theme
from breaking the admin: it can only set the tokens below, to values of
the right kind.

### Colors

Every color is either one color for both color schemes, or
`light-dark(light, dark)` with a color for each:

```json
"background": "light-dark(#f6f2ec, #15120e)",
"accent": { "text": "#ffffff" }
```

Colors can be hex (`#rgb`, `#rrggbb`, with or without alpha), `rgb()`,
`hsl()`, `oklch()`, or `transparent`. Color names, `var()`, and other
functions aren't accepted.

A group's `default` can be given as the group itself, so
`"surface": "#fffdfa"` is the same as `"surface": { "default": "#fffdfa" }`.

### Fonts

A font family token is a list of family names, first choice first:

```json
"fontFamily": { "ui": ["Karla", "ui-sans-serif", "system-ui", "sans-serif"] }
```

If a list doesn't end with a generic family (such as `sans-serif`,
`serif`, or `system-ui`), the platform's own UI font is used after it.
The admin's code font is always Fira Code, so the Markdown editor's
columns line up.

To bring a font, put its `.woff2` files in the theme's folder and list
them under `typography.fontFace`, as CSS's `@font-face` does:

```json
"typography": {
	"fontFace": [
		{ "fontFamily": "Karla", "src": "fonts/karla.woff2", "fontWeight": "200 800" },
		{ "fontFamily": "Karla", "src": "fonts/karla-italic.woff2", "fontWeight": "200 800", "fontStyle": "italic" }
	]
}
```

- **`fontFamily`** and **`src`** are required. `src` is a `.woff2` file
  inside the theme.
- **`fontWeight`** is a weight, or a variable font's range (`"200 800"`).
  It defaults to `400`.
- **`fontStyle`** is `normal` (the default), `italic`, or `oblique`.
- **`unicodeRange`** limits a file to some characters
  (`"U+0000-00FF, U+0131"`), so browsers download it only when a page
  needs them.

Bring every language the font covers, not only the ones you read, split
the way [Google Fonts](https://fonts.google.com) serves it: a file for
each language, each with its own `unicodeRange`, and a large script,
such as Japanese, in Google's numbered slices. List every file Google's
CSS for the font lists (`https://fonts.googleapis.com/css2?family=...`).
Browsers download only the files a page's text needs.

Blush serves only the font files a theme lists, and nothing else in its
folder.

### Every token

Lengths are in pixels (`"8px"`, `"13.5px"`, or `"0"`), and letter
spacing is in ems (`"-.014em"`). Each has a range, so a theme can loosen
or tighten the admin without breaking it.

| Token | CSS custom property | Value |
|---|---|---|
| `color.background` | `--color-background` | color: the app's ground, behind panels |
| `color.surface.default` | `--color-surface` | color: panels, tables, and the like |
| `color.surface.subtle` | `--color-surface-subtle` | color: row hover, chips, inset fills |
| `color.surface.strong` | `--color-surface-strong` | color: pressed, avatar ground |
| `color.text.default` | `--color-text` | color: primary text |
| `color.text.secondary` | `--color-text-secondary` | color: secondary text, icons, labels |
| `color.text.muted` | `--color-text-muted` | color: slugs, timestamps, placeholders |
| `color.accent.default` | `--color-accent` | color: the primary action, selection, and focus |
| `color.accent.hover` | `--color-accent-hover` | color |
| `color.accent.text` | `--color-accent-text` | color: text on a filled accent |
| `color.accent.subtle` | `--color-accent-subtle` | color: selected rows, soft pills |
| `color.accent.border` | `--color-accent-border` | color: borders on soft accent fills |
| `color.success.default` | `--color-success` | color |
| `color.success.subtle` | `--color-success-subtle` | color: its soft fill |
| `color.success.indicator` | `--color-success-indicator` | color: its dot |
| `color.warning.default` | `--color-warning` | color |
| `color.warning.subtle` | `--color-warning-subtle` | color |
| `color.warning.indicator` | `--color-warning-indicator` | color |
| `color.danger.default` | `--color-danger` | color |
| `color.danger.subtle` | `--color-danger-subtle` | color |
| `color.danger.indicator` | `--color-danger-indicator` | color |
| `color.chrome.default` | `--color-chrome` | color: the chrome's ground; left out, `color.surface` |
| `color.chrome.subtle` | `--color-chrome-subtle` | color: hover in the chrome, the search field; left out, `color.surface.subtle` |
| `color.chrome.strong` | `--color-chrome-strong` | color: pressed in the chrome, the avatar's ground; left out, `color.surface.strong` |
| `color.chrome.text` | `--color-chrome-text` | color: text on the chrome; left out, `color.text` |
| `color.chrome.textSecondary` | `--color-chrome-text-secondary` | color: links and icons on the chrome; left out, `color.text.secondary` |
| `color.chrome.textMuted` | `--color-chrome-text-muted` | color: labels and counts on the chrome; left out, `color.text.muted` |
| `color.chrome.border` | `--color-chrome-border` | color: lines in and around the chrome; left out, `border.color` |
| `color.chrome.accent` | `--color-chrome-accent` | color: the current section and link, focus in the chrome; left out, `color.accent` |
| `color.chrome.accentSubtle` | `--color-chrome-accent-subtle` | color: the current section and link's fill; left out, `color.accent.subtle` |
| `border.color.default` | `--border-color` | color: the default hairline |
| `border.color.strong` | `--border-color-strong` | color: checkboxes, hover borders, floating edges |
| `border.radius.small` | `--border-radius-small` | 0–24px: buttons, inputs, chips |
| `border.radius.medium` | `--border-radius-medium` | 0–24px: menus, small cards |
| `border.radius.large` | `--border-radius-large` | 0–24px: panels, the table's shell |
| `boxShadow.color` | `--box-shadow-color` | color: shadows, at their strongest |
| `boxShadow.style` | `--box-shadow-style` | `soft` or `hard`: see below |
| `backgroundImage` | `--background-image` | `none` or gradients: the ground's texture, see below |
| `backgroundSize` | `--background-size` | `auto`, or one or two sizes in pixels or a percent (`"20px 20px"`) |
| `typography.fontFamily.ui` | `--font-family-ui` | font families: everything but titles |
| `typography.fontFamily.display` | `--font-family-display` | font families: page and panel headings |
| `typography.fontFamily.title` | `--font-family-title` | font families: entry titles |
| `typography.fontSize.base` | `--font-size-base` | 12–16px: body and control text |
| `typography.fontSize.small` | `--font-size-small` | 11–14px: secondary text |
| `typography.fontSize.xsmall` | `--font-size-xsmall` | 10–13px: micro-labels, badges |
| `typography.fontSize.xxsmall` | `--font-size-xxsmall` | 9–12px: the section rail's labels |
| `typography.fontSize.pageTitle` | `--font-size-page-title` | 20–40px |
| `typography.fontSize.panelTitle` | `--font-size-panel-title` | 13–20px |
| `typography.fontSize.entryTitle` | `--font-size-entry-title` | 12–18px: entry titles in lists |
| `typography.fontSize.editor` | `--font-size-editor` | 13–18px: the editor's text |
| `typography.fontSize.editorTitle` | `--font-size-editor-title` | 26–44px: the editor's title |
| `typography.fontSize.stat` | `--font-size-stat` | 24–40px: figures on stat tiles |
| `typography.fontWeight.display` | `--font-weight-display` | 400–900: page and panel headings, the editor's title, stat figures |
| `typography.fontWeight.entryTitle` | `--font-weight-entry-title` | 400–700 |
| `typography.letterSpacing.pageTitle` | `--letter-spacing-page-title` | -.05em to .05em |
| `typography.letterSpacing.entryTitle` | `--letter-spacing-entry-title` | -.05em to .05em |
| `padding.row` | `--padding-row` | 10–20px: table cells and list rows, top and bottom |
| `padding.panel` | `--padding-panel` | 12–24px: panel headers, top and bottom |
| `padding.inline` | `--padding-inline` | 16–32px: panels, cells, and rows, left and right |

The **chrome** is the section rail, the section panel beside it, and the
top bar: the frame around the work. Its colors are the page's until a
theme sets them, so it can draw a dark frame around a light page, or a
frame in its own colors, without changing anything else. Leave them out
and the chrome follows the theme's surface, text, border, and accent.
The chrome usually needs an accent of its own, since one accent rarely
reads well on both a light page and a dark frame. Menus opened from the
chrome keep the page's colors.

`backgroundImage` lays a texture over `color.background`, under the
work: a dot grid, a hatch, a grain. It's `none`, or one or more
gradients (`linear-gradient()`, `radial-gradient()`, `conic-gradient()`,
and their `repeating-` forms), written with colors, numbers, and the
gradients' own words (`circle`, `at`, `to`, `center`, and the like).
Nothing else is accepted, so a texture can't load a file. Give each
scheme its colors with `light-dark()` inside the gradient, and repeat a
pattern with `backgroundSize`:

```json
{
	"backgroundImage": "radial-gradient(circle, light-dark(rgb(20 24 29 / 16%), rgb(255 255 255 / 7%)) 1px, transparent 1.4px)",
	"backgroundSize": "20px 20px"
}
```

Keep a texture faint: it sits behind everything on the page.

`boxShadow.style` picks how things stand off the page, everywhere in
the admin at once:

- **`soft`** keeps resting surfaces flat, with soft, blurred shadows
  under menus, popovers, toasts, and modals.
- **`hard`** gives everything that has a shadow a solid offset in
  `boxShadow.color`, with no blur: panels, cards, and buttons at rest
  and menus by 4px, modals and drawers by 8px. Use an opaque color for
  it, such as `"light-dark(#000, #f2ee3c)"`.

The admin's spacing scale, layout widths, control heights, code font, and
the shape of each style's shadows aren't tokens a theme can set.

## Checking a theme

```sh
bin/blush admin-theme:check
bin/blush admin-theme:check acme/harbor
```

This reads every admin theme (or one) and reports anything wrong with
its manifest or `tokens.json`, [requirements](extending.md#requirements)
that aren't met, and colors that are hard to read: text on the
background and surfaces, accent text on the accent, each status
color on its own soft fill, and the
chrome's text and accent on the chrome, in both color schemes. Low contrast is a
warning, not an error, so a theme with it still works.

## Autocomplete in your editor

Point `$schema` at the schemas in `resources/schemas/` of the framework
(as in the examples above), and editors that read JSON Schema will
suggest every token, its kind of value, and its range as you type.
