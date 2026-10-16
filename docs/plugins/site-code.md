# Site code in `src/`

Code belongs in a [plugin](README.md) wherever it can: a plugin can be
turned on and off, versioned, and reused on another site. For code that's
only ever one site's, there's an escape hatch: the site's own `src/`
folder.

It fits when you're setting up a build for a client, as an agency or a
freelancer, or when you're running your own site and don't need the
code anywhere else. Everything in the [APIs and features](../features/README.md)
pages works the same from a site's provider as from a plugin's.

## The site's provider

Put your classes in `src/`, under the `App\` namespace (Composer
autoloads it). Connect them to Blush with a
[service provider](service-providers.md), and list it in
`config/app.php`:

```php
return new AppConfig(
	// ...
	providers: [App\SiteServiceProvider::class]
);
```

The provider is written as a plugin's is:

```php
<?php

declare(strict_types=1);

namespace App;

use Blush\Console\CommandRegistry;
use Blush\Core\ServiceProvider;

final class SiteServiceProvider extends ServiceProvider
{
	protected const array SINGLETONS = [
		Newsletter::class
	];

	protected const array TAGS = [
		CommandRegistry::TAG => [Console\Stats::class]
	];
}
```

## Routes from config

A site can also list its controllers in `config/routes.php`, in place of
tagging them in a provider (see [Your own pages](../features/routes.md)):

```php
return new RouteConfig(controllers: [App\Http\Hello::class]);
```

## What the site can't hold

The site itself has no templates, icons, or text of its own, and Blush
finds nothing in `src/` by file, only what the provider registers.
Templates come from [themes](../themes/README.md) (to change one, make a
child theme), icons from [icon packs](../icon-packs/README.md), plugins,
and themes, and blocks, content types, and relations from plugins.
