<?php

/**
 * API entries controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api\Http;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Api\ApiEntries;
use Blush\Api\ApiError;
use Blush\Api\ApiIncludes;
use Blush\Api\ApiQuery;
use Blush\Api\ApiResponses;
use Blush\Api\EntryResource;
use Blush\Auth\Account;
use Blush\Auth\Token;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Query;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\Relations;
use Blush\Content\Status;
use Blush\Core\AppConfig;
use Blush\Routing\RouteTable;
use Blush\Storage\Record\Order;
use Blush\Support\Uuid;

/**
 * Answers `/entries` and `/entries/{id}` (D-737 to D-740).
 *
 * A list is filtered by plain parameters: `type` (commas for several),
 * `rel:{relation}` (targets' ids or slugs, any of them), `parent` (an
 * id), `published_after` and `published_before`, `updated_after`,
 * `search`, and `ids`; sorted by `sort` (`published`, `title`,
 * `position`, or `updated`, `-` first for descending; one type's own
 * order by default, else newest first); and paged by `page` and
 * `per_page`. `?url=` finds the entry at a site address instead, with
 * only `body` and `include` beside it: a list of it alone, or empty,
 * with `redirect` when the address redirects. Lists leave the body out
 * unless `body` asks for it; a single entry has its HTML.
 *
 * `status` (D-745), with an API token: entries of those statuses its
 * token may edit, whatever their visibility, in place of what the site
 * shows; on a list and on a single entry. Without a token, it's a
 * `401`. With a token, an entry it may edit has its `revision`, for a
 * write to send back in `If-Match` (D-762).
 */
