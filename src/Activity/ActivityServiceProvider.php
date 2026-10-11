<?php

/**
 * Activity service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

use Override;
use Blush\Container\ServiceResolver;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Storage\File\FileLayout;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\Record\TableRegistry;

/**
 * Binds the activity log (D-758) and registers its table and, for files,
 * where it's kept (`storage/activity`).
 */
final class ActivityServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		ActivityLog::class
	];

	#[Override]
	public function register(): void
	{
		$this->container->resolving(TableRegistry::class, static function (object $tables): void {
			if ($tables instanceof TableRegistry) {
				$tables->register(ActivityLog::table());
			}
		});

		$this->container->resolving(FileLayouts::class, static function (object $layouts, ServiceResolver $resolver): void {
			if ($layouts instanceof FileLayouts) {
				$layouts->register(ActivityLog::TABLE, FileLayout::folder("{$resolver->make(Paths::class)->storage}/activity", 0660));
			}
		});
	}
}
