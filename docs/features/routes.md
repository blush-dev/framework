# Routes

A plugin adds its own pages, such as a form, an API, or a webhook, with
controllers that declare their routes as attributes.

## A controller

```php
<?php

declare(strict_types=1);

namespace Acme\Hello\Http;

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

Tag it in your plugin's [provider](../plugins/service-providers.md):

```php
use Blush\Routing\Sources\ControllerRoutes;

protected const array TAGS = [
	ControllerRoutes::TAG => [Http\Hello::class]
];
```

`#[Post]`, `#[Put]`, `#[Patch]`, and `#[Delete]` work the same way. A
`#[Group]` on the class prefixes the path, name, and middleware of every
route it declares (see [Capabilities](capabilities.md#signed-in-routes)).

- **Parameters** are passed to your method by name, and typed ones
  (`int $year`) only match values of that type. A parameter typed for
  the request is passed the request.
- **A route that isn't a page**, such as an API or a webhook, can skip
  the `trailingSlash` redirect (see
  [Configuration](../reference/configuration/README.md)) with
  `exact: true` (`#[Post('/hooks/deploy', exact: true)]`, or
  `->exact()` on a `Route`): it answers its path with or without the
  slash, and its URLs are made as written.
- **Your routes come after the built-in ones**, so they can't break
  content URLs; `bin/blush routes:list` shows any conflicts.

## Every URL on the site

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
use Blush\Routing\UrlSource;

protected const array TAGS = [
	UrlSource::TAG => [Http\HelloUrls::class]
];
```
