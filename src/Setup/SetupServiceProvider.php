<?php

/**
 * Setup service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

use Blush\Core\ServiceProvider;

/**
 * Binds what the setup checks need beyond the container's own reach:
 * the probe the exposure check asks the site's address with (D-716),
 * which a site or extension may replace.
 */
final class SetupServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		UrlProbe::class => StreamUrlProbe::class
	];
}
