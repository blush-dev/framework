<?php

/**
 * Related list controller.
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
use Blush\Content\Query\EntryCollection;
use Blush\Content\Query\Paginator;
use Blush\Content\RelationArchives;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Http\NotFound;

/**
 * Serves what a type's relation with an archive word links to
 * (`{type}.{relation}.collection`, D-596, D-602), such as `/movies/directors`
 * or `/recipes/cooks`: every published target at least one listed entry
 * of the type links to there, by title, on one page. The page written for
 * the list, `_{word}` in the type's folder (`_cooks.md`), gives it a title
 * and an introduction when it's published; without one, it's the
 * relation's label.
 */
final class RelatedListController extends ContentController
{
	public function __construct(
		ContentRepository $content,
		ContentTypes $types,
		ContentUrls $urls,
		PageRenderer $renderer,
		private readonly RelationArchives $archives
	) {
		parent::__construct($content, $types, $urls, $renderer);
	}

	/**
	 * @throws NotFound
	 */
	public function __invoke(ServerRequestInterface $request, string $type, string $relation): ResponseInterface
	{
		$contentType = $this->type($type);
		$archived    = RelatedController::archived($this->types, $contentType, $relation);
		$listed      = $this->archives->linked($contentType, $archived);
		$written     = RelatedController::page($this->content->named($contentType->name, RelatedController::word($archived)));

		return $this->renderer->render(new ContentPage(
			kind: PageKind::RelatedList,
			title: $written !== null && $written->title !== '' ? $written->title : ($archived->label === '' ? ucfirst(str_replace('_', ' ', $archived->name)) : $archived->label),
			entry: $written,
			type: $contentType,
			entries: new Paginator(EntryCollection::of(...$listed), max(1, count($listed))),
			pageUrl: fn (int $number): ?string => $number === 1 ? $this->urls->relatedList($contentType, $archived) : null,
			relation: $archived
		), $request);
	}
}
