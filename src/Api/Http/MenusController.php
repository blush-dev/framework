<?php

/**
 * API menus controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api\Http;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Api\ApiError;
use Blush\Api\ApiQuery;
use Blush\Api\ApiResponses;
use Blush\Api\ApiValues;
use Blush\Api\MediaResource;
use Blush\Core\AppConfig;
use Blush\Http\Status;
use Blush\Media\MediaException;
use Blush\Menu\MenuException;
use Blush\Menu\MenuItem;
use Blush\Menu\MenuLocation;
use Blush\Menu\Menus;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;

/**
 * Answers `/menus` and `/menus/{location}` (D-736): the active theme's
 * menu locations, and each one's menu as a tree of items, resolved as
 * the site resolves it (D-199, D-676): its links as full URLs, in the
 * site's language, to the location's depth, with what doesn't resolve
 * (a draft, a missing route) left out.
 *
 * A list gives each location's name, label, and the site menu filling
 * it (`null` for the theme's default items). A location's menu is its
 * `location`, `name`, `label`, and `items`, each with its `label`,
 * `url` (`null` for a heading), `description`, `icon`, `image` (a media
 * stub, or `null`), `badge`, `class`, `rel`, the location's `fields`,
 * and its `children`; a location with nothing to show has no items.
 */
final readonly class MenusController
{
	public function __construct(
		private ApiResponses $responses,
		private Menus $menus,
		private ThemeResolver $themes,
		private MediaResource $media,
		private AppConfig $app
	) {}

	public function index(ServerRequestInterface $request): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request): array {
			$query   = ApiQuery::from($request, ['page', 'per_page']);
			$chain   = $this->theme(fn () => $this->themes->active());
			$names   = $this->theme(fn (): array => $this->menus->assignments($chain));
			$items   = array_values(array_map(static fn (MenuLocation $location): array => [
				'location' => $location->name,
				'label'    => $location->label,
				'menu'     => $names[$location->name] ?? null
			], $this->theme(fn (): array => $this->menus->locations($chain))));
			$page    = $query->page();
			$perPage = $query->perPage();

			return $this->responses->page($request, array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $page, $perPage);
		});
	}

	public function show(ServerRequestInterface $request, string $location): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request, $location): array {
			ApiQuery::from($request, []);

			$chain    = $this->theme(fn () => $this->themes->active());
			$declared = $this->theme(fn (): array => $this->menus->locations($chain))[$location]
				?? throw ApiError::notFound(sprintf('The theme has no "%s" menu location.', $location));
			$menu     = $this->menus->forLocation($chain, $location);

			return [
				'location' => $location,
				'name'     => $menu === null || $menu->name === '' ? null : $menu->name,
				'label'    => $menu->label ?? $declared->label,
				'items'    => $menu === null ? [] : array_map($this->item(...), $menu->items)
			];
		});
	}

	/**
	 * Returns a menu item and its children.
	 *
	 * @return array<string, mixed>
	 * @throws MediaException
	 */
	private function item(MenuItem $item): array
	{
		return [
			'label'       => $item->label,
			'url'         => $item->url === null ? null : (str_starts_with($item->url, '/') && ! str_starts_with($item->url, '//') ? $this->app->absoluteUrl($item->url) : $item->url),
			'description' => $item->description,
			'icon'        => $item->icon,
			'image'       => $item->image === '' ? null : $this->media->reference($item->image, false),
			'badge'       => $item->badge,
			'class'       => $item->class,
			'rel'         => $item->rel,
			'fields'      => ApiValues::object(array_map(ApiValues::value(...), $item->fields)),
			'children'    => array_map($this->item(...), $item->children)
		];
	}

	/**
	 * Runs a read of the theme or its menus, as a server error when it
	 * fails.
	 *
	 * @template T
	 * @param  Closure(): T $read
	 * @return T
	 * @throws ApiError
	 */
	private function theme(Closure $read): mixed
	{
		try {
			return $read();
		} catch (MenuException | ThemeException $error) {
			throw new ApiError('server_error', $error->getMessage(), Status::InternalServerError);
		}
	}
}
