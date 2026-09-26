<?php

/**
 * Export crawler.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use Closure;
use Dom\HTMLDocument;
use Generator;
use Uri\Rfc3986\Uri;
use Blush\Container\Attributes\Tagged;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Media\MediaConfig;
use Blush\Theme\ThemeChain;

/**
 * Renders a site's URLs through the kernel, for static export (D-136).
 * It runs in the export application, so every page is rendered as
 * production renders it, with absolute URLs on the export's origin.
 *
 * 1. The queue starts with `ExportConfig::$paths` and every tagged
 *    `UrlSource`'s URLs.
 * 2. Each path is requested once. A paged URL that answers 200 queues its
 *    next page, until one doesn't.
 * 3. With `$follow`, the links on each HTML page (`<a>`, `<area>`, and
 *    `<link>` with `rel` `alternate`, `canonical`, `next`, or `prev`)
 *    on the export's origin are queued too, and so is the target of a
 *    redirect.
 *
 * Paths under the media URL and the theme asset URL are never requested,
 * since the exporter copies those files, and neither are excluded ones.
 */
final readonly class Crawler
{
	/**
	 * The `<link>` relations that point at pages.
	 */
	private const array LINK_RELATIONS = ['alternate', 'canonical', 'next', 'prev'];

	/**
	 * A path no site serves, requested for the 404 page.
	 */
	public const string MISSING = '/_' . Framework::BINARY . '-export-404';

	/**
	 * @param list<UrlSource> $sources
	 */
	public function __construct(
		private Kernel $kernel,
		private AppConfig $app,
		private ExportConfig $config,
		private MediaConfig $media,
		#[Tagged(UrlSource::TAG)] private array $sources = []
	) {}

	/**
	 * Renders every URL, yielding each answer (redirects and errors
	 * included). `$progress` gets how many URLs are done and known.
	 *
	 * @param  ?Closure(int, int): void $progress
	 * @return Generator<int, RenderedUrl>
	 */
	public function crawl(bool $follow = true, ?Closure $progress = null): Generator
	{
		/** @var list<array{string, ?string}> $queue Paths and their referrers. */
		$queue = [];
		/** @var array<int, array{ExportUrl, int}> $paging Queued pages of paged URLs: the URL and the page number. */
		$paging = [];
		$seen   = [];
		$ok     = [];

		$enqueue = function (string $path, ?string $referrer) use (&$queue, &$seen): ?int {
			$path = self::clean($path);

			if (isset($seen[$path]) || $this->skips($path)) {
				return null;
			}

			$seen[$path] = count($queue);
			$queue[]     = [$path, $referrer];

			return $seen[$path];
		};

		foreach ($this->config->paths as $path) {
			$enqueue($path, null);
		}

		foreach ($this->sources as $source) {
			foreach ($source->urls() as $url) {
				$index = $enqueue($url->path, null);

				if ($index !== null && $url->page !== null) {
					$paging[$index] = [$url, 1];
				}
			}
		}

		for ($done = 0; $done < count($queue); $done++) {
			[$path, $referrer] = $queue[$done];
			[$url, $page]      = $paging[$done] ?? [null, 1];

			$rendered  = $this->render($path, $referrer);
			$ok[$done] = $rendered->isOk();

			if ($url !== null && $rendered->isOk()) {
				$this->queueNextPage($url, $page, $done, $enqueue, $seen, $ok, $paging);
			}

			if ($rendered->isRedirect()) {
				$target = $this->internalPath((string) $rendered->location, $path);

				if ($target !== null) {
					$enqueue($target, $path);
				}
			}

			if ($follow && $rendered->isOk() && $rendered->isHtml()) {
				foreach ($this->links($rendered->body, $path) as $link) {
					$enqueue($link, $path);
				}
			}

			if ($progress !== null) {
				$progress($done + 1, count($queue));
			}

			// A page past the last is how paging ends, not news.
			if ($page > 1 && $rendered->status === 404) {
				continue;
			}

			yield $rendered;
		}
	}

	/**
	 * Queues the page after a paged URL's rendered page. When that page is
	 * already queued (a page linked to it), it takes over the paging; when
	 * it's already been rendered, the page after it is queued instead.
	 *
	 * @param Closure(string, ?string): ?int     $enqueue
	 * @param array<string, int>                 $seen
	 * @param array<int, bool>                   $ok
	 * @param array<int, array{ExportUrl, int}>  $paging
	 */
	private function queueNextPage(ExportUrl $url, int $page, int $done, Closure $enqueue, array $seen, array $ok, array &$paging): void
	{
		while (($next = $url->page(++$page)) !== null) {
			$index = $seen[self::clean($next)] ?? $enqueue($next, null);

			if ($index === null) {
				return;
			}

			if ($index > $done) {
				$paging[$index] = [$url, $page];

				return;
			}

			if (! ($ok[$index] ?? false)) {
				return;
			}
		}
	}

	/**
	 * Renders the site's 404 page.
	 */
	public function notFound(): RenderedUrl
	{
		return $this->render(self::MISSING, null);
	}

	/**
	 * Requests a path through the kernel.
	 */
	private function render(string $path, ?string $referrer): RenderedUrl
	{
		$response = $this->kernel->handle(Request::create($this->app->origin() . $path));
		$status   = $response->getStatusCode();
		$redirect = $status >= 300 && $status < 400;

		return new RenderedUrl(
			path: $path,
			status: $status,
			contentType: $response->getHeaderLine('Content-Type'),
			body: $redirect ? '' : (string) $response->getBody(),
			location: $redirect ? $response->getHeaderLine('Location') : null,
			referrer: $referrer
		);
	}

	/**
	 * Returns the paths an HTML page links to on the export's origin.
	 *
	 * @return list<string>
	 */
	private function links(string $html, string $path): array
	{
		$document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
		$base     = $document->querySelector('base[href]')?->getAttribute('href') ?? $path;
		$links    = [];

		foreach ($document->querySelectorAll('a[href], area[href], link[href]') as $element) {
			if ($element->localName === 'link') {
				$relations = preg_split('/\s+/', strtolower(trim((string) $element->getAttribute('rel')))) ?: [];

				if (array_intersect($relations, self::LINK_RELATIONS) === []) {
					continue;
				}
			}

			$link = $this->internalPath((string) $element->getAttribute('href'), $base);

			if ($link !== null) {
				$links[] = $link;
			}
		}

		return $links;
	}

	/**
	 * Resolves a URL against a base path and returns its path when it's
	 * on the export's origin, or `null`.
	 */
	private function internalPath(string $url, string $base): ?string
	{
		$origin = Uri::parse($this->app->origin() . '/');
		$base   = $origin === null ? null : Uri::parse($base, $origin);
		$uri    = $base === null ? null : Uri::parse(trim($url), $base);

		if ($origin === null || $uri === null || ! self::isSameOrigin($uri, $origin)) {
			return null;
		}

		return $uri->getPath() === '' ? '/' : $uri->getPath();
	}

	/**
	 * Returns whether two URIs share a scheme, host, and port.
	 */
	private static function isSameOrigin(Uri $uri, Uri $origin): bool
	{
		$port = static fn (Uri $url): ?int => $url->getPort() ?? match (strtolower((string) $url->getScheme())) {
			'http'  => 80,
			'https' => 443,
			default => null
		};

		return strtolower((string) $uri->getScheme()) === strtolower((string) $origin->getScheme())
			&& $uri->getHost() === $origin->getHost()
			&& $port($uri) === $port($origin);
	}

	/**
	 * Returns whether a path isn't requested: excluded, or under the media
	 * or theme asset URL.
	 */
	private function skips(string $path): bool
	{
		return $this->config->excludes($path)
			|| str_starts_with($path, $this->media->url . '/')
			|| str_starts_with($path, ThemeChain::ASSET_URL . '/');
	}

	/**
	 * Returns a path without its query string or fragment, starting with
	 * `/`.
	 */
	private static function clean(string $path): string
	{
		return '/' . ltrim((string) preg_replace('/[?#].*$/s', '', $path), '/');
	}
}
