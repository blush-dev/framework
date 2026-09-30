<?php

/**
 * Home and page catch-all routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Override;
use Blush\Content\Http\HomeController;
use Blush\Content\Http\PageController;
use Blush\Content\Type\ContentTypes;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The fallback routes, which any other route overrides: the home page at
 * `/` (`home`), its later pages at `/page/{page}` (`home.paged`) when a
 * type is the home page, and the page catch-all (`page.single`), which
 * serves pages at their folder paths. A site's own `/` route in
 * `config/routes.php` replaces the home page.
 */
final readonly class PageRoutes implements RouteSource
{
	/**
	 * The page catch-all's route name.
	 */
	public const string SINGLE = 'page.single';

	public function __construct(private ContentTypes $types)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::Fallback;
	}

	/**
	 * @inheritDoc
	 * @return list<Route>
	 */
	#[Override]
	public function routes(): iterable
	{
		$routes = [ContentRoutes::route('/', HomeController::class, 'home')];

		if ($this->types->homeType() !== null) {
			$routes[] = ContentRoutes::route('/page/{page}', HomeController::class, 'home.paged');
		}

		$routes[] = Route::get('/{path:.+}', PageController::class)->named(self::SINGLE);

		return $routes;
	}
}
