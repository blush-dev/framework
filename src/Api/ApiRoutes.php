<?php

/**
 * API routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use Override;
use Blush\Api\Http\EntriesController;
use Blush\Api\Http\IndexController;
use Blush\Api\Http\MediaController;
use Blush\Api\Http\MenusController;
use Blush\Api\Http\RelationsController;
use Blush\Api\Http\SiteController;
use Blush\Api\Http\TypesController;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * With `ApiConfig::$enabled` on, the system routes under
 * `ApiConfig::base()` (`/api/v1`), named `api.*`, each answering its
 * path exactly as written: the index, each resource and its single
 * items, a CORS preflight for any of them, and a JSON 404 for any other
 * address under the API, so a client never gets the site's HTML error
 * page. Off, there are none, and the site answers 404 itself (D-737).
 *
 * They come before the Markdown pages' route (`/{path}.md`), which
 * would take an address under the API ending in `.md`: the provider
 * registers before the one for Markdown pages.
 */
final readonly class ApiRoutes implements RouteSource
{
	public function __construct(private ApiConfig $config)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::System;
	}

	/**
	 * @inheritDoc
	 * @return list<Route>
	 */
	#[Override]
	public function routes(): iterable
	{
		if (! $this->config->enabled) {
			return [];
		}

		return Route::group($this->config->base(), [
			Route::get('/', [IndexController::class, 'index'])->named('index'),
			Route::get('/site', SiteController::class)->named('site'),
			Route::get('/types', [TypesController::class, 'index'])->named('types'),
			Route::get('/types/{name}', [TypesController::class, 'show'])->named('types.single'),
			Route::get('/entries', [EntriesController::class, 'index'])->named('entries'),
			Route::get('/entries/{id}', [EntriesController::class, 'show'])->named('entries.single'),
			Route::get('/relations', RelationsController::class)->named('relations'),
			Route::get('/media', [MediaController::class, 'index'])->named('media'),
			Route::get('/media/{id}', [MediaController::class, 'show'])->named('media.single'),
			Route::get('/menus', [MenusController::class, 'index'])->named('menus'),
			Route::get('/menus/{location}', [MenusController::class, 'show'])->named('menus.single'),
			Route::match(['OPTIONS'], '/', [IndexController::class, 'preflight'])->named('preflight'),
			Route::match(['OPTIONS'], '/{path:.+}', [IndexController::class, 'preflight'])->named('preflight.any'),
			Route::get('/{path:.+}', [IndexController::class, 'missing'])->named('missing')
		], name: 'api.', exact: true);
	}
}
