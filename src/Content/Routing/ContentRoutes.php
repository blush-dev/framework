<?php

/**
 * Content type routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Override;
use Blush\Content\Http\AuthorController;
use Blush\Content\Http\AuthorsController;
use Blush\Content\Http\CollectionController;
use Blush\Content\Http\DateArchiveController;
use Blush\Content\Http\SingleController;
use Blush\Content\Http\TermController;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Taxonomy;
use Blush\Routing\Route;
use Blush\Routing\RoutePattern;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The routes of every public, routed content type, named and shaped as in
 * 1.x (D-078): `{type}.collection`, `.collection.paged`, the date
 * archives the type's granularity allows (`.collection.year` …
 * `.collection.second`, each with `.paged`), and `{type}.single` (plus
 * `.single.paged` for a taxonomy's term archives). A type with author
 * archives (D-329) adds `{type}.authors.collection`,
 * `.authors.single`, and `.authors.single.paged`, ahead of its single
 * route. Paths come from the type's `TypeUrls` under its prefix.
 *
 * Types register deepest path first, and each type's routes in 1.x's
 * order, so date archives match before a single entry. Date and page
 * parameters are constrained to digits (`ContentUrls::CONSTRAINTS`). The
 * home type (`ContentConfig::$home`) has no collection routes of its own;
 * `PageRoutes` serves its collection at `/`. Feed routes come from
 * `Feed\FeedRoutes`.
 */
final readonly class ContentRoutes implements RouteSource
{
	public function __construct(
		private ContentTypes $types,
		private ContentUrls $urls
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
		$types = array_filter($this->types->all(), static fn (ContentType $type): bool => $type->public && $type->hasUrls());

		uasort($types, static fn (ContentType $a, ContentType $b): int => strcmp($b->folder, $a->folder));

		$routes = [];

		foreach ($types as $type) {
			array_push($routes, ...$this->typeRoutes($type, $type->name === $this->types->home));
		}

		return $routes;
	}

	/**
	 * Returns one type's routes.
	 *
	 * @return list<Route>
	 */
	private function typeRoutes(ContentType $type, bool $home): array
	{
		$controllers = $home ? [] : ['collection.paged' => CollectionController::class];

		foreach (array_reverse($type->dateArchives->levels()) as $level) {
			$controllers["collection.{$level->value}.paged"] = DateArchiveController::class;
			$controllers["collection.{$level->value}"]       = DateArchiveController::class;
		}

		if ($this->urls->hasAuthorArchives($type)) {
			$controllers['authors.single.paged'] = AuthorController::class;
			$controllers['authors.single']       = AuthorController::class;
			$controllers['authors.collection']   = AuthorsController::class;
		}

		if ($type instanceof Taxonomy) {
			$controllers['single.paged'] = TermController::class;
			$controllers['single']       = TermController::class;
		} else {
			$controllers['single'] = SingleController::class;
		}

		if (! $home) {
			$controllers['collection'] = CollectionController::class;
		}

		$routes = [];

		foreach ($controllers as $key => $controller) {
			$pattern = $type->routePattern($key);

			if ($pattern !== null) {
				$routes[] = self::route($pattern, $controller, "{$type->name}.{$key}", ['type' => $type->name], $type);
			}
		}

		return $routes;
	}

	/**
	 * Builds a `GET` route with the content constraints its parameters
	 * need.
	 *
	 * @param class-string                               $controller
	 * @param array<string, string|int|float|bool|null> $defaults
	 * @param ?ContentType                               $type       The type whose route it is, for its own constraints.
	 */
	public static function route(string $pattern, string $controller, string $name, array $defaults = [], ?ContentType $type = null): Route
	{
		$params = RoutePattern::parse($pattern)->params;

		return Route::get($pattern, $controller)
			->named($name)
			->defaults($defaults)
			->where(ContentUrls::constraints($type, $params));
	}
}
