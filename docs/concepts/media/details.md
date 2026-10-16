# Details about a file

Each media file can carry details, such as a title, a caption, and alt
text, kept apart from the file in `user/data/media/` (see
[Media in the admin](../../admin/media.md)).

## Built-in fields

Every file has these:

| Field | What it's for |
|---|---|
| `title` | What the library calls it, in place of its file name |
| `caption` | Shown with the file where it's used, such as under an image |
| `credit` | Who made it, or where it's from |
| `content` | A longer description, for the library (Markdown); the admin calls it **Description** |

Images also have `alt`, which says what the image shows for anyone who
can't see it. Where an image is used, what the entry writes wins; the
library's alt text and caption are filled in when you insert it, and a
page's image without alt text uses the library's.

## Fields from plugins

A plugin can [register fields](../../features/fields.md) of
its own for the kinds of file they're for: `media:image`,
`media:video`, `media:audio`, `media:document` (PDFs, office files,
plain text), or `media:file` (anything else). Their values are checked
and kept in the metadata file, and can't reuse a built-in field's name.
The admin doesn't show them yet; fields from the site's
[field library](../custom-fields.md) go on content types.

## Autocomplete in your editor

A metadata file can point your editor at the built-in fields'
schema, with a `"$schema"` key:
`"$schema": "../../../vendor/blush-dev/framework/resources/schemas/media.schema.json"`
(with as many `../` as it's deep). The admin keeps the key when it saves the file.
