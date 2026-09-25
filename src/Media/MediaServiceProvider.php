<?php

/**
 * Media service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;

/**
 * Binds media resolution and the media route.
 */
final class MediaServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		MediaResolver::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		MediaController::class,
		MediaRoutes::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [MediaRoutes::class]
	];
}
