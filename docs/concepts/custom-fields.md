# Custom fields

Custom fields give a type's entries values of their own, such as a
recipe's servings or a movie's runtime. Fields are made once, in the
site's **field library**, and each type chooses which of them it carries.

A field's key, type, and settings are the same on every type that has
it. In the admin, the library is **Structure → Fields**, and a type's
fields are on its own screen (see [the admin](../admin/README.md)). On
files, each field is `user/data/fields/{key}.json`, named for its key,
and a type lists it in its `fields`.

`user/data/fields/servings.json`:

```json
{ "type": "number", "integer": true, "label": "Servings" }
```

`user/data/types/recipe.json`:

```json
{ "fields": ["servings", "difficulty", "ingredients"] }
```

Blush checks the values when it indexes your content and in
`bin/blush content:lint`.

Plugins can register fields of their own, and field types (see
[Fields](../features/fields.md) and
[Field types](../features/field-types.md)).

## Field types

| Type        | Holds                  | Extra options                                                                                                       |
|-------------|------------------------|---------------------------------------------------------------------------------------------------------------------|
| `text`      | A line of text         | `format`: `url` (an `http` or `https` address, or a path on the site such as `/about`), `email`, or `icon` (a name with no spaces) |
| `markdown`  | Formatted text         |                                                                                                                     |
| `date`      | A date and time        | `time`: `false` keeps only the day                                                                                  |
| `bool`      | `true` or `false`      |                                                                                                                     |
| `number`    | A number               | `integer`, `min`, `max`, `step` (values go in steps of it, counted from `min` or zero)                              |
| `enum`      | One of a set of values | `options`: values, or values with labels for people (`{"value": "gf", "label": "Gluten free"}`), so a label can change without touching what entries keep |
| `list`      | Several values         | `item` (a field definition for each value; text by default), `min` and `max` (the fewest and most items)            |
| `media`     | A media file, by its id | `kind`: `image`, `video`, `audio`, `document`, or `file`, so the admin's picker offers only those (any file when it's left out) |
| `slug`      | A URL-safe name        |                                                                                                                     |
| `object`    | Several fields, kept together under its key (`details: {servings: 4}`) | `fields` (a list of definitions with names, or a map of names to definitions), `closed` |

