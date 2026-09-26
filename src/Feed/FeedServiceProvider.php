<?php

/**
 * Feed service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Feed;

use Blush\Core\ServiceProvider;
use Blush\Export\UrlSource;
use Blush\Routing\RouteSource;

/**
 * Binds feeds: the builder, the head links, the controller, and the feed
 * routes.
 */
final class FeedServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		FeedBuilder::class,
		FeedLinks::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		FeedController::class,
		FeedRoutes::class,
		FeedExportUrls::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [FeedRoutes::class],
		UrlSource::TAG   => [FeedExportUrls::class]
	];
}
