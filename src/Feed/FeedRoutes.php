<?php

/**
 * Feed routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Feed;

use Override;
use Blush\Content\Routing\ContentRoutes;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Taxonomy;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The feed routes of every public, routed type with a `feed`, for each
 * format `FeedConfig` turns on, with 1.x's names and paths (D-078):
 *
 * - `{type}.collection.feed` (`{prefix}/feed`), `.feed.atom`
 *   (`/feed/atom`), and `.feed.json` (`/feed/json`);
 * - for the home type, `home.feed`, `home.feed.atom`, and
 *   `home.feed.json` at the site root instead;
 * - for a taxonomy, `{type}.single.feed` (`{prefix}/{name}/feed`) and the
 *   Atom and JSON variants, one feed per term.
 *
 * Paths come from the type's `TypeUrls`, so a type can move them.
 */
final readonly class FeedRoutes implements RouteSource
{
	public function __construct(
		private ContentTypes $types,
		private FeedConfig $config
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::Content;
	}

	/**
	 * @inheritDoc
	 * @return list<Route>
	 */
	#[Override]
	public function routes(): iterable
	{
		$routes = [];

		foreach ($this->types->all() as $type) {
			if (! $type->public || ! $type->hasUrls() || ! $type->hasFeed()) {
				continue;
			}

			foreach ($this->config->formats as $format) {
				array_push($routes, ...$this->typeRoutes($type, $format));
			}
		}

		return $routes;
	}

	/**
	 * Returns one type's routes for a format.
	 *
	 * @return list<Route>
	 */
	private function typeRoutes(ContentType $type, FeedFormat $format): array
	{
		$suffix   = $format->routeSuffix();
		$defaults = ['type' => $type->name, 'format' => $format->value];
		$routes   = [];
		$path     = $type->urls === false ? null : $type->urls->path("collection.feed{$suffix}");

		if ($type->name === $this->types->home) {
			if ($path !== null) {
				$routes[] = ContentRoutes::route('/' . $path, FeedController::class, "home.feed{$suffix}", $defaults);
			}
		} else {
			$pattern = $type->routePattern("collection.feed{$suffix}");

			if ($pattern !== null) {
				$routes[] = ContentRoutes::route($pattern, FeedController::class, "{$type->name}.collection.feed{$suffix}", $defaults);
			}
		}

		$single = $type instanceof Taxonomy ? $type->routePattern("single.feed{$suffix}") : null;

		if ($single !== null) {
			$routes[] = ContentRoutes::route($single, FeedController::class, "{$type->name}.single.feed{$suffix}", $defaults);
		}

		return $routes;
	}
}
