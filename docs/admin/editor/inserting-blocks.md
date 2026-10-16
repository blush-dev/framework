# Inserting blocks

[Blocks](../../concepts/blocks/README.md) are callouts,
galleries, buttons, and the rest, written as `:::callout` … `:::` and
the like. The editor's blocks panel puts them in for you.

## The blocks panel

**+** opens the blocks beside the text, on the left, grouped
by category: search by name or what it does, or scroll, then choose one
(or use the arrow keys and Enter). The strip at the bottom describes the
highlighted one. The panel stays open, so you can add several; close it
with its **×** or Escape.

The **A** menu lists the inline blocks, the ones that go inside a
sentence (a keyboard key, an abbreviation), each with what it does.

Typing `/` at the start of an empty line opens the same panel: keep
typing to narrow it (`/call` for a callout), then press Enter or Tab.
Press Escape to keep the `/` as text.

## What goes in

The block is written into the Markdown by its full name, such as
`:::core/callout` … `:::`, with the cursor where your text goes. An
inline block (a keyboard key, an abbreviation, an icon) goes at the
cursor, inside the sentence; the rest go on lines of their own. Select
some text first to make it the block's text. Options the block
needs are written empty for you to fill in, such as
`::core/video{src=""}`. Undo takes an insertion back.

## Which blocks are listed

The list has every registered block with a class: the built-in
ones and your plugins' (grouped by where they come from).
Themes don't add blocks; they style them. See
[Making a block](../../features/blocks/README.md) to add yours.

A block's options are set in its [element settings](element-settings.md).
