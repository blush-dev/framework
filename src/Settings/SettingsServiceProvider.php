<?php

/**
 * Settings service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

use Override;
use Blush\Container\ServiceResolver;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Field\FieldTargetSource;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\Record\TableRegistry;

/**
 * Binds the saved settings' groups (`SettingGroups`, D-673) and their
 * table, and the site settings field sets add (D-343): the Settings
 * screens as places sets attach to, and `SiteSettings`, which reads their
 * values.
 */
final class SettingsServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		SettingGroups::class,
		SiteSettings::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		FieldTargetSource::TAG => [SettingsTargets::class]
	];

	/**
	 * Registers the settings' table and, for files, where it's kept
	 * (D-673).
	 */
	#[Override]
	public function register(): void
	{
		$this->container->resolving(TableRegistry::class, static function (object $tables): void {
			if ($tables instanceof TableRegistry) {
				$tables->register(SettingGroups::table());
			}
		});

		$this->container->resolving(FileLayouts::class, static function (object $layouts, ServiceResolver $resolver): void {
			if ($layouts instanceof FileLayouts) {
				$layouts->register(SettingGroups::TABLE, SettingGroups::layout($resolver->make(Paths::class)));
			}
		});
	}
}
