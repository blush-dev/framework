<?php

/**
 * Admin entries controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AuthConfig;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Order;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Taxonomy;
use Blush\Http\Response;
use Blush\Http\Status as HttpStatus;

/**
 * Answers `GET {path}/api/entries` (D-225, D-230): the entries the
 * account may edit, a page at a time, so an author sees their own and an
 * editor everyone's. The query string narrows the list:
 *
 * - `status`: `draft`, `scheduled`, `published`, or `any` (the default).
 * - `type`: a content type's name.
 * - `search`: text the title or file path must contain (any case).
 * - `page` (from 1) and `per` (20 by default, at most 100).
 *
 * Drafts and the whole list come most recently changed first, scheduled
 * entries soonest first, and published entries newest first. The
 * permission rules, the filters, and paging all run in the index as one
 * query (`Permissions::restrict()`), so only the page's entries are built.
 * Terms also say how many published entries use them (`uses`, D-236).
 */
final readonly class EntriesController
{
	/**
	 * How many entries a page holds unless `per` says otherwise.
	 */
	public const int PER_PAGE = 20;

	/**
	 * The most entries a page may hold.
	 */
	public const int MAX_PER_PAGE = 100;

	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private Permissions $permissions,
		private AuthConfig $config
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], HttpStatus::Unauthorized);
		}

		$params = $request->getQueryParams();
		$value  = $params['status'] ?? 'any';
		$status = is_string($value) ? Status::tryFrom($value) : null;

		if ($status === null && $value !== 'any') {
			return self::json(['error' => '"status" must be draft, scheduled, published, or any.'], HttpStatus::BadRequest);
		}

		$type = $params['type'] ?? null;

		if ($type !== null && (! is_string($type) || ! $this->types->has($type))) {
			return self::json(['error' => 'There is no such content type.'], HttpStatus::BadRequest);
		}

		$search = $params['search'] ?? '';

		if (! is_string($search)) {
			return self::json(['error' => '"search" must be text.'], HttpStatus::BadRequest);
		}

		$page = self::positive($params['page'] ?? '1');
		$per  = self::positive($params['per'] ?? (string) self::PER_PAGE);

		if ($page === null || $per === null || $per > self::MAX_PER_PAGE) {
			return self::json(['error' => sprintf('"page" must be a whole number from 1, and "per" from 1 to %d.', self::MAX_PER_PAGE)], HttpStatus::BadRequest);
		}

		$query = $this->content->query()->any()->search($search);
		$query = $status === null ? $query : $query->status($status);
		$query = $type === null ? $query : $query->type($type);
		$query = match ($status) {
			Status::Scheduled => $query->orderBy('published', Order::Asc),
			Status::Published => $query->orderBy('published', Order::Desc),
			default           => $query->orderBy('updated', Order::Desc)
		};

		$entries = $this->permissions->restrict($account, Capability::ContentEdit, $query)->paginate($per, $page);
		$counts  = [];

		// How many published entries use each term on the page, one pass
		// per taxonomy (D-236).
		foreach ($entries->all() as $entry) {
			if ($entry->type instanceof Taxonomy) {
				$counts[$entry->type->name] ??= $this->content->termCounts($entry->type->name);
			}
		}

		return self::json([
			'status'  => $status->value ?? 'any',
			'type'    => $type,
			'search'  => trim($search),
			'total'   => $entries->total(),
			'page'    => $page,
			'pages'   => $entries->pages(),
			'per'     => $per,
			'entries' => array_map(fn (Entry $entry): array => $this->describe($account, $entry, $counts), $entries->all())
		]);
	}

	/**
	 * Returns what the admin shows of an entry. A term's `uses` is how
	 * many published entries reference it; other entries' is `null`.
	 *
	 * @param  array<string, array<string, int>> $counts Term counts by taxonomy.
	 * @return array<string, mixed>
	 */
	private function describe(Account $account, Entry $entry, array $counts): array
	{
		return [
			'id'        => $entry->id,
			'title'     => $entry->title,
			'type'      => $entry->type->name,
			'status'    => $entry->status->value,
			'published' => $entry->published?->format(DateTimeInterface::ATOM),
			'updated'   => $entry->updated->format(DateTimeInterface::ATOM),
			'path'      => $entry->source?->path,
			'authors'   => $entry->terms($this->config->authorTaxonomy),
			'own'       => $this->permissions->owns($account, $entry),
			'uses'      => $entry->type instanceof Taxonomy ? ($counts[$entry->type->name][$entry->key] ?? 0) : null
		];
	}

	/**
	 * Reads a whole number from 1 up, or `null` when it isn't one.
	 */
	private static function positive(mixed $value): ?int
	{
		return is_string($value) && preg_match('/^[1-9][0-9]{0,8}$/', $value) === 1 ? (int) $value : null;
	}

	/**
	 * Returns a JSON answer the browser won't cache.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, HttpStatus $status = HttpStatus::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
