<?php

/**
 * Related controller.
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
use Blush\Content\Relation\Relation;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Http\NotFound;

/**
 * Serves a target's archive under a type's relation with an archive word
 * (`{type}.{relation}.single` and `.single.paged`, D-596, D-602), such as
 * `/movies/directors/penny` or `/recipes/cooks/jane`: the entries of that
 * type linking to it through that relation, listed as the type's listing
 * lists. What introduces it resolves in order:
 *
 * 1. the page written for this archive, `_{word}/{slug}` in the type's
 *    folder (`_cooks/jane.md`), when it's published;
 * 2. the target's own entry (a profile's bio).
 *
 * A target nothing links to there has no archive.
 */
final class RelatedController extends ContentController
{
	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, string $type, string $relation, string $target, int $page = 1): ResponseInterface
	{
		$contentType = $this->type($type);
		$archived    = self::archived($this->types, $contentType, $relation);
		$entry       = $this->visible($this->content->named($archived->to[0], basename($target)));
		$key         = (string) $archived->termKey();

		if ($entry === null) {
			throw new NotFound(sprintf('There is no %s "%s".', $archived->to[0], $target));
		}

		$query = $this->query([...$contentType->listing->arguments(), 'type' => $contentType->name])->whereTerm($key, $entry->slug);

		if ($query->count() === 0) {
			throw new NotFound(sprintf('No "%s" entries link to "%s" in %s.', $type, $target, $relation));
		}

		if ($page === 1 && self::isPaged($request)) {
			return self::redirect($request, $this->urls->related($contentType, $archived, $entry->slug) ?? '/');
		}

		$url = $this->urls->related($contentType, $archived, $entry->slug, $page);

		if ($url !== null && $url !== $request->getUri()->getPath()) {
			return self::redirect($request, $url);
		}

		$written = self::page($this->content->named($contentType->name, self::word($archived) . "/{$entry->slug}"));

		return $this->renderer->render(new ContentPage(
			kind: PageKind::Related,
			title: $written !== null && $written->title !== '' ? $written->title : $entry->title,
			entry: $written ?? $entry,
			type: $contentType,
			entries: $this->paginate($query, $page),
			pageUrl: fn (int $number): ?string => $this->urls->related($contentType, $archived, $entry->slug, $number),
			relation: $archived,
			target: $entry
		), $request);
	}

	/**
	 * Returns the key, in a type's folder, of a relation archive's own
	 * pages: `_{word}` introduces its list, and `_{word}/{slug}` one
	 * target's archive (D-602).
	 */
	public static function word(Relation $relation): string
	{
		return '_' . ($relation->inverse === false ? $relation->name : (string) $relation->inverse->archive);
	}

	/**
	 * Returns an archive's own page when it's published.
	 */
	public static function page(?Entry $entry): ?Entry
	{
		return $entry !== null && $entry->isPublished() ? $entry : null;
	}

	/**
	 * Returns a type's relation when it has archives under the type, or a
	 * 404.
	 *
	 * @throws NotFound
	 */
	public static function archived(ContentTypes $types, ContentType $type, string $relation): Relation
	{
		return $types->relationArchives($type)[$relation] ?? throw new NotFound(sprintf('"%s" has no "%s" archives.', $type->name, $relation));
	}
}
