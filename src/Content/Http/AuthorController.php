<?php

/**
 * Author controller.
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
use Blush\Content\Query\InvalidQuery;
use Blush\Http\NotFound;

/**
 * Serves an author's archive in a type (`{type}.authors.single` and
 * `.authors.single.paged`, D-329): the author, real or virtual, with the
 * entries of that type crediting them, listed as the type's listing
 * lists. An author the type's entries don't credit has no archive there.
 */
final class AuthorController extends ContentController
{
	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, string $type, string $author, int $page = 1): ResponseInterface
	{
		$contentType = $this->type($type);
		$authors     = $this->types->authors();
		$entry       = $authors === null ? null : $this->visible($this->content->term($authors->name, $author));

		if ($authors === null || $entry === null || ! $this->urls->hasAuthorArchives($contentType)) {
			throw new NotFound(sprintf('There is no "%s" author "%s".', $type, $author));
		}

		$query = $this->query([...$contentType->listing->arguments(), 'type' => $contentType->name])->whereTerm($authors->name, $entry->slug);

		if ($query->count() === 0) {
			throw new NotFound(sprintf('No "%s" entries credit "%s".', $type, $author));
		}

		if ($page === 1 && self::isPaged($request)) {
			return self::redirect($request, $this->urls->author($contentType, $entry->slug) ?? '/');
		}

		$url = $this->urls->author($contentType, $entry->slug, $page);

		if ($url !== null && $url !== $request->getUri()->getPath()) {
			return self::redirect($request, $url);
		}

		return $this->renderer->render(new ContentPage(
			kind: PageKind::Author,
			title: $entry->title,
			entry: $entry,
			type: $contentType,
			entries: $this->paginate($query, $page),
			pageUrl: fn (int $number): ?string => $this->urls->author($contentType, $entry->slug, $number)
		), $request);
	}
}
