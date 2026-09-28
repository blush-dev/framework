<?php

/**
 * Icon service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

use Blush\Core\ServiceProvider;

/**
 * Binds icon lookup (D-187): the registry extensions add icon folders to,
 * and `Icons`.
 */
final class IconServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		IconRegistry::class,
		Icons::class
	];
}
