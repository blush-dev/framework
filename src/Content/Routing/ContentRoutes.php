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
use Blush\Content\Http\CollectionController;
use Blush\Content\Http\DateArchiveController;
use Blush\Content\Http\HomeController;
use Blush\Content\Http\PeopleController;
use Blush\Content\Http\PersonController;
use Blush\Content\Http\ProfileController;
use Blush\Content\Http\SingleController;
use Blush\Content\Http\TermController;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Profiles;
use Blush\Content\Type\Taxonomy;
use Blush\Core\AppConfig;
use Blush\Routing\Route;
use Blush\Routing\RoutePattern;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The routes of every public, routed content type, named and shaped as in
 * 1.x (D-078): `{type}.collection`, `.collection.paged`, the date
 * archives the type's granularity allows (`.collection.year` …
 * `.collection.second`, each with `.paged`), and `{type}.single` (plus
 * `.single.paged` for a taxonomy's term archives). Each people field
 * with archives (D-351) adds `{type}.{field}.collection`,
 * `.{field}.single`, and `.{field}.single.paged`, ahead of the type's
 * single route. The profiles type has only `{type}.single` and
 * `.single.paged`, each profile's page; nothing answers at its prefix.
 * Paths come from the type's `TypeUrls` under its prefix.
 *
 * Types register deepest path first, and each type's routes in 1.x's
 * order, so date archives match before a single entry. Date and page
 * parameters are constrained to digits (`ContentUrls::CONSTRAINTS`). The
 * home type (`ContentConfig::$home`) has no collection routes of its own;
 * `PageRoutes` serves its collection at `/`. Feed routes come from
 * `Feed\FeedRoutes`.
 *
 * On a multilingual site (D-455), each language other than the default
 * gets the homepage and every type's collection, date archive, single,
 * and term routes again under `/{code}`, named `{code}:{name}` (`fr:home`,
 * `fr:post.single`), with the code as the `language` parameter. They come
 * first, so a type's pattern at the root can't take a language's paths.
 * Profiles and people archives aren't in other languages yet.
 */
final readonly class ContentRoutes implements RouteSource
{
	public function __construct(
		private ContentTypes $types,
		private ContentUrls $urls,
		private AppConfig $app
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

		foreach ($this->app->languages->others() as $code => $language) {
			$localized = [self::route('/', HomeController::class, 'home')];

			if ($this->types->homeType() !== null) {
				$localized[] = self::route('/page/{page}', HomeController::class, 'home.paged');
			}

			foreach ($types as $type) {
				if (! $type instanceof Profiles) {
					array_push($localized, ...$this->typeRoutes($type, $type->name === $this->types->home, people: false));
				}
			}

			array_push($routes, ...self::localized($code, $localized));
		}

		foreach ($types as $type) {
			array_push($routes, ...$this->typeRoutes($type, $type->name === $this->types->home));
		}

		return $routes;
	}

	/**
	 * Returns routes under a language's prefix, named for it, with its
	 * code as the `language` parameter.
	 *
	 * @param  list<Route> $routes
	 * @return list<Route>
	 */
	public static function localized(string $code, array $routes): array
	{
		return array_map(
			static fn (Route $route): Route => $route->defaults(['language' => $code]),
			Route::group("/{$code}", $routes, name: "{$code}:")
		);
	}

	/**
	 * Returns one type's routes, with its people archives' unless
	 * `$people` is `false`.
	 *
	 * @return list<Route>
	 */
	private function typeRoutes(ContentType $type, bool $home, bool $people = true): array
	{
		if ($type instanceof Profiles) {
			return $this->routesFor($type, ['single.paged' => ProfileController::class, 'single' => ProfileController::class]);
		}

		$controllers = $home ? [] : ['collection.paged' => CollectionController::class];

		foreach (array_reverse($type->dateArchives->levels()) as $level) {
			$controllers["collection.{$level->value}.paged"] = DateArchiveController::class;
			$controllers["collection.{$level->value}"]       = DateArchiveController::class;
		}

		$routes = [];

		foreach ($people ? $type->archivedPeople() : [] as $field) {
			if ($this->urls->hasArchive($type, $field)) {
				array_push($routes, ...$this->routesFor($type, [
					"{$field->field}.single.paged" => PersonController::class,
					"{$field->field}.single"       => PersonController::class,
					"{$field->field}.collection"   => PeopleController::class
				], ['field' => $field->field]));
			}
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

		return [...$routes, ...$this->routesFor($type, $controllers)];
	}

	/**
	 * Returns a type's routes for route keys and their controllers, in
	 * order, with the type (and any other defaults) passed on.
	 *
	 * @param  array<string, class-string> $controllers
	 * @param  array<string, string>       $defaults
	 * @return list<Route>
	 */
	private function routesFor(ContentType $type, array $controllers, array $defaults = []): array
	{
		$routes = [];

		foreach ($controllers as $key => $controller) {
			$pattern = $type->routePattern($key);

			if ($pattern !== null) {
				$routes[] = self::route($pattern, $controller, "{$type->name}.{$key}", ['type' => $type->name, ...$defaults], $type);
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
