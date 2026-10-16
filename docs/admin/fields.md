# Fields

With `site.settings`, **Structure → Fields** is the site's
[field library](../concepts/custom-fields.md): every field the site
makes, one at a time, kept in `user/data/fields`.

## The library

Each content type
chooses which of them it carries, on its own screen (see
[Content types](content-types.md#fields)); a field never chooses its types.
Fields Blush and plugins register aren't in the library and aren't
listed. Each row says the field's label and key, how it's entered, and
the types that have it (**Left Off** when a type already declares its
key, which its screen explains); its menu opens it or deletes it.

## A new field

**New Field** opens a screen of its own, which asks how the field is
entered first (choose a control from its sections, or search them),
then for the field itself, as a field's screen has it below. Its key is
made from the label until you type one; it's the file's name, and fixed
once the field is made (`new` is the admin's own). **Create** writes
`user/data/fields/{key}.json` and opens it. **New Field** on a type's
screen opens the same screen, and **Create** goes back to the type with
the field added to it, to save with the type's other changes.

## A field's screen

Its facts sit under the title: its key, its control, and the types that
have it, each a link to the type's screen. Below them is the field:

- Its label, its key (fixed), and its control (a saved field changes
  only to a control that stores the same value, such as Select Dropdown
  and Radio Buttons), a default, a placeholder for a box, help, and
  whether it's required to publish. A choice lists its options, each a
  label and a key (the key is what entries keep, so a label can be
  reworded); a number has its lowest, highest, and step, and whole
  numbers only; a date whether it has a time; a List what each item is
  called, the fewest and most items, and the fields that sum an item
  up. **Advanced** lists older keys the field still reads.
- An **Object**, or a List of Objects, holds its fields in a box, as
  rows that move, drag, and open, with **Add Field** at the box's foot
  (a field added there is selected, not opened). A field without a key
  yet says **No Key** on its row. Fields nest one level here: an
  Object's fields can't be Objects (a file written by hand may nest
  deeper). Removing a field entries have values for asks first; their
  files keep the values. Changing the key of a field inside it that
  entries have values for asks, and keeps reading them from the old key
  unless you say not to.
- An Object keeps its fields' values together under its key (an `seo`
  Object's `title` is `seo.title` in an entry's file).

The same field has the same key and settings on every type that has
it; a type changes only its label, help, and whether it's required.

## Saving and deleting

Changes are saved together with **Update** (it says **Saved** when
there's nothing to save). Saving moves the values of an Object's fields
put somewhere new in every entry of the types that have it, each change
listed in [Tools → Activity](activity.md) with Undo; an entry that already has a value
at the new place keeps it, and the save says so. The menu beside it has
**Revert changes** and **Delete field**, which removes its file and
takes it off every type that has it; entries keep their values, shown
as other front matter. A field can't use a key a type that has it
already declares: that's refused with the reason, and the file is left
as it was.
