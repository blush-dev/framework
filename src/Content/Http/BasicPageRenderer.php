<?php

/**
 * Basic content page renderer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\Routing\ContentUrls;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Markdown\MarkdownException;

/**
 * A plain, unstyled HTML page for any content page: the title, the
 * entry's body, the listed entries as links, and pagination. It keeps
 * content URLs working until themes arrive in M5, which replace it by
 * binding `PageRenderer`.
 */
final readonly class BasicPageRenderer implements PageRenderer
{
	public function __construct(
		private ContentUrls $urls,
		private AppConfig $app
	) {}

	/**
	 * @inheritDoc
	 *
	 * @throws MarkdownException
	 */
	#[Override]
	public function render(ContentPage $page, ServerRequestInterface $request): ResponseInterface
	{
		$title = $page->kind === PageKind::Home || $page->title === ''
			? $this->app->name
			: "{$page->title} | {$this->app->name}";

		$html  = '<!DOCTYPE html>' . "\n";
		$html .= sprintf('<html lang="%s">', self::e(str_replace('_', '-', $this->app->locale)));
		$html .= sprintf('<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>%s</title></head>', self::e($title));
		$html .= '<body><main>';

		if ($page->title !== '') {
			$html .= sprintf('<h1>%s</h1>', self::e($page->title));
		}

		if ($page->entry !== null) {
			$html .= $page->entry->body();
		}

		if ($page->entries !== null && ! $page->entries->entries->isEmpty()) {
			$html .= '<ul>';

			foreach ($page->entries as $entry) {
				$url   = $this->urls->entry($entry);
				$label = self::e($entry->title !== '' ? $entry->title : $entry->slug);

				$html .= $url === null ? "<li>{$label}</li>" : sprintf('<li><a href="%s">%s</a></li>', self::e($url), $label);
			}

			$html .= '</ul>';
			$html .= $this->pagination($page);
		}

		$html .= '</main></body></html>' . "\n";

		return Response::html($html);
	}

	/**
	 * Returns the previous and next page links.
	 */
	private function pagination(ContentPage $page): string
	{
		$entries  = $page->entries;
		$previous = $entries?->previous() === null ? null : $page->pageUrl($entries->previous());
		$next     = $entries?->next() === null ? null : $page->pageUrl($entries->next());

		if ($previous === null && $next === null) {
			return '';
		}

		return '<nav>'
			. ($previous === null ? '' : sprintf('<a rel="prev" href="%s">Previous</a> ', self::e($previous)))
			. ($next === null ? '' : sprintf('<a rel="next" href="%s">Next</a>', self::e($next)))
			. '</nav>';
	}

	/**
	 * Escapes text for HTML.
	 */
	private static function e(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
