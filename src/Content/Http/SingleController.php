<?php

/**
 * Single entry controller.
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
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Http\NotFound;

/**
 * Serves one entry of a routed type (`{type}.single`). The entry is found
 * by its slug; if the rest of the URL (a date, an author, a term) doesn't
 * match the entry, the request redirects to the entry's own URL. Drafts,
 * scheduled entries, and hidden entries are 404s.
 */
final class SingleController extends ContentController
{
	public function __construct(
		ContentRepository $content,
		ContentTypes $types,
		ContentUrls $urls,
		PageRenderer $renderer,
		private readonly AppConfig $app
	) {
		parent::__construct($content, $types, $urls, $renderer);
	}

	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, string $type, string $name, ?string $language = null): ResponseInterface
	{
		$contentType = $this->type($type);
		$entry       = $this->visible($this->content->named($contentType->name, $name, $language));

		if ($entry === null && $language !== null) {
			$redirect = $this->untranslated($request, $contentType, $name, $language, $this->app);

			if ($redirect !== null) {
				return $redirect;
			}
		}

		if ($entry === null || $entry->landing) {
			throw new NotFound(sprintf('There is no "%s" entry named "%s".', $type, $name));
		}

		return $this->canonicalRedirect($request, $entry) ?? $this->renderer->render(new ContentPage(
			kind: PageKind::Single,
			title: $entry->title,
			entry: $entry,
			type: $entry->type,
			entries: $this->ownCollection($entry),
			alternateUrl: $this->translationUrl($entry)
		), $request);
	}
}
