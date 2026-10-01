<?php

/**
 * Exporter.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use Closure;
use Blush\Cache\ContentVersion;
use Blush\Content\Index\IndexException;
use Blush\Content\Index\Indexer;
use Blush\Content\Source\UnreadableSource;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Core\Paths;
use Blush\Event\Dispatcher;
use Blush\Export\Events\ExportFinished;
use Blush\Export\Events\ExportStarted;
use Blush\Export\Host\HostContext;
use Blush\Export\Host\HostFilesFactory;
use Blush\Routing\RouteConfig;
use Blush\Routing\RouteTable;
use Blush\Support\Filesystem;

/**
 * Exports the site to static files (D-011): what `build` runs, and later
 * the admin.
 *
 * 1. Reindex incrementally, as publishing does.
 * 2. Boot the export application (`ExportSite`) for the export's origin:
 *    the `$url` given, `ExportConfig::$url`, or the site's own.
 * 3. Copy `public/`'s files. Then, unless `$incremental` finds the last
 *    export current (same folder, origin, content version, and
 *    `ExportFingerprint`, with its files in place; D-139), render every
 *    URL the crawler finds (`ExportLayout` decides each file), the 404
 *    page, a page at each redirected path (`ExportConfig::$redirectPages`),
 *    and the host files (D-140). A current export keeps its rendered
 *    files instead.
 * 4. Copy theme assets and media.
 * 5. Remove what the previous export wrote and this one didn't, and
 *    record this one in the manifest.
 *
 * `ExportStarted` and `ExportFinished` are dispatched around it. Only one
 * export runs at a time (`storage/cache/export.lock`), and the output
 * folder can't be one of the site's own folders.
 */
