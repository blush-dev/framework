# Creating a plugin

A local plugin is a folder in `extensions/{vendor}/{name}/` with a
`plugin.json` manifest. A Composer plugin is a package that carries the
same manifest in its `composer.json`.

## Starting one

`bin/blush plugin:new acme/hello` starts one for you in
`extensions/acme/hello/`: the manifest, an empty
[provider](service-providers.md) in `src/HelloServiceProvider.php` to
fill in, and a `lang/en.json` for its text, with its `@@locale` and
`@@domain` filled in (see [Text in PHP code](../features/translations.md)).

## `plugin.json`

```json
{
	"name": "acme/hello",
	"label": "Hello",
	"namespace": "hello",
	"version": "1.0.0",
	"description": "Says hello.",
	"provider": "Acme\\Hello\\HelloServiceProvider",
	"autoload": {
		"psr-4": { "Acme\\Hello\\": "src/" },
		"files": ["src/helpers.php"]
	},
	"require": { "blush-dev/framework": "^2.0" },
	"authors": [{ "name": "Jane Doe", "homepage": "https://example.com" }],
	"license": "MIT"
}
```

Only `name` (here or in its `composer.json`) is required. Without a
`label`, it's shown by its name, and without a `namespace`, it goes by
its name, hyphenated (see [Extensions](../concepts/extensions.md)).
Without a `provider`, it registers nothing (see
[A plugin without a provider](without-a-provider.md)). Blush finds the
plugin and loads it; no Composer step needed.

`autoload` works as Composer's does: `psr-4` maps namespace prefixes
(each ending in `\`) to folders, and `files` lists files loaded once
when the plugin runs, such as helper functions. Every path must be
inside the plugin.

It's off until you turn it on, in **Extend → Plugins** or by naming it
in `config/plugins.php`'s `enabled` list (see
[Installing extensions](../concepts/installing-extensions.md)).

`require` says what the plugin needs (see [Requirements](requirements.md)),
and `conflict`, `replace`, and `provide` work as Composer's do (see
[Conflicts](conflicts.md), [Replacing another extension](replacing-extensions.md),
and [Providing a package](composer-packages.md)).

## Authors and license

`authors` (each with a `name`, and optionally an `email`, `homepage`,
and `role`, as in `composer.json`) and `license` are shown in the
admin, though not email addresses. `license` takes Composer's forms: an
[SPDX identifier](https://spdx.org/licenses/) (`MIT`), a list any of
which applies (`["MIT", "GPL-2.0-or-later"]`), `(MIT or Apache-2.0)`,
`(MIT and Apache-2.0)` when all apply, or `proprietary`. Common open
source licenses link to their text.

## Links and funding

`homepage`, `support`, and `funding` say where to learn about, get help
with, and fund it, as in `composer.json`:

```json
{
	"homepage": "https://example.com/hello",
	"support": {
		"docs": "https://example.com/hello/docs",
		"source": "https://github.com/acme/hello",
		"issues": "https://github.com/acme/hello/issues",
		"forum": "https://example.com/forum",
		"chat": "https://example.com/chat",
		"wiki": "https://github.com/acme/hello/wiki",
		"irc": "irc://irc.libera.chat/acme",
		"rss": "https://example.com/hello/feed",
		"security": "https://example.com/hello/security",
		"email": "help@example.com"
	},
	"funding": [
		{ "type": "github", "url": "https://github.com/sponsors/acme" }
	]
}
```

Every address is an `http` or `https` URL, except `irc` (`irc://` or
`ircs://`) and `email` (an email address). The admin shows them on the
extension's details, under **Links** and **Sponsor** (all but `email`).
In a manifest, one that doesn't fit is an error; in `composer.json`,
it's left out.

## Abandoned

`abandoned` says it's no longer maintained, as in `composer.json`:
`true`, or the name of the package to use instead (any package's, not
only an extension's):

```json
{
	"abandoned": "acme/hello-next"
}
```

It only warns, as Composer does: an abandoned extension still runs, and
can still be turned on, activated, or installed. The admin marks it
**Abandoned** in its list and says so on its details, linking the
replacement when it's installed, and `plugin:check`, `theme:check`, and
`icon-pack:check` warn of it. In a manifest, anything but `true`,
`false`, or a `vendor/name` is an error; in `composer.json`, any other
string counts as `true`.

## Suggestions

`suggest` names packages that work well with the extension, each with
why, as in `composer.json`: other plugins, themes, or icon packs by
`vendor/name`, libraries, or PHP extensions as `ext-{name}`:

```json
{
	"suggest": {
		"acme/brands": "For brand icons in the share buttons.",
		"ext-intl": "For dates in your site's language."
	}
}
```

Nothing is checked or installed for it: an extension runs the same with
or without what it suggests. A theme that suggests a plugin for a
component or block prints it with `$template->componentIf()` or
`$template->blockIf()`, which print nothing without it (see
[Components](../features/components/README.md)). The admin lists them on the
extension's details, under **Suggests**, each with why beneath it,
linking one that's installed and saying whether a PHP extension is
loaded, and installing an extension from a zip lists what it suggests.
In a manifest, anything but an object of names to strings is an error;
in `composer.json`, an entry that doesn't fit is left out.

## Keywords

`keywords` lists words the extension is about, as in `composer.json`:

```json
{
	"keywords": ["gallery", "images", "lightbox"]
}
```

They aren't shown anywhere. The admin's Themes, Plugins, Icon Packs,
and Admin Themes screens search them, with an extension's label, name,
and description, so someone filtering for "images" finds it. In a
manifest, anything but a list of strings is an error; in
`composer.json`, anything that isn't a string is left out.

## Autocomplete in your editor

For autocomplete in your editor, add a `$schema` key pointing at the
schema Blush ships for your file's
[schema version](../concepts/extensions.md) (the path is relative to
`plugin.json`):

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/plugin.v1.schema.json",
	"schemaVersion": 1,
	"name": "acme/hello"
}
```

## A Composer plugin

A Composer plugin is a package of type `blush-plugin`. Its name is the
package's, and the rest of the manifest goes in its `composer.json`
under `extra.blush` (every key is optional, so a package can leave
`extra.blush` out):

```json
{
	"name": "acme/hello",
	"type": "blush-plugin",
	"extra": {
		"blush": {
			"label": "Hello",
			"namespace": "hello",
			"provider": "Acme\\Hello\\HelloServiceProvider",
			"require": { "blush-dev/framework": "^2.0" }
		}
	}
}
```

Every installed plugin is on. Turn one off on the admin's
[Plugins](../admin/plugins.md) screen, or by its name in
[`config/plugins.php`](../reference/configuration/plugins-and-middleware.md).

## A broken plugin

A plugin whose manifest can't be read (a `plugin.json` that doesn't
parse, or is missing a key it needs) is broken. It never runs, even
when it's turned on, and the rest of the site carries on without it.
The Plugins screen, `plugin:list`, and `plugin:check` list it by where
it was found, such as `extensions/acme/hello`, with the reason.
