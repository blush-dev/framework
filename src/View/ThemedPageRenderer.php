<?php

/**
 * Themed page renderer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;
use Blush\Content\Http\PageRenderer;
use Blush\Core\AppConfig;
use Blush\Data\InvalidData;
use Blush\Feed\FeedLinks;
use Blush\Http\Response;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;

/**
 * Renders content pages with the request's theme chain: the first view
 * in the page's `Hierarchy`, in a context whose `Head` already has the
 * title, canonical URL, OpenGraph basics, pagination links, and feed
 * links. Later pages of a listing add the page number to the title.
 * When the page shows an entry, the head also gets its
 * description (its summary, or the start of its body) and its `image`
 * field as `og:image`, with a Twitter card (D-149). Themes can replace
 * any of them, since a later value for the same tag wins.
 *
 * Templates get `$page` (the `ContentPage`), `$entry`, `$entries` (a
 * `Paginator` or `null`), `$type`, and `$title`, plus the shared `$site`.
 */
final readonly class ThemedPageRenderer implements PageRenderer
{
	public function __construct(
		private ThemeResolver $themes,
		private ViewFactory $views,
		private AppConfig $app,
		private FeedLinks $feeds
	) {}

	/**
	 * @inheritDoc
	 *
	 * @throws ThemeException
	 * @throws ViewException
	 */
	#[Override]
	public function render(ContentPage $page, ServerRequestInterface $request): ResponseInterface
	{
		$views   = $this->views->forChain($this->themes->forRequest($request));
		$context = $this->views->context($views, $page->entry);

		$this->describe($views, $context, $page, $request);

		$context->share([
			'page'    => $page,
			'entry'   => $page->entry,
			'entries' => $page->entries,
			'type'    => $page->type,
			'title'   => $page->title
		]);

		return Response::html($views->render(Hierarchy::forPage($page)->names, [], $context));
	}

	/**
	 * Fills in the head and the `<body>` classes for a page.
	 */
	private function describe(Views $views, ViewContext $context, ContentPage $page, ServerRequestInterface $request): void
	{
		$isFront   = $page->kind === PageKind::Home || $page->kind === PageKind::Welcome;
		$canonical = $this->app->absoluteUrl($request->getUri()->getPath());
		$head      = $context->head->title($this->title($views, $page, $isFront))->canonical($canonical);

		$head->property('og:site_name', $this->app->name)
			->property('og:title', $isFront || $page->title === '' ? $this->app->name : $page->title)
			->property('og:type', $page->kind === PageKind::Single ? 'article' : 'website')
			->property('og:url', $canonical);

		$this->describeEntry($head, $page);

		$entries = $page->entries;

		foreach (['prev' => $entries?->previous(), 'next' => $entries?->next()] as $rel => $number) {
			$url = $number === null ? null : $page->pageUrl($number);

			if ($url !== null) {
				$head->link($rel, $url);
			}
		}

		foreach ($this->feeds->forPage($page) as [$url, $format, $title]) {
			$head->link('alternate', $url, ['type' => $format->mediaType(), 'title' => $title]);
		}

		$context->addClass("is-{$page->kind->value}");

		if ($page->type !== null) {
			$context->addClass("type-{$page->type->name}");
		}

		if ($entries !== null && $entries->page > 1) {
			$context->addClass('is-paged');
		}
	}

	/**
	 * Returns the page's title for the head: none on the front page, and
	 * the page number on later pages of a listing ("Blog: Page 2", or
	 * "Page 2" on the front page), so each page's `<title>` is its own.
	 * The wording is the `blush` catalog's `document_title.paged` and
	 * `document_title.page`, which a theme's catalog can override.
	 *
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	private function title(Views $views, ContentPage $page, bool $isFront): string
	{
		$title  = $isFront ? '' : $page->title;
		$number = $page->entries->page ?? 1;

		if ($number < 2) {
			return $title;
		}

		$key    = $title === '' ? 'document_title.page' : 'document_title.paged';
		$domain = $views->translator->has($key, 'theme') ? 'theme' : 'blush';

		return $views->translator->translate($key, ['title' => $title, 'page' => $number], $domain);
	}

	/**
	 * Adds the page entry's description and image to the head.
	 */
	private function describeEntry(Head $head, ContentPage $page): void
	{
		$entry = $page->entry;

		if ($entry === null || $entry->isVirtual()) {
			return;
		}

		$description = trim(html_entity_decode(strip_tags($entry->excerpt(30)), ENT_QUOTES | ENT_HTML5));

		if ($description !== '') {
			$head->meta('description', $description)->property('og:description', $description);
		}

		$image = $entry->field('image');

		if (is_string($image) && $image !== '') {
			$url = preg_match('#^https?://#i', $image) === 1 ? $image : $this->app->absoluteUrl($image);

			$head->property('og:image', $url)->meta('twitter:card', 'summary_large_image');
		}
	}
}
