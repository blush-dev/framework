# Capabilities

A capability is something an account may do, such as editing posts.
Roles grant them (see [Accounts and roles](../concepts/accounts/README.md)).
A plugin adds its own for its features, checks them, and puts its routes
behind a sign-in.

## Adding a capability

Register it from your plugin's [provider's](../plugins/service-providers.md)
`boot()`:

```php
use Blush\Auth\Capabilities;

public function boot(): void
{
	$this->container->get(Capabilities::class)->register('shop.orders', 'Manage orders');
}
```

Owners get it automatically; give it to other roles, the Administrator
included, in the admin or `config/auth.php`.

## Checking a capability

Ask `Blush\Auth\Permissions`:

```php
$permissions->can($account, 'shop.orders');
```

## Content capabilities

What an account may do to entries is per content type. Check a
`Blush\Auth\ContentAction` on an entry, which also applies ownership
(see [Accounts and roles](../concepts/accounts/README.md)), on a type by
name, or, with neither, on any type:

```php
use Blush\Auth\ContentAction;

$permissions->can($account, ContentAction::Edit, $entry);
$permissions->can($account, ContentAction::Create, 'recipe');
```

To list the entries an account may act on, let `restrict()` narrow a
query instead of checking each entry. The same rules then run in the
index, so paging stays quick on large sites:

```php
$page = $permissions->restrict($account, ContentAction::Edit, $content->query()->any())
	->orderBy('updated', Order::Desc)
	->paginate(20, $number);
```

## Signed-in routes

A [route](routes.md) that needs a signed-in account runs three
middleware, in this order: `Blush\Session\StartSession`,
`Blush\Auth\Middleware\VerifyCsrf`, and
`Blush\Auth\Middleware\Authenticate`. Give them to every route a
controller declares with `#[Group]`. The controller then reads the
account from the request's `Blush\Auth\Account::class` attribute:

```php
namespace Acme\Shop\Http;

use Blush\Auth\Account;
use Blush\Auth\Middleware\Authenticate;
use Blush\Auth\Middleware\VerifyCsrf;
use Blush\Routing\Attributes\Get;
use Blush\Routing\Attributes\Group;
use Blush\Session\StartSession;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[Group('/shop', middleware: [StartSession::class, VerifyCsrf::class, Authenticate::class])]
final readonly class Orders
{
	#[Get('/orders')]
	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		// …
	}
}
```

Requests that change things (anything but `GET`) must send the session's
CSRF token in an `X-CSRF-Token` header. The admin API's `session` answer
includes it (see [the admin API](admin-api/README.md)).
