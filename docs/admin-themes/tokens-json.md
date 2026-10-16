# `tokens.json`

`tokens.json` sets the admin's design tokens, grouped the way CSS
names things. Here is a theme that changes the accent, the corners, and
the UI font:

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/admin-tokens.v1.schema.json",
	"schemaVersion": 1,
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

## Set every token

Set every token, fonts included, even where you want Neutral's value.
Anything a theme leaves out stays as Neutral has it, and Neutral's values
can change in a later version, so a theme that leaves tokens out changes
with them. Neutral's own
[`tokens.json`](../../resources/admin-themes/neutral/tokens.json) sets every
token but the font families and the [chrome's colors](every-token.md#the-chrome), so it's a
good place to start from. For the
platform's own UI font, list its stack:

```json
"fontFamily": {
	"ui": ["-apple-system", "BlinkMacSystemFont", "Segoe UI Variable Text", "Segoe UI", "system-ui", "Roboto", "Noto Sans", "Ubuntu", "Cantarell", "Helvetica Neue", "Arial", "sans-serif"]
}
```

## How it's checked

Blush reads `tokens.json`, checks every value, and writes the theme's
stylesheet itself. A theme never ships CSS. That's what keeps a theme
from breaking the admin: it can only set the [tokens it lists](every-token.md), to values of
the right kind.

## Colors

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

## Fonts

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

Every token a theme can set, with its range, is on [Every token](every-token.md).
