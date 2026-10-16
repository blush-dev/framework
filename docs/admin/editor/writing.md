# Writing in the editor

The editor's text box knows Markdown: Enter carries list and quote
markers, keys format text, and pasting keeps blocks whole.

## Lists, quotes, tables, and code

In a list, Enter starts the next item with the same marker (the next
number, renumbering the list; an open `[ ]` for a task), and Enter on
an empty item ends the list, leaving a blank line so what you write next
is a paragraph. A quote carries its `>` the same way. In a table, Enter
adds a row with the same columns (and the `| --- |` row a table needs, if
it hasn't one yet); Enter on an empty row ends the table. Typing three
backticks alone on a line writes the rest of the code block (an empty
line and the closing fence), with the cursor left where the language
goes; Enter from there steps into the block.

## Formatting keys

Formatting has the usual keys (Ctrl in place of ⌘ on Windows and
Linux), each turning it on for the selected text, or off when it's
already there (either way it was written: `*` or `_`, `**` or `__`).
With nothing selected, bold and italic act on the word at the cursor:

| Keys | Does |
|---|---|
| ⌘B | **Bold** (`**text**`) |
| ⌘I | *Italic* (`_text_`, or `*text*` inside a word, where `_` isn't italic) |
| ⌘E | Inline code (`` `text` ``) |
| ⌘⇧X | Strikethrough (`~~text~~`) |
| ⌘⇧H | Highlight (`==text==`) |
| ⌘K | The link form, for the selected text, the word at the cursor, or the link the cursor is in. Outside the text, ⌘K opens the command palette as usual |
| ⌘⇧K | Remove the link the cursor is in, leaving its text |
| ⌘⌥1 to ⌘⌥6 | Make the line (or the selected lines) a heading of that level; the same keys again make it a paragraph. In a quote or list item, the heading goes inside it |
| ⌘⌥0 | Make the line a paragraph |
| ⌥↑, ⌥↓ | Move the element the cursor is in up or down among the ones beside it |
| Backspace | Just after a list item's marker, a quote's `>`, or a heading's `#`s: take the marker off, keeping the words (an indented list item comes out a level first) |
| Tab, Shift+Tab | In a list, nest the item under the one above, or bring it back out; what's nested under it moves with it. In a quote, add a `>` level, or take one off (the last one leaves plain paragraphs): the whole quote, or just the selected lines. Over several selected lines of a code block, indent them two spaces, or take up to two off. Elsewhere, Tab leaves the text as usual |

## Typing around syntax

Text can't land inside the syntax around it by accident: a character
typed right after an attribute block at the end of a line goes before
it, at the end of the words; one typed right after a block's
`:::name{…}` or `::name[…]` goes on a new line below; and anything pasted
or inserted into a block's own line goes onto a line after it.
Typing inside the braces or a block's name still edits them.

## Pasting and dropping

Pasting an address over selected text makes it a link. Pasted
blocks are tidied so they can't break the ones around them: one
that was copied without its closing `:::` gets one, a stray `:::` left
over from copying part of a block is dropped, longer fences such as
`::::` become `:::`, and a pasted block goes on lines of its own,
as the inserter puts it.

Dropping or pasting files into the text uploads them to the library and
puts them in together where the cursor is, in the order given, as the
media picker inserts several (see [Inserting media and icons](inserting-media.md));
a file that isn't uploaded is left out; that needs uploading the file's
kind (`media.image.upload` and so on). They're in the command palette too.
