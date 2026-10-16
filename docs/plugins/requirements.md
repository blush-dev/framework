# Requirements

`require` in a manifest maps what an extension needs to a version
constraint, as Composer's does. It works the same way for plugins,
themes, and icon packs.

```json
{
	"require": {
		"blush-dev/framework": "^2.0",
		"php": ">=8.5",
		"ext-intl": "*",
		"acme/shop": "^2.0"
	}
}
```

## Version constraints

Constraints are Composer's (`^2.0`, `~1.2`, `>=8.4`, `1.*`,
`1.0 - 2.0`, `^1.0 || ^2.0`), checked by Composer's rules, wherever a
version comes from (the manifest, `composer.json`, or Composer). That
includes stability: `^2.0` and `>=2.0` take `2.0.0-beta1` and
`2.0.0-dev`, `<2.0` doesn't, and `>=2.0@beta` starts at the first beta.
A branch version, such as `dev-main`, meets only `*` or its own name.

Give an extension a `version` Composer can read (`1.2.0`,
`2.0.0-beta1`); one it can't, such as `1.0-final`, meets only `*`, and
the check commands warn about it. One with no `version` counts as
`0.0.0`.

## What you can require

- `blush-dev/framework`: the Blush version.
- `php`: the PHP version.
- `ext-{name}`: a PHP extension that must be loaded (`"ext-intl": "*"`).
  Site Health's Requirements tab lists it under Plugins and Themes,
  naming your extension.
- Another plugin, theme, or icon pack, by its name: `"acme/shop": "^2.0"`
  needs Shop installed at a version that fits, and running. A plugin or
  icon pack runs when it's turned on and its own requirements are met;
  a theme runs when it's the active theme or one it falls back to.
- A library, by its name: `"guzzlehttp/guzzle": "^7.0"` needs the
  site's Composer to have installed it at a version that fits, as
  Composer checks it (a version it's aliased to, or a package that
  [replaces](replacing-extensions.md) or [provides](composer-packages.md)
  it, counts too).
- `lib-*` (such as `lib-icu`), `composer-plugin-api`,
  `composer-runtime-api`, and `php-64bit`, `php-ipv6`, `php-zts`, and
  `php-debug` are always met: Composer checks them when it installs a
  package, and Blush can't.

No two extensions share a name, of any kind, so a name in `require`
always means one extension. A theme, icon pack, or admin theme with the
name of an extension found before it (plugins first, then themes, then
icon packs) is broken.

## When requirements aren't met

- **A plugin** doesn't run, even when it's on, and the Plugins screen
  says why.
- **An icon pack** adds no icons, even when it's on, and the Icon Packs
  screen says why.
- **An admin theme** isn't offered, even when it's on, so no account can
  choose it, and anyone who had sees the site's default.
- **A theme** can't be activated, in the admin or with `theme:activate`.
  If the active theme stops meeting them (a plugin it needs is turned
  off, say), visitors see the default theme in its place until that's
  fixed, and the Themes screen says why. A theme and the themes it falls
  back to run together or not at all, so a requirement of any of them
  counts for all of them.

A `vendor/name` that's neither an extension nor installed by Composer
isn't installed, so it isn't met, and anything else in `require` can't
be checked, so it isn't met either. Turning an extension off (or
switching themes) also stops every extension that requires it, and the
admin names what stopped. A plugin's requirements are loaded before it.

A Composer package's `require` is read from its `composer.json` too, as
Composer reads it, unless its manifest (or, for a plugin,
`extra.blush`) has one.

## Checking

`bin/blush plugin:check`, `theme:check`, `icon-pack:check`, and
`admin-theme:check` check each kind from the command line, and `doctor`
warns of anything that's on but can't run.
