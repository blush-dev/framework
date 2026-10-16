# Bleed

The admin's editor can widen any element at the top of an entry (an
image, a paragraph, a block) past the text column: **Wide**, into
the margin, or **Full**, edge to edge. Each is a class on the element,
`bleed-wide` and `bleed-full` unless your theme names its own; the
column's own width writes no class at all. Style both:

```css
.bleed-wide { /* wider than the text, centered on it */ }
.bleed-full { /* as wide as the page */ }
```

## Your own class names

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
