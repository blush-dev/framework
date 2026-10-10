<?php

/**
 * Admin menus controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use stdClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Status as EntryStatus;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;
use Blush\Content\Visibility;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Menu\Link\LinksEntry;
use Blush\Menu\Link\MenuLinkFactory;
use Blush\Menu\Link\UnresolvedLink;
use Blush\Menu\MenuException;
use Blush\Menu\MenuLoader;
use Blush\Menu\MenuRecord;
use Blush\Menu\Menus;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteTable;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;
use Blush\Translation\LocaleMap;

/**
 * The Menus screens' API (from the menus sketch), for accounts with
 * `menus.edit`. Menus are named by their name; locations are the active
 * theme's.
 *
 * - `GET menus`: every menu (its name, label, item count, and the
 *   locations showing it), and the theme's locations (label, depth, how
 *   many default items it ships, and the menu assigned), with the link
 *   kinds and the site's locale, for a new menu's screen.
 * - `GET menus/{name}`: a menu's items as a tree of nodes, each its
 *   stored keys (`item`, without `children`), what its link leads to now
 *   (`link`: kind, value, title, address, and state), and its children;
 *   with the locations, the link kinds, and every menu's name and label. A menu
 *   whose stored shape is wrong comes back `editable: false`, with its
 *   problems, so saving can't drop what the screen can't show.
 * - `POST menus`: writes a menu whole, `{was, name, label, items,
 *   locations}`: a new one when `was` is `null`, else `was` replaced and
 *   renamed when `name` differs. The theme's locations listed show it
 *   afterward, and those it leaves go back to their default.
 * - `DELETE menus/{name}`: removes a menu, and its assignments; answers
 *   what it was, for Undo to post back.
 * - `PUT menu-locations/{location}`: assigns a location `{menu}`, or its
 *   default with `null`, at once.
 * - `GET menu-links?search=`: what an item can link to, matching text:
 *   entries (terms of types with term pages as terms), collections, and
 *   named routes without parameters, the best matches first (the title
 *   itself, then titles starting with it, then a word in them), and
 *   among those listings, terms, pages, other entries, then routes; with
 *   how many matched (`total`).
 */
