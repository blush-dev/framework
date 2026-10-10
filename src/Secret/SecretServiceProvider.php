<?php

/**
 * Secret service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

use Override;
use Blush\Container\ServiceResolver;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Storage\File\FileLayout;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\Record\TableRegistry;

/**
 * Binds the secrets store (D-715) and registers its table, which on
 * files is one file, `storage/secrets.json`, readable by its owner and
 * group only.
 */
final class SecretServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		SecretKeys::class,
		SecretCipher::class,
		SecretStore::class,
		Secrets::class
	];

	/**
	 * Registers the secrets' table and, for files, where it's kept.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->resolving(TableRegistry::class, static function (object $tables): void {
			if ($tables instanceof TableRegistry) {
				$tables->register(SecretStore::table());
			}
		});

		$this->container->resolving(FileLayouts::class, static function (object $layouts, ServiceResolver $resolver): void {
			if ($layouts instanceof FileLayouts) {
				$layouts->register(SecretStore::TABLE, FileLayout::oneFile("{$resolver->make(Paths::class)->storage}/secrets.json", SecretStore::TABLE, 0660));
			}
		});
	}
}
