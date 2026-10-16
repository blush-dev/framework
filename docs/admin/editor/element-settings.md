# Element settings

The element tab in the editor's settings is named for what the cursor
is in (**Callout**, **Heading 2**, **List**), and edits it.

## Which element

The element tab follows the cursor and shows whatever it's most
precisely in: inside a callout, that's the paragraph (the callout is
above it in the breadcrumb); on the callout's own first or last line,
it's the callout. On a blank line, it's the element above, but not one
inside a block that has already closed. A block, a list, a list
item, and a definition list list what's directly inside them under
**Content**; choose one to select it.

## Blocks

A block's options are a form. A block with
[variants](../../features/blocks/README.md) lists them first, under **Variant**,
with what each one looks like; **Default** writes nothing. Changing one
rewrites just that option in the Markdown: the rest of what you wrote
stays as it is. Setting an option back to its default removes it, so the
default applies. A block that takes a line of text has it here as
**Text**. **Classes** and **ID** set its `.class` and `#id`; other
attributes that aren't options of the block are listed but edited in
the text.

**Remove Block** takes the block out: a container with
everything inside it, a line block with its line, and an inline
block leaving its text in the sentence. Undo in the text puts it
back.

## Images

An image is plain Markdown, `![A lake](/media/lake.jpg "The lake at dawn"){.bleed-wide}`,
and its panel edits each part of it:

- **Variant**: the looks your theme offers images, such as **Float
  Left** (see [Image variants](../../themes/image-variants.md)). Each is a
  class on the image, so choosing one swaps that class and leaves the
  others, its bleed included. How wide it is is **Bleed**, in the
  header.
- **Image**: the picture itself, with its address and size under it.
  Point at it (or tab to it) for **Replace**, which opens the media
  picker, and **Remove**. A file that isn't there says so. A replacement
  brings the library's alt text and caption, if the image had none.
- **Decorative**, for an image that adds nothing a reader needs (an
  ornament, a divider). An image without alt text is decorative, so it's
  on when the brackets are empty; turn it off to write **Alt text**,
  which describes the picture for anyone who can't see it. Turning it on
  clears the alt text. If the library has alt text for the file, a
  decorative image offers **Use the library's**.
- **Caption**, the quoted part the site shows under it (left empty,
  nothing is written).

  Alt text and caption are this image's, in this entry: the library's
  don't change.
- **Classes** and **ID**.

## Markdown elements

Every Markdown element takes classes and an id too, so a heading's,
paragraph's, list's, list item's, quote's, code block's, table's,
divider's, definition list's, term's, or definition's panel has
**Classes** and **ID**, which write `{.class #id}` where the site reads
it: at the end of a heading, paragraph, list item, term, or definition,
and on a line of its own above a list, definition list, quote, code
block, table, or divider (emptying both fields takes the line away
again). Some have
more: a heading's **Level** (1 to 6), a code block's **Language**, a
list's **List Type** (Bulleted, Numbered, or Task, which rewrites its
markers and leaves the lists inside it alone), and whether a list item
is a task, and done.
