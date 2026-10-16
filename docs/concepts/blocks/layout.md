# Layout blocks

Four built-in [blocks](README.md) arrange what's inside them. Their
layout works in every theme; themes style them further through their
`block-group`, `block-grid`, `block-row`, and `block-stack` classes.

| Block | Example                           | Props                                                                                                                                                          |
|-----------|-----------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `group`   | `:::group{.highlight}` … `:::`    | Use it to put a class or id on several blocks.                                                                                                                 |
| `grid`    | `:::grid{columns=3}` … `:::`      | `columns`: the most columns, 1 to 12 (default 2). `min`: the narrowest a column gets before the grid drops a column (default `12rem`; `0` never drops). `gap`. |
| `row`     | `:::row{justify=between}` … `:::` | `justify`: `start` (default), `center`, `end`, or `between`. `align`: `center` (default), `start`, `end`, `stretch`, or `baseline`. `wrap=false`. `gap`.       |
| `stack`   | `:::stack{gap=2rem}` … `:::`      | `align`: `stretch` (default), `start`, `center`, or `end`. `gap`. Blocks one above another, with the gap in place of their margins.                            |

## Landmarks

All four also take `tag`, the element they render as: `div` (the
default), `section`, or `aside`. A section or aside is a landmark, so
give it a label to name it for screen readers:

```markdown
:::grid[Related reading]{tag=aside columns=3}
…
:::
```

## Cells and nesting

Each block inside a grid is a cell, and each block inside a row or a
stack is an item. To put several blocks in one cell, wrap them in a
`group`. Blocks nest with `:::` throughout: each `:::` line closes
the most recent block that's still open.

```markdown
:::grid{columns=2}
:::group
### Fast
No database, so pages are quick.
:::

:::group
### Simple
Every page is a file you can edit.
:::
:::
```

## Sizes

`min` and `gap` take a CSS length, such as `12rem`, `240px`, or `30%`.
Without `gap`, the theme's `--layout-gap` custom property is used, or a
default.
