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
use Blush\Content\Entry\Entry;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;
use Blush\Content\Http\PageRenderer;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Data\InvalidData;
use Blush\Event\Dispatcher;
use Blush\Feed\FeedLinks;
use Blush\Http\Response;
use Blush\Llms\MarkdownPages;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;
use Blush\View\Events\PageRendering;

/**
 * Renders content pages with the request's theme chain: the first view
 * in the page's `Hierarchy`, in a context whose `Head` already has the
 * title, canonical URL, OpenGraph basics, pagination links, feed links,
 * and, on an entry's own URL, its Markdown version's (D-395). Later
 * pages of a listing add the page number to the title. When the page
 * shows an entry, the head also gets its
 * description (its summary, or the start of its body) and its `image`
 * field as `og:image`, with a Twitter card (D-149); a front page
 * without one gets the site's description (D-398). Themes can replace
 * any of them, since a later value for the same tag wins. Then
 * `PageRendering` is dispatched (D-571), for plugins to load assets.
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
		private FeedLinks $feeds,
		private ContentTypes $types,
		private ContentUrls $urls,
		private MarkdownPages $markdown,
		private Dispatcher $events
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
		$context = $this->views->context($views, $page->entry, $request->getUri()->getPath(), $page->language);

		$this->describe($views, $context, $page, $request);

		$context->share([
			'page'    => $page,
			'entry'   => $page->entry,
			'entries' => $page->entries,
			'type'    => $page->type,
			'title'   => $page->title
		]);

		$this->events->dispatch(new PageRendering($context, $request, $views->chain, $page, $page->entry));

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
			->property('og:type', match ($page->kind) {
				PageKind::Single                     => 'article',
				PageKind::Person, PageKind::Profile => 'profile',
				default                              => 'website'
			})
			->property('og:url', $canonical);

		if ($page->kind === PageKind::Single && $page->entry !== null) {
			$this->describeByline($head, $page->entry);
		}

		if (! $this->describeEntry($head, $page) && $isFront && $this->app->description !== '') {
			$head->meta('description', $this->app->description)->property('og:description', $this->app->description);
		}

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

		$markdown = $page->entry === null || $this->urls->entry($page->entry) !== $request->getUri()->getPath() ? null : $this->markdown->url($page->entry);

		if ($markdown !== null) {
			$head->link('alternate', $markdown, ['type' => MarkdownPages::MEDIA_TYPE]);
		}

		$this->describeLanguages($head, $page, $request->getUri()->getPath());

		$context->addClass("is-{$page->kind->value}");

		if ($page->type !== null) {
			$context->addClass("type-{$page->type->name}");
		}

		if ($entries !== null && $entries->page > 1) {
			$context->addClass('is-paged');
		}
	}

	/**
	 * Adds `hreflang` alternates (D-461) when the page is in more than one
	 * language: a link for each language it's in, its own included, by
	 * the language's BCP 47 tag (`fr-FR`), and `x-default` for the
	 * default language's, when it has one.
	 */
	private function describeLanguages(Head $head, ContentPage $page, string $path): void
	{
		$languages = $this->app->languages;

		if (! $languages->isMultilingual() || $page->alternateUrl === null) {
			return;
		}

		$current = $page->language ?? $page->entry->language ?? $languages->default->code;
		$urls    = [];

		foreach (array_keys($languages->all()) as $code) {
			$url = $code === $current ? $path : $page->alternateUrl($code);

			if ($url !== null) {
				$urls[$code] = $url;
			}
		}

		if (count($urls) < 2) {
			return;
		}

		foreach ($urls as $code => $url) {
			$head->link('alternate', $url, ['hreflang' => $languages->find($code)?->tag() ?? $code]);
		}

		$default = $urls[$languages->default->code] ?? null;

		if ($default !== null) {
			$head->link('alternate', $default, ['hreflang' => 'x-default']);
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

		$key = $title === '' ? 'document_title.page' : 'document_title.paged';

		// The theme's wording first, then the framework's.
		return $views->messages->with('blush')->translate($key, ['title' => $title, 'page' => $number]);
	}

	/**
	 * Adds an article's byline to the head (D-351): for each profile its
	 * type's first people field credits, their archive under that field,
	 * else their profile's page, the URL that stands for the person.
	 */
	private function describeByline(Head $head, Entry $entry): void
	{
		$profiles = $this->types->profiles();
		$field    = array_first($entry->type->people);

		if ($profiles === null || $field === null) {
			return;
		}

		foreach ($entry->terms($field->termKey($profiles->name)) as $slug) {
			$url = $this->urls->byline($entry, $field->field, $slug);

			if ($url !== null) {
				$head->addProperty('article:author', $this->app->absoluteUrl($url));
			}
		}
	}

	/**
	 * Adds the page entry's description and image to the head, and
	 * returns whether it had a description.
	 */
	private function describeEntry(Head $head, ContentPage $page): bool
	{
		$entry = $page->entry;

		if ($entry === null) {
			return false;
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

		return $description !== '';
	}
}
