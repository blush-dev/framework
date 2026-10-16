# The editor

The editor is one column of text: the title (press Enter to start a new
paragraph at the top of the body), then the body in Markdown. It opens
with the section panel and the settings closed.

| Page | What it covers |
|---|---|
| [Writing](writing.md) | Lists, quotes, and tables as you type, formatting keys, and pasting |
| [The Document tab](document-tab.md) | The entry's settings: publishing, fields, and the rows at its end |
| [Choosing relations](relation-pickers.md) | Pickers for terms, authors, and other entries |
| [Changing the slug](slug.md) | Renaming an entry |
| [Changing the language](language.md) | Moving an entry to another language |
| [Editing an index page](index-pages.md) | The page that introduces a type's archive |
| [Inserting blocks](inserting-blocks.md) | Callouts, galleries, buttons, and the rest |
| [Inserting media and icons](inserting-media.md) | The media picker, uploading, and icons |
| [The outline and the breadcrumb](outline.md) | Finding your way around an entry |
| [Element settings](element-settings.md) | A block's options, and images and Markdown elements |
| [When a save doesn't go through](saving.md) | Unsaved changes, conflicts, and refused saves |

## How the text reads

The body stays Markdown, set in Fira Code (the admin's monospace font
throughout), but the words read first: every mark (`*`, `**`, `#`, `>`,
a link's brackets, an attribute block's braces) is dimmed, and what it
wraps keeps full strength. *Emphasis* is slanted, **strong text** is
bold, headings are bold (lighter from `###` down), quotes are muted, and
struck text is dimmed. A link's text is colored and its address dimmed;
an image's text is muted. Inline code sits on a gray background, and a
fenced code block, fences and all, is one gray box, with its language
named. A task's `[x]` is green.
A table's pipes and its `| --- |` row are dimmed, but for the row's
alignment colons; its header row is bold. In a definition list, a term
is bold and a definition's `:` is dimmed. Footnotes are
colored. Attribute blocks, such as `{.bleed-wide}` after an image or a
heading, are a gray chip with their class and id names in full
strength. A block's name is colored, and a block or image the
settings are showing is boxed (a container on its first and last lines).
A container's closing line is followed by the full name of the block
it closes, such as `core/callout`, faintly; it's only shown, never
part of the text. With the cursor on a container's first or last
line, the colons on both lines are colored, so you can find its other
end. Pressing Enter at the end of a container's first line, when
nothing closes it yet, adds its closing `:::` too, with the cursor on
the empty line between.
Only valid Markdown lights up, so something that stays plain won't be
read the way you meant.

## The editor's address

Each entry's editor has its own address, from its content type and
[id](../../concepts/ids-and-links.md), such as
`/admin/content/post/0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74`. It stays
the same when you rename or move the entry, so a bookmark keeps
working.

## The header

There's no back button: the type's name in the top bar (**Posts** in
*Content / Posts / Editing*) goes back to its list. The header's left
side is what you do to the text, in three groups:

- **What goes in the entry**, always there: **+** for a block
  or a Markdown element (heading, quote, list, definitions, code block,
  table, divider), which goes in with its placeholder selected so you
  can type over it, and the picture for media.
- While the cursor is in the text, **▴▾** move the element it's in (the
  one the footer names last) up or down past the one beside it, inside
  whatever holds it: a list item within its list, a paragraph within its
  callout, a callout among the entry's other elements (⌥↑ and ⌥↓ do
  too). An arrow is off when there's nothing to swap with. A numbered
  list keeps counting from where it started, and the blank lines
  between elements stay where they are.
