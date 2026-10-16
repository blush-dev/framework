# Blocks and icons

The blocks and icons the editor's inserters offer. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `GET icons` | The icons the active theme can show: `{"icons": [{"name", "label", "keywords", "category", "source", "svg"}]}`; a built-in icon has its `category` (such as `arrows` or `media`) and a `null` `source`, and the rest have a `null` `category` and a `source` like a block's |
| `GET blocks` | The blocks the editor's inserter offers: `{"blocks": [{"name", "label", "description", "content", "kind", "category", "source", "props"}]}` (see below) |

## Listing blocks

`GET blocks` lists the blocks the editor's inserter offers, the
registered blocks with a class. Each has:

| Key | What it is |
|---|---|
| `name` | Its full name, as written in Markdown: `core/callout`, `acme/tabs` |
| `label`, `description` | From the translation catalogs, or a label made from the name |
| `content` | What it wraps: `none`, `text` (its `[label]`), or `blocks` |
| `kind` | How it's written: `container` (`:::`), `leaf` (`::`), or `inline` (`:`) |
| `category` | A built-in block's group (`text`, `media`, `layout`, `navigation`, `data`), else `null` |
| `source` | Where the rest come from: `{"kind": "theme", "site", "icon-pack", or "plugin", "label"}`, else `null` |
| `props` | Its props as schema fields, each with its `label` and, for a choice, `choices` labels by value |
| `variants` | Its variants under the active theme, not including Default: `{"name", "label", "description", "source"}`, where `source` is `null` when the block's own namespace declared it, else like the block's |

Beside `blocks`, `image` has the `variants` the active theme offers
Markdown images (its `theme.json` `variants.image`): each a class, with
its `name`, `label`, `description`, and `source` (`null`).
