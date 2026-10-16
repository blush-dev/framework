# Buttons

`:button` is a link that looks like a button, with an optional icon. It
goes inside a sentence, or on a line of its own (a paragraph of its
own):

```markdown
:button[Get started]{url=/start icon=arrow-right iconPosition=end}

:button[Download the guide]{url=guide.pdf icon=download variant=secondary}

:button[Share this post]{url=/share icon=share-2 iconOnly}
```

| Prop           | What it does                                                                              |
|----------------|-------------------------------------------------------------------------------------------|
| `url`          | Where it goes (required)                                                                  |
| `icon`         | Any [icon](icons.md), shown before the text                                                 |
| `iconPosition` | `start` (the default) or `end`, to show the icon after the text                           |
| `iconOnly`     | Shows only the icon; the label still names the button for screen readers and as a tooltip |

The label is the button's text, and it's required, even for an
icon-only button. Without a [variant](../../features/blocks/variants.md)
it's the main, filled button; `variant=secondary` is an outlined one,
for an action beside the main one. A `url` starting with `/` becomes a
full URL, as Markdown's links do.

## Side by side

To put buttons side by side, write them on one line:

```markdown
:button[Get started]{url=/start} :button[Read the docs]{url=/docs variant=secondary}
```

Or in a sentence: `Read the :button[docs]{url=/docs}.`
