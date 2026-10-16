# Image variants

An image in Markdown takes classes, `![A lake](/media/lake.jpg){.inline-left}`,
and the site puts them on the image's figure. List the ones your
stylesheet styles under `variants.image` in `theme.json`, and the
editor offers them as the image's **Variant**. Widths aren't variants:
they're [bleed](bleed.md).

```json
{
	"variants": {
		"image": ["inline-left", "polaroid"]
	}
}
```

## Labels

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

`theme:check` notes an image variant without a label.

## The default theme's

The default theme offers `inline-left` (Float Left) and `inline-right`
(Float Right), and styles them. They're only offered while it's the active theme, not
when it's a parent, since another theme's stylesheet may not style them;
list the ones yours does.
