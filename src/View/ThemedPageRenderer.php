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
use Blush\Feed\FeedLinks;
use Blush\Http\Response;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;

/**
 * Renders content pages with the request's theme chain: the first view
 * in the page's `Hierarchy`, in a context whose `Head` already has the
 * title, canonical URL, OpenGraph basics, pagination links, and feed
 * links.
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

		$this->describe($context, $page, $request);

		return Response::html($views->render(Hierarchy::forPage($page)->names, [
			'page'    => $page,
			'entry'   => $page->entry,
			'entries' => $page->entries,
			'type'    => $page->type,
			'title'   => $page->title
		], $context));
	}

	/**
	 * Fills in the head and the `<body>` classes for a page.
	 */
	private function describe(ViewContext $context, ContentPage $page, ServerRequestInterface $request): void
	{
		$isFront   = $page->kind === PageKind::Home || $page->kind === PageKind::Welcome;
		$canonical = $this->app->absoluteUrl($request->getUri()->getPath());
		$head      = $context->head->title($isFront ? '' : $page->title)->canonical($canonical);

		$head->property('og:site_name', $this->app->name)
			->property('og:title', $isFront || $page->title === '' ? $this->app->name : $page->title)
			->property('og:type', $page->kind === PageKind::Single ? 'article' : 'website')
			->property('og:url', $canonical);

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
}
