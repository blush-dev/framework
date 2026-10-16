# Icon packs

An icon pack is a set of SVG icons in a namespace of its own, with no
code. Its icons are used in content with the [icon block](../concepts/blocks/README.md)
(`:icon[GitHub]{name=brands/github}`), in templates with
`$template->block('icon', name: 'brands/github')`, and from the admin's
icon inserter.

| Page | What it covers |
|---|---|
| [Creating an icon pack](creating-an-icon-pack.md) | `icons.json`, the icons' names, labels, and Composer packs |
| [Turning icon packs on and off](turning-on-and-off.md) | Which packs' icons show, and restyling one icon from a theme |

Plugins and themes can bring icons too; see [Icons](../features/icons.md).
