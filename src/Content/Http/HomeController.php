<?php

/**
 * Homepage controller.
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
use Blush\Content\Entries;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Http\NotFound;
use Blush\Setup\WelcomeFactory;

/**
 * Serves `/` (`home`) and, with a home type, `/page/{page}`
 * (`home.paged`):
 *
 * 1. With `ContentConfig::$home` set, the home type's collection (1.x's
 *    home alias).
 * 2. Otherwise the root `index.md`, with any `collection` it asks for.
 * 3. Otherwise, on a site with no homepage yet, the welcome page (the
 *    theme's `welcome` view), with its `Welcome` notes.
 */
final class HomeController extends ContentController
{
	public function __construct(
		Entries $content,
		ContentTypes $types,
		ContentUrls $urls,
		PageRenderer $renderer,
		private readonly WelcomeFactory $welcome
	) {
		parent::__construct($content, $types, $urls, $renderer);
	}

	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, int $page = 1, ?string $language = null): ResponseInterface
	{
		$home = $this->types->homeType();

		if ($home !== null) {
			if ($page === 1 && self::isPaged($request)) {
				return self::redirect($request, $this->urls->home($language));
			}

			return $this->renderer->render($this->collectionPage($home, $page, PageKind::Home, $language), $request);
		}

		$index = $this->visible($this->content->named($this->types->forFile('index.md')->name, '', $language));

		if ($index === null && $language !== null) {
			throw new NotFound(sprintf('There is no "%s" homepage.', $language));
		}

		if ($index === null) {
			return $this->renderer->render(new ContentPage(kind: PageKind::Welcome, title: '', welcome: $this->welcome->make()), $request);
		}

		return $this->renderer->render(new ContentPage(
			kind: PageKind::Home,
			title: $index->title,
			entry: $index,
			type: $index->type,
			entries: $this->ownCollection($index),
			base: PageKind::Page,
			alternateUrl: fn (string $code): ?string => $this->visible($this->content->translation($index, $code)) === null ? null : $this->urls->home($code)
		), $request);
	}
}
