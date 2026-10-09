# Extending Blush

Most sites never need PHP beyond their config files. When yours does, this
page shows where your code goes. It assumes you know PHP.

Code belongs in a [plugin](#plugins) wherever it can: a plugin can be
turned on and off, versioned, and reused on another site. The examples
on this page use a provider and classes under `App\` for short, but
each works the same from a plugin's provider.

## Your site's code

For code that's only ever this site's, there's an escape hatch: put
your classes in `src/`, under the `App\` namespace (Composer autoloads
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

Blush finds nothing in your site by file: no templates, icons, or
classes are picked up from folders, only what your provider registers.
Templates and icons belong to [themes](themes.md) and icon packs, and
directives, content types, and relations to plugins.

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

### Every URL on the site

`Blush\Routing\SiteUrls` lists every page Blush knows about: entries,
listings and their terms, date archives, relation archives and profiles,
feeds, sitemaps, `robots.txt`, `llms.txt` and the Markdown pages, and
redirects. It's for code that needs to visit every page, such as a
static site exporter, a cache warmer, or a link checker. Blush doesn't
visit them itself. Render each one with the kernel:

```php
$origin = $container->make(AppConfig::class)->origin();
$kernel = $container->make(Kernel::class);

foreach ($container->make(SiteUrls::class)->all() as $url) {
	$response = $kernel->handle(Request::create($origin . $url->path));
}
```

A listing's later pages aren't in the list. Ask `$url->page(2)`, then
3, and so on, until you get `null` or a page that isn't a 200.

If your routes serve pages, list them too. Write a class that
implements `Blush\Routing\UrlSource` and returns a `SiteUrl` for each
path, then tag it in your provider:

```php
protected const array TAGS = [
	UrlSource::TAG => [App\Http\HelloUrls::class]
];
```

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

## Finding entries

`Blush\Content\ContentRepository` finds entries. Ask for it in a
constructor (a command's, a directive's, a component's), and build a
query from it:

```php
$recent = $this->content->query()
	->type('post')                // a type's name, or the type itself
	->whereTerm('category', 'news')
	->limit(5)
	->get();                      // or ->first(), ->count(), ->paginate(10, $page)
```

A query finds published, listed entries in the site's language, newest
first, unless it says otherwise (`orderBy()`, `status()`, `language()`).
A few things to know:

- **`search('grid')`** matches entries whose title or file path contains
  the text, in any case. It doesn't search what an entry says.
- **`termCounts('category')`** gives how many entries name each term, by
  slug. A parent term counts only the entries that name it, not those
  filed under its children.
- **`neighbors($entry)`** gives the entries just `before` and `after`
  one in its type's listing, in that listing's order, so for a
  collection (newest first) `before` is newer and `after` older. Either
  is `null` at an end. Give a query to walk another listing:
  `neighbors($entry, $this->content->query()->type('post')->orderBy('title'))`.
- **`whereParent($entry)`** finds the entries under a page or a term,
  by the entry or its key (`whereParent('about')`), as a query you can
  sort, page, and count; **`whereParent(null)`** finds the top level: a
  tree's top pages, or a nesting collection's top entries (such as top
  categories).
- **`parent($entry)`** and **`children($entry)`** give a page's or a
  nesting collection entry's parent and children, whatever their status, so filter
  with `isPublished()` where you show them.

An entry can tell you what it is:

| Check | True when | For example |
|---|---|---|
| `isPublished()` | Its status is published and its date has come | Not a draft, a post scheduled for tomorrow, or one in the trash |
| `isRoutable()` | It has a page of its own: it isn't hidden. Status doesn't count | A draft is routable; its page is served once it's published |
| `isListed()` | It's in collections, feeds, and sitemaps: published, public, and not a landing page | An unlisted page is routable but not listed |

To link to an entry you found some other way than a query (a parent, a
child, a term), check `isPublished() && isRoutable()`.

## Directives and components

Directives and components come from extensions, in the extension's
namespace, never from the site itself:

- **[Directives](directives.md)** are what your content says, such as
  `::acme/pricing`. They come from Blush and [plugins](#directives-and-components-from-a-plugin),
  so they work with every theme, and content that uses them never
  breaks when you switch.
- **[Components](components.md)** are pieces for templates, such as
  `notebook/post-header`. They come from themes and plugins.

To change how a theme draws either one, make a
[child theme](themes.md#overriding-templates).

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

$writer->update($entry->path, new EntryChanges(
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
- Entries are named by their path in `user/content`. `create()`,
  `rename()` (a new slug; a dated file keeps its date, and a
  an entry in its own folder, `trip/index.md`, moves the folder), `duplicate($path, $slug,
  $changes)` (a copy beside it under the first free name from `$slug`,
  dated today if it's dated, an entry's own folder copied whole),
  `trash($path)` (sets `status: trash` and `trashed`, leaving the file
  where it is), `restore($path)` (back to `status: draft`, without
  `trashed`), and `delete()` (removes the file for good) work the same
  way.
- **Ids** (see [Ids](content.md#ids)): every entry the writer creates,
  a copy included, gets a new `id`, last in its front matter, and an
  `update()` adds one to a file that has none. Changes never set or
  remove `id`; `assignIds($paths)` gives files new ones.
- Every change reindexes content and refreshes cached pages.

The writer only writes content files inside `user/content`, and refuses
any edit it can't make without changing something else (it throws
`WriteException`, and the file is left as it was).

## Storing your own data

A plugin that keeps its own records, such as albums or sign-ups, keeps
them in a **table**. Tables work the same whatever stores the site's
data, so your code keeps working if a site moves to a database later.

Describe the table, and register it when Blush starts, in your
provider's `register()`:

```php
use Blush\Storage\Record\Table;
use Blush\Storage\Record\TableRegistry;
use Blush\Storage\StorageArea;

public static function albums(): Table
{
	return new Table('gallery/albums', StorageArea::Data, key: 'slug');
}

public function register(): void
{
	$this->container->resolving(TableRegistry::class, static function (object $tables): void {
		$tables->register(self::albums());
	});
}
```

- **The name** is lowercase letters, digits, `_`, and `-`, with `/` to
  group a plugin's tables (`gallery/albums`).
- **The area** is `Data` for site data, `Accounts` for data about
  people, or `Content`.
- **The key** is optional: a value each record has, unique in the table,
  such as a slug or a name, that you can find records by. Key values are
  letters, digits, `.`, `_`, and `-`, starting with a letter or digit.

Then ask for `Blush\Storage\Record\RecordStores` in a constructor:

```php
use Blush\Storage\Record\Order;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordQuery;

$table = Gallery::albums();
$store = $stores->store($table);

// Add one. Every record has an id; create() makes a new one.
$store->save($table, Record::create($clock->now(), ['slug' => 'summer', 'title' => 'Summer', 'year' => 2026]));

// Find one by id or by key.
$album = $store->findByKey($table, 'summer');

// Change it: save it again with new fields. Passing the version you
// read refuses the save if someone changed the record since.
$store->save($table, $album->with('title', 'Summer 2026'), $album->version);

// Query.
$recent = $stores->query($table)
	->where('year', '>=', 2024)
	->whereAny(
		static fn (RecordQuery $q): RecordQuery => $q->where('featured', '=', true),
		static fn (RecordQuery $q): RecordQuery => $q->where('tags', 'contains', 'travel')
	)
	->orderBy('year', Order::Desc)
	->paginate(perPage: 20, page: 1)
	->get();

foreach ($recent as $album) {
	echo $album->value('title');
}

$store->delete($table, $album->id);
```

- **A record** is an `id` (a UUID), its **fields** (text, numbers,
  `true`/`false`, `null`, and lists or maps of them; never `id` or
  `content`), and optional **content**, such as Markdown.
  `value('seo.title')` reaches into nested fields.
- **Versions:** every record you read has a `version`, which changes
  when it's saved. Pass it to `save()` or `delete()`, and the write is
  refused with `RecordConflict` if the record changed or was deleted
  since; leave it out to write regardless. `save()` returns the record
  as stored, with its new version.
- **Conditions:** `=`, `!=`, `<`, `<=`, `>`, `>=`, `between` (two
  values, inclusive), `in` and `not in` (a list), `like` (`%` for any
  run of characters, `_` for one, without regard to case), `contains`
  (a list field holds it), `intersects` (a list field holds any of a
  list), `null`, and `not null`. A missing field is `null`.
- **Subqueries:** `in` and `not in` also take the values one key has in
  the records another query finds, in the same table or another of the
  same area:
  `->where('id', 'not in', new Subquery(new RecordQuery()->where('status', '=', 'hidden'), 'album_id', $photos))`.
- **Comparisons are strict:** the number `2020` and the text `"2020"`
  aren't equal, and `<` compares numbers with numbers and text with text.
  Text matches with `=` are case-sensitive.
- **Order:** `orderBy()` takes several keys; text sorts without regard
  to case, and `null` sorts last either way. Without an order, records
  come in the order they were added.
- **Counting:** `count()`, `countBy('tags')` (how many records have each
  value), and `min()`, `max()`, `sum()`, and `avg()` of a key. The limit
  and page don't apply to them.
- **Transactions:** `$store->transaction(fn () => …)` runs several
  writes so none from elsewhere interleave; if it throws, each write is
  put back.

**Refs** join one record to others through a named relation, in order:
an album's photos, say. Ask for `Blush\Storage\Record\Refs`:

```php
$refs->set($albums, $album->id, 'photos', [$first->id, $second->id]);

// Albums with a given photo, or the photos an album has.
$stores->query($albums)->whereRelated('photos', [$photo->id])->get();
$stores->query($photos)->whereRelated('photos', [$album->id], inverse: true)->get();

// Load each album's photo ids with the albums.
$result = $stores->query($albums)->with('photos')->get();
$result->refs($album->id, 'photos'); // [$first->id, $second->id]
```

`set()` replaces the record's refs for that relation; an empty list
removes them. Refs live in their area's `refs` table, so they work
between any of the area's tables.

On a flat-file site, a table is a folder of JSON files, one a record,
named by its key (or its id): `gallery/albums` is
`user/data/gallery/albums/summer.json`. Don't edit those files from
code; go through the store.

For tests, `Blush\Storage\Record\ArrayRecordStore` keeps records in
memory and answers queries the same way.

## Admin actions

An action is a button on [the admin's](admin.md#tools) Tools screen,
written in PHP, grouped there under your plugin (by its autoload prefix
or its provider's namespace) or under the site for classes in `App\`; the admin draws it, so you don't write any JavaScript. Extend
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
enough to finish within a request. For work that may take longer, have
the action queue a [job](#background-jobs) instead: return the job's key
from `job()` and leave out `run()`. The admin queues it, runs it while
the person watches, with its progress, and shows how it ended:

```php
public function job(): ?string
{
	return 'acme/sync-orders';
}
```

## Background jobs

A job is work done outside the request that asked for it: an import, a
sync with another service, anything that might outlast a page load.
Jobs are queued and run by [cron, a worker, page visits, or the
admin](going-live.md#background-jobs-and-cron). Extend
`Blush\Job\Job`:

```php
namespace Acme\Shop;

use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;

final class SyncOrders extends Job
{
	public function __construct(private readonly Orders $orders)
	{}

	public function label(): string
	{
		return 'Sync orders';
	}

	public function handle(JobRecord $job): JobResult
	{
		return JobResult::done(sprintf('Fetched %d orders.', $this->orders->sync()));
	}
}
```

Register it under a `vendor/name` key from a provider's `boot()`, and
queue it from anywhere with the queue:

```php
use Blush\Job\JobQueue;
use Blush\Job\JobRegistry;

$this->container->get(JobRegistry::class)->register('acme/sync-orders', Acme\Shop\SyncOrders::class);

$queue->push('acme/sync-orders', ['since' => '2026-10-01'], account: $account->username);
```

- **Data** is what the job works on: ids and plain values (strings,
  numbers, booleans, and arrays of them), never objects, since it's
  saved until a runner picks it up. Read it from `$job->data`.
- **`unique:`** keeps a second copy out while one waits or runs, such
  as `unique: 'sync-orders'`. Pushing again returns the one already
  there. **`delay:`** holds a job back that many seconds.
- **`account:`** records who asked. Check their capability before you
  push; the job runs as the site. A person can follow a job they queued
  in the admin.

**Long work, a chunk at a time.** On shared hosting, a request may get
only 30 seconds, so a job does what it can and hands back where it got
to with `JobResult::more()`, and it's queued again to carry on:

```php
public function handle(JobRecord $job): JobResult
{
	$offset = (int) ($job->data['offset'] ?? 0);
	$total  = $this->orders->count();

	$this->orders->syncBatch($offset, 50);

	return $offset + 50 >= $total
		? JobResult::done("Synced {$total} orders.")
		: JobResult::more(['offset' => $offset + 50], intdiv(($offset + 50) * 100, $total), 'Syncing…');
}
```

The second argument to `more()` is how far along it is (0 to 100), which
the admin shows as a progress bar.

**When it fails.** Throw when trying again might work (a service is
down): the job is tried again after a minute, then five, and failed
after three attempts. Override `attempts()` and `backoff()` to change
that. Return `JobResult::failed('Why.')` when trying again won't help;
it's failed at once. Failed jobs can be retried from the admin's Tools
screen or with `jobs:retry`.

### Scheduled tasks

A scheduled task is a job queued on a timetable. Schedule a job you've
registered from your provider's `boot()`:

```php
use Blush\Job\Frequency;
use Blush\Job\Schedule;

$this->container->get(Schedule::class)->add('acme/sync-orders', Frequency::hourly());
```

`Frequency` has `everyMinute()`, `everyMinutes(15)`, `hourly(30)` (at
half past), `daily('03:00')`, `weekly(1, '06:00')` (Mondays; 0 is
Sunday), and `cron('0 9 1 * *')` for any cron expression, in the site's
time zone. A run missed while nothing came by (no cron, a quiet site)
happens once, when a runner next comes by. `add()` again replaces a
job's place on the schedule, and `remove()` takes it off, Blush's own
tasks included (`blush/go-live`, `blush/prune-cache`,
`blush/prune-sessions`, `blush/prune-jobs`).

### Testing code that queues jobs

To check what your code queued without running it, bind
`Blush\Job\Testing\RecordingQueue` in place of the queue. It refuses
what the real queue refuses (unregistered jobs, data that isn't plain
values) and keeps `unique` keys the same way:

```php
use Blush\Job\JobQueue;
use Blush\Job\JobRegistry;
use Blush\Job\Testing\RecordingQueue;
use Psr\Clock\ClockInterface;

$queue = new RecordingQueue($container->get(JobRegistry::class), $container->get(ClockInterface::class));
$container->instance(JobQueue::class, $queue);

// … run your code …

$this->assertCount(1, $queue->pushed('acme/sync-orders'));
```

To run jobs as they're queued instead, use the `sync` runner in
`config/jobs.php` (see [Configuration](configuration.md#background-jobs)).

## Capabilities and signed-in routes

Add a [capability](accounts.md#capabilities) for your own feature from a
provider's `boot()`. Owners get it automatically; give it to other
roles, the Administrator included, in the admin or `config/auth.php`.

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

- **Plugins** are where a site's code belongs, so it can be turned on,
  off, and reused across sites: a manifest plus, usually, a service provider. Below.
- **[Themes](themes.md)** control how the site looks.
- **[Icon packs](#icon-packs)** are SVG icons, with no code.

Every extension's manifest has the same three keys:

- **`name`:** the key it's known by, `vendor/name` (`acme/hello`), in
  lowercase letters, digits, `-`, `_`, and `.`. For a Composer package,
  it's the package's name.
- **`label`:** its title, as people read it. Optional: leave it out and
  the extension is shown by its `name`.
- **`namespace`:** what its directives, components, icons, and translations go by
  (`hello`, for `hello/tabs`). Lowercase letters, digits, `-`, and `_`.
  `blush`, `theme`, and `default` are reserved. Optional, and
  best left out: without one, it's the name with a hyphen for the `/`
  (and for any `.`), so `acme/hello` goes by `acme-hello`
  (`acme-hello/tabs`). Since names are unique, so is that namespace.
  `plugin:new` and `theme:new` write that form too. Give one only when
  you want something shorter.

No two installed extensions may share a namespace. Two plugins that do
are an error. Two themes, or two icon packs, that do are both broken.
Across kinds, installed plugins (even ones turned off) come first, then
themes, then icon packs, and the one that comes later is listed as broken
on the admin's screens, naming who has the namespace.

Every extension you add yourself, of every kind, lives in your site's
`extensions/` folder at its name, the way Composer keeps packages in
`vendor/`: `acme/hello` is `extensions/acme/hello/`. So two extensions
can't share a name on one site, and two vendors' `hello` live side by
side. The folder must match the manifest's `name`. One that doesn't is
listed as broken, saying where it belongs.

What kind an extension is comes from its manifest file, `plugin.json`,
`theme.json`, or `icons.json`, or from the `type` in its
`composer.json`: `blush-plugin`, `blush-theme`, or `blush-icons`, as
Composer has it. Either is enough, and a folder may have both, as long
as they name the same kind. A folder holds one kind, so one that names
two (two manifests, or a manifest and another kind's `type`) is broken.
Each extension can be its own git repository.

Manifests use Composer's names and shapes for the keys they share with
`composer.json`: `name`, `description`, `version`, `license`,
`authors`, `autoload`, `require`, `conflict`, `replace`, `provide`, `homepage`,
`support`, `funding`, `abandoned`, `suggest`, and `keywords`. A manifest that leaves one of
those out takes it from the `composer.json` beside it, so a package says
them once. A manifest's own value replaces `composer.json`'s whole; the
two aren't merged. Blush's own keys (`label`, `namespace`, `provider`,
and a theme's or icon pack's own) go in the manifest, or in
`composer.json` under `extra.blush`, never at its top level. A manifest
may even be empty (`{}`) when its `composer.json` names it.

So an extension can do without a manifest file entirely, as a Composer
package does: a `composer.json` of a Blush type, with Blush's keys under
`extra.blush`, is its manifest.

```json
{
	"name": "acme/hello",
	"type": "blush-plugin",
	"version": "1.0.0",
	"autoload": { "psr-4": { "Acme\\Hello\\": "src/" } },
	"extra": {
		"blush": {
			"label": "Hello",
			"provider": "Acme\\Hello\\HelloServiceProvider"
		}
	}
}
```

When there's both, each key is read from the first place that has it:
the manifest file, then `extra.blush`, then the top of `composer.json`
(for the keys shared with Composer).

The admin lists them under **Extensions** (Themes, Plugins, and Icon
Packs), and installs them from a `.zip` (see
[Installing from a zip](#installing-from-a-zip)).

## Installing from a zip

**Install Theme**, **Install Plugin**, and **Install Icon Pack** take a
`.zip` of an extension's folder, up to 25 MB (or less, if PHP's upload
limit is lower). The manifest (`plugin.json`, `theme.json`, or
`icons.json`, or a `composer.json` of the kind's `type`) can be at the
zip's root or inside one folder, as GitHub's release zips have it. It's unpacked into `extensions/` at the name in
its manifest (or its `composer.json`): `acme/hello` goes in
`extensions/acme/hello`, whatever the zip or its folder is called.
Nothing is turned on: a plugin or icon pack arrives off, and a theme
inactive.

Blush checks the zip before anything is written, and installs nothing
when:

- it holds another kind of extension (the message names the screen it
  belongs on), more than one kind, or none;
- an extension of another kind has its name;
- a file in it would land outside its folder, or is a symbolic link, or
  it holds more than 5,000 files or 100 MB unpacked;
- its manifest doesn't pass, or its namespace is reserved or another
  installed extension's;
- Composer installed an extension with its name (Composer updates it);
- it's a plugin or theme with a PHP file that doesn't parse (checked
  without running it).

An extension installed from a zip has no `vendor/` folder of its own.
One that [requires](#requirements) a library (`"guzzlehttp/guzzle":
"^7.0"`) is installed anyway, but can't run until the site's Composer
installs that library (`composer require guzzlehttp/guzzle`), and its
details say so.

A zip of an extension that's already installed offers to replace it,
naming both versions. Replacing swaps the folder and changes nothing
else: an active theme stays active, and a plugin that's on stays on.
The old folder is kept in `storage/backups/{vendor}/{name}`, one per
extension: the next replace overwrites it, and deleting the extension
deletes it. A folder that's a git checkout isn't replaced; update it
with git.

The extension's details screen shows the version that was kept, with
**Roll back to {version}**, which asks first and then swaps it in. The
version it replaces is kept in its place, so the message's **Undo**, or
rolling back again, switches back. An earlier version that wouldn't
run on the site now isn't rolled back to: a plugin whose requirements
aren't met, a theme or icon pack in use whose requirements aren't, or a
theme in use whose parent theme isn't installed.
**Discard** removes the kept version. Rolling back needs the kind's
`extensions.{kind}.update` capability, and discarding its
`extensions.{kind}.delete`.

Installing needs the kind's `extensions.{kind}.install` capability, and
replacing its `extensions.{kind}.update` (see
[Capabilities](accounts.md#capabilities)). The server's PHP needs the
`zip` extension, and the web server must be able to write to
`extensions/`; the Install modal says so when it can't.

## Turning extensions on

Nothing in `extensions/` is on just because it's there. A plugin or an
icon pack there is off until it's named,
either by the admin (its switch on **Extend → Plugins** or **Extend →
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
`config/theme.php` or on **Extend → Themes**.

## Plugins

**A local plugin** lives in `extensions/{vendor}/{name}/`, with a
`plugin.json`. `bin/blush plugin:new acme/hello`
starts one for you in `extensions/acme/hello/`: the manifest, an
empty provider in `src/HelloServiceProvider.php` to fill in, and a
`lang/en.json` for its text, with its
[`@@locale` and `@@domain`](themes.md#translations) filled in.

```json
{
	"name": "acme/hello",
	"label": "Hello",
	"namespace": "hello",
	"version": "1.0.0",
	"description": "Says hello.",
	"provider": "Acme\\Hello\\HelloServiceProvider",
	"autoload": {
		"psr-4": { "Acme\\Hello\\": "src/" },
		"files": ["src/helpers.php"]
	},
	"require": { "blush-dev/framework": "^2.0" },
	"authors": [{ "name": "Jane Doe", "homepage": "https://example.com" }],
	"license": "MIT"
}
```

Only `name` (here or in its `composer.json`) is required. Without a
`label`, it's shown by its name, and without a `namespace`, it goes by
its name, hyphenated. Without a `provider`, it registers nothing (see
[A plugin without a provider](#a-plugin-without-a-provider)). Blush finds the plugin and loads it; no
Composer step needed. `autoload` works as Composer's does: `psr-4` maps
namespace prefixes (each ending in `\`) to folders, and `files` lists
files loaded once when the plugin runs, such as helper functions. Every
path must be inside the plugin. It's off until you turn it on, in
**Extend → Plugins** or by naming it in `config/plugins.php`'s
`enabled` list (see [Turning extensions on](#turning-extensions-on)).
`authors` (each with a `name`, and optionally an `email`, `homepage`,
and `role`, as in `composer.json`) and `license` are shown in the
admin, though not email addresses. `license` takes Composer's forms: an
[SPDX identifier](https://spdx.org/licenses/) (`MIT`), a list any of
which applies (`["MIT", "GPL-2.0-or-later"]`), `(MIT or Apache-2.0)`,
`(MIT and Apache-2.0)` when all apply, or `proprietary`. Common open
source licenses link to their text.

`homepage`, `support`, and `funding` say where to learn about, get help
with, and fund it, as in `composer.json`:

```json
{
	"homepage": "https://example.com/hello",
	"support": {
		"docs": "https://example.com/hello/docs",
		"source": "https://github.com/acme/hello",
		"issues": "https://github.com/acme/hello/issues",
		"forum": "https://example.com/forum",
		"chat": "https://example.com/chat",
		"wiki": "https://github.com/acme/hello/wiki",
		"irc": "irc://irc.libera.chat/acme",
		"rss": "https://example.com/hello/feed",
		"security": "https://example.com/hello/security",
		"email": "help@example.com"
	},
	"funding": [
		{ "type": "github", "url": "https://github.com/sponsors/acme" }
	]
}
```

Every address is an `http` or `https` URL, except `irc` (`irc://` or
`ircs://`) and `email` (an email address). The admin shows them on the
extension's details, under **Links** and **Sponsor** (all but `email`). In a manifest, one
that doesn't fit is an error; in `composer.json`, it's left out.