Every field also takes `required` (a value is needed to publish),
`default`, `aliases` (other keys it's read from), `label`,
`description`, and `control`. There's no type for links to other
entries: use a [relation](relationships.md#linking-entries-to-other-entries).

An `object` is how several fields travel together: an `seo` field with
`title` and `description` inside it is one field to add to each type,
and an entry keeps its values as `seo.title` and `seo.description`.

A `media` field holds the id of a file in the media library, as the
featured `image` and a profile's `avatar` do, so the file can be
renamed or moved without breaking anything. Older files may still name
one by its path (`/media/2026/10/photo.jpg`); it's still read, and
`content:lint` and Site Health list it until `bin/blush
content:media-ids --write` rewrites it to the file's id.

## A type's fields

A type's `fields` lists the library fields it carries, in the order the
editor shows them. Each is the field's key, or an object naming the
`field` with what this type changes about how it's asked for: its
`label`, its help (`description`), and whether it's `required` to
publish. Anything left out is the field's own. Nothing about the value
changes per type, and only a field at the top changes, never one inside
an `object`.

A **section** is a heading over some of the fields, for the editor's
sidebar: `section` is the heading, `description` a line under it, and
`fields` the fields under it, as above. Sections are only presentation: they
save nothing to entries, and they're one level deep. Fields outside any
section are shown with the type's own.

```json
{
	"fields": [
		"difficulty",
		{ "field": "servings", "required": true },
		{
			"section": "Timing",
			"description": "In minutes.",
			"fields": ["prep_time", { "field": "cook_time", "label": "Cook time (oven)" }]
		}
	]
}
```

- Any type can be given library fields: the built-in pages, the
  profiles type, and types from plugins too. A file in `user/data/types`
  that sets only `fields` (and the type's [place in the menu](content-types.md#where-a-type-sits-in-the-admins-menu))
  gives them library fields and changes nothing else about them.
- A type in `user/data/types` can't define fields of its own: its
  `fields` list library fields by key, and a definition there is
  refused, since fields are the library's. A plugin's type may (`new Collection('recipe', fields:
  [new TextField('cuisine')])`); the admin shows them, read only, and the
  type can still add library fields.
- Each key is declared once on a type. A library field can't use a key
  (or alias) that the type, the built-in fields (which every type starts
  with), or a plugin's registered fields already use, nor `id` or
  `refs`. Such a field is left off that type, and the site keeps
  running. Site Health, `bin/blush content:lint`, and Structure → Fields
  (**Left Off**) say which field and key.
- A key the library doesn't have is skipped, and
  `bin/blush content:lint` notes it.

## Giving pages fields

To give pages fields, place library fields on the built-in `page` type
in `user/data/types/page.json`, which changes nothing else about it:

```json
{ "fields": ["mood"] }
```

A plugin's tree can have fields of its own in code:
`new Tree('doc', fields: [new TextField('edition')])`.

## How a field is entered

A field's `type` says what its value is, and its `control` says how
someone enters it in the admin. A field without a `control` uses its
type's default. Some types can use another control instead: set
`control` to an object naming it (`"control": {"name": "radios"}`),
beside the field's own keys. The control's options go inside it, so a
library field's file holds how it's entered with the field.

| Control       | Stores                                  | Is                                                        |
|---------------|-----------------------------------------|-----------------------------------------------------------|
| `object`      | `object`                                | An Object: several fields, their values together under its key |
| `list`        | `list` of `object` items, or of values that fit on a line | A List: items, each with the same fields, or each one box (the default for both) |
| `text`        | `text`                                  | One line (the default)                                    |
| `textarea`    | `text`                                  | Several lines                                             |
| `markdown`    | `markdown`                              | Formatted text (the default)                              |
| `link`        | `text` with `format: url`               | A web address (the default for that format)               |
| `email`       | `text` with `format: email`             | An email address (the default for that format)            |
| `select`      | `enum`                                  | A menu (the default)                                      |
| `radios`      | `enum`                                  | Radio buttons                                             |
| `checkboxes`  | `list` of `enum` items                  | Checkboxes (the default)                                  |
| `multiselect` | `list` of `enum` items                  | Several picked from a menu, shown as chips                |
| `toggle`      | `bool`                                  | On or off (the default)                                   |
| `number`      | `number`                                | A number (the default)                                    |
| `date`        | `date`                                  | A date picker (the default)                               |
| `image`, `video`, `audio` | `media` with that `kind`    | A file of that kind from the library (the default for it) |
| `file`        | `media` of any other kind, or any kind  | A file from the library (the default for it)              |
| `icon`        | `text` with `format: icon`              | An icon (the default for that format)                     |
| `code`        | `text`, `slug`, or `media`              | One line in a code font (the default for `slug`)          |
| `readonly`    | Anything                                | Shown, not edited                                         |

Text-box controls (`text`, `textarea`, `markdown`, `link`, `email`,
`number`) take a `placeholder`, a prompt shown while the box is empty:
`"control": {"name": "text", "placeholder": "Salt"}`. A `list` takes
`itemLabel` (what each item is called, for "Add Ingredient") and
`summary` (the keys that sum up an item in one line). An object's fields
and a list's item take a `control` of their own.

You can name only the control, and the field gets the control's type.
`user/data/fields/diets.json`:

```json
{ "control": { "name": "checkboxes" }, "options": ["vegetarian", "vegan", "gluten-free"] }
```

`diets` is a `list` of `enum` items; a field naming only
`{"name": "link"}` is a `text` field with `format: url`, and one naming
`{"name": "image"}` a `media` field of images. A control that doesn't
store its field's type is an error. A list's items need to fit on one
line to be repeated a box each, so a list of `markdown` is shown
read-only. For text written freely, use a `text` field with `textarea`.

## Keys nothing declares

Only declared keys are read: Blush's built-in fields, a plugin's type's
own, a plugin's or theme's
[registered fields](../features/fields.md), and the library
fields the type lists. A key nothing declares stays in its
file (and a save in the admin keeps it), but templates, queries, the
admin, and the [API](../features/api/README.md) never see it. `content:lint --strict` and
Site Health (**Fields Nothing Reads**, which can remove them) list them.
Set `closed: true` on the type to make them errors instead.

## The field library's files

The admin's **Structure → Fields** makes and edits the fields in
`user/data/fields/`. A field's key is its
file's name, fixed once it's made, since every type that has it reads it
by that key. Deleting one takes it off every type that has it; entries
keep their values in their files.

The library is only made there: `config/fields.php` configures fields
but doesn't add them, and plugins
[register fields](../features/fields.md) of their own
instead, which aren't in the library. Set `library: false` in
`config/fields.php` to ignore `user/data/fields/`.

Editors that read JSON Schema can check a field's file: start it with
`"$schema": "../../../vendor/blush-dev/framework/resources/schemas/field.schema.json"`.
