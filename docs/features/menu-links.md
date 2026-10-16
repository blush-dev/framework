# Menu links

A plugin can add a new kind of [menu](../concepts/menus.md) link, such as
a link to a product by its slug.

A link is a class that extends `Blush\Menu\Link\MenuLink`. Its
`resolve()` returns a `LinkTarget` (a URL and the label it brings) or
throws `UnresolvedLink` to leave the item out; `validate()` checks the
value's shape. Register it under the item key it answers to, in your
plugin's [provider's](../plugins/service-providers.md) `boot()`:

```php
use Blush\Menu\Link\MenuLinkRegistry;

public function boot(): void
{
	$this->container->get(MenuLinkRegistry::class)->register('product', Menu\ProductLink::class);
}
```

Then a menu item can say `product: blue-mug`.

- A link is built through the container, so its constructor can ask for
  services.
- A link to an entry can also implement `Blush\Menu\Link\LinksEntry`, so
  its items keep the entry's id in `ref` and `bin/blush menu:refs` files
  it.
