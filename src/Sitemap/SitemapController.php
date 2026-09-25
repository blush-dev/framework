<?php

/**
 * Sitemap controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\Routing\ContentUrls;
use Blush\Http\NotFound;
use Blush\Http\Response;
use Blush\Theme\ThemeException;
use Blush\View\DocumentRenderer;
use Blush\View\ViewException;

/**
 * Serves the sitemap index (`/sitemap`, with `sitemap-index`) and each
 * type's sitemap (`/sitemap/{type}`, with `sitemap`), rendered by the
 * theme (D-029). Templates get `$sitemaps` or `$urls`, lists of
 * `SitemapUrl`.
 */
final readonly class SitemapController
{
	public function __construct(
		private SitemapBuilder $builder,
		private ContentUrls $urls,
		private DocumentRenderer $documents
	) {}

	/**
	 * @throws NotFound
	 * @throws ThemeException
	 * @throws ViewException
	 */
	public function __invoke(ServerRequestInterface $request, ?string $type = null): ResponseInterface
	{
		if ($type === null) {
			$sitemaps = array_map(
				fn (array $sitemap): SitemapUrl => new SitemapUrl($this->urls->absolute($sitemap[0]), $sitemap[1]),
				$this->builder->index()
			);

			return Response::xml($this->documents->render($request, ['sitemap-index'], ['sitemaps' => $sitemaps]));
		}

		$contentType = $this->builder->types()[$type] ?? throw new NotFound(sprintf('There is no "%s" sitemap.', $type));

		return Response::xml($this->documents->render($request, ["sitemap-{$type}", 'sitemap'], ['urls' => $this->builder->urls($contentType)]));
	}
}
