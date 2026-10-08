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
use Blush\Storage\StorageArea;

/**
 * Binds accounts, roles, capabilities, and permissions (D-219). Accounts
 * are kept by the storage driver for accounts (D-642), and roles as
 * records in its `roles` table (D-646), unless a site or extension binds
 * another `AccountStore` or `RoleStore`.
 * Extensions add capabilities to `Capabilities` from their own `boot()`.
 */
final class AuthServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		Accounts::class,
		Authenticator::class,
		LoginThrottle::class,
		Passwords::class,
		Permissions::class,
		RoleEditor::class,
		Roles::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		RoleStore::class => RecordRoleStore::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array STORAGE = [
		AccountStore::class => StorageArea::Accounts
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
	 * registers the roles' table and, for files, where it's kept.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(Capabilities::class, static fn (Container $container): Capabilities => Capabilities::withBuiltIns($container->make(ContentTypes::class)));

		$this->container->resolving(TableRegistry::class, static function (object $tables): void {
			if ($tables instanceof TableRegistry) {
				$tables->register(RecordRoleStore::table());
			}
		});

		$this->container->resolving(FileLayouts::class, static function (object $layouts, ServiceResolver $resolver): void {
			if ($layouts instanceof FileLayouts) {
				$layouts->register(RecordRoleStore::TABLE, FileLayout::oneFile("{$resolver->make(Paths::class)->storage}/roles.json", 'roles', 0660));
			}
		});
	}
}