- **What goes in a sentence**, shown only where Markdown's marks mean
  something (not in a code block, on a block's `:::` line, a
  divider, or a table's `| --- |` row): **Bold** and **Italic**, lit
  when the text at the cursor is bold or italic; the **link** form
  (**Text** and **Address**, filled in from the selection, the word at
  the cursor, or the link the cursor is in, with **Remove** for a link
  that's there); the **A** menu of other things that go in a sentence:
  **Strikethrough**, **Highlight**, and **Inline code** (each lit when
  it's on at the cursor), **Mention** (a search for a published profile
  by name, which writes `@slug` where the cursor is; shown when
  [mentions](../settings/writing.md) are on), then any block that goes inside a sentence;
  and the shapes for an icon. Typing `@` in the text does the same as you
  type: the published profiles whose names or slugs match are listed
  under the cursor; ↑ and ↓ move, Enter or Tab writes `@slug`, and Esc
  leaves what you typed. Mentions are colored like links. Inside a block that holds only some
  things, such as a gallery's images, they go, the blocks panel
  offers only what it holds, and the media picker shows only images.
  The site leaves out anything else typed inside it, so the Outline
  marks it, its settings say so, and the block's own settings list
  the lines that won't show. A block written another way than its
  own (see [Using blocks in content](../../concepts/blocks/README.md)),
  such as `::button[…]` for `:button[…]`, is marked the same way, and
  its settings say how it's written.
- **Bleed**, for an element at the top of the entry: how far it reaches
  past the text column. **Base** is the column's width and writes
  nothing; **Wide** and **Full** write the classes your theme names (see
  [Bleed](../../themes/bleed.md)). The button shows the width in force, and is
  colored while the element is widened.

One of these is open at a time: opening a menu, the link form, or a
picker closes the blocks panel and anything else open. Its right
side has the settings button, **Save Draft** (or **Update**) beside
the main button while the main button changes the entry's status, the
main button, and a **⋮** menu in two parts: **View** (the settings
panel, the **Outline**, **Focus mode**, and **Preview**, or **View**
once it's live; a preview shows the entry as last saved) and **Entry**
(**Unpublish** once it's scheduled or live, **Copy link** once it's
live, **Duplicate**, and **Move to trash**), with their shortcuts.

## Publishing buttons

The buttons follow the type's workflow: the main button is the first
step it lists from where the entry is, among those your account may
take. The **Status** row in the settings offers the rest. With the
standard workflow:

| The entry is... | You can |
|---|---|
| A draft | **Save Draft**, or **Publish** (or **Schedule**, when the date is in the future) |
| Scheduled | **Update**, **Publish** once its date is past, or **Unpublish** |
| Published | **Update** (or **Schedule**, with a future date), or **Unpublish** |

A workflow with a review step works the same way: a writer's main
button on a draft might be **Submit for Review**, and an editor's on an
entry in review **Publish**, with **Send Back** under Status. Without a
step you may take, the main button saves (**Save Draft** or
**Update**).

The buttons show whether your changes are saved: **Update** and **Save
draft** stay off until you change something, since there's nothing to
save yet; the one you pressed reads **Saving…** while it saves; and
once it's done, it reads **Saved** until you change something again. The
entry's status is in the settings, under its publishing.

In a type with [expiry](../../concepts/status-and-visibility.md), an **Expires** row
under the date sets when the entry comes off the site; its calendar's
**Clear** removes it. An expired entry's main button is **Update**:
moving the date later, or clearing it, brings it back.

If you can't publish, you can save drafts but not publish them.

Fields the content type marks as required must be filled in to publish,
schedule, or update a live entry. Anything missing is named at the top
and marked under the field; a draft saves without them.

## The settings

The settings (⌘/ or Ctrl+/) open beside the text and push it aside, on
the element the cursor is in when it's in one, else on the entry's tab;
close them with their **×** or Escape. Your browser remembers whether
you left them open, so the next entry you edit opens the same way (on a
small screen, where they'd cover the text, they always start closed).
They have two tabs:

- **The entry's**, named for its type (**Post**, **Page**): see
  [The Document tab](document-tab.md).
- **The element's**, named for what the cursor is in (**Callout**,
  **Heading 2**, **List**), with its settings (see
  [Element settings](element-settings.md)).

## The footer

The body is an ordinary text field, so undo, spelling, and your
browser's shortcuts work as usual. The footer shows where the cursor is
on the left (see [the breadcrumb](outline.md)), and the words and
reading time on the right.
When the cursor leaves the text (you click the title, the margin, or
the entry's tab), the footer shows only the entry, the element's tab
says nothing's selected, and the sentence tools and bleed go; using the
header, the footer, the settings, or a picker doesn't count as leaving.

## Focus mode

**Focus mode** (⌘⇧F or Ctrl+Shift+F, or the **⋮** menu) hides
everything but the text and the editor's own header. Press Escape to
leave it.

## Saving

Ctrl+S (⌘S on a Mac) saves without changing the status. Saving changes
only what you changed: every other line of the file stays exactly as it
was. Going to another admin screen with unsaved changes asks first, and
so do the reload shortcuts (⌘R, Ctrl+R, F5); either way your changes
are kept (see [When a save doesn't go through](saving.md)). Closing the
tab or leaving the admin doesn't ask.

**Move to trash** takes the entry off your site and puts it in the
**Trash** tab of its list (see [Trash](../trash.md)).