final readonly class Exporter
{
	public function __construct(
		private ExportSite $site,
		private ExportConfig $config,
		private ExportFingerprint $fingerprint,
		private AppConfig $app,
		private Paths $paths,
		private Indexer $indexer,
		private ContentVersion $version,
		private Dispatcher $events,
		private Filesystem $filesystem
	) {}

	/**
	 * Exports the site. `$url` overrides the export's origin and `$crawl`
	 * `ExportConfig::$crawl`; `$incremental` skips rendering when nothing
	 * changed; `$progress` gets how many URLs are done and known.
	 *
	 * @param  ?Closure(int, int): void $progress
	 * @throws ExportException When the export can't run.
	 * @throws IndexException
	 * @throws UnreadableSource
	 */
	public function export(?string $url = null, ?bool $crawl = null, bool $incremental = false, ?Closure $progress = null): ExportReport
	{
		$start = hrtime(true);
		$url   = rtrim($url ?? $this->config->url ?? $this->app->origin(), '/');
		$root  = $this->filesystem->normalize($this->paths->export);
		$crawl ??= $this->config->crawl;

		if (! ExportConfig::isOrigin($url)) {
			throw new ExportException(sprintf('The export URL must be an http(s) origin, such as "https://example.com"; "%s" given.', $url));
		}

		$this->assertSafe($root);

		$lock = $this->lock();

		try {
			$index       = $this->indexer->index();
			$version     = $this->version->current();
			$fingerprint = $this->fingerprint->compute($url, $crawl);
			$site        = $this->site->boot($url);

			$this->events->dispatch(new ExportStarted($root, $url));

			$manifest = ExportManifest::read($this->manifestPath());
			$previous = $manifest?->root === $root ? $manifest : null;
			$writer   = new ExportWriter($root, $previous->files ?? [], $this->filesystem);
			$assets   = $site->container()->make(ExportAssets::class);
			$files    = $assets->public($writer);
			$current  = $incremental
				&& $previous !== null
				&& $previous->url === $url
				&& $previous->version === $version
				&& $previous->fingerprint === $fingerprint
				&& array_all($writer->previouslyRendered(), static fn (string $file): bool => is_file("{$root}/{$file}"));

			if ($current) {
				foreach ($writer->previouslyRendered() as $file) {
					$writer->keep($file);
				}

				$rendered = new ExportReport($root, $url, rendered: false);
			} else {
				$rendered = $this->render($site, $writer, $url, $crawl, $progress);
			}

			$files  += $assets->themes($writer) + $assets->media($writer);
			$removed = $writer->prune();

			new ExportManifest($root, $url, $writer->files(), $version, $fingerprint)->write($this->manifestPath(), $this->filesystem);

			$report = new ExportReport(
				path: $root,
				url: $url,
				index: $index,
				pages: $rendered->pages,
				files: $files,
				written: $writer->written,
				unchanged: $writer->unchanged,
				removed: $removed,
				redirects: $rendered->redirects,
				skipped: $rendered->skipped,
				broken: $rendered->broken,
				failures: $rendered->failures,
				notices: $rendered->notices,
				rendered: $rendered->rendered,
				milliseconds: intdiv(hrtime(true) - $start, 1_000_000)
			);
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}

		$this->events->dispatch(new ExportFinished($report));

		return $report;
	}

	/**
	 * Returns the manifest's path.
	 */
	public function manifestPath(): string
	{
		return $this->site->cachePath() . '/manifest.json';
	}

	/**
	 * Renders every URL, the 404 page, the redirect pages, and the host
	 * files. Returns a partial report: pages, redirects, skipped, broken,
	 * failures, and notices.
	 *
	 * @param  ?Closure(int, int): void $progress
	 * @throws ExportException
	 */
	private function render(Application $site, ExportWriter $writer, string $url, bool $crawl, ?Closure $progress): ExportReport
	{
		$crawler   = $site->container()->make(Crawler::class);
		$pages     = 0;
		$redirects = [];
		$confirmed = [];
		$indexes   = [];
		$skipped   = [];
		$broken    = [];
		$failures  = [];
		$notices   = [];

		foreach ($crawler->crawl($crawl, $progress) as $rendered) {
			if ($rendered->isOk()) {
				try {
					$file = ExportLayout::file($rendered->path, $rendered->contentType);

					$writer->write($file, $rendered->body);
					$pages++;

					if (str_starts_with(basename($file), 'index.') && basename($file) !== 'index.html') {
						$indexes[rtrim(rawurldecode($rendered->path), '/')] = [$file, trim(explode(';', $rendered->contentType)[0])];
					}
				} catch (ExportException $error) {
					$failures[$rendered->path] = $error->getMessage();
				}
			} elseif ($rendered->isRedirect()) {
				$location                   = self::relative((string) $rendered->location, $url);
				$redirects[$rendered->path] = $location;
				$confirmed[]                = ExportRedirect::fromPath($rendered->path, $location, $rendered->status);
			} elseif ($rendered->status >= 400 && $rendered->status < 500) {
				if ($rendered->referrer === null) {
					$skipped[] = $rendered->path;
				} else {
					$broken[$rendered->path] = $rendered->referrer;
				}
			} else {
				$failures[$rendered->path] = sprintf('The site answered HTTP %d.', $rendered->status);
			}
		}

		$missing = $crawler->notFound();

		if ($missing->status === 404 && $missing->isHtml()) {
			$writer->write(ExportLayout::NOT_FOUND, $missing->body);
		}

		if ($this->config->redirectPages) {
			foreach ($confirmed as $redirect) {
				$this->writeRedirectPage($writer, $redirect, $url);
			}
		}

		$patterns = [];

		foreach ($site->container()->make(RouteTable::class)->redirects() as $redirect) {
			$pattern = str_contains($redirect->from, '{') ? ExportRedirect::fromPattern($redirect->from, $redirect->to, $redirect->status->value) : null;

			if ($pattern !== null) {
				$patterns[] = $pattern;
			}
		}

		$context = new HostContext(
			redirects: [...$confirmed, ...$patterns],
			indexes: $indexes,
			trailingSlash: $site->container()->make(RouteConfig::class)->trailingSlash,
			notFound: $writer->has(ExportLayout::NOT_FOUND) ? ExportLayout::NOT_FOUND : null
		);

		$factory = $site->container()->make(HostFilesFactory::class);

		foreach ($this->config->hosts as $host) {
			$output = $factory->make($host)->files($context);

			foreach ($output->files as $file => $contents) {
				if (! $writer->write($file, $contents)) {
					$notices[] = sprintf('public/%s replaces the generated %s.', $file, $file);
				}
			}

			array_push($notices, ...$output->notices);
		}

		return new ExportReport(
			path: $writer->root,
			url: $url,
			pages: $pages,
			redirects: $redirects,
			skipped: $skipped,
			broken: $broken,
			failures: $failures,
			notices: $notices
		);
	}

	/**
	 * Writes a page that redirects in the browser at a redirected path,
	 * unless a page or file already has it.
	 *
	 * @throws ExportException
	 */
	private function writeRedirectPage(ExportWriter $writer, ExportRedirect $redirect, string $url): void
	{
		$path = $redirect->path();

		if ($path === null) {
			return;
		}

		$to        = htmlspecialchars($redirect->to, ENT_QUOTES | ENT_HTML5);
		$canonical = htmlspecialchars(str_starts_with($redirect->to, '/') ? $url . $redirect->to : $redirect->to, ENT_QUOTES | ENT_HTML5);

		$writer->write(ExportLayout::file(implode('/', array_map(rawurlencode(...), explode('/', $path))), 'text/html'), <<<HTML
			<!DOCTYPE html>
			<html>
			<head>
			<meta charset="utf-8">
			<title>Redirecting…</title>
			<meta http-equiv="refresh" content="0; url={$to}">
			<meta name="robots" content="noindex">
			<link rel="canonical" href="{$canonical}">
			</head>
			<body>
			<p><a href="{$to}">{$to}</a></p>
			</body>
			</html>

			HTML);
	}

	/**
	 * Returns a redirect target on the export's origin as a path, and
	 * any other as is.
	 */
	private static function relative(string $location, string $url): string
	{
		return str_starts_with($location, "{$url}/") ? substr($location, strlen($url)) : $location;
	}

	/**
	 * Refuses an output folder that is, holds, or sits inside one of the
	 * site's own folders, since an export removes the files it wrote
	 * before. `storage/export`, or a folder outside the project, is fine.
	 *
	 * @throws ExportException
	 */
	private function assertSafe(string $root): void
	{
		$paths = $this->paths->toArray();
		unset($paths['export']);

		foreach ($paths as $name => $path) {
			$path   = $this->filesystem->normalize($path);
			$holds  = $path === $root || str_starts_with($path . '/', rtrim($root, '/') . '/');
			$inside = ! in_array($name, ['root', 'storage'], true) && str_starts_with($root . '/', $path . '/');

			if ($holds || $inside) {
				throw new ExportException(sprintf('The export folder "%s" overlaps the site\'s "%s" folder; export somewhere else, such as storage/export.', $root, $name));
			}
		}
	}

	/**
	 * Takes the export lock.
	 *
	 * @return resource
	 * @throws ExportException
	 */
	private function lock(): mixed
	{
		if (! is_dir($this->paths->cache)) {
			@mkdir($this->paths->cache, 0775, true);
		}

		$lock = @fopen("{$this->paths->cache}/export.lock", 'c');

		if ($lock === false) {
			throw new ExportException(sprintf('Unable to open the export lock in "%s".', $this->paths->cache));
		}

		if (! flock($lock, LOCK_EX | LOCK_NB)) {
			fclose($lock);

			throw new ExportException('Another export is running.');
		}

		return $lock;
	}
}
