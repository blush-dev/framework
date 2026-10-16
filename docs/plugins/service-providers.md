# Service providers

A service provider connects a plugin's classes to Blush. It's where a
plugin says what it adds: its commands, routes, blocks, tools, jobs, and
the rest. This page assumes you know PHP.

## Naming the provider

A plugin names its provider in its `plugin.json`, with an `autoload`
rule so Blush can find the class:

```json
{
	"name": "acme/hello",
	"provider": "Acme\\Hello\\HelloServiceProvider",
	"autoload": {
		"psr-4": { "Acme\\Hello\\": "src/" }
	}
}
```

`bin/blush plugin:new acme/hello` writes both for you, with an empty
provider in `extensions/acme/hello/src/HelloServiceProvider.php`. See
[Creating a plugin](creating-a-plugin.md) for the rest of the manifest.

## What a provider says

A provider tells Blush about your classes. Most need only constants:

```php
<?php

declare(strict_types=1);

namespace Acme\Hello;

use Blush\Console\CommandRegistry;
use Blush\Core\ServiceProvider;

final class HelloServiceProvider extends ServiceProvider
{
	// Classes built once and shared.
	protected const array SINGLETONS = [
		Newsletter::class
	];

	// Classes added to a group Blush looks for, such as commands.
	protected const array TAGS = [
		CommandRegistry::TAG => [Console\Stats::class]
	];
}
```

For work that needs more than constants, override `register()` or
`boot()` and use `$this->container`. The pages under
[APIs and features](../features/README.md) show which each feature uses.

## Getting what a class needs

Classes get what they need through their constructor: ask for a Blush
service (or one of your own) by its type, and Blush passes it in. For
example, `Blush\Content\Entries` finds entries, and every config object
(`Blush\Core\AppConfig` and the rest) is available the same way.

## Nothing is found by file

Blush finds nothing in your site by file: no templates, icons, or
classes are picked up from folders, only what your provider registers.
Templates and icons belong to [themes](../themes/README.md) and
[icon packs](../icon-packs/README.md), and blocks, content types, and
relations to plugins.

A site can also have a provider of its own, outside any plugin, for code
that's only ever that site's. See [Site code in `src/`](site-code.md).
