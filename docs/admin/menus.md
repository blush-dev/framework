# Menus

With `menus.edit` (administrators and editors), **Structure → Menus** lists
the site's [menus](../concepts/menus.md) and the active theme's
locations for them.

## The list

Each menu shows its label (with its name under it),
how many items it has at every level, and the locations showing it. A
row's menu has **Edit**, **Duplicate**, and **Delete**. Deleting asks
first, says which locations go back to the theme's default, and offers
**Undo**. An entry that shows the menu with `::menu{name=…}` shows
nothing there once it's gone.

## Locations

Under the list, **Locations** are the active theme's places for a menu,
each with a menu to choose. A change is saved at once, with **Undo**.
Until a location is given a menu, it shows the theme's default items,
if the theme has them, or nothing. Each location says its key, how deep
it shows a menu, and how many default items it ships.

## New menus

**New Menu** asks for a label and a name (made from the label until you
type one) and opens the new menu at once. **Duplicate** does the same
with a copy of a menu's items, in no location. Either is only a draft
in your browser tab until you press **Publish**; leaving it asks first,
then discards it.

## A menu's screen

Under the menu's label are its name, how many items it has, and the
locations showing it. **Settings** opens its label (what screen readers
call the navigation), its name, the block to copy into an entry,
and the theme's locations, each saying what it will show once the menu
is saved. Renaming is allowed: its locations follow the new name, but an
entry that shows it by its old name doesn't. Everything, the items and
the settings, is saved by one button: **Publish** for a new menu,
**Update** once something's changed, and **Saved** when nothing has.

### Items

**Items** is the menu as a tree. Each row shows what kind of link it is,
its label (or the title of what it links to), and a pill when its link
isn't on the site as it is: **Draft**, **Scheduled**, or **Not on the
Site** (in the trash, hidden, or gone). Such an item is left out of the
menu on the site until it's live. **Empty** marks an item with neither a
label nor a link, which has to get one before the menu can be saved.

A row's buttons move it up, down, out beside its parent, or in under
the item above it, and open its fields (**Edit**) or its menu: **Add
item above**, **Add item below**, **Duplicate**, and **Remove** (with
**Undo**). Rows can be dragged too: onto the top or bottom of a row to
go before or after it, or onto its middle to go in under it. **Add
Item** adds one after the selected row; **Expand All** and **Collapse
All** open and close the items with sub-items.

With a row selected, ↑ and ↓ move between rows, → and ← open and close
an item (or go to its first sub-item or its parent), Home and End go to
the first and last rows, F2 opens its fields, and ⌥ (Alt) with an arrow
moves it.

### An item's fields

An item's fields open under its row:

- **Label**: the text shown. Leave it empty to use the title of what it
  links to; an item without a link needs one.
- **Link**: one box. Type part of a page's name to pick an entry, a
  term, a type's listing, or a named route (the best matches first, so
  a topic called Writing comes before posts that start with it; the list
  says when there are more than it shows), or type a path (`/about`) or
  a whole address (`https://…`). A link to something on the site keeps
  its id, so it follows the entry when its address changes. Clear it
  with × for plain text, often a heading over the items under it.
- **Description**, **Badge**, and **Icon** (**Insert an Icon** picks
  one from the site's icons; press its tile to change it, or **Reset**
  to remove it), for themes that show them.
- **Class**, added to the item in the theme's markup, and **Link
  Relationship**, the link's `rel` (such as `me`).

Options the screen doesn't show (an `image`, a route's `params`, a
theme's own fields) are kept as they are in the file. A menu whose file
can't be read as a menu (an item that isn't an object, or one with two
links) is shown with what's wrong and can't be saved until it's fixed in
the file.
