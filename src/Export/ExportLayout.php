<?php

/**
 * Export layout.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

/**
 * Where a rendered URL goes in the export (D-137). A path whose last
 * segment already has an extension its content type uses is a file
 * (`/robots.txt`, `/sitemap.xml`); any other path is a folder with an
 * index file named for the content type:
 *
 * | URL path              | File                          |
 * |-----------------------|-------------------------------|
 * | `/`                   | `index.html`                  |
 * | `/about`              | `about/index.html`            |
 * | `/feed`               | `feed/index.rss`              |
 * | `/feed/atom`          | `feed/atom/index.atom`        |
 * | `/feed/json`          | `feed/json/index.json`        |
 * | `/sitemap`            | `sitemap/index.xml`           |
 *
 * So URLs keep their exact form (no `.html`, no trailing slash) on any
 * host that serves a folder's index file, and the index's extension
 * gives the host its content type. `INDEXES` is the lookup order a host
 * needs.
 */
final class ExportLayout
{
	/**
	 * The file extensions of each exportable content type; the first is
	 * the index file's.
	 *
	 * @var array<string, list<string>>
	 */
	public const array EXTENSIONS = [
		'text/html'              => ['html', 'htm'],
		'application/rss+xml'    => ['rss', 'xml'],
		'application/atom+xml'   => ['atom', 'xml'],
		'application/feed+json'  => ['json'],
		'application/json'       => ['json'],
		'application/xml'        => ['xml'],
		'text/xml'               => ['xml'],
		'text/plain'             => ['txt'],
		'text/css'               => ['css'],
		'text/javascript'        => ['js', 'mjs'],
		'application/javascript' => ['js', 'mjs']
	];

	/**
	 * The index file names a host looks for in a folder, in order.
	 *
	 * @var list<string>
	 */
	public const array INDEXES = ['index.html', 'index.xml', 'index.rss', 'index.atom', 'index.json', 'index.txt'];

	/**
	 * The error page's file.
	 */
	public const string NOT_FOUND = '404.html';

	/**
	 * Returns the file, relative to the export folder, for a URL path (raw,
	 * percent-encoded) and the `Content-Type` it was served with.
	 *
	 * @throws ExportException When the path can't be a file, or the
	 *                         content type has no extension for an index.
	 */
	public static function file(string $path, string $contentType): string
	{
		$segments = [];

		foreach (explode('/', $path) as $segment) {
			$segment = rawurldecode($segment);

			if ($segment === '') {
				continue;
			}

			if ($segment === '.' || $segment === '..' || str_contains($segment, "\0") || str_contains($segment, '\\')) {
				throw new ExportException(sprintf('The path "%s" can\'t be exported as a file.', $path));
			}

			$segments[] = $segment;
		}

		$type       = strtolower(trim(explode(';', $contentType)[0]));
		$extensions = self::EXTENSIONS[$type] ?? null;
		$last       = array_last($segments);
		$extension  = $last !== null && str_contains($last, '.') ? strtolower(pathinfo($last, PATHINFO_EXTENSION)) : null;

		if ($extension !== null && ($extensions === null || in_array($extension, $extensions, true))) {
			return implode('/', $segments);
		}

		if ($extensions === null) {
			throw new ExportException(sprintf('The path "%s" is served as "%s", which has no file extension to export it with.', $path, $type));
		}

		return ltrim(implode('/', $segments) . "/index.{$extensions[0]}", '/');
	}
}
