<?php

/**
 * API service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use Blush\Api\Http\EntriesController;
use Blush\Api\Http\EntryWritesController;
use Blush\Api\Http\IndexController;
use Blush\Api\Http\MediaController;
use Blush\Api\Http\MediaWritesController;
use Blush\Api\Http\MenusController;
use Blush\Api\Http\RelationsController;
use Blush\Api\Http\SiteController;
use Blush\Api\Http\TypesController;
use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;

/**
 * Binds the API (D-736 to D-741), its API tokens (D-744 to D-750), and
 * its writes (D-762).
 */
final class ApiServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		ApiResponses::class,
		ApiEntries::class,
		EntryResource::class,
		MediaResource::class,
		ApiWrites::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		ApiAuthentication::class,
		ApiRoutes::class,
		IndexController::class,
		SiteController::class,
		TypesController::class,
		EntriesController::class,
		RelationsController::class,
		MediaController::class,
		MenusController::class,
		EntryWritesController::class,
		MediaWritesController::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [ApiRoutes::class]
	];
}
