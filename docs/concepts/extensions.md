# Extensions

Extensions are what a site installs. They're how most sites get their
looks and their features, and how most people build for Blush.

## Four kinds

- **[Plugins](../plugins/README.md)** are where a site's code belongs, so
  it can be turned on, off, and reused across sites: a manifest plus,
  usually, a service provider.
- **[Themes](../themes/README.md)** control how the site looks.
- **[Icon packs](../icon-packs/README.md)** are SVG icons, with no code.
- **[Admin themes](../admin-themes/README.md)** change how the admin
  looks, with no code: colors, fonts, and sizes. They have a name but no
  namespace.

To add one to a site and turn it on, see
[Installing extensions](installing-extensions.md).

## Names, labels, and namespaces

Every extension's manifest has the same three keys:

- **`name`:** the key it's known by, `vendor/name` (`acme/hello`), in
  lowercase letters, digits, `-`, `_`, and `.`. For a Composer package,
  it's the package's name.
- **`label`:** its title, as people read it. Optional: leave it out and
  the extension is shown by its `name`.
- **`namespace`:** what its blocks, components, icons, and translations
  go by (`hello`, for `hello/tabs`). Lowercase letters, digits, `-`, and
  `_`. `core`, `theme`, and `default` are reserved. Optional, and best
  left out: without one, it's the name with a hyphen for the `/` (and
  for any `.`), so `acme/hello` goes by `acme-hello` (`acme-hello/tabs`).
  Since names are unique, so is that namespace. `plugin:new` and
  `theme:new` write that form too. Give one only when you want something
  shorter.

No two installed extensions may share a namespace. Two plugins that do
are an error. Two themes, or two icon packs, that do are both broken.
Across kinds, installed plugins (even ones turned off) come first, then
themes, then icon packs, and the one that comes later is listed as broken
on the admin's screens, naming who has the namespace.

## Where extensions live

Every extension you add yourself, of every kind, lives in your site's
`extensions/` folder at its name, the way Composer keeps packages in
`vendor/`: `acme/hello` is `extensions/acme/hello/`. So two extensions
can't share a name on one site, and two vendors' `hello` live side by
side. The folder must match the manifest's `name`. One that doesn't is
listed as broken, saying where it belongs. Each extension can be its own
git repository.

## Manifests

What kind an extension is comes from its manifest file, `plugin.json`,
`theme.json`, `icons.json`, or `admin-theme.json`, or from the `type` in
its `composer.json`: `blush-plugin`, `blush-theme`, `blush-icons`, or
`blush-admin-theme`, as Composer has it. Either is enough, and a folder
may have both, as long as they name the same kind. A folder holds one
kind, so one that names two (two manifests, or a manifest and another
kind's `type`) is broken.

Manifests use Composer's names and shapes for the keys they share with
`composer.json`: `name`, `description`, `version`, `license`, `authors`,
`autoload`, `require`, `conflict`, `replace`, `provide`, `homepage`,
`support`, `funding`, `abandoned`, `suggest`, and `keywords`. A manifest
that leaves one of those out takes it from the `composer.json` beside
it, so a package says them once. A manifest's own value replaces
`composer.json`'s whole; the two aren't merged. Blush's own keys
(`label`, `namespace`, `provider`, and a theme's or icon pack's own) go
in the manifest, or in `composer.json` under `extra.blush`, never at its
top level. A manifest may even be empty (`{}`) when its `composer.json`
names it.

So an extension can do without a manifest file entirely, as a Composer
package does: a `composer.json` of a Blush type, with Blush's keys under
`extra.blush`, is its manifest.

```json
{
	"name": "acme/hello",
	"type": "blush-plugin",
	"version": "1.0.0",
	"autoload": { "psr-4": { "Acme\\Hello\\": "src/" } },
	"extra": {
		"blush": {
			"label": "Hello",
			"provider": "Acme\\Hello\\HelloServiceProvider"
		}
	}
}
```

When there's both, each key is read from the first place that has it:
the manifest file, then `extra.blush`, then the top of `composer.json`
(for the keys shared with Composer).

The admin lists them under **Extensions** (Themes, Plugins, and Icon
Packs), and installs them from a `.zip` (see
[Installing extensions](installing-extensions.md)).

## Schema versions

The shape of an extension's JSON files can change between Blush
versions. So that Blush always knows which shape it's reading, each
file says the version of the schema it's written to, in
`schemaVersion`:

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/plugin.v1.schema.json",
	"schemaVersion": 1,
	"name": "acme/hello"
}
```

- It's in `plugin.json`, `theme.json`, `icons.json`, `admin-theme.json`,
  and an admin theme's `tokens.json`, each with its own version. A
  Composer package without a manifest file puts it under
  `extra.blush`. A manifest file's version is never taken from
  `composer.json`.
- Without it, a file is version 1. `plugin:new` and `theme:new` always
  write it.
- Each version has its own schema file, such as `theme.v1.schema.json`,
  which never changes once it's released. Point `$schema` at the
  version your file says.
- A file written to an older version still works: Blush reads it in
  the current shape and never changes it. `plugin:check`,
  `theme:check`, `icon-pack:check`, and `admin-theme:check` warn about
  it and show the file written for the current version.
- A file written to a newer version than your Blush reads is broken,
  and listed as needing a newer Blush.
- Every version released in 2.x is read throughout 2.x. A later major
  release may drop old ones.
