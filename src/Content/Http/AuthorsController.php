<?php

/**
 * Authors controller.
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
use Blush\Content\AuthorArchives;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\EntryCollection;
use Blush\Content\Query\Paginator;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Http\NotFound;

/**
 * Serves the authors a type's entries credit (`{type}.authors.collection`,
 * D-329): every published author with at least one listed entry of the
 * type, alphabetically by name, on one page. The type's own authors page
 * (`_authors` in its folder, hidden from everything else) gives it a
 * title and an introduction; without one, it's the authors type's plural
 * label.
 */
final class AuthorsController extends ContentController
{
	/**
	 * The key of a type's authors page: `_authors` in its folder.
	 */
	public const string PAGE = '_authors';

	public function __construct(
		ContentRepository $content,
		ContentTypes $types,
		ContentUrls $urls,
		PageRenderer $renderer,
		private readonly AuthorArchives $archives
	) {
		parent::__construct($content, $types, $urls, $renderer);
	}

	/**
	 * @throws NotFound
	 */
	public function __invoke(ServerRequestInterface $request, string $type): ResponseInterface
	{
		$contentType = $this->type($type);
		$authors     = $this->types->authors();

		if ($authors === null || ! $this->urls->hasAuthorArchives($contentType)) {
			throw new NotFound(sprintf('"%s" has no author archives.', $type));
		}

		$page   = self::page($this->content->named($contentType->name, self::PAGE));
		$listed = $this->archives->authors($contentType);

		return $this->renderer->render(new ContentPage(
			kind: PageKind::Authors,
			title: $page->title ?? $authors->labels->plural,
			entry: $page,
			type: $contentType,
			entries: new Paginator(EntryCollection::of(...$listed), max(1, count($listed))),
			pageUrl: fn (int $number): ?string => $number === 1 ? $this->urls->authors($contentType) : null
		), $request);
	}

	/**
	 * Returns a type's authors page, when it's published.
	 */
	public static function page(?Entry $entry): ?Entry
	{
		return $entry !== null && $entry->isPublished() ? $entry : null;
	}
}
