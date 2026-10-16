# Replacing another extension

`replace` in a manifest names the packages an extension stands in for,
as Composer's does: a fork, or a package that's been renamed. Each maps
to the versions it stands in for, or `self.version` for the extension's
own version:

```json
{
	"name": "acme/seo-pro",
	"version": "2.0.0",
	"replace": {
		"acme/seo": "self.version"
	}
}
```

- **It meets their requirements.** While `acme/seo-pro` runs, an
  extension that [requires](requirements.md) `acme/seo` is met by it,
  when the versions match: `"acme/seo": "^2.0"` is met by
  `self.version` 2.0.0 above, and `^1.0` isn't. Versions match as they
  do in Composer. The requirement says what met it ("Seo Pro 2.0.0
  replaces it"), and **Required by** on the replacing extension lists
  what requires the packages it replaces.
- **It doesn't run alongside them.** As in Composer, where the two can't
  be installed together, an extension doesn't run while one it replaces
  is turned on, at any version, the same way as a
  [conflict](conflicts.md): the extension declaring `replace` is the one
  that stops, and says why (`Replaces SEO (is on).`). Turn the old one
  off (or delete it) and the new one runs.

Its details list what it replaces under **Replaces**. A Composer
package's own `composer.json` `replace` counts too, as in Composer
(`extra.blush`'s, if it has one, is used in its place).

A fork that keeps its original's
[namespace](../concepts/extensions.md) can't be installed beside it,
since no two extensions share a namespace; delete the original first.

## Seeing it from the other side

An extension's details also show the links other extensions have to
it: **Conflicts with it** (the extensions whose `conflict` hits it at
its version, naming it or a package it replaces or provides), **Replaced
by**, and **Also provided by**, beside **Required by**.

Turning an extension on, or activating a theme, asks first when that
would stop others, naming them: the ones that conflict with it or
replace it, and the ones that need those.
