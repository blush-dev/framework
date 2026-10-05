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
use Blush\Content\Type\ContentType;
use Blush\Content\Visibility;
use Blush\Http\NotFound;

/**
 * The page catch-all (`page.single`): serves entries of types without
 * routing (pages, and types with `urls: false`) at their folder path,
 * as 1.x did. `/about` is `about/index.md` or `about.md` (the bundle
 * wins), and `/about/biography` is `about/biography.md`. A path with a
 * `_`-prefixed segment is private, and so a 404.
 *
 * In another language (D-455), the path is the entry's key in that
 * language under its type's path, whose folders are their translations'
 * slugs (D-457): `/fr/a-propos/biographie`.
 */
final class PageController extends ContentController
{
	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, string $path, ?string $language = null): ResponseInterface
	{
		$path = trim($path, '/');

		if (array_any(explode('/', $path), static fn (string $segment): bool => str_starts_with($segment, '_'))) {
			throw new NotFound(sprintf('"%s" is private.', $path));
		}

		$entry = $this->find($this->types->folderPath($path), $language) ?? throw new NotFound(sprintf('There is no page at "%s".', $path));

		return $this->canonicalRedirect($request, $entry) ?? $this->renderer->render(new ContentPage(
			kind: PageKind::Page,
			title: $entry->title,
			entry: $entry,
			type: $entry->type,
			entries: $this->ownCollection($entry),
			alternateUrl: $this->translationUrl($entry)
		), $request);
	}

	/**
	 * Finds the entry at a folder path: the landing page of an unrouted type
	 * whose folder it is, or the entry with its last segment as slug in
	 * the folder above; in another language, its entry by key.
	 */
	private function find(string $path, ?string $language): ?Entry
	{
		if ($language !== null) {
			return $this->translated($path, $language);
		}

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

	/**
	 * Finds the entry of a language at a page path: the key below the
	 * deepest type served as pages whose path the page's is in, with the
	 * type's own path being its landing page.
	 */
	private function translated(string $path, string $language): ?Entry
	{
		$types = array_filter($this->types->all(), static fn (ContentType $type): bool => $type->servedAsPages());

		usort($types, static fn (ContentType $a, ContentType $b): int => strlen($b->pagePath()) <=> strlen($a->pagePath()));

		foreach ($types as $type) {
			$base = $type->pagePath();

			if ($base === '' || $path === $base || str_starts_with($path, "{$base}/")) {
				return $this->visible($this->content->named($type->name, trim(substr($path, strlen($base)), '/'), $language));
			}
		}

		return null;
	}
}
