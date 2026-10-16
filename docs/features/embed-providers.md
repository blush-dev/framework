# Embed providers

An embed provider turns a link to another site, such as a video, into
an embed. A plain provider needs no code: list it in `config/embed.php`
(see [Configuration](../reference/configuration/README.md)). A provider
that needs code, such as one that rewrites its frame's URL, comes from a
plugin.

## A provider with code

Extend `Blush\Embed\EmbedProvider`. Its constructor passes the name,
label, URL schemes, and oEmbed address to the parent's; override
`frame()` to change the URL that's framed.

Register it in your plugin's [provider's](../plugins/service-providers.md)
`boot()`:

```php
use Blush\Embed\ProviderRegistry;

public function boot(): void
{
	$this->container->get(ProviderRegistry::class)->register('peertube', Embed\PeerTube::class);
}
```

## A post drawn by its own script

A provider whose oEmbed answer is a quote its own script draws as a post
(as X's is) overrides `frame()` to return `null` and `asset()` to name a
[registered asset](scripts-and-styles.md) holding that script, such as
core's `core/embed-x`. The `embed` block then shows the answer's
`<blockquote>`, cleaned down to its text and links, and asks for the
asset, so the script loads once on the page. The script is always the
asset's, never the one in the answer.
