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

## Embed providers

A provider that needs code, such as one that rewrites its frame's URL,
is a class that extends `Blush\Embed\EmbedProvider`. Register it in
your provider's `boot()`:

```php
use Blush\Embed\ProviderRegistry;

public function boot(): void
{
	$this->container->get(ProviderRegistry::class)->register('peertube', App\Embed\PeerTube::class);
}
```

Its constructor passes the name, label, URL schemes, and oEmbed address
to the parent's; override `frame()` to change the URL that's framed. A
plain provider needs no code: list it in
[`config/embed.php`](configuration.md#embeds).

## Menu links and region items

A new kind of [menu](menus.md) link is a class that extends
`Blush\Menu\Link\MenuLink`. Its `resolve()` returns a `LinkTarget` (a URL
and the label it brings) or throws `UnresolvedLink` to leave the item out;
`validate()` checks the value's shape. Register it under the item key it
answers to:

```php
use Blush\Menu\Link\MenuLinkRegistry;

public function boot(): void
{
	$this->container->get(MenuLinkRegistry::class)->register('product', App\Menu\ProductLink::class);
}
```

Then a menu item can say `product: blue-mug`. A new kind of region item
extends `Blush\Region\Item\RegionItem`, whose `render()` returns HTML, and
is registered with `Blush\Region\Item\RegionItemRegistry` the same way.
Both are built through the container, so their constructors can ask for
services.

## Changing content from code

`Blush\Content\Writer\ContentWriter` creates and edits entries, the
same way `content:new` and the admin do. Ask for it in a constructor:

```php
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\EntryChanges;

$entry = $writer->load('_posts/2026-09-29.hello.md');

$writer->update($entry->id, new EntryChanges(
	set: ['status' => 'draft', 'tags' => ['news']],
	remove: ['summary']
), $entry->revision);
```

- **Only what you change changes.** Other front matter keeps its order,
  comments, and spacing, and a key the file writes by an older name
  (`date` for `published`) is updated under that name.
- **Values** are plain data: text, numbers, `true`/`false`, `null`, and
  lists or maps of them. Write dates as text, such as
  `2026-09-29 09:00:00 -05:00`.
- **`$entry->revision`** protects against lost edits: if the file changed
  since you loaded it, `update()` throws `WriteConflict` and writes
  nothing. Leave it out to skip the check.
- `create()`, `rename()` (a new slug; a dated file keeps its date, and a
  bundle's folder moves with its media), and `delete()` (the file moves
  to `storage/trash/`) work the same way.
- Every change reindexes content and refreshes cached pages.

The writer only writes content files inside `user/content`, and refuses
any edit it can't make without changing something else (it throws
`WriteException`, and the file is left as it was).

## Admin actions

An action is a button on [the admin's](admin.md) dashboard, written in
PHP; the admin draws it, so you don't write any JavaScript. Extend
`Blush\Admin\Action\AdminAction`:

```php
namespace App\Admin;

use App\Shop\Orders;
use Blush\Admin\Action\ActionResult;
use Blush\Admin\Action\AdminAction;

final class SyncOrders extends AdminAction
{
	public function __construct(private readonly Orders $orders)
	{}

	public function label(): string
	{
		return 'Sync orders';
	}

	public function description(): string
	{
		return 'Fetch new orders from the shop.';
	}

	public function capability(): string
	{
		return 'shop.orders';
	}

	public function confirm(): ?string
	{
		return 'Fetch orders now?';
	}

	public function run(): ActionResult
	{
		return ActionResult::success(sprintf('Fetched %d orders.', $this->orders->sync()));
	}
}
```

Register it by name from a provider's `boot()`. The name is its URL, so
use lowercase letters, digits, and hyphens:

```php
use Blush\Admin\Action\AdminActionRegistry;

$this->container->get(AdminActionRegistry::class)->register('sync-orders', App\Admin\SyncOrders::class);
```

Only accounts with the action's capability see it. `confirm()` is
optional; without it, the action runs straight away. Keep `run()` short
enough to finish within a request.

## Capabilities and signed-in routes

Add a [capability](accounts.md#capabilities) for your own feature from a
provider's `boot()`. Administrators get it automatically; give it to
other roles in `config/auth.php`.

```php
use Blush\Auth\Capabilities;

public function boot(): void
{
	$this->container->get(Capabilities::class)->register('shop.orders', 'Manage orders');
}
```

To check it, ask `Blush\Auth\Permissions`:
`$permissions->can($account, 'shop.orders')`. Pass an entry as a third
argument to check that entry, which also applies ownership (see
[Accounts and roles](accounts.md#authors)).

To list the entries an account may use a capability on, let
`restrict()` narrow a query instead of checking each entry. The same
rules then run in the index, so paging stays quick on large sites:

```php
$page = $permissions->restrict($account, 'content.edit', $content->query()->any())
	->orderBy('updated', Order::Desc)
	->paginate(20, $number);
```

A route that needs a signed-in account runs three middleware, in this
order: `Blush\Session\StartSession`, `Blush\Auth\Middleware\VerifyCsrf`,
and `Blush\Auth\Middleware\Authenticate`. The controller then reads the
account from the request's `Blush\Auth\Account::class` attribute:

```php
Route::group('/shop', [
	Route::get('/orders', App\Http\Orders::class)
], middleware: [StartSession::class, VerifyCsrf::class, Authenticate::class]);
```

Requests that change things (anything but `GET`) must send the session's
CSRF token in an `X-CSRF-Token` header. The admin API's `session` answer
includes it.

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

For autocomplete in your editor, add a `$schema` key pointing at the
schema Blush ships (the path is relative to `extension.json`):

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/extension.schema.json",
	"name": "acme/hello"
}
```

In `extension.yaml`, use a first-line comment instead:
`# yaml-language-server: $schema=../../../vendor/blush-dev/framework/resources/schemas/extension.schema.json`.

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

### Icons from an extension

An extension's icons use its vendor as their namespace. Add its folder
of SVG files in the provider's `boot()`:

```php
use Blush\Icon\IconRegistry;

public function boot(): void
{
	$this->container->get(IconRegistry::class)->add('acme', __DIR__ . '/../icons');
}
```

Each `icons/{name}.svg` is then `acme/{name}`, with its label in the
extension's `lang/en.json` under `icons.{name}.label`.

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

