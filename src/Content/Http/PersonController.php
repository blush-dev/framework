<?php

/**
 * Person controller.
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
 * Serves a person's archive under a type's people field
 * (`{type}.{field}.single` and `.single.paged`, D-351), such as
 * `/recipes/cooks/jane`: the entries of that type crediting them through
 * that field, listed as the type's listing lists. What introduces it
 * resolves in order:
 *
 * 1. the page written for this archive, `_cooks/jane` in the type's
 *    folder, when it's published;
 * 2. the profile's own bio;
 * 3. the profile's name alone (an empty bio).
 *
 * A person the field doesn't credit on any listed entry has no archive.
 */
final class PersonController extends ContentController
{
	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, string $type, string $field, string $profile, int $page = 1): ResponseInterface
	{
		$contentType = $this->type($type);
		$people      = PeopleController::archived($this->urls, $contentType, $field);
		$profiles    = $this->types->profiles();
		$person      = $profiles === null ? null : $this->visible($this->content->term($profiles->name, $profile));

		if ($profiles === null || $person === null) {
			throw new NotFound(sprintf('There is no profile "%s".', $profile));
		}

		$query = $this->query([...$contentType->listing->arguments(), 'type' => $contentType->name])->whereTerm($people->termKey($profiles->name), $person->slug);

		if ($query->count() === 0) {
			throw new NotFound(sprintf('No "%s" entries credit "%s" as %s.', $type, $profile, $people->field));
		}

		if ($page === 1 && self::isPaged($request)) {
			return self::redirect($request, $this->urls->person($contentType, $people, $person->slug) ?? '/');
		}

		$url = $this->urls->person($contentType, $people, $person->slug, $page);

		if ($url !== null && $url !== $request->getUri()->getPath()) {
			return self::redirect($request, $url);
		}

		$written = PeopleController::page($this->content->named($contentType->name, $people->personPage($person->slug)));

		return $this->renderer->render(new ContentPage(
			kind: PageKind::Person,
			title: $written !== null && $written->title !== '' ? $written->title : $person->title,
			entry: $written ?? $person,
			type: $contentType,
			entries: $this->paginate($query, $page),
			pageUrl: fn (int $number): ?string => $this->urls->person($contentType, $people, $person->slug, $number),
			people: $people,
			profile: $person
		), $request);
	}
}
