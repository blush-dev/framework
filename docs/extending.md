# Extending Blush

Most sites never need PHP beyond their config files. When yours does, this
page shows where your code goes. It assumes you know PHP.

## Your site's code

Put your classes in `src/`, under the `App\` namespace (Composer autoloads
it). Connect them to Blush with a **service provider**, and list it in
`config/app.php`:

```php
return new AppConfig(
	// ...
	providers: [App\SiteServiceProvider::class]
);
```

A provider tells Blush about your classes. Most need only constants:

```php
<?php

declare(strict_types=1);

namespace App;

use Blush\Console\CommandRegistry;
use Blush\Core\ServiceProvider;

final class SiteServiceProvider extends ServiceProvider
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

Classes get what they need through their constructor: ask for a Blush
service (or one of your own) by its type, and Blush passes it in. For
example, `Blush\Content\ContentRepository` finds entries, and every
config object (`Blush\Core\AppConfig` and the rest) is available the same
way.

For work that needs more than constants, override `register()` or
`boot()` and use `$this->container`.

## Your own pages

Add a controller with route attributes:

```php
<?php

declare(strict_types=1);

namespace App\Http;

use Blush\Http\Response;
use Blush\Routing\Attributes\Get;
use Psr\Http\Message\ResponseInterface;

final readonly class Hello
{
	#[Get('/hello/{name}', name: 'hello')]
	public function __invoke(string $name): ResponseInterface
	{
		return Response::text("Hello, {$name}!");
	}
}
```

List it in `config/routes.php`:

```php
return new RouteConfig(controllers: [App\Http\Hello::class]);
```

Route parameters are passed to your method by name, and typed ones
(`int $year`) only match values of that type. Your routes come after the
built-in ones, so they can't break content URLs; `bin/blush routes:list`
shows any conflicts.

## Your own commands

```php
<?php

declare(strict_types=1);

namespace App\Console;

use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Content\ContentRepository;

#[Command('stats', 'Count the published posts.')]
final readonly class Stats
{
	public function __construct(private ContentRepository $content) {}

	public function __invoke(
		Output $output,
		#[Option('The type to count.')] string $type = 'post'
	): ExitCode {
		$count = $this->content->query()->type($type)->count();

		$output->success("{$count} published entries of type {$type}.");

		return ExitCode::Success;
	}
}
```

Tag it with `CommandRegistry::TAG` in your provider (as above), then run
`bin/blush stats --type=page`. Options and arguments come from the
method's parameters, so `bin/blush help stats` is written for you.

## Components

Your site's own components are in the `app` namespace (`app/badge`).
Their templates go in `resources/views/components/` (`app-badge.php`),
and any classes are registered by your provider. They work with every
theme, in templates and in Markdown. See [Components](components.md) for writing one, with or
without a PHP class.

## Extensions

An extension packages the same kind of code for reuse across sites. It's a
manifest plus a service provider.

**A local extension** lives in `user/extensions/{slug}/`, with an
`extension.json`:

```json
{
	"name": "acme/hello",
	"version": "1.0.0",
	"description": "Says hello.",
	"provider": "Acme\\Hello\\HelloServiceProvider",
	"autoload": {
		"psr-4": { "Acme\\Hello\\": "src/" }
	}
}
```

Blush finds it and loads its classes; no Composer step needed.

**A Composer extension** is a package of type `blush-extension`, with the
same manifest in its `composer.json` under `extra.blush`.

Every installed extension is on. Turn one off in
[`config/extensions.php`](configuration.md#extensions-and-middleware).

Each extension can be its own git repository. If `user/` is one too,
ignore `extensions/` there (see [the site layout](README.md#how-a-blush-site-is-laid-out)).

### Components from an extension

An extension's [components](components.md) use its vendor as their
namespace: `acme/hello` registers `acme/tabs`, not `tabs`. Their text
(labels, descriptions) goes in the extension's `lang/en.json`, under
`components.tabs`. Extensions can't ship component templates yet, so the
theme or site provides `views/components/acme-tabs.php`.

### Content types from an extension

An extension can define [content types](content-types.md), much like a
WordPress plugin registering post types. Write a class that implements
`ContentTypeSource` and returns the types:

```php
<?php

declare(strict_types=1);

namespace Acme\Recipes;

use Blush\Content\Query\Order;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentTypeSource;
use Blush\Content\Type\Listing;
use Blush\Content\Type\Taxonomy;

final class ContentTypes implements ContentTypeSource
{
	public function types(): iterable
	{
		yield new Collection(
			'recipe',
			folder: 'recipes',
			listing: new Listing(orderBy: 'published', order: Order::Desc)
		);

		yield new Taxonomy('cuisine', folder: 'recipes/cuisines', types: ['recipe']);
	}
}
```

Tag it in the extension's provider:

```php
protected const array TAGS = [
	ContentTypeSource::TAG => [ContentTypes::class]
];
```

The kinds and options are the same as in `config/content.php`. A site can
still redefine one of your types in its `config/content.php`, but not in
`user/data/types/`. Two extensions can't define the same type.

## Events

Blush announces what it's doing through events you can listen for, such as
`Blush\Content\Events\ContentIndexed` (content changed),
`Blush\Publish\Events\ContentPublished`, and `Blush\Export\Events\ExportFinished`.
Listen in your provider's `boot()`:

```php
use Blush\Content\Events\ContentIndexed;
use Blush\Event\Listener\ListenerRegistry;

public function boot(): void
{
	$this->container->get(ListenerRegistry::class)->listen(ContentIndexed::class, App\PingSearchEngines::class);
}
```

A listener is an invokable class that takes the event.

## Debugging

`dump($value)` prints a readable view of any value, and `dd($value)`
prints it and stops. They come from Symfony's VarDumper, a development
tool; add it to your site if it isn't there yet:

```sh
composer require --dev symfony/var-dumper
```

Call them anywhere: in a template, a controller, or a service provider.
On a web page, a dump made outside a template shows at the top of the
page, and the page still loads. From `bin/blush`, dumps print in the
terminal.

Remove your dumps before going live. A production install without
development packages (`composer install --no-dev`) doesn't have
`dump()`, so a leftover call is an error.

