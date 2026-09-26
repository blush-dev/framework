<?php

/**
 * Publish service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;

/**
 * Binds publishing: the publisher, the content puller (`git` by default;
 * a site can bind its own `Puller`), and the webhook route, which only
 * exists when `PublishConfig::$secret` is set.
 */
final class PublishServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		Publisher::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		Puller::class => GitPuller::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		WebhookController::class,
		PublishRoutes::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [PublishRoutes::class]
	];
}
