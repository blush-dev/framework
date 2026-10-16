# Icons from a plugin

A plugin can add its own icons, which use its namespace. Icons that
need no code are simpler as an [icon pack](../icon-packs/README.md).

Add its folder of SVG files in the plugin's
[provider's](../plugins/service-providers.md) `boot()`:

```php
use Blush\Icon\IconRegistry;

public function boot(): void
{
	$this->container->get(IconRegistry::class)->add('hello', __DIR__ . '/../icons');
}
```

Each `icons/{name}.svg` is then `hello/{name}`, with its label in the
plugin's `lang/en.json` under `icons.{name}.label`.
