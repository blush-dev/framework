# Registering a block

Every block is a registered class; a name no one registered is
plain text in your content, and a template on its own isn't a
block. Its props, content, kind, and variants come from its
constructor, `CONTENT`, `KIND`, and `VARIANTS`, so registering takes
only its name and class, in the plugin's provider's `boot()` (see
[Making a block](making-a-block.md#registering-it)):

```php
$this->container->get(BlockRegistry::class)->register('acme/recent-posts', RecentPosts::class);
```

## Replacing a built-in block

A provider can also replace a built-in block with its own class:
`register('callout', MyCallout::class)`. The `core` namespace is only for
the built-in ones, and every other name needs its namespace. A name in a
theme's namespace is refused: themes can't register blocks.

## Where blocks live

| Where                                     | What it does                                                                    |
|-------------------------------------------|---------------------------------------------------------------------------------|
| Blush                                     | The built-in blocks, each drawing itself                                    |
| A plugin (registered by its provider)     | Adds its blocks while it's on. See [Plugins](../../plugins/README.md). |
| A theme's `views/blocks/`             | Draws blocks its own way, while it or a child of it is active               |

The active theme's templates come first, then its parents', and last
the block's own `render()`. `bin/blush theme:why blocks/callout` shows which file is
used and what it overrides; `block:list` says `(its own)` for a
block that no file overrides. To draw a block your way, make a
[child theme](../../themes/child-themes.md) of the active one, with
its own template.