`abandoned` says it's no longer maintained, as in `composer.json`:
`true`, or the name of the package to use instead (any package's, not
only an extension's):

```json
{
	"abandoned": "acme/hello-next"
}
```

It only warns, as Composer does: an abandoned extension still runs, and
can still be turned on, activated, or installed. The admin marks it
**Abandoned** in its list and says so on its details, linking the
replacement when it's installed, and `plugin:check`, `theme:check`, and
`icon-pack:check` warn of it. In a manifest, anything but `true`,
`false`, or a `vendor/name` is an error; in `composer.json`, any other
string counts as `true`.

`suggest` names packages that work well with the extension, each with
why, as in `composer.json`: other plugins, themes, or icon packs by
`vendor/name`, libraries, or PHP extensions as `ext-{name}`:

```json
{
	"suggest": {
		"acme/brands": "For brand icons in the share buttons.",
		"ext-intl": "For dates in your site's language."
	}
}
```

Nothing is checked or installed for it: an extension runs the same with
or without what it suggests. The admin lists them on the extension's
details, under **Suggests**, each with why beneath it, linking one
that's installed and saying whether a PHP extension is loaded, and
installing an extension from a zip lists what it suggests. In a manifest, anything but an object of
names to strings is an error; in `composer.json`, an entry that doesn't
fit is left out.

`keywords` lists words the extension is about, as in `composer.json`:

```json
{
	"keywords": ["gallery", "images", "lightbox"]
}
```

They aren't shown anywhere. The admin's Themes, Plugins, and Icon Packs
screens search them, with an extension's label, name, and description,
so someone filtering for "images" finds it. In a manifest, anything but
a list of strings is an error; in `composer.json`, anything that isn't
a string is left out.

For autocomplete in your editor, add a `$schema` key pointing at the
schema Blush ships (the path is relative to `plugin.json`):

```json
{
	"$schema": "../../../vendor/blush-dev/framework/resources/schemas/plugin.schema.json",
	"name": "acme/hello"
}
```

**A Composer plugin** is a package of type `blush-plugin`. Its name is
the package's, and the rest of the manifest goes in its `composer.json`
under `extra.blush` (every key is optional, so a package can leave
`extra.blush` out):

```json
{
	"name": "acme/hello",
	"type": "blush-plugin",
	"extra": {
		"blush": {
			"label": "Hello",
			"namespace": "hello",
			"provider": "Acme\\Hello\\HelloServiceProvider",
			"require": { "blush-dev/framework": "^2.0" }
		}
	}
}
```

Every installed plugin is on. Turn one off on the admin's
[Plugins](admin.md#plugins) screen, or by its name in
[`config/plugins.php`](configuration.md#plugins-and-middleware).

### A plugin without a provider

A provider is what connects a plugin's classes to Blush: directives,
components, icons, commands, listeners, and the rest. A plugin that has none of those
can leave `provider` out. It can still:

- load files of functions, such as helpers for templates, with
  `autoload.files`;
- need other plugins, PHP, or extensions, with `require`;
- carry a `lang/` catalog for its text (see [Translations](themes.md#translations)).

```json
{
	"name": "acme/template-helpers",
	"autoload": { "files": ["src/helpers.php"] }
}
```

Its files load when it's on, before any provider registers, so templates
can call its functions. They can't reach Blush's services. Wrap each
function in `if (! function_exists(…))`, since a file of functions
can't be loaded twice. A plugin with none of these is allowed too; it's
listed, and turning it on does nothing.

### Requirements

`require` maps what an extension needs to a version constraint, as
Composer's does (`^2.0`, `~1.2`, `>=8.4`, `1.*`, `1.0 - 2.0`,
`^1.0 || ^2.0`). It works the same way for plugins, themes, and icon
packs. Constraints are checked by Composer's rules, wherever a version
comes from (the manifest, `composer.json`, or Composer). That includes
stability: `^2.0` and `>=2.0` take `2.0.0-beta1` and `2.0.0-dev`, `<2.0`
doesn't, and `>=2.0@beta` starts at the first beta. A branch version,
such as `dev-main`, meets only `*` or its own name. Give an extension a
`version` Composer can read (`1.2.0`, `2.0.0-beta1`); one it can't, such
as `1.0-final`, meets only `*`, and the check commands warn about it.
One with no `version` counts as `0.0.0`.

- `blush-dev/framework`: the Blush version.
- `php`: the PHP version.
- `ext-{name}`: a PHP extension that must be loaded (`"ext-intl": "*"`).
  Site Health's Requirements tab lists it under Plugins and Themes,
  naming your extension.
- Another plugin, theme, or icon pack, by its name: `"acme/shop": "^2.0"`
  needs Shop installed at a version that fits, and running. A plugin or
  icon pack runs when it's turned on and its own requirements are met;
  a theme runs when it's the active theme or one it falls back to.
- A library, by its name: `"guzzlehttp/guzzle": "^7.0"` needs the
  site's Composer to have installed it at a version that fits, as
  Composer checks it (a version it's aliased to, or a package that
  replaces or provides it, counts too).
- `lib-*` (such as `lib-icu`), `composer-plugin-api`,
  `composer-runtime-api`, and `php-64bit`, `php-ipv6`, `php-zts`, and
  `php-debug` are always met: Composer checks them when it installs a
  package, and Blush can't.

No two extensions share a name, of any kind, so a name in `require`
always means one extension. A theme or icon pack with the name of an
extension found before it (plugins first, then themes) is broken.

When requirements aren't met:

- **A plugin** doesn't run, even when it's on, and the Plugins screen
  says why.
- **An icon pack** adds no icons, even when it's on, and the Icon Packs
  screen says why.
- **A theme** can't be activated, in the admin or with `theme:activate`.
  If the active theme stops meeting them (a plugin it needs is turned
  off, say), visitors see the default theme in its place until that's
  fixed, and the Themes screen says why. A theme and the themes it falls
  back to run together or not at all, so a requirement of any of them
  counts for all of them.

A `vendor/name` that's neither an extension nor installed by Composer
isn't installed, so it isn't met, and anything else in `require` can't
be checked, so it isn't met either. Turning an extension off (or
switching themes) also stops every extension that requires it, and the
admin names what stopped. A plugin's requirements are loaded before it.
A Composer package's `require` is read from its `composer.json` too, as
Composer reads it, unless its manifest (or, for a plugin, `extra.blush`)
has one.
`bin/blush plugin:check`, `theme:check`, and `icon-pack:check` check
each kind from the command line, and `doctor` warns of anything that's
on but can't run.

### Conflicts

`conflict` names what an extension can't run with, each mapped to the
versions it can't, as Composer's does. It has the same names and
constraints as `require`, and works the same way for every kind:

```json
{
	"conflict": {
		"acme/old-seo": "<2.0",
		"php": ">=9.0"
	}
}
```

An extension conflicts when the site has what it names at a version
that fits: `blush-dev/framework` or `php` at that version, `ext-{name}`
loaded at it, or another plugin, theme, or icon pack **turned on** at
it (a plugin or pack that's on, or a theme that's active or one the
active theme falls back to). One that's off, isn't installed, or is at
another version doesn't conflict.

**The extension that declares the conflict is the one that stops,**
the same way as when its requirements aren't met: a plugin doesn't
run, a pack adds no icons, and a theme can't be activated (an active
one shows the default theme in its place). The extension it names
carries on. So turning on `acme/old-seo` above isn't refused; the
extension declaring the conflict stops, and the admin names it among
what stopped. An extension it names counts while it's on even if it
can't run itself. Two that each name the other both stop.

A conflict shows on the extension's details, under **Conflicts**, and
the check commands and `doctor` report one the same way as a
requirement that isn't met: `Conflicts with Old SEO <2.0 (version 1.4.0
is on).` A constraint Blush can't read counts as a conflict. A library counts too:
it conflicts when the site's Composer installed it at a version that
fits. So does an extension that's on and
[replaces](#replacing-another-extension) or [provides](#providing-a-package)
what a conflict names, at a version that fits, as in Composer. A
Composer package's own `composer.json` `conflict` counts, as in
Composer, since Composer can't see the extensions in `extensions/`
(`extra.blush`'s `conflict`, if it has one, is used in its place).

### Replacing another extension

`replace` names the packages an extension stands in for, as Composer's
does: a fork, or a package that's been renamed. Each maps to the
versions it stands in for, or `self.version` for the extension's own
version:

```json
{
	"name": "acme/seo-pro",
	"version": "2.0.0",
	"replace": {
		"acme/seo": "self.version"
	}
}
```

- **It meets their requirements.** While `acme/seo-pro` runs, an
  extension that requires `acme/seo` is met by it, when the versions
  match: `"acme/seo": "^2.0"` is met by `self.version` 2.0.0 above, and
  `^1.0` isn't. Versions match as they do in Composer. The requirement
  says what met it ("Seo Pro 2.0.0 replaces it"), and **Required by** on
  the replacing extension lists what requires the packages it replaces.
- **It doesn't run alongside them.** As in Composer, where the two can't
  be installed together, an extension doesn't run while one it replaces
  is turned on, at any version, the same way as a
  [conflict](#conflicts): the extension declaring `replace` is the one
  that stops, and says why (`Replaces SEO (is on).`). Turn the old one
  off (or delete it) and the new one runs.

Its details list what it replaces under **Replaces**. A Composer
package's own `composer.json` `replace` counts too, as in Composer
(`extra.blush`'s, if it has one, is used in its place). A fork that
keeps its original's [namespace](#plugins) can't be installed beside
it, since no two extensions share a namespace; delete the original
first.

### Seeing it from the other side

An extension's details also show the links other extensions have to
it: **Conflicts with it** (the extensions whose `conflict` hits it at
its version, naming it or a package it replaces or provides), **Replaced
by**, and **Also provided by**, beside **Required by**. Turning an
extension on, or activating a theme, asks first when that would stop
others, naming them: the ones that conflict with it or replace it, and
the ones that need those.

### Providing a package

`provide` names packages an extension implements, as Composer's does,
usually a shared name that several extensions can stand behind, such as
an AI provider any plugin can require without caring which one is
installed. Each maps to the versions it provides, or `self.version` for
the extension's own version:

```json
{
	"name": "acme/claude",
	"provide": {
		"acme/ai-provider": "1.0"
	}
}
```

While `acme/claude` runs, an extension that requires `acme/ai-provider`
is met by it, when the versions match (`"acme/ai-provider": "^1.0"`
is; `^2.0` isn't), and the requirement says what met it ("Claude 1.0.0
provides it"). Unlike `replace`, providing isn't a conflict: any number
of extensions may provide the same package and run together, and a
package provided this way doesn't need to exist anywhere. A
[conflict](#conflicts) naming it does hit an extension that's on and
provides it. Its details list what it provides under **Provides**, and
**Required by** lists what requires those packages. A Composer
package's own `composer.json` `provide` counts too (`extra.blush`'s, if
it has one, is used in its place), and a library Composer installed is
met by what other Composer packages provide, as Composer checks it.

A plugin whose manifest can't be read (a `plugin.json` that doesn't
parse, or is missing a key it needs) is broken. It never runs, even
when it's turned on, and the rest of the site carries on without it.
The Plugins screen, `plugin:list`, and `plugin:check` list it by where
it was found, such as `extensions/acme/hello`, with the reason.

### Directives and components from a plugin

A plugin's [directives](directives.md) use its namespace: `acme/hello`,
with the namespace `hello`, registers `hello/tabs`, not `tabs`. Their
text (labels, descriptions) goes in the plugin's `lang/en.json`, under
`directives.tabs`, with `@@locale` and `@@domain` (`"acme/hello"`) at
the top ([Translations](themes.md#translations)). A plugin's directive
draws itself with its `render()`, usually a template file in the plugin
returned by `$this->view(__DIR__ . '/../views/tabs.php')` (see
[Rendering itself](directives.md#rendering-itself)); a theme (or a
child theme) restyles it with `views/directives/hello-tabs.php`. Turning the
plugin off turns its directives in your content into plain text.

A plugin can also offer [components](components.md) for themes to use
in their templates, such as a newsletter plugin's `newsletter/signup`,
registered with `ComponentRegistry` and drawn by their own `render()`,
which a theme replaces with a template of its own.

### Template engines from a plugin

Templates are plain PHP, but a plugin can add another template
language, such as Twig. Write a class that implements
`Blush\View\Engine\ViewEngine`, which turns one file into HTML, and
register it for a file extension (without the dot) in the provider's
`boot()`:

```php
use Blush\View\Engine\ViewEngineRegistry;

public function boot(): void
{
	$this->container->get(ViewEngineRegistry::class)->register('twig', TwigEngine::class);
}
```

```php
use Blush\View\Engine\ViewEngine;
use Blush\View\Template;

final class TwigEngine implements ViewEngine
{
	public function render(string $file, array $data, Template $template): string
	{
		// Render $file with $data, and $template as a global.
	}
}
```

Themes can then use `single.twig` alongside `single.php`. A file goes to
the engine for the longest extension it ends with (`card.blade.php` to
`blade.php` before `php`). Templates in different engines can include
each other, since every include goes back through Blush. When one folder
has the same template in two engines, PHP's wins.

Give templates `$template`'s methods, the same API PHP templates have.
Use `$template->setSection('name', $html)` for a section your engine
builds with its own blocks, and `$template->layout('base')` to wrap a
template in a layout. If your engine escapes output on its own, don't
escape Blush's HTML a second time: methods marked with the
`Blush\View\ReturnsHtml` attribute (`include()`, `section()`,
`component()`, `directive()`, and others) return HTML, and so does any value that
implements `Blush\View\SafeHtml`.

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

A plugin can define [content types](content-types.md). Write a class that implements
`ContentTypeSource` and returns the types:

```php
<?php

declare(strict_types=1);

namespace Acme\Recipes;

use Blush\Content\Query\Order;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentTypeSource;
use Blush\Content\Type\Listing;
use Blush\Content\Type\TypeOrder;

final class ContentTypes implements ContentTypeSource
{
	public function types(): iterable
	{
		yield new Collection(
			'recipe',
			folder: 'recipes',
			listing: new Listing(orderBy: 'published', order: Order::Desc)
		);

		// Cuisines are terms: ordered by position.
		yield new Collection(
			'cuisine',
			folder: 'recipes/cuisines',
			order: TypeOrder::Position,
			llms: false
		);
	}
}
```

What files recipes under cuisines is a
[relation](content-types.md#terms-and-relationships), which comes from
a class of its own that implements `RelationSource` (one class can't be
both):

```php
<?php

declare(strict_types=1);

namespace Acme\Recipes;

use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\RelationSource;

final class Relations implements RelationSource
{
	public function relations(): iterable
	{
		yield new Relation('cuisine', RelationKind::Classify, from: ['recipe'], to: ['cuisine'], create: true);
	}
}
```

Tag both in the plugin's provider:

```php
protected const array TAGS = [
	ContentTypeSource::TAG => [ContentTypes::class],
	RelationSource::TAG    => [Relations::class]
];
```

The kinds and options are the same as a [data type's](content-types.md#two-ways-to-define-a-type).
A site can change one of your collections or trees with a file in
`user/data/types/` named for it (which the admin writes when someone
edits the type): each option the file sets replaces yours, and the
admin's **Reset the Type** removes the file, going back to yours. A
relation in `user/data/relations/` replaces yours by name.

When two plugins define a type or a relation by the same name, the
site uses the first plugin's and leaves the other's out. Site Health
and the Plugins screen in the admin say so, naming both. So give
generic names a prefix of your own (`acme_related`, not `related`;
`acme_series`, not `series`), and keep plain names for types only your
plugin would have. A plugin still defining a type with the old
taxonomy kind stops the site from loading, with a message saying what
to change.

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

Then `type: color` works in `user/data/types`, `user/data/fields`, and
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
code. Put it in `extensions/{vendor}/{name}/`, with an `icons.json`:

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

Only `name` is required. Without a `label` it's shown by its name, and
without a `namespace` it goes by its name, hyphenated (`acme-brands`). Like a local plugin, it's
off until it's turned on, in **Extend → Icon Packs** or in
`config/icons.php`'s `enabled` list. `authors`, `license`, `homepage`,
`support`, `funding`, `abandoned`, `suggest`, [`require`](#requirements), [`conflict`](#conflicts), [`replace`](#replacing-another-extension), and [`provide`](#providing-a-package) work as a plugin's
do: a pack whose requirements aren't met adds no icons, even when it's
on. Each `{icon}.svg` in the
pack's `folder` (the pack's own folder, without one) is
`{namespace}/{icon}`: `svg/github.svg` is `brands/github`, used as
`:icon[GitHub]{name=brands/github}` or `$template->icon('brands/github')`.
Labels go in the pack's `lang/en.json`, under `icons.{icon}.label`:

```json
{
	"@@locale": "en",
	"@@domain": "acme/brands",
	"icons": {
		"github": { "label": "GitHub" }
	}
}
```

Start every catalog with `@@locale` and `@@domain` (the pack's name), as
[themes do](themes.md#translations).

Every installed pack is on, and its icons appear in `bin/blush
icon:list` and the admin's icon inserter. Turn one off on the admin's
[Icon Packs](admin.md#icon-packs) screen, or by its name in
[`config/icons.php`](configuration.md#plugins-and-middleware); its icons
then show nowhere. A theme (or a child theme) can restyle one with
`icons/brands/github.svg` (see [Icons](directives.md#icons)).

For autocomplete, point `$schema` at
`vendor/blush-dev/framework/resources/schemas/icons.schema.json`.

**A Composer icon pack** is a package of type `blush-icons`. Its
manifest is the `icons.json` in the package, or its `composer.json`'s
`extra.blush`, or both. Its name is the package's, so the manifest can
leave `name` out; one naming something else is broken.

## Scripts and styles

Register the scripts and styles your plugin, theme, or site needs by a
name, a handle in the `vendor/name` form, in your provider's `boot()`.
Pages then ask for them by handle, and each prints once, after the
ones it `requires`:

```php
use Blush\Asset\Asset;
use Blush\Asset\AssetRegistry;
use Blush\Asset\Script;
use Blush\Asset\Style;

public function boot(): void
{
	$this->container->make(AssetRegistry::class)->register(new Asset(
		'acme/gallery',
		styles: [new Style('css/gallery.css', from: 'acme/gallery')],
		scripts: [new Script('js/gallery.js', from: 'acme/gallery', attributes: ['type' => 'module'], footer: true)],
		requires: ['blush/player']
	));
}
```

`from` says where a file is: your plugin's or theme's name for a file
in its folder (`/extensions/acme/gallery/js/gallery.js` for a plugin,
`/themes/acme/nova/...` for a theme), or leave it out for a URL as
given. Files are served with `?v=` and a hash of their contents, so a
browser fetches one again only when it changes. Only stylesheets,
scripts, fonts, and images are served, never files in `src/`,
`views/`, `lang/`, `resources/`, `vendor/`, or `node_modules/`, and a
plugin's only while it's on.

A script's `attributes` control how it loads: it's deferred unless they
say otherwise (`['defer' => false]`, `['async' => true]`, or a `type`
such as `module`). `footer: true` prints it at the end of the page,
where the theme's layout prints `$template->foot()`, instead of in the
`<head>`. A `Style` takes `attributes` too, such as
`['media' => 'print']`.

Registering a handle that's already taken replaces it. Core's are
registered first, then plugins', then themes' (those in `theme.json`'s
`assets`, then their providers'), then your site's own providers' (in `src/`), so a theme can
replace core's `blush/player` with its own, or with an empty
`new Asset('blush/player')` to load nothing. A theme without a
provider lists its assets in `theme.json` instead
([Scripts and styles](themes.md#scripts-and-styles)).

### Inline code and data

A script often needs a few lines to start it, or settings from the
page. Tie them to its handle, and they print beside its files, wherever
those print:

```php
$template->foot()->data('acme-gallery', ['columns' => 3, 'label' => $label], for: 'acme/gallery');
$template->foot()->inlineScript('acme-gallery-start', 'AcmeGallery.start();', after: 'acme/gallery');
```

```html
<script type="application/json" id="acme-gallery">{"columns":3,"label":"Photos"}</script>
<script src="/extensions/acme/gallery/js/gallery.js?v=…" defer></script>
<script type="module" id="acme-gallery-start">AcmeGallery.start();</script>
```

- **`data($id, $value, for:)`** prints the value as JSON in a
  `<script type="application/json">`, which never runs, just before the
  asset's first script. Read it in your script with
  `JSON.parse(document.getElementById('acme-gallery').textContent)`.
  It's safe for any text, `</script>` included.
- **`inlineScript($id, $js, after:)`** prints the code just after the
  asset's last script. When that script is deferred (the default) or a
  module, the code prints as a module, so it runs after the script
  rather than before it; inline code otherwise runs the moment the
  browser reaches it. Module code runs in strict mode, and its
  top-level variables stay its own.

Either one asks for the asset, so you don't need `enqueue()` as well.
If the asset has no script on the page (its plugin is off, or the
handle doesn't exist), they don't print, and in development the log
says so. Without `for:` or `after:`, data prints before the head's or
foot's scripts and inline code after them. Both are on the head and the
foot, and on `$event->head` and `$event->foot` in a `PageRendering`
listener.

There are three ways to load an asset:

- **Where it's drawn.** A [directive](directives.md#the-class) or
  [component](components.md) lists the handles it needs in `ASSETS`,
  and they load on every page it's drawn on, an entry's text included.
- **From a template**: `$template->enqueue('acme/gallery')`.
- **On the pages that need it**, from a listener for
  `Blush\View\Events\PageRendering`, which every themed page, content or
  error, sends just before its templates render:

```php
use Blush\Event\Listener\ListenerRegistry;
use Blush\View\Events\PageRendering;

$this->container->get(ListenerRegistry::class)->listen(PageRendering::class, function (PageRendering $event): void {
	if ($event->page?->type?->name === 'gallery') {
		$event->enqueue('acme/gallery');
	}
});
```

The event has the `page` (`null` on an error page), its `entry`, the
error page's `status` (`isError()`), the `request`, the theme `chain`,
`path()`, and `locale()`. Besides `enqueue()` and `dequeue()`, it can
add `<body>` classes with `addClass()`, anything else to the `head`,
which Blush has already filled in, and scripts to the end of the page
through `foot` (`$event->foot->script($url)`); a template's value for
the same tag still wins.

## Events

Blush announces what it's doing through events you can listen for, such as
`Blush\View\Events\PageRendering` (a page is about to render; see
[Scripts and styles](#scripts-and-styles)),
`Blush\Content\Events\ContentIndexed` (content changed),
`Blush\Content\Events\EntriesWentLive` (scheduled entries went live; see
below),
`Blush\Publish\Events\ContentPublished`, and
`Blush\Cache\Events\CacheCleared` (the cache store was emptied by
`cache:clear`, the admin's Clear caches, a publish, or `cache:compile`;
its `namespaces` say which, a good moment to purge a CDN).
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

`EntriesWentLive` comes from the [scheduled task](#scheduled-tasks) that
puts scheduled entries live, every minute with cron. Its `entries` are
the entries that were scheduled when it last looked and are published
now, so you can send a webhook, purge a CDN, or post to a social site
when a scheduled post goes live. An entry published as it's saved isn't
in it. Without cron, the next runner catches up, so `since` (when it
last looked) can be long before `at`; check an entry's `published` if
you only want fresh news.

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

