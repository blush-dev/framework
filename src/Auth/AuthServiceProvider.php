<?php

/**
 * Auth service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Override;
use Blush\Auth\Middleware\Authenticate;
use Blush\Auth\Middleware\VerifyCsrf;
use Blush\Container\Container;
use Blush\Container\ServiceResolver;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Storage\File\FileLayout;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\Record\TableRegistry;
use Blush\Translation\Messages;

/**
 * Binds accounts, roles, capabilities, permissions, and API tokens
 * (D-219, D-747). Accounts, stored roles, and tokens are records in the
 * accounts area's `accounts`, `roles`, and `tokens` tables (D-646,
 * D-669), kept by its storage driver (D-642): on files,
 * `storage/accounts/{username}.json`, `storage/roles.json`, and
 * `storage/tokens/{id}.json`.
 * Extensions add capabilities to `Capabilities` from their own `boot()`.
 */
final class AuthServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		AccountProfiles::class,
		BearerTokens::class,
		Accounts::class,
		Authenticator::class,
		Avatars::class,
		LoginThrottle::class,
		Passwords::class,
		Permissions::class,
		RoleEditor::class,
		Roles::class,
		Tokens::class,
		TokenThrottle::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		Authenticate::class,
		VerifyCsrf::class
	];

	/**
	 * Binds the capability registry, seeded with the built-ins, and
	 * registers the accounts' and roles' tables and, for files, where
	 * they're kept.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(Capabilities::class, static fn (Container $container): Capabilities => Capabilities::withBuiltIns($container->make(ContentTypes::class), $container->make(Messages::class)));

		$this->container->resolving(TableRegistry::class, static function (object $tables): void {
			if ($tables instanceof TableRegistry) {
				$tables->register(Accounts::table());
				$tables->register(Roles::table());
				$tables->register(Tokens::table());
			}
		});

		$this->container->resolving(FileLayouts::class, static function (object $layouts, ServiceResolver $resolver): void {
			if ($layouts instanceof FileLayouts) {
				$paths = $resolver->make(Paths::class);

				$layouts->register(Accounts::TABLE, FileLayout::folder($paths->accounts, 0660));
				$layouts->register(Roles::TABLE, FileLayout::oneFile("{$paths->storage}/roles.json", 'roles', 0660));
				$layouts->register(Tokens::TABLE, FileLayout::folder("{$paths->storage}/tokens", 0660));
			}
		});
	}
}
