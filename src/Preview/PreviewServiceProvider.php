<?php

/**
 * Preview service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Preview;

use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;

/**
 * Binds signed preview links and their route (D-226).
 */
final class PreviewServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		PreviewLinks::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		PreviewController::class,
		PreviewRoutes::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [PreviewRoutes::class]
	];
}
