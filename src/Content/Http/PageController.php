<?php

/**
 * Page catch-all controller.
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
use Blush\Content\Entry\Entry;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Visibility;
use Blush\Http\NotFound;

/**
 * The page catch-all (`page.single`): serves entries of types without
 * routing (pages, and types with `urls: false`) at their folder path,
 * as 1.x did. `/about` is `about/index.md` or `about.md` (the bundle
 * wins), and `/about/biography` is `about/biography.md`. A path with a
 * `_`-prefixed segment is private, and so a 404.
 */
final class PageController extends ContentController
{
	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$path = trim($path, '/');

		if (array_any(explode('/', $path), static fn (string $segment): bool => str_starts_with($segment, '_'))) {
			throw new NotFound(sprintf('"%s" is private.', $path));
		}

		$entry = $this->find($path) ?? throw new NotFound(sprintf('There is no page at "%s".', $path));

		return $this->canonicalRedirect($request, $entry) ?? $this->renderer->render(new ContentPage(
			kind: PageKind::Page,
			title: $entry->title,
			entry: $entry,
			type: $entry->type,
			entries: $this->ownCollection($entry)
		), $request);
	}

	/**
	 * Finds the entry at a path: the landing page of an unrouted type
	 * whose folder it is, or the entry with its last segment as slug in
	 * the folder above.
	 */
	private function find(string $path): ?Entry
	{
		$type = $this->types->byFolder($path);

		if ($type !== null && $type->servedAsPages() && $path !== '') {
			return $this->visible($this->content->named($type->name, ''));
		}

		$slash = strrpos($path, '/');
		$query = $this->content->query()
			->in($slash === false ? '' : substr($path, 0, $slash))
			->names($slash === false ? $path : substr($path, $slash + 1))
			->visibility(Visibility::Public, Visibility::Unlisted);

		return array_find(
			$query->get()->all(),
			fn (Entry $entry): bool => $entry->type->servedAsPages() && $this->visible($entry) !== null
		);
	}
}
