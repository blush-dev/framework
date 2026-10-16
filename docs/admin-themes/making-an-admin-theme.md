# Making an admin theme

An admin theme is a folder with two files, plus any fonts it brings.
Local admin themes go in `extensions/{vendor}/{name}`, like every
[extension](../concepts/extensions.md):

```text
extensions/acme/harbor/
    admin-theme.json    its name and label
    tokens.json         what it changes
    fonts/              fonts it brings, if any
```

## `admin-theme.json`

`admin-theme.json` says what the theme is:

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/admin-theme.v1.schema.json",
	"schemaVersion": 1,
	"name": "acme/harbor",
	"label": "Harbor",
	"description": "Sea blues and a serif for titles",
	"version": "1.0.0"
}
```

- **`name`** is required: `vendor/name`, matching the folder.
- **`label`** is the name people see. Without one, the theme is shown by
  its name.
- **`description`** is shown beside its swatch on Your Account. Keep it
  to a few words.

It takes `version`, `authors`, `license`, `require`, `conflict`,
`replace`, `provide`, `abandoned`, `suggest`, `keywords`, and links as
every [manifest](../concepts/extensions.md) does. `schemaVersion` is the
version of the schema the file is written to, and `tokens.json` has
its own (see [schema versions](../concepts/extensions.md)). An admin theme has no
`namespace`: it has no blocks, components, icons, or text of its own.

## As a Composer package

To publish a theme as a Composer package instead, give its
`composer.json` the type `blush-admin-theme`.

Next: what the theme changes, in [`tokens.json`](tokens-json.md).
