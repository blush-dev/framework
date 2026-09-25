<?php

/**
 * Content controller base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Query\Paginator;
use Blush\Content\Query\Query;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Http\NotFound;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * What the content controllers share: building listings from 1.x query
 * arguments (the type's, then the landing page's or term's `collection`
 * front matter), paging them, and keeping URLs canonical.
 *
 * - A page past the last one is a 404; `/page/1` redirects to the
 *   listing's first page.
 * - Page 1 of an empty listing still renders; date archives are the
 *   exception, since an empty one isn't a real page.
 * - A single entry reached by a URL that isn't its own (a wrong date in
 *   the path, say) redirects to its URL.
 */
abstract class ContentController
{
	public function __construct(
		protected readonly ContentRepository $content,
		protected readonly ContentTypes $types,
		protected readonly ContentUrls $urls,
		protected readonly PageRenderer $renderer
	) {}

	/**
	 * Returns a type, or a 404.
	 *
	 * @throws NotFound
	 */
	protected function type(string $name): ContentType
	{
		return $this->types->find($name) ?? throw new NotFound(sprintf('There is no "%s" content type.', $name));
	}

	/**
	 * Returns the page of a type's collection, as `CollectionController`
	 * and the home page show it.
	 *
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	protected function collectionPage(ContentType $type, int $page, PageKind $kind): ContentPage
	{
		$landing = $this->visible($this->content->named($type->name, ''));
		$query   = $this->query(
			['type' => $type->collect === false ? $type->name : $type->collect],
			$type->collection,
			self::collectionArguments($landing)
		);

		return new ContentPage(
			kind: $kind,
			title: $landing->title ?? '',
			entry: $landing,
			type: $type,
			entries: $this->paginate($query, $page),
			pageUrl: fn (int $number): ?string => $this->urls->collection($type, $number),
			base: $kind === PageKind::Home ? PageKind::Collection : null
		);
	}

	/**
	 * Builds a query from sets of 1.x arguments, later sets winning.
	 *
	 * @param  array<array-key, mixed> ...$sets
	 * @throws InvalidQuery
	 */
	protected function query(array ...$sets): Query
	{
		return Query::fromArray(array_merge(...$sets), $this->content);
	}

	/**
	 * Returns one page of a query's entries.
	 *
	 * @throws NotFound When the page is past the last one.
	 */
	protected function paginate(Query $query, int $page): Paginator
	{
		if ($query->limit === null) {
			if ($page > 1) {
				throw new NotFound(sprintf('Page %d is past the last page.', $page));
			}

			$entries = $query->get();

			return new Paginator($entries, max(1, count($entries)));
		}

		$paginator = $query->paginate(max(1, $query->limit), $page);

		return $paginator->isOutOfRange()
			? throw new NotFound(sprintf('Page %d is past the last page.', $page))
			: $paginator;
	}

	/**
	 * Returns the entries an entry's own `collection` front matter asks
	 * for, all on one page, or `null`.
	 *
	 * @throws InvalidQuery
	 */
	protected function ownCollection(Entry $entry): ?Paginator
	{
		$arguments = self::collectionArguments($entry);

		if ($arguments === []) {
			return null;
		}

		$entries = $this->query($arguments)->get();

		return new Paginator($entries, max(1, count($entries)));
	}

	/**
	 * Returns whether the request asked for a page number in its path.
	 */
	protected static function isPaged(ServerRequestInterface $request): bool
	{
		return $request->getAttribute('page') !== null;
	}

	/**
	 * Returns a redirect to a URL path, keeping the query string.
	 */
	protected static function redirect(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$query = $request->getUri()->getQuery();

		return Response::redirect($query === '' ? $path : "{$path}?{$query}", Status::MovedPermanently);
	}

	/**
	 * Returns a redirect when an entry was reached by a URL that isn't its
	 * own, or `null`.
	 */
	protected function canonicalRedirect(ServerRequestInterface $request, Entry $entry): ?ResponseInterface
	{
		$url = $this->urls->entry($entry);

		return $url !== null && $url !== $request->getUri()->getPath() ? self::redirect($request, $url) : null;
	}

	/**
	 * Returns the entry if the public may see it at its URL: published,
	 * and not hidden.
	 */
	protected function visible(?Entry $entry): ?Entry
	{
		return $entry !== null && $entry->isPublished() && $entry->isRoutable() ? $entry : null;
	}

	/**
	 * Returns an entry's `collection` front matter, or `[]`.
	 *
	 * @return array<array-key, mixed>
	 */
	protected static function collectionArguments(?Entry $entry): array
	{
		$arguments = $entry?->field('collection');

		return is_array($arguments) ? $arguments : [];
	}
}
