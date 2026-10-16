# Every token

Every design token an admin theme can set in
[`tokens.json`](tokens-json.md), the CSS custom property it becomes, and
the values it takes.

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

## The chrome

The **chrome** is the section rail, the section panel beside it, and the
top bar: the frame around the work. Its colors are the page's until a
theme sets them, so it can draw a dark frame around a light page, or a
frame in its own colors, without changing anything else. Leave them out
and the chrome follows the theme's surface, text, border, and accent.
The chrome usually needs an accent of its own, since one accent rarely
reads well on both a light page and a dark frame. Menus opened from the
chrome keep the page's colors.

## Textures

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

## Shadows

`boxShadow.style` picks how things stand off the page, everywhere in
the admin at once:

- **`soft`** keeps resting surfaces flat, with soft, blurred shadows
  under menus, popovers, toasts, and modals.
- **`hard`** gives everything that has a shadow a solid offset in
  `boxShadow.color`, with no blur: panels, cards, and buttons at rest
  and menus by 4px, modals and drawers by 8px. Use an opaque color for
  it, such as `"light-dark(#000, #f2ee3c)"`.

## What a theme can't set

The admin's spacing scale, layout widths, control heights, code font, and
the shape of each style's shadows aren't tokens a theme can set.

