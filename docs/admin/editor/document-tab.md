# The Document tab

The entry's tab in the editor's settings, named for its type (**Post**,
**Page**), holds everything about the entry that isn't its text.

## Publishing and the entry's settings

First comes **Publish**: its featured image (choose,
replace, or remove it), its summary as written, with **Edit summary**
(or **Add a summary…**) under it to write it in a small form beside
it, then its **Status** (choose it to see what each status does,
and to change it), its **Visibility**, in a type that has it
(**Public**, **Unlisted**: it has an address but isn't in lists,
feeds, or the sitemap, or **Hidden**: no address; see
[Who sees an entry](../../concepts/status-and-visibility.md)), its **Date** (choose it for a calendar and
the time, on a 12-hour clock), its **Slug** (see [Changing the slug](slug.md)), its
**Subtitle**, its **Language** (on a site with
[translations](../../concepts/translations.md); see [Changing the language](language.md)), and a new page's
**Parent** (see [New entries and parents](../new-entries.md)), or the
parent of an entry in a nesting collection (such
as a category), and its **Template**: **Default**, where the theme
picks one by the entry's type, or one of the templates the active
theme [declares](../../themes/template-hierarchy.md) for the type,
each with what it's for. The row shows only when the theme declares
one for the type, and never on an index page, an archive's page, or
an error page. A template the theme doesn't offer (or a list of them)
shows as **Not in this theme** and stays until you choose another. A
line under them says what the date means and when the file was last
edited.

Then the **Authors**, each type of terms it's filed under (see
[Choosing relations](relation-pickers.md)), and the
content type's other fields (a few kinds show their value read-only);
each [section](../../concepts/custom-fields.md) the type arranges its
fields in, under its heading, in the type's order (see
[Fields on the Document tab](#fields-on-the-document-tab)); and
what Site Health finds in the file, as last saved (notices only if
you ask). Every save, and every bulk change, sets `updated` to when it
was made, so there's no field for it, and `collection` is kept in the file
as written, without a field. Front matter the type doesn't declare
isn't shown, and
saving keeps it as it is. Only the types of terms whose relation files the content
type are offered, plus any the file already uses. At its foot,
**Outline** lists everything in the entry (see
[The outline](outline.md)).

## Fields on the Document tab

A type's fields are groups on the Document tab, each field drawn by its
control: those outside any [section](../../concepts/custom-fields.md)
under the type's name ("Recipe Fields"), then each section as a group of
its own, in the type's order. A section's group is folded until you open
it, and opens by itself when one of its fields has an error or a
warning to show. Its heading counts its fields, with a red count of the
required ones still empty beside it.

A value saved in the file that doesn't fit its field (only possible
when the file was edited by hand) shows a warning under the field,
saying what's wrong. The site leaves such a value out until it's
fixed, and Site Health lists it with the file's other problems. The
warning goes once you change the field.

- A **toggle** is a switch at the end of its label's line.
- **Radio buttons** with up to four short options are one row of
  buttons; choosing the chosen one again clears it, unless it's
  required. Longer sets are a list of radio buttons.
- A **multi-select** shows what's chosen as chips; **Add** lists the
  rest, and a chip's × takes it out.
- An **image** shows the picture itself, with **Replace** and
  **Remove** on it; video, sound, and other files show their path and
  **Choose**. Each opens the media library on its kind.
- An **icon** opens the site's icons, and a **date** without a time
  picks only the day.
- An **Object** is its heading with its fields under it.
- A **List** lists its items, one line each, summed up by
  the fields it names (or its first fields). A list of plain values
  (text, numbers, dates) is a List too, each item one box. Click one to open it and
  fill it in, move it up or down, or remove it; **Add** (with the item's
  name, such as **Add Ingredient**) adds one and opens it.

An Object or List inside another is a row saying what's in it
("2 of 3 fields filled in", "4 ingredients") that opens a level down, in
place of the tab, with the path back at the top; Escape goes back too.
Any depth works this way.

## Rows at the end of the Document tab

The Document tab ends in rows for what you can't change there, always
last, after every field, in this order: **Archive Page** (only on a page
that introduces an archive), **Linked From**, **Outline**, and
**Activity**. On a
short tab they sit at the drawer's bottom edge; on a long one they
follow the last field. Each is its name, with a count on Linked From
and Outline, and opens a level down, in place of the fields. The path
at the top goes back, and so does Escape.

- **Linked From** lists the entries that link to this one, by
  relationship and the type that stores the link, live entries and
  drafts (marked; they link here once published), never the trash.
  Each opens its entry, since that's where the link is changed. A group
  shows 8; up to 50, **Show More** shows the rest in place, and past
  that **View All** opens that type's list filtered to the entries
  linking here. The list says so, with **Show All** to clear it.
- **Archive Page** says which archive the page introduces, which entry
  it's for, its address, how many entries it lists (and how many drafts
  it leaves out), and what the archive shows without the page.
- **Outline** lists every element in the text; see [the
  outline](outline.md).
- **Activity** lists the entry's changes, newest first, with **Undo**;
  see [Activity](../activity.md).
