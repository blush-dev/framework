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

## Components with logic

A [template-only component](themes.md#components) is enough most of the
time. When props need logic or services, back it with a class:

```php
<?php

declare(strict_types=1);

namespace App\View;

use Blush\View\Component\Component;

final class Card extends Component
{
	public function __construct(
		public string $title,
		public int $columns = 2
	) {}
}
```

It renders the same `views/components/card.php` template, with its public
properties as variables. Props from Markdown (`::card{title="Hi"
columns=3}`) are converted to the types you declare. Register it in your
provider's `boot()`:

```php
use Blush\View\Component\ComponentRegistry;

public function boot(): void
{
	$this->container->get(ComponentRegistry::class)->register('card', Card::class);
}
```

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
