# Conflicts

`conflict` in a manifest names what an extension can't run with, each
mapped to the versions it can't, as Composer's does. It has the same
names and constraints as [`require`](requirements.md), and works the
same way for every kind:

```json
{
	"conflict": {
		"acme/old-seo": "<2.0",
		"php": ">=9.0"
	}
}
```

## When an extension conflicts

An extension conflicts when the site has what it names at a version
that fits: `blush-dev/framework` or `php` at that version, `ext-{name}`
loaded at it, or another plugin, theme, or icon pack **turned on** at
it (a plugin or pack that's on, or a theme that's active or one the
active theme falls back to). One that's off, isn't installed, or is at
another version doesn't conflict.

A library counts too: it conflicts when the site's Composer installed
it at a version that fits. So does an extension that's on and
[replaces](replacing-extensions.md) or [provides](composer-packages.md)
what a conflict names, at a version that fits, as in Composer. A
constraint Blush can't read counts as a conflict.

## Which one stops

**The extension that declares the conflict is the one that stops,** the
same way as when its requirements aren't met: a plugin doesn't run, a
pack adds no icons, and a theme can't be activated (an active one shows
the default theme in its place). The extension it names carries on.

So turning on `acme/old-seo` above isn't refused; the extension
declaring the conflict stops, and the admin names it among what
stopped. An extension it names counts while it's on even if it can't
run itself. Two that each name the other both stop.

## Where it shows

A conflict shows on the extension's details, under **Conflicts**, and
the check commands and `doctor` report one the same way as a
requirement that isn't met: `Conflicts with Old SEO <2.0 (version 1.4.0
is on).`

A Composer package's own `composer.json` `conflict` counts, as in
Composer, since Composer can't see the extensions in `extensions/`
(`extra.blush`'s `conflict`, if it has one, is used in its place).
