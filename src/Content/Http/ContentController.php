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

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Query\Paginator;
use Blush\Content\Query\Query;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
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
 * - A language's routes (D-455) pass its code as `$language`; `null` is
 *   the default language. A page in a language finds and lists that
 *   language's entries, and with the site's `untranslated` setting at
 *   `include`, the originals of the rest (D-469).
 * - A language's URL for an entry without a published translation in it
 *   redirects to the original (a 302), unless `untranslated` is `hide`
 *   (D-467).
 */
abstract class ContentController
{
	public function __construct(
		protected readonly Entries $content,
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
	 * and the homepage show it.
	 *
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	protected function collectionPage(ContentType $type, int $page, PageKind $kind, ?string $language = null): ContentPage
	{
		$landing = $this->visible($this->content->named($type->name, '', $language));
		$query   = $this->query($type->listingArguments(), self::collectionArguments($landing))->language($language);

		return new ContentPage(
			kind: $kind,
			title: $landing->title ?? '',
			entry: $landing,
			type: $type,
			entries: $this->paginate($query, $page),
			pageUrl: fn (int $number): ?string => $this->urls->collection($type, $number, $language),
			base: $kind === PageKind::Home ? PageKind::Collection : null,
			language: $language,
			alternateUrl: fn (string $code): ?string => ($page === 1 && $this->visible($this->content->named($type->name, '', $code)) !== null) || $this->listsPage($query, $page, $code)
				? $this->urls->collection($type, $page, $code)
				: null
		);
	}

	/**
	 * Returns what gives an entry's URL path in a language (D-461): its
	 * translation's, when that's published and routable.
	 *
	 * @return Closure(string): ?string
	 */
	protected function translationUrl(Entry $entry): Closure
	{
		return function (string $language) use ($entry): ?string {
			$translation = $this->visible($this->content->translation($entry, $language));

			return $translation === null ? null : $this->urls->entry($translation);
		};
	}

	/**
	 * Returns a temporary redirect for a language's URL to an entry with
	 * no published translation in it (D-467): to its original, or to its
	 * translation when the URL used the original's key. `null` when the
	 * site's `untranslated` setting is `hide` or there's no original.
	 */
	protected function untranslated(ServerRequestInterface $request, ContentType $type, string $key, string $language, AppConfig $app): ?ResponseInterface
	{
		if (! $app->untranslated->redirects()) {
			return null;
		}

		$original = $this->original($type, trim($key, '/'), $language, $app->languages->default->code);
		$target   = $original === null ? null : $this->visible($this->content->translation($original, $language)) ?? $original;
		$url      = $target === null ? null : $this->urls->entry($target);
		$query    = $request->getUri()->getQuery();

		return $url === null ? null : Response::redirect($query === '' ? $url : "{$url}?{$query}", Status::Found);
	}

	/**
	 * Returns the published original an entry's key in a language names:
	 * the original of a translation that isn't published, the default
	 * language's entry with the key, or, below translated folders
	 * (D-457), the entry in the deepest one's original folder.
	 */
	private function original(ContentType $type, string $key, string $language, string $default): ?Entry
	{
		$entry = $this->content->named($type->name, $key, $language);

		if ($entry !== null) {
			return $this->visible($this->content->translation($entry, $default));
		}

		$original = $this->visible($this->content->named($type->name, $key));

		if ($original !== null || ! str_contains($key, '/')) {
			return $original;
		}

		$segments = explode('/', $key);

		for ($count = count($segments) - 1; $count > 0; $count--) {
			$folder = $this->content->named($type->name, implode('/', array_slice($segments, 0, $count)), $language);
			$source = $folder === null ? null : $this->content->translation($folder, $default);

			if ($source !== null) {
				return $this->visible($this->content->named($type->name, $source->key . '/' . implode('/', array_slice($segments, $count))));
			}
		}

		return null;
	}

	/**
	 * Returns whether a listing's query has a page in a language: page 1
	 * when it finds anything, and a later page when it finds more than
	 * the pages before it hold.
	 */
	protected function listsPage(Query $query, int $page, string $language): bool
	{
		$count = $query->language($language)->limit(null)->offset(0)->count();

		return $page === 1 ? $count > 0 : $query->limit !== null && $count > ($page - 1) * max(1, $query->limit);
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

		$entries = $this->query($arguments)->language($entry->language)->get();

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
