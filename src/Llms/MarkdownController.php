<?php

/**
 * Markdown page controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Llms;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\Routing\ContentUrls;
use Blush\Http\NotFound;
use Blush\Http\Response;

/**
 * Serves a page's Markdown version (`llms.markdown`, any path ending in
 * `.md`), with a `Link` header naming the page as canonical, so search
 * engines index the page rather than its copy.
 */
final readonly class MarkdownController
{
	public function __construct(
		private MarkdownPages $pages,
		private ContentUrls $urls
	) {}

	/**
	 * @throws NotFound
	 */
	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$path  = $request->getUri()->getPath();
		$entry = $this->pages->find($path) ?? throw new NotFound(sprintf('There is no Markdown page at "%s".', $path));
		$url   = $this->urls->entry($entry);
		$link  = $url === null ? [] : ['Link' => sprintf('<%s>; rel="canonical"', $this->urls->absolute($url))];

		return Response::text($this->pages->render($entry), headers: [
			'Content-Type' => MarkdownPages::MEDIA_TYPE . '; charset=UTF-8',
			...$link
		]);
	}
}
