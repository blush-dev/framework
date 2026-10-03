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
(`int $year`) only match values of that type. A route that isn't a page,
such as an API or a webhook, can skip the
[`trailingSlash`](configuration.md#routes-and-redirects) redirect with
`exact: true` (`#[Post('/hooks/deploy', exact: true)]`, or
`->exact()` on a `Route`): it answers its path with or without the
slash, and its URLs are made as written. Your routes come after the
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
  an entry in its own folder, `trip/index.md`, moves the folder), `duplicate($id, $slug,
  $changes)` (a copy beside it under the first free name from `$slug`,
  dated today if it's dated, an entry's own folder copied whole),
  and `delete()` (the file moves to `storage/trash/`) work the same way.
  `trashed()` lists the trash, `loadTrashed($id)` reads one,
  `restore($id, $changes)` brings an entry back after making the changes
  (such as `new EntryChanges(set: ['status' => 'draft'])`), and
  `purge($id)` deletes one for good.
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
`$permissions->can($account, 'shop.orders')`.

What an account may do to entries is per content type. Check a
`Blush\Auth\ContentAction` on an entry, which also applies ownership
(see [Accounts and roles](accounts.md#profiles)), on a type by name, or,
with neither, on any type:

```php
use Blush\Auth\ContentAction;

$permissions->can($account, ContentAction::Edit, $entry);
$permissions->can($account, ContentAction::Create, 'recipe');
```

To list the entries an account may act on, let
`restrict()` narrow a query instead of checking each entry. The same
rules then run in the index, so paging stays quick on large sites:

```php
$page = $permissions->restrict($account, ContentAction::Edit, $content->query()->any())
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

Extensions are what a site installs. There are three kinds:

- **Plugins** package the same kind of code as your site's, for reuse
  across sites: a manifest plus a service provider. Below.
- **[Themes](themes.md)** control how the site looks.
- **[Icon packs](#icon-packs)** are SVG icons, with no code.

Every extension's manifest has the same three keys:

- **`name`:** the key it's known by, `vendor/name` (`acme/hello`), in
  lowercase letters, digits, `-`, `_`, and `.`. For a Composer package,
  it's the package's name.
- **`label`:** its title, as people read it.
- **`namespace`:** what its components, icons, and translations go by
  (`hello`, for `hello/tabs`). Lowercase letters, digits, `-`, and `_`.
  `blush`, `app`, `theme`, and `default` are reserved.

No two installed extensions may share a namespace. Two plugins that do
are an error. Two themes, or two icon packs, that do are both broken.
Across kinds, installed plugins (even ones turned off) come first, then
themes, then icon packs, and the one that comes later is listed as broken
on the admin's screens, naming who has the namespace.

Each extension lives in a folder of its own under `user/`
(`user/plugins/`, `user/themes/`, `user/icons/`); the folder's name is
only where it lives. Each can be its own git repository. If `user/` is
one too, ignore `plugins/`, `themes/`, and `icons/` there (see
[the site layout](README.md#how-a-blush-site-is-laid-out)).

The admin lists them under **Extensions** (Themes, Plugins, and Icon
Packs), and installs them from a `.zip` (see
[Installing from a zip](#installing-from-a-zip)).

## Installing from a zip

**Install Theme**, **Install Plugin**, and **Install Icon Pack** take a
`.zip` of an extension's folder, up to 25 MB (or less, if PHP's upload
limit is lower). The manifest (`plugin.json`, `theme.json`, or
`icons.json`) can be at the zip's root or inside one folder, as GitHub's
release zips have it. It's unpacked into the kind's folder in `user/`,
named for the second half of its name (`acme/hello` goes in
`user/plugins/hello`). Nothing is turned on: a plugin or icon pack
arrives off, and a theme inactive.

Blush checks the zip before anything is written, and installs nothing
when:

- it holds another kind of extension (the message names the screen it
  belongs on), or none;
- a file in it would land outside its folder, or is a symbolic link, or
  it holds more than 5,000 files or 100 MB unpacked;
- its manifest doesn't pass, or its namespace is reserved or another
  installed extension's;
- Composer installed an extension with its name (Composer updates it);
- its `composer.json` requires packages besides PHP, its extensions, and
  Blush, since an extension installed from a zip has no `vendor/`
  folder of its own;
- it's a plugin or theme with a PHP file that doesn't parse (checked
  without running it).

A zip of an extension that's already installed offers to replace it,
naming both versions. Replacing swaps the folder and changes nothing
else: an active theme stays active, and a plugin that's on stays on.
The old folder is kept in `storage/backups/{kind}/{folder}`, one per
extension: the next replace overwrites it, and deleting the extension
deletes it. A folder that's a git checkout isn't replaced; update it
with git.

The extension's details screen shows the version that was kept, with
**Roll back to {version}**, which asks first and then swaps it in. The
version it replaces is kept in its place, so the message's **Undo**, or
rolling back again, switches back. An earlier version that wouldn't
run on the site now isn't rolled back to: a plugin whose requirements
aren't met, or a theme in use whose parent theme isn't installed.
**Discard** removes the kept version. Rolling back needs the kind's
`extensions.{kind}.update` capability, and discarding its
`extensions.{kind}.delete`.

Installing needs the kind's `extensions.{kind}.install` capability, and
replacing its `extensions.{kind}.update` (see
[Capabilities](accounts.md#capabilities)). The server's PHP needs the
`zip` extension, and the web server must be able to write to the kind's
folder; the Install modal says so when it can't.

## Turning extensions on

Nothing in `user/` is on just because it's there. A plugin in
`user/plugins` or an icon pack in `user/icons` is off until it's named,
either by the admin (its switch on **Config → Plugins** or **Config →
Icon Packs**) or in config:

```php
// config/plugins.php
return new Blush\Plugin\PluginConfig(enabled: ['acme/hello']);

// config/icons.php
return new Blush\Icon\IconConfig(enabled: ['acme/brands']);
```

Config only ever lists what's on; there's no list of what's off.

A plugin or icon pack installed with Composer is on by default:
installing it is the decision to use it.

Once the admin has turned something on or off, it saves its own list in
`user/data/settings.json`, used in place of the config file's. That list
names everything that's on, Composer's included, so you can turn a
Composer plugin off there. It starts from what was already on, so the
first switch changes only that one. From then on, anything the list
doesn't name is off, including a plugin Composer installs later: turn
it on in the admin. **Use `config/plugins.php`'s list** (or
`config/icons.php`'s) on the screen goes back to the defaults.
Themes work as they always have: one is active, set in
`config/theme.php` or on **Config → Themes**.

## Plugins

**A local plugin** lives in `user/plugins/{folder}/`, with a
`plugin.json` (or `plugin.yaml`):

```json
{
	"name": "acme/hello",
	"label": "Hello",
	"namespace": "hello",
	"version": "1.0.0",
	"description": "Says hello.",
	"provider": "Acme\\Hello\\HelloServiceProvider",
	"autoload": {
		"psr-4": { "Acme\\Hello\\": "src/" }
	},
	"requires": { "blush": "^2.0" },
	"authors": [{ "name": "Jane Doe", "homepage": "https://example.com" }],
	"license": "MIT"
}
```

`name`, `label`, `namespace`, and `provider` are required. Blush finds
the plugin and loads its classes; no Composer step needed. It's off
until you turn it on, in **Config → Plugins** or by naming it in
`config/plugins.php`'s `enabled` list (see
[Turning extensions on](#turning-extensions-on)). `authors`
(each with a `name`, and optionally an `email`, `homepage`, and `role`,
as in `composer.json`) and `license` are shown in the admin; leave them
out and the `composer.json` beside `plugin.json` is used, if there is
one.

For autocomplete in your editor, add a `$schema` key pointing at the
schema Blush ships (the path is relative to `plugin.json`):

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/plugin.schema.json",
	"name": "acme/hello"
}
```

In `plugin.yaml`, use a first-line comment instead:
`# yaml-language-server: $schema=../../../vendor/blush-dev/framework/resources/schemas/plugin.schema.json`.

**A Composer plugin** is a package of type `blush-plugin`. Its name is
the package's, and the rest of the manifest goes in its `composer.json`
under `extra.blush`:

```json
{
	"name": "acme/hello",
	"type": "blush-plugin",
	"extra": {
		"blush": {
			"label": "Hello",
			"namespace": "hello",
			"provider": "Acme\\Hello\\HelloServiceProvider",
			"requires": { "blush": "^2.0" }
		}
	}
}
```

Every installed plugin is on. Turn one off on the admin's
[Plugins](admin.md#plugins) screen, or by its name in
[`config/plugins.php`](configuration.md#plugins-and-middleware).

### Requirements

`requires` maps what a plugin needs to a Composer-style version
constraint (`^2.0`, `~1.2`, `>=8.4`, `1.*`, `^1.0 || ^2.0`):

- `blush`: the Blush version.
- `php`: the PHP version.
- `ext-{name}`: a PHP extension that must be loaded (`"ext-intl": "*"`).
- Another plugin, by its name: `"acme/shop": "^2.0"` needs Shop
  installed at a version that fits, and turned on.

A plugin whose requirements aren't met doesn't run, even when it's on,
and the Plugins screen says why. Anything else in `requires` can't be
checked, so it isn't met. Turning a plugin off also stops every plugin
that requires it, and a plugin's requirements are loaded before it.

### Components from a plugin

A plugin's [components](components.md) use its namespace: `acme/hello`,
with the namespace `hello`, registers `hello/tabs`, not `tabs`. Their
text (labels, descriptions) goes in the plugin's `lang/en.json`, under
`components.tabs`. A plugin's component draws itself with its
`render()`, usually a template file in the plugin returned by
`$this->view(__DIR__ . '/../views/tabs.php')` (see
[Rendering itself](components.md#rendering-itself)); a theme or your
site restyles it with `views/components/hello-tabs.php`.

### Icons from a plugin

A plugin's icons use its namespace too. Add its folder of SVG files in
the provider's `boot()`:

```php
use Blush\Icon\IconRegistry;

public function boot(): void
{
	$this->container->get(IconRegistry::class)->add('hello', __DIR__ . '/../icons');
}
```

Each `icons/{name}.svg` is then `hello/{name}`, with its label in the
plugin's `lang/en.json` under `icons.{name}.label`. Icons that need no
code are simpler as an [icon pack](#icon-packs).

### Content types from a plugin

A plugin can define [content types](content-types.md), much like a
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

Tag it in the plugin's provider:

```php
protected const array TAGS = [
	ContentTypeSource::TAG => [ContentTypes::class]
];
```

The kinds and options are the same as in `config/content.php`. A site can
still redefine one of your types in its `config/content.php`, but not in
`user/data/types/`. Two plugins can't define the same type.

### Field sets from a plugin

A plugin can add fields to content types, its own or the site's,
and to media files' [details](media.md#details-about-a-file), with
[field sets](content-types.md#field-sets) (`media:image` and so on). Write a class that
implements `FieldSetSource`:

```php
<?php

declare(strict_types=1);

namespace Acme\Seo;

use Blush\Field\FieldSet;
use Blush\Field\FieldSetSource;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\TextField;

final class SeoFields implements FieldSetSource
{
	public function fieldSets(): iterable
	{
		yield new FieldSet(
			'seo',
			[new TextField('meta_title'), new BoolField('noindex')],
			['type:post', 'type:page'],
			'SEO'
		);
	}
}
```

Tag it with `FieldSetSource::TAG` in the plugin's provider. A site's
`config/fields.php` or `user/data/fields/` can replace any of your sets
by its name. Two plugins can't define the same set.

A set aimed at a Settings screen (`settings:general`, and so on) adds
settings the site owner fills in ([Your own settings](themes.md#your-own-settings)).
Read them in PHP from `Blush\Settings\SiteSettings`, which the
container gives you: `$site->get('tagline')`, or `$site->all()`.

### Field types from a plugin

A plugin can add a field type for every place fields are defined:
content types, media details, theme settings, and menu fields. Extend
`Blush\Field\Field`, and describe the type for the admin with
`typeLabel()`, `typeDescription()`, and `controls()`, the controls it can
be edited with (the first is its default; one with no controls is shown
read-only):

```php
<?php

declare(strict_types=1);

namespace Acme\Colors;

use Override;
use Blush\Field\Control;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;

final class ColorField extends Field
{
	public function __construct(string $name = '')
	{
		$this->name = $name;
	}

	#[Override]
	public function type(): string
	{
		return 'color';
	}

	#[Override]
	public static function typeLabel(): string
	{
		return 'Color';
	}

	#[Override]
	public static function controls(): array
	{
		return [Control::Mono];
	}

	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (! is_string($value) || preg_match('/^#[0-9a-f]{6}$/i', $value) !== 1) {
			throw $this->invalid('must be a hex color, such as #ff6600.');
		}

		return strtolower($value);
	}

	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(new static($definition->string('name')), $definition);
	}
}
```

Register it in the plugin's provider's `boot()`:

```php
use Blush\Field\FieldRegistry;

public function boot(): void
{
	$this->container->get(FieldRegistry::class)->register('color', Acme\Colors\ColorField::class);
}
```

Then `type: color` works in `user/data/types`, `config/content.php`, and
the rest, and the admin's field editor offers it. The admin has a fixed
set of controls (`Blush\Field\Control`), so a field type picks from
those; it can't bring its own. Options of its own, described by
`definitionSchema()`, are offered in the field editor when they're
text, numbers, true or false, a set of values, or a list of text.

### Reading more from media files

Blush reads XMP, IPTC, and EXIF from images, and MP3, MP4, Ogg, WAV,
and WebM files
([What a file says about itself](media.md#what-a-file-says-about-itself)).
To read another format, implement `Blush\Media\Embedded\EmbeddedReader`
(`read(string $path, string $mime): EmbeddedMetadata`, returning empty
metadata for a file it can't read) and register it:

```php
$this->container->resolving(EmbeddedReaderRegistry::class, static function (EmbeddedReaderRegistry $registry): void {
	$registry->register('heic', HeicReader::class);
});
```

Readers' values are merged in the registry's order, the first value for
a key winning, so yours fills in what the built-in readers leave out.
Run `bin/blush media:index` afterwards: the index reads every file again
when its readers change.

## Icon packs

An icon pack is a set of SVG icons in a namespace of its own, with no
code. Put it in `user/icons/{folder}/`, with an `icons.json` (or
`icons.yaml`):

```json
{
	"name": "acme/brands",
	"label": "Brand Logos",
	"namespace": "brands",
	"version": "1.0.0",
	"description": "Logos for social links.",
	"folder": "svg",
	"authors": [{ "name": "Jane Doe" }]
}
```

`name`, `label`, and `namespace` are required. Like a local plugin, it's
off until it's turned on, in **Config → Icon Packs** or in
`config/icons.php`'s `enabled` list. `authors` works as a
plugin's does. Each `{icon}.svg` in the
pack's `folder` (the pack's own folder, without one) is
`{namespace}/{icon}`: `svg/github.svg` is `brands/github`, used as
`:icon[GitHub]{name=brands/github}` or `$template->icon('brands/github')`.
Labels go in the pack's `lang/en.json`, under `icons.{icon}.label`:

```json
{
	"icons": {
		"github": { "label": "GitHub" }
	}
}
```

Every installed pack is on, and its icons appear in `bin/blush
icon:list` and the admin's icon inserter. Turn one off on the admin's
[Icon Packs](admin.md#icon-packs) screen, or by its name in
[`config/icons.php`](configuration.md#plugins-and-middleware); its icons
then show nowhere. A theme can restyle one with
`icons/brands/github.svg`, and your site with
`resources/icons/brands/github.svg` (see [Icons](components.md#icons)).

For autocomplete, point `$schema` at
`vendor/blush-dev/framework/resources/schemas/icons.schema.json`.

**A Composer icon pack** is a package of type `blush-icons`, with its
`icons.json` in the package. Its name is the package's, so the manifest
can leave `name` out; one naming something else is broken.

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

