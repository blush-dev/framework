# Providing a package

`provide` in a manifest names packages an extension implements, as
Composer's does: usually a shared name that several extensions can
stand behind, such as an AI provider any plugin can require without
caring which one is installed. Each maps to the versions it provides,
or `self.version` for the extension's own version:

```json
{
	"name": "acme/claude",
	"provide": {
		"acme/ai-provider": "1.0"
	}
}
```

While `acme/claude` runs, an extension that
[requires](requirements.md) `acme/ai-provider` is met by it, when the
versions match (`"acme/ai-provider": "^1.0"` is; `^2.0` isn't), and the
requirement says what met it ("Claude 1.0.0 provides it").

Unlike [`replace`](replacing-extensions.md), providing isn't a
conflict: any number of extensions may provide the same package and run
together, and a package provided this way doesn't need to exist
anywhere. A [conflict](conflicts.md) naming it does hit an extension
that's on and provides it.

Its details list what it provides under **Provides**, and
**Required by** lists what requires those packages. A Composer
package's own `composer.json` `provide` counts too (`extra.blush`'s, if
it has one, is used in its place), and a library Composer installed is
met by what other Composer packages provide, as Composer checks it.
