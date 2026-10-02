<?php

/**
 * People controller.
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
use Blush\Content\PeopleArchives;
use Blush\Content\Query\EntryCollection;
use Blush\Content\Query\Paginator;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\PeopleField;
use Blush\Http\NotFound;

/**
 * Serves the people a type's people field credits
 * (`{type}.{field}.collection`, D-351), such as `/recipes/cooks`: every
 * published profile with at least one listed entry of the type crediting
 * them there, by name, on one page. The field's own page (`_cooks` in the
 * type's folder, hidden from everything else) gives it a title and an
 * introduction; without one, it's the field's plural label.
 */
final class PeopleController extends ContentController
{
	public function __construct(
		ContentRepository $content,
		ContentTypes $types,
		ContentUrls $urls,
		PageRenderer $renderer,
		private readonly PeopleArchives $archives
	) {
		parent::__construct($content, $types, $urls, $renderer);
	}

	/**
	 * @throws NotFound
	 */
	public function __invoke(ServerRequestInterface $request, string $type, string $field): ResponseInterface
	{
		$contentType = $this->type($type);
		$people      = self::archived($this->urls, $contentType, $field);
		$page        = self::page($this->content->named($contentType->name, $people->listPage()));
		$listed      = $this->archives->credited($contentType, $people);

		return $this->renderer->render(new ContentPage(
			kind: PageKind::People,
			title: $page->title ?? $people->plural,
			entry: $page,
			type: $contentType,
			entries: new Paginator(EntryCollection::of(...$listed), max(1, count($listed))),
			pageUrl: fn (int $number): ?string => $number === 1 ? $this->urls->people($contentType, $people) : null,
			people: $people
		), $request);
	}

	/**
	 * Returns a type's people field when it has archives, or a 404.
	 *
	 * @throws NotFound
	 */
	public static function archived(ContentUrls $urls, ContentType $type, string $field): PeopleField
	{
		$people = $type->peopleField($field);

		return $people !== null && $urls->hasArchive($type, $people)
			? $people
			: throw new NotFound(sprintf('"%s" has no "%s" archives.', $type->name, $field));
	}

	/**
	 * Returns a people page (a field's list page, or one written for a
	 * person's archive) when it's published.
	 */
	public static function page(?Entry $entry): ?Entry
	{
		return $entry !== null && $entry->isPublished() ? $entry : null;
	}
}
