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

use Blush\Core\ServiceProvider;
use Blush\Field\FieldTargetSource;

/**
 * Binds the site settings field sets add (D-343): the Settings screens as
 * places sets attach to, and `SiteSettings`, which reads their values.
 */
final class SettingsServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		SiteSettings::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		FieldTargetSource::TAG => [SettingsTargets::class]
	];
}