final readonly class MenusController
{
	/**
	 * How many links a search offers.
	 */
	private const int LINKS = 10;

	/**
	 * How many entries of each group a search ranks.
	 */
	private const int CANDIDATES = 100;

	/**
	 * The order kinds of links come in among matches that rank the same:
	 * a type's listing, a term, a tree's page, any other entry, a route.
	 */
	private const int COLLECTION = 0;

	private const int TERM = 1;

	private const int PAGE = 2;

	private const int ENTRY = 3;

	private const int ROUTE = 4;

	public function __construct(
		private Menus $menus,
		private MenuLoader $loader,
		private MenuLinkFactory $links,
		private ThemeResolver $themes,
		private Permissions $permissions,
		private Entries $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private RouteTable $routes,
		private AppConfig $app
	) {}

	public function index(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error('You aren\'t allowed to edit menus.', Status::Forbidden);
		}

		try {
			return self::json($this->overview($this->themes->active()));
		} catch (MenuException | ThemeException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}
	}

	public function show(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error('You aren\'t allowed to edit menus.', Status::Forbidden);
		}

		try {
			$chain = $this->themes->active();
			$menu  = $this->menus->menus()[$name] ?? null;

			if ($menu === null) {
				return self::error(sprintf('There\'s no "%s" menu.', $name), Status::NotFound);
			}

			return self::json($this->detail($chain, $menu));
		} catch (MenuException | ThemeException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}
	}

	public function save(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error('You aren\'t allowed to edit menus.', Status::Forbidden);
		}

		$input     = self::input($request);
		$was       = $input['was'] ?? null;
		$name      = $input['name'] ?? null;
		$label     = $input['label'] ?? null;
		$items     = $input['items'] ?? null;
		$locations = $input['locations'] ?? [];

		if (($was !== null && ! is_string($was)) || ! is_string($name) || ! is_array($items) || ! array_is_list($items) || ! is_array($locations) || ! array_is_list($locations) || ! array_all($locations, static fn (mixed $location): bool => is_string($location))) {
			return self::error('Send the menu as {was, name, label, items, locations}.', Status::BadRequest);
		}

		if ($label !== null && $label !== '' && LocaleMap::text($label, $this->app->locale, $this->app->locale) === null) {
			return self::error('A menu\'s label is text.', Status::UnprocessableContent);
		}

		if (preg_match(MenuLoader::NAME, $name) !== 1) {
			return self::error('A menu\'s name uses lowercase letters, digits, hyphens, and underscores, and starts with a letter or digit.', Status::UnprocessableContent);
		}

		$problems = [];
		$this->check($items, '', $problems);

		if ($problems !== []) {
			return self::json(['error' => $problems[0], 'problems' => $problems], Status::UnprocessableContent);
		}

		try {
			$chain    = $this->themes->active();
			$declared = $this->menus->locations($chain);
			$current  = $this->menus->assignments($chain);

			if ($was !== $name && isset($this->menus->menus()[$name])) {
				return self::error(sprintf('There\'s already a menu named "%s". Pick another name.', $name), Status::Conflict);
			}

			$this->loader->save($name, is_string($label) ? trim($label) : $label, $items, $was);

			$assign = [];

			foreach (array_keys($declared) as $location) {
				$showing = $current[$location] ?? null;

				if (in_array($location, $locations, true)) {
					$assign[$location] = $name;
				} elseif ($showing !== null && ($showing === $name || $showing === $was)) {
					$assign[$location] = null;
				}
			}

			// A location the theme no longer declares follows a rename.
			foreach ($current as $location => $showing) {
				if (! isset($declared[$location]) && $was !== null && $was !== $name && $showing === $was) {
					$assign[$location] = $name;
				}
			}

			$assign = array_filter($assign, static fn (?string $menu, string $location): bool => ($current[$location] ?? null) !== $menu, ARRAY_FILTER_USE_BOTH);

			if ($assign !== []) {
				$this->menus->assignAll($chain, $assign);
			}

			$menu = $this->menus->menus()[$name] ?? throw new MenuException(sprintf('The menu "%s" wasn\'t saved.', $name));

			return self::json($this->detail($chain, $menu));
		} catch (MenuException | ThemeException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}
	}

	public function delete(ServerRequestInterface $request, string $name): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error('You aren\'t allowed to edit menus.', Status::Forbidden);
		}

		try {
			$chain = $this->themes->active();
			$menu  = $this->menus->menus()[$name] ?? null;

			if ($menu === null) {
				return self::error(sprintf('There\'s no "%s" menu.', $name), Status::NotFound);
			}

			$showing = array_keys(array_filter($this->menus->assignments($chain), static fn (string $menu): bool => $menu === $name));

			$this->loader->delete($name);

			if ($showing !== []) {
				$this->menus->assignAll($chain, array_fill_keys($showing, null));
			}

			return self::json([
				'deleted'   => ['name' => $menu->name, 'label' => $menu->label, 'items' => $menu->items],
				'locations' => $showing
			]);
		} catch (MenuException | ThemeException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}
	}

	public function assign(ServerRequestInterface $request, string $location): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error('You aren\'t allowed to edit menus.', Status::Forbidden);
		}

		$menu = self::input($request)['menu'] ?? null;

		if ($menu !== null && ! is_string($menu)) {
			return self::error('Send {menu}: a menu\'s name, or null for the location\'s default.', Status::BadRequest);
		}

		try {
			$chain = $this->themes->active();

			if (! isset($this->menus->locations($chain)[$location])) {
				return self::error(sprintf('The active theme has no "%s" location.', $location), Status::NotFound);
			}

			if ($menu !== null && ! isset($this->menus->menus()[$menu])) {
				return self::error(sprintf('There\'s no "%s" menu.', $menu), Status::NotFound);
			}

			$this->menus->assign($chain, $location, $menu);

			return self::json($this->overview($chain));
		} catch (MenuException | ThemeException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}
	}

	public function links(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->allowed($request)) {
			return self::error('You aren\'t allowed to edit menus.', Status::Forbidden);
		}

		$search = $request->getQueryParams()['search'] ?? '';

		if (! is_string($search)) {
			return self::error('"search" must be text.', Status::BadRequest);
		}

		$search = trim($search);
		$links  = [];
		$groups = [];

		// Terms, a tree's pages, and the rest are each asked for apart,
		// so a site's many posts can't crowd out the term or page that
		// matches best.
		foreach ($this->types->all() as $type) {
			$group = match (true) {
				$this->types->hasTermPages($type->name) => self::TERM,
				$type instanceof Tree                   => self::PAGE,
				default                                 => self::ENTRY
			};

			$groups[$group][] = $type->name;
		}

		foreach ($groups as $order => $names) {
			$entries = $this->content->query()
				->type(...$names)
				->status(EntryStatus::Published, EntryStatus::Draft, EntryStatus::Scheduled)
				->search($search === '' ? null : $search)
				->limit($search === '' ? self::LINKS : self::CANDIDATES)
				->get();

			$kind = $order === self::TERM ? 'term' : 'entry';
			$link = $this->links->make($kind);

			if (! $link instanceof LinksEntry) {
				continue;
			}

			foreach ($entries as $entry) {
				if ($entry->id === null) {
					continue;
				}

				$links[] = [
					'kind'    => $kind,
					'value'   => $link->value($entry),
					'ref'     => $entry->id,
					'title'   => $entry->title,
					'address' => $entry->isPublished() ? $this->urls->entry($entry) ?? '' : '',
					'state'   => self::state($entry),
					'message' => null,
					'order'   => $order
				];
			}
		}

		foreach ($this->types->all() as $type) {
			if (! $type->public || ! self::matches($search, $type->labels->plural, $type->name)) {
				continue;
			}

			$address = $this->urls->collection($type);

			if ($address !== null) {
				$links[] = ['kind' => 'collection', 'value' => $type->name, 'ref' => null, 'title' => $type->labels->plural, 'address' => $address, 'state' => 'live', 'message' => null, 'order' => self::COLLECTION];
			}
		}

		foreach ($this->routes->routes() as $route) {
			$name = $route->name;

			if ($name === null || $route->priority === RoutePriority::System || $route->pattern->params !== [] || ! in_array('GET', $route->methods, true) || ! self::matches($search, $name, $route->pattern->path)) {
				continue;
			}

			$links[] = ['kind' => 'route', 'value' => $name, 'ref' => null, 'title' => $name, 'address' => $route->pattern->path, 'state' => 'live', 'message' => null, 'order' => self::ROUTE];
		}

		$needle = mb_strtolower($search);

		usort($links, static fn (array $a, array $b): int => [self::rank($a['title'], $needle), $a['order'], 0] <=> [self::rank($b['title'], $needle), $b['order'], strnatcasecmp($a['title'], $b['title'])]);

		return self::json([
			'links' => array_map(static fn (array $link): array => array_diff_key($link, ['order' => true]), array_slice($links, 0, self::LINKS)),
			'total' => count($links)
		]);
	}

	/**
	 * The menus and the theme's locations.
	 *
	 * @return array<string, mixed>
	 * @throws MenuException
	 */
	private function overview(ThemeChain $chain): array
	{
		$assigned = $this->menus->assignments($chain);
		$menus    = [];

		foreach ($this->menus->menus() as $name => $menu) {
			$menus[] = [
				'name'      => $name,
				'label'     => $this->labelText($menu->label),
				'items'     => self::count($menu->items),
				'locations' => array_keys(array_filter($assigned, static fn (string $showing): bool => $showing === $name))
			];
		}

		return ['theme' => $chain->active()->label, 'menus' => $menus, 'locations' => $this->locations($chain, $assigned), 'kinds' => $this->kinds(), 'locale' => $this->app->locale];
	}

	/**
	 * A menu, for its screen.
	 *
	 * @return array<string, mixed>
	 * @throws MenuException
	 */
	private function detail(ThemeChain $chain, MenuRecord $menu): array
	{
		$assigned = $this->menus->assignments($chain);
		$problems = $menu->problems;
		$nodes    = $this->nodes($menu->items, '', $problems);

		if ($menu->label !== null && LocaleMap::text($menu->label, $this->app->locale, $this->app->locale) === null) {
			$problems[] = '"label" must be text or a map of locales to text.';
		}

		return [
			'menu'      => [
				'name'     => $menu->name,
				'label'    => $menu->label ?? '',
				'where'    => $menu->location,
				'items'    => $nodes,
				'editable' => $problems === [],
				'problems' => $problems
			],
			'theme'     => $chain->active()->label,
			'locations' => $this->locations($chain, $assigned),
			'menus'     => array_map(fn (MenuRecord $other): array => ['name' => $other->name, 'label' => $this->labelText($other->label)], array_values($this->menus->menus())),
			'kinds'     => $this->kinds(),
			'locale'    => $this->app->locale
		];
	}

	/**
	 * Each link kind's key, and the other item keys it reads.
	 *
	 * @return list<array{key: string, keys: list<string>}>
	 * @throws MenuException
	 */
	private function kinds(): array
	{
		return array_map(fn (string $key): array => ['key' => $key, 'keys' => $this->links->make($key)->keys()], $this->links->keys());
	}

	/**
	 * The theme's locations, with the menu each shows.
	 *
	 * @param  array<string, string> $assigned
	 * @return list<array<string, mixed>>
	 * @throws MenuException
	 */
	private function locations(ThemeChain $chain, array $assigned): array
	{
		$locations = [];

		foreach ($this->menus->locations($chain) as $name => $location) {
			$locations[] = [
				'name'     => $name,
				'label'    => $location->label !== '' ? $location->label : $name,
				'depth'    => $location->depth,
				'defaults' => self::count($location->items),
				'menu'     => $assigned[$name] ?? null
			];
		}

		return $locations;
	}

	/**
	 * Items as nodes for the screen, adding what can't be shown to
	 * `$problems`.
	 *
	 * @param  list<mixed>  $items
	 * @param  list<string> $problems
	 * @return list<array<string, mixed>>
	 */
	private function nodes(array $items, string $trail, array &$problems): array
	{
		$nodes = [];

		foreach ($items as $index => $item) {
			$position = ltrim("{$trail}." . ($index + 1), '.');

			if (! is_array($item) || ($item !== [] && array_is_list($item))) {
				$problems[] = "Item {$position} isn't a map of keys to values.";
				continue;
			}

			/** @var array<string, mixed> $item */
			$children = $item['children'] ?? [];
			unset($item['children']);

			if (! is_array($children) || ! array_is_list($children)) {
				$problems[] = "Item {$position} has \"children\" that aren't a list.";
				$children = [];
			}

			$linkKeys = array_values(array_intersect(array_keys($item), $this->links->keys()));

			if (count($linkKeys) > 1) {
				$problems[] = sprintf('Item %s has more than one link (%s).', $position, implode(', ', $linkKeys));
			}

			$nodes[] = [
				'item'     => $item === [] ? new stdClass() : $item,
				'link'     => count($linkKeys) === 1 ? $this->describe($linkKeys[0], $item) : null,
				'children' => $this->nodes($children, $position, $problems)
			];
		}

		return $nodes;
	}

	/**
	 * What an item's link leads to now.
	 *
	 * @param  array<string, mixed> $item
	 * @return array<string, mixed>
	 */
	private function describe(string $key, array $item): array
	{
		$link  = $this->links->make($key);
		$value = $item[$key];
		$text  = is_scalar($value) ? trim((string) $value) : '';
		$found = ['kind' => $key, 'value' => $text, 'ref' => null, 'title' => '', 'address' => '', 'state' => 'live', 'message' => null];

		$invalid = $link->validate($value, $item);

		if ($invalid !== null) {
			return [...$found, 'state' => 'missing', 'message' => "\"{$key}\" {$invalid}"];
		}

		if ($link instanceof LinksEntry) {
			$entry = $link->entry($text, $item);

			if ($entry === null) {
				return [...$found, 'state' => 'missing', 'message' => sprintf('Nothing on the site is "%s" anymore.', $text)];
			}

			$found = [...$found, 'ref' => $entry->id, 'title' => $entry->title, 'state' => self::state($entry)];
		}

		$found['title'] = match ($key) {
			'collection' => $this->types->find($text)?->labels->plural ?? $text,
			'url'        => self::host($text),
			'route'      => $found['title'] !== '' ? $found['title'] : $text,
			default      => $found['title'] !== '' ? $found['title'] : $text
		};

		try {
			$target = $link->resolve($text, $item, $this->app->locale);

			return [...$found, 'title' => $target->label !== '' && $key === 'collection' ? $target->label : $found['title'], 'address' => $target->url];
		} catch (UnresolvedLink $error) {
			return [...$found, 'state' => $found['state'] === 'live' ? 'missing' : $found['state'], 'message' => $error->getMessage()];
		}
	}

	/**
	 * Adds what keeps items from being saved to `$problems`: an item that
	 * isn't a map, has more than one link or one that isn't written right,
	 * or has neither a link nor a label.
	 *
	 * @param list<mixed>  $items
	 * @param list<string> $problems
	 */
	private function check(array $items, string $trail, array &$problems): void
	{
		foreach ($items as $index => $item) {
			$position = ltrim("{$trail}." . ($index + 1), '.');

			if (! is_array($item) || ($item !== [] && array_is_list($item))) {
				$problems[] = "Item {$position} isn't a map of keys to values.";
				continue;
			}

			/** @var array<string, mixed> $item */
			$linkKeys = array_values(array_intersect(array_keys($item), $this->links->keys()));
			$label    = LocaleMap::text($item['label'] ?? '', $this->app->locale, $this->app->locale);

			if (count($linkKeys) > 1) {
				$problems[] = sprintf('Item %s has more than one link (%s).', $position, implode(', ', $linkKeys));
			} elseif ($linkKeys !== [] && ($invalid = $this->links->make($linkKeys[0])->validate($item[$linkKeys[0]], $item)) !== null) {
				$problems[] = "Item {$position}'s link {$invalid}";
			} elseif ($linkKeys === [] && trim($label ?? '') === '') {
				$problems[] = "Item {$position} needs a label or a link.";
			}

			$children = $item['children'] ?? [];

			if (! is_array($children) || ! array_is_list($children)) {
				$problems[] = "Item {$position} has \"children\" that aren't a list.";
			} else {
				$this->check($children, $position, $problems);
			}
		}
	}

	/**
	 * A label in the site's language, or `''`.
	 */
	private function labelText(mixed $label): string
	{
		return trim(LocaleMap::text($label ?? '', $this->app->locale, $this->app->locale) ?? '');
	}

	/**
	 * Whether the signed-in account may edit menus.
	 */
	private function allowed(ServerRequestInterface $request): bool
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::MenusEdit);
	}

	/**
	 * How many items there are, at every level.
	 *
	 * @param list<mixed> $items
	 */
	private static function count(array $items): int
	{
		$count = 0;

		foreach ($items as $item) {
			$count++;

			if (is_array($item) && is_array($item['children'] ?? null) && array_is_list($item['children'])) {
				$count += self::count($item['children']);
			}
		}

		return $count;
	}

	/**
	 * An entry's state, for a link to it.
	 */
	private static function state(Entry $entry): string
	{
		return match (true) {
			$entry->status === EntryStatus::Trash     => 'trash',
			$entry->status === EntryStatus::Draft     => 'draft',
			$entry->status === EntryStatus::Scheduled => 'scheduled',
			$entry->visibility === Visibility::Hidden => 'hidden',
			default                                   => 'live'
		};
	}

	/**
	 * A URL's host, or the URL when it's a path.
	 */
	private static function host(string $url): string
	{
		$host = parse_url($url, PHP_URL_HOST);

		return is_string($host) && $host !== '' ? preg_replace('/^www\./', '', $host) ?? $host : $url;
	}

	/**
	 * How well a title matches a search, as the relation pickers rank
	 * (D-607), with the title itself first: 0 when it's the search, 1
	 * when it starts with it, 2 when a word in it does, 3 otherwise.
	 */
	private static function rank(string $title, string $search): int
	{
		$title = mb_strtolower(trim($title));

		return match (true) {
			$search === '' || $title === $search                                             => 0,
			str_starts_with($title, $search)                                                 => 1,
			preg_match('/(?<![\p{L}\p{N}])' . preg_quote($search, '/') . '/u', $title) === 1 => 2,
			default                                                                          => 3
		};
	}

	/**
	 * Whether any of the texts contains the search, in any case.
	 */
	private static function matches(string $search, string ...$texts): bool
	{
		return $search === '' || array_any($texts, static fn (string $text): bool => stripos($text, $search) !== false);
	}

	/**
	 * The request's JSON object, or `[]`.
	 *
	 * @return array<mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? $input : [];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return self::json(['error' => $message], $status);
	}
}
