# Making blocks

A block is something content says beyond plain Markdown, such as
`::acme/pricing`. Blocks come from Blush and plugins, never from the
site itself or a theme, so they work with every theme and content that
uses them never breaks when you switch. For using blocks in your
writing, and the built-in ones, see [Blocks](../../concepts/blocks/README.md).

## Blocks from a plugin

A plugin's blocks use its namespace: `acme/hello`, with the namespace
`hello`, registers `hello/tabs`, not `tabs`. Their text (labels,
descriptions) goes in the plugin's `lang/en.json`, under `blocks.tabs`,
with `@@locale` and `@@domain` (`"acme/hello"`) at the top (see
[Labels and translations](labels-and-translations.md)). Text written in
the plugin's code (`t()` in its templates and classes) is the plugin's,
looked up in its catalogs and then Blush's, never the theme's (see
[Translations](../translations.md)). A plugin's block draws itself with
its `render()`, usually a template file in the plugin returned by
`$this->view(__DIR__ . '/../views/tabs.php')` (see
[How a block draws itself](rendering.md)); a theme (or a child theme)
restyles it with `views/blocks/hello-tabs.php`. Turning the plugin off
turns its blocks in your content into plain text.

To change how a theme draws a block, make a
[child theme](../../themes/child-themes.md).

## Pages

| Page | What it covers |
|---|---|
| [Using blocks in templates](using-in-templates.md) | Drawing a block from a template, and blocks a site may not have |
| [Making a block](making-a-block.md) | A block's class: props, content, kind, and what it holds |
| [How a block draws itself](rendering.md) | `render()`, its template, `$block`, and BEM classes |
| [Registering a block](registering.md) | Registering, replacing a built-in, and where blocks live |
| [Variants](variants.md) | Named styles of a block, from the block, a theme, or a plugin |
| [Labels and translations](labels-and-translations.md) | A block's translatable text |

For pieces of a theme's own design, such as a card or a post header,
use a [component](../components/README.md) instead.
