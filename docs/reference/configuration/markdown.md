# Markdown

How Markdown renders: punctuation, heading anchors, figures, raw HTML,
line breaks, links, blocks, and footnotes.

`config/markdown.php` · `Blush\Markdown\MarkdownConfig`

Blush's Markdown is the same on every site: CommonMark, plus autolinks,
`~~struck~~` and `==highlighted==` text, tables, task lists, footnotes,
definition lists, attributes, and [blocks](../../concepts/blocks/README.md) (see
[Markdown](../../concepts/markdown.md)). These options
change how it renders, not what it means. The first five are on the
[Writing settings screen](../../admin/settings/README.md) too.

| Option | Default | What it does |
|---|---|---|
| `mentions` | `true` | `@name` links to the [profile](../../concepts/credits-and-profiles.md) with that slug, once it's published |
| `smartPunctuation` | `true` | Straight quotes become curly ones, `--` and `---` dashes, and `...` an ellipsis |
| `headingAnchors` | `true` | Each heading gets a link to itself, styled by `anchors` |
| `figures` | `true` | Turn a lone image into a `<figure>` |
| `html` | `RawHtml::Allow` | What raw HTML in content does: `Allow` renders it, `Filter` shows script, frames, forms, and styles as text and drops `javascript:` link addresses, and `Escape` shows it all as text |
| `lineBreaks` | `true` | A line break inside a paragraph is kept as `<br>`; off, it's a space |
| `absoluteLinks` | `true` | Turn links starting with `/` into full URLs |
| `blocks` | `true` | Render [blocks](../../concepts/blocks/README.md) in Markdown |
| `anchors` | see below | How heading anchors look: a `Blush\Markdown\HeadingAnchorOptions` |
| `footnotes` | see below | How footnotes look: a `Blush\Markdown\FootnoteOptions` |

## Heading anchors

`HeadingAnchorOptions` takes the anchor's `class` (`heading-anchor`),
the `symbol` it shows (`#`), its `title` (by default "Link to this section" in the
language each entry is written in; `''` for none), a `prefix` for its id (`''`), and whether it goes `before`
the heading's text (`false`, after). A heading with an id of its own
(`## Install {#setup}`) is linked by that id. The anchor is hidden from
screen readers and the tab order, and left out of excerpts. For
example:

```php
<?php

declare(strict_types=1);

use Blush\Markdown\HeadingAnchorOptions;
use Blush\Markdown\MarkdownConfig;
use Blush\Markdown\RawHtml;

return new MarkdownConfig(
	html: RawHtml::Filter,
	anchors: new HeadingAnchorOptions(symbol: '¶', before: true)
);
```

## Footnotes

`FootnoteOptions` takes the classes of the list at the end
(`container`, `footnotes`), of each reference in the text (`reference`,
`footnote-ref`), of each note (`note`, `footnote`), and of each note's
link back (`backReference`, `footnote-backref`), and whether a rule goes
above the list (`rule`, `true`).

## Raw HTML and capabilities

Who may add raw HTML in the admin is a matter of capabilities (see
[Capabilities](../../concepts/accounts/capabilities.md)); `html` applies to every page,
whoever wrote it.
