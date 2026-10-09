<?php

/**
 * Data service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use Blush\Core\ServiceProvider;

/**
 * Binds the data loader, which reads JSON files. The data area itself is
 * tables of records, each read through its repository (D-606, D-681).
 */
final class DataServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		DataLoader::class
	];
}
