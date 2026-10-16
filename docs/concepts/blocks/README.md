# Blocks

A block is something your content says beyond plain Markdown: a
callout, a gallery, a video, a button. You write it as a line of
Markdown, `:::callout[Heads up]{variant=warning}`, and the active theme
decides how it looks.

Blocks are part of your content, and content outlives themes, so a
block comes from Blush or a plugin, never from a theme.
Themes give blocks their look; switching themes changes how a
callout looks, never whether it's there. For the reusable pieces a
theme builds its own templates from, see
[Components](../../features/components/README.md). To make a block of
your own, see [Making blocks](../../features/blocks/README.md).

## Block names

A block's name has two parts, `{namespace}/{name}`, so blocks
from different places never clash:

| Namespace              | Whose blocks  | Example         |
|------------------------|-------------------|-----------------|
| `core`                 | The built-in ones | `core/callout`  |
| The plugin's namespace | A plugin's own    | `tabs/tabs`     |

A [plugin](../../plugins/README.md) declares its namespace in its manifest
(`"namespace": "tabs"`), and no two installed extensions can share one.

**Only the built-in blocks have short names:** `callout` is
`core/callout`. Everything else is always written with its namespace.

## Using blocks in content

Three forms, depending on what the block wraps:

```markdown
:::callout[Heads up]{variant=warning}
Back up your site before updating.
:::

::embed[Our launch]{url="https://youtu.be/…" title="Launch video"}

Read the :acme/badge[new]{tone=tip} release notes.
```

| Form              | Use it for                                                   |
|-------------------|--------------------------------------------------------------|
| `:::name` … `:::` | A block that wraps other content (paragraphs, images, lists) |
| `::name`          | A block on a line of its own, wrapping nothing           |
| `:name[text]`     | A block inside a sentence                                |

Each block has one form, the one it's registered with. Of the
built-in ones, those that wrap content (callout, gallery, playlist,
figure, and the [layouts](layout.md)) are `:::`; audio, video, file, embed, menu,
meter, progress, and the table of contents are `::`; and those that go
in a sentence (abbr, badge, button, cite, dfn, icon, ins, kbd, samp,
small, time, and var) are `:`. Written another way, such as
`:::audio{src=…}` or `::button[…]`, a block shows only its label,
if it has one, and the admin's editor marks it. A `:::` line for one
that isn't a container never opens a block, so a `:::` after it still
closes the block around it.

Each part after the name is optional:

- **`[text]` is the label.** It's plain text, such as a title or caption.
  Every block gets it as the `label` prop, and a `::` or `:`
  block also gets it as its content.
- **`{…}` holds settings**, which blocks call props:
  `key=value`, `key="a value with spaces"`, or `key='…'`. A bare `key` means
  `true`, `.name` adds a CSS class, and `#name` sets the `id`.

A name no one registered, or a short name that isn't built in, shows as
plain text, so a typo never breaks a page. To see every block you
can use, run:

```sh
bin/blush block:list
```

## Variants

A variant is a named style of a block: `variant=warning` on a
callout, `variant=secondary` on a button. The block decides what the
thing is; the variant decides how it looks.

```markdown
:::callout[Heads up]{variant=warning}
Back up your site before updating.
:::
```

Without `variant`, a block is drawn as its Default, and a variant the
block doesn't have (such as one from a theme that isn't active) renders
as Default too; `content:lint` warns about these. The editor in the
admin shows a block's variants in a list at the top of its options,
each with its description. Themes and plugins add variants: see
[Variants](../../features/blocks/variants.md).

## Built-in blocks

These work in every theme, because Blush draws them itself. Themes can
restyle them, and add [variants](../../features/blocks/variants.md) to them.

| Page | Blocks |
|---|---|
| [Callouts, galleries, figures, and embeds](callouts-and-embeds.md) | `callout`, `gallery`, `figure`, `embed` |
| [Layout](layout.md) | `group`, `grid`, `row`, `stack` |
| [Media](media.md) | `audio`, `video`, `file`, `playlist` |
| [Menus](menus.md) | `menu` |
| [Table of contents](table-of-contents.md) | `toc` |
| [Buttons](buttons.md) | `button` |
| [Progress and meters](progress-and-meters.md) | `progress`, `meter` |
| [Icons](icons.md) | `icon` |
| [Inside a sentence](inline.md) | `abbr`, `badge`, `cite`, `dfn`, `ins`, `kbd`, `samp`, `small`, `time`, `var` |

## Good to know

- **Blocks never come from a theme.** They're Blush's or a
  plugin's, so switching themes changes how they look but never
  turns them into plain text. Turning off the plugin that adds one does.
- **They're cached with the page's content.** A block in Markdown is
  drawn once and kept with the rest of the rendered text, until your content
  changes (a publish) or you switch themes. So a slow one, such as a list
  of every post, is still fast. In development nothing is cached.
- **They can't change the page's `<head>`.** A block drawn by a
  template can add a stylesheet or meta tag; one used from Markdown
  can't. Load what it needs from the theme instead.
- **You can turn them off** with `blocks: false` in
  `config/markdown.php` (see [Markdown options](../../reference/configuration/markdown.md)).
  The syntax then shows as plain text.