final readonly class EntriesController
{
	/**
	 * What `sort` takes, without the `-`.
	 *
	 * @var list<string>
	 */
	public const array SORTS = ['published', 'title', 'position', 'updated'];

	/**
	 * What a list takes besides `rel:{relation}`.
	 *
	 * @var list<string>
	 */
	private const array LIST = ['type', 'parent', 'published_after', 'published_before', 'updated_after', 'search', 'ids', 'url', 'sort', 'page', 'per_page', 'body', 'include', 'status'];

	public function __construct(
		private ApiResponses $responses,
		private ApiEntries $entries,
		private EntryResource $resource,
		private Relations $relations,
		private RouteTable $routes,
		private AppConfig $app
	) {}

	public function index(ServerRequestInterface $request): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request): array {
			$query   = ApiQuery::from($request, self::LIST, relations: true);
			$body    = $query->choice('body', EntryResource::BODIES, 'none');
			$include = ApiIncludes::from($query->list('include'));

			if ($query->has('url')) {
				return $this->at($request, $query, $body, $include);
			}

			$page     = $query->page();
			$perPage  = $query->perPage();
			$statuses = $this->statuses($request, $query);
			$base     = $statuses === null ? $this->entries->query() : $this->entries->editable(...$statuses);

			[$entries, $total] = $this->find($base, $query, $page, $perPage);

			return $this->responses->page(
				$request,
				array_map(fn (Entry $entry): array => $this->item($request, $entry, $body, $include), $entries),
				$total,
				$page,
				$perPage
			);
		});
	}

	public function show(ServerRequestInterface $request, string $id): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request, $id): array {
			$query    = ApiQuery::from($request, ['body', 'include', 'status']);
			$statuses = $this->statuses($request, $query);
			$entry    = match (true) {
				! Uuid::isValid($id) => null,
				$statuses === null   => $this->entries->find($id),
				default              => $this->entries->findEditable($id, ...$statuses)
			};

			if ($entry === null) {
				throw ApiError::notFound(sprintf('No entry has the id "%s".', $id));
			}

			return $this->item($request, $entry, $query->choice('body', EntryResource::BODIES, 'html'), ApiIncludes::from($query->list('include')));
		});
	}

	/**
	 * Returns an entry as the API sends it, with its revision when the
	 * request's token may edit it.
	 *
	 * @return array<string, mixed>
	 * @throws \Blush\Media\MediaException
	 */
	private function item(ServerRequestInterface $request, Entry $entry, string $body, ApiIncludes $include): array
	{
		$item    = $this->resource->item($entry, $body, $include);
		$token   = $request->getAttribute(Token::class);
		$account = $request->getAttribute(Account::class);

		if ($token instanceof Token && $account instanceof Account) {
			$revision = $this->entries->revision($entry, $token, $account);

			if ($revision !== null) {
				$item['revision'] = $revision;
			}
		}

		return $item;
	}

	/**
	 * Returns what `status` asks for, with the request's token and its
	 * account, or `null` when it isn't asked (D-745). Asking needs a
	 * token.
	 *
	 * @return ?array{Token, Account, list<Status>}
	 * @throws ApiError For a request without a token, or a status that isn't one.
	 */
	private function statuses(ServerRequestInterface $request, ApiQuery $query): ?array
	{
		if (! $query->has('status')) {
			return null;
		}

		$token   = $request->getAttribute(Token::class);
		$account = $request->getAttribute(Account::class);

		if (! $token instanceof Token || ! $account instanceof Account) {
			throw ApiError::unauthorized('token_required', '"status" finds entries a token may edit, so it needs one, sent in the Authorization header as "Bearer {token}".');
		}

		$statuses = [];

		foreach ($query->list('status') as $value) {
			$statuses[] = Status::tryFrom($value) ?? throw ApiError::invalidQuery(sprintf('"status" is one or more of: %s.', implode(', ', array_column(Status::cases(), 'value'))));
		}

		return $statuses === [] ? throw ApiError::invalidQuery('"status" needs a status.') : [$token, $account, array_values(array_unique($statuses, SORT_REGULAR))];
	}

	/**
	 * Answers `?url=`: the entry at a site address, or an empty list,
	 * with where the address redirects when it does.
	 *
	 * @return array<string, mixed>
	 * @throws ApiError
	 */
	private function at(ServerRequestInterface $request, ApiQuery $query, string $body, ApiIncludes $include): array
	{
		$others = array_diff(array_keys($query->all()), ['url', 'body', 'include']);

		if ($others !== []) {
			throw ApiError::invalidQuery(sprintf('"url" finds one entry, so it takes only body and include beside it, not %s.', implode(', ', $others)));
		}

		$address  = (string) $query->string('url');
		$entry    = $this->entries->at($address);
		$path     = ApiEntries::path($address, $this->app->origin());
		$redirect = $entry === null && $path !== null ? $this->routes->redirectFor($path) : null;
		$items    = $entry === null ? [] : [$this->item($request, $entry, $body, $include)];

		return [
			...$this->responses->page($request, $items, count($items), 1, ApiQuery::PER_PAGE),
			...($redirect === null ? [] : ['redirect' => ['to' => preg_match('#^[a-z][a-z0-9+.-]*://#i', $redirect->to) === 1 ? $redirect->to : $this->app->absoluteUrl($redirect->to), 'status' => $redirect->status->value]])
		];
	}

	/**
	 * Returns a page of the entries a list's filters find among a query's,
	 * and how many they find in all.
	 *
	 * @return array{list<Entry>, int}
	 * @throws ApiError
	 */
	private function find(Query $search, ApiQuery $query, int $page, int $perPage): array
	{
		$zone   = $this->app->timezone();
		$types  = $query->list('type');

		foreach ($types as $type) {
			if (! isset($this->entries->types()[$type])) {
				throw ApiError::invalidQuery(sprintf('There is no "%s" type.', $type));
			}
		}

		if ($types !== []) {
			$search = $search->type(...$types);
		}

		foreach ($query->relations() as $name => $values) {
			$search = $this->related($search, $name, $values);
		}

		if ($query->has('parent')) {
			$parent = $this->entries->find((string) $query->string('parent'));

			if ($parent === null) {
				return [[], 0];
			}

			$search = $search->whereParent($parent);
		}

		$updated = $query->date('updated_after', $zone);
		$search  = $search->search($query->string('search'))->updatedSince($updated?->getTimestamp());

		[$key, $order] = $this->sort($query, $types);
		$search        = $search->orderBy($key, $order);

		$after  = $query->date('published_after', $zone);
		$before = $query->date('published_before', $zone);
		$ids    = array_map(strtolower(...), $query->list('ids'));

		foreach ($ids as $id) {
			if (! Uuid::isValid($id)) {
				throw ApiError::invalidQuery(sprintf('"%s" in "ids" isn\'t an id.', $id));
			}
		}

		if ($after === null && $before === null && $ids === []) {
			$paginator = $search->paginate($perPage, $page);

			return [$paginator->all(), $paginator->total()];
		}

		$found = array_values(array_filter(
			[...$search->get()],
			static fn (Entry $entry): bool => ($ids === [] || in_array(strtolower((string) $entry->id), $ids, true))
				&& self::within($entry->published, $after, $before)
		));

		return [array_slice($found, ($page - 1) * $perPage, $perPage), count($found)];
	}

	/**
	 * Returns a query limited to entries linking, through relations by a
	 * name, to any of the targets given by id or slug.
	 *
	 * @param  list<string> $values
	 * @throws ApiError When no relation entries list has the name.
	 */
	private function related(Query $search, string $name, array $values): Query
	{
		$keys = [];

		foreach ($this->relations->relations as $relation) {
			if ($relation->name === $name && self::filters($relation)) {
				$keys[] = (string) $relation->termKey();
			}
		}

		if ($keys === []) {
			throw ApiError::invalidQuery(sprintf('There is no "%s" relation to filter by.', $name));
		}

		$slugs = array_map(fn (string $value): string => Uuid::isValid($value) ? ($this->entries->find($value)->slug ?? "\0") : $value, $values);

		return $search->whereAnyTerm(array_values(array_unique($keys)), ...$slugs);
	}

	/**
	 * Returns the sort asked for, or the default: one type's own order,
	 * else newest published first.
	 *
	 * @param  list<string> $types
	 * @return array{string, Order}
	 * @throws ApiError
	 */
	private function sort(ApiQuery $query, array $types): array
	{
		$sort = $query->string('sort');

		if ($sort === null) {
			return count($types) === 1 ? $this->entries->types()[$types[0]]->order() : ['published', Order::Desc];
		}

		$key = ltrim($sort, '-');

		return in_array($key, self::SORTS, true)
			? [$key, str_starts_with($sort, '-') ? Order::Desc : Order::Asc]
			: throw ApiError::invalidQuery(sprintf('"sort" is one of: %s, with - first for descending.', implode(', ', self::SORTS)));
	}

	/**
	 * Returns whether a date falls on or after `$after` and before
	 * `$before`; an entry without one falls within neither.
	 */
	private static function within(?DateTimeImmutable $date, ?DateTimeImmutable $after, ?DateTimeImmutable $before): bool
	{
		if ($after === null && $before === null) {
			return true;
		}

		return $date !== null && ($after === null || $date >= $after) && ($before === null || $date < $before);
	}

	/**
	 * Returns whether a relation is filtered on by its name.
	 */
	public static function filters(Relation $relation): bool
	{
		return EntryResource::lists($relation) && $relation->termKey() !== null;
	}
}
