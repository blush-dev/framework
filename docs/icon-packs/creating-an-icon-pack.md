# Creating an icon pack

An icon pack is a set of SVG icons in a namespace of its own, with no
code. Put it in `extensions/{vendor}/{name}/`, with an `icons.json`.

## `icons.json`

```json
{
	"name": "acme/brands",
	"label": "Brand Logos",
	"namespace": "brands",
	"version": "1.0.0",
	"description": "Logos for social links.",
	"folder": "svg",
	"authors": [{ "name": "Jane Doe" }]
}
```

Only `name` is required. Without a `label` it's shown by its name, and
without a `namespace` it goes by its name, hyphenated (`acme-brands`).
Like a local plugin, it's off until it's turned on (see
[Turning icon packs on and off](turning-on-and-off.md)).

`authors`, `license`, `homepage`, `support`, `funding`, `abandoned`,
`suggest`, [`require`](../plugins/requirements.md),
[`conflict`](../plugins/conflicts.md),
[`replace`](../plugins/replacing-extensions.md), and
[`provide`](../plugins/composer-packages.md) work as a plugin's
do: a pack whose requirements aren't met adds no icons, even when it's
on.

## Icon names

Each `{icon}.svg` in the pack's `folder` (the pack's own folder, without
one) is `{namespace}/{icon}`: `svg/github.svg` is `brands/github`, used
as `:icon[GitHub]{name=brands/github}` or
`$template->block('icon', name: 'brands/github')`.

## Labels

Labels go in the pack's `lang/en.json`, under `icons.{icon}.label`, and
the pack's own `label` and `description` are translated under
`manifest.label` and `manifest.description` (see
[Your manifest's text](../themes/translations.md#your-manifests-text)):

```json
{
	"@@locale": "en",
	"@@domain": "acme/brands",
	"icons": {
		"github": { "label": "GitHub" }
	}
}
```

Start every catalog with `@@locale` and `@@domain` (the pack's name), as
[themes do](../themes/translations.md#catalogs).

## Autocomplete in your editor

For autocomplete, point `$schema` at
`vendor/blush-dev/framework/resources/schemas/icons.v1.schema.json`,
the schema for its [schema version](../concepts/extensions.md).

## A Composer icon pack

**A Composer icon pack** is a package of type `blush-icons`. Its
manifest is the `icons.json` in the package, or its `composer.json`'s
`extra.blush`, or both. Its name is the package's, so the manifest can
leave `name` out; one naming something else is broken.
