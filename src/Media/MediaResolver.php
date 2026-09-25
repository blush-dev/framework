<?php

/**
 * Media resolver.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Turns a media reference, as written in front matter or Markdown, into a
 * `MediaFile`:
 *
 * - A path under the media URL (`/media/2019/01/artemis.jpg`), or under
 *   `user/media`'s own path (1.x's `/user/media/...`), is a file in
 *   `user/media`.
 * - A relative path (`photo.jpg`) is a file next to the entry, in a page
 *   bundle: `$base` is the entry's folder under `user/content`. Bundle
 *   files are served at `{url}/_content/{path}`.
 * - Anything else (absolute URLs, other site paths) isn't local media.
 *
 * Only existing files of an allowed MIME type resolve, never hidden ones,
 * and every path is confined to its root.
 */
final readonly class MediaResolver
{
	/**
	 * The folder under the media URL that serves page bundle files.
	 */
	public const string CONTENT = '_content';

	private Filesystem $filesystem;

	public function __construct(
		private Paths $paths,
		private MediaConfig $config
	) {
		$this->filesystem = new Filesystem();
	}

	/**
	 * Resolves a reference, relative to a folder under `user/content` for
	 * bundle files.
	 */
	public function resolve(string $reference, string $base = ''): ?MediaFile
	{
		$reference = trim($reference);

		if ($reference === '' || str_starts_with($reference, '//') || str_starts_with($reference, '#') || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $reference) === 1) {
			return null;
		}

		$path = rawurldecode((string) preg_replace('/[?#].*$/s', '', $reference));

		if (! str_starts_with($path, '/')) {
			$relative = ltrim(trim($base, '/') . '/' . $path, '/');

			return $this->file($this->paths->content, $relative, $this->config->url . '/' . self::CONTENT);
		}

		foreach ($this->prefixes() as $prefix) {
			if (str_starts_with($path, "{$prefix}/")) {
				$relative = substr($path, strlen($prefix) + 1);

				return str_starts_with($relative, self::CONTENT . '/') && $prefix === $this->config->url
					? $this->file($this->paths->content, substr($relative, strlen(self::CONTENT) + 1), $prefix . '/' . self::CONTENT)
					: $this->file($this->paths->media, $relative, $this->config->url);
			}
		}

		return null;
	}

	/**
	 * Resolves a URL path the media controller was asked for.
	 */
	public function fromUrl(string $path): ?MediaFile
	{
		return str_starts_with($path, "{$this->config->url}/") ? $this->resolve($path) : null;
	}

	/**
	 * Returns the URL path prefixes that point into `user/media`.
	 *
	 * @return list<string>
	 */
	private function prefixes(): array
	{
		$prefixes = [$this->config->url];

		if (str_starts_with($this->paths->media, $this->paths->root . '/')) {
			$prefixes[] = '/' . $this->paths->relative($this->paths->media);
		}

		return array_values(array_unique($prefixes));
	}

	/**
	 * Returns the media file at a path under a root, if it's allowed.
	 */
	private function file(string $root, string $relative, string $urlPrefix): ?MediaFile
	{
		if ($relative === '' || array_any(explode('/', $relative), static fn (string $segment): bool => str_starts_with($segment, '.'))) {
			return null;
		}

		try {
			$path = $this->filesystem->confine($root, $relative);
		} catch (FilesystemException) {
			return null;
		}

		if (! is_file($path) || ! is_readable($path)) {
			return null;
		}

		$mime = self::mime($path);

		if (! $this->config->allows($mime)) {
			return null;
		}

		$image    = str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml' ? @getimagesize($path) : false;
		$relative = substr($path, strlen($this->filesystem->normalize($root)) + 1);
		$url      = $urlPrefix . '/' . implode('/', array_map(rawurlencode(...), explode('/', $relative)));

		return new MediaFile(
			$path,
			$url,
			$mime,
			(int) filesize($path),
			$image === false ? null : $image[0],
			$image === false ? null : $image[1]
		);
	}

	/**
	 * Returns a file's MIME type from its contents, reading SVGs (which
	 * sniff as XML or text) by their extension.
	 */
	private static function mime(string $path): string
	{
		$mime = strtolower(mime_content_type($path) ?: 'application/octet-stream');

		if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg' && in_array($mime, ['image/svg', 'text/xml', 'application/xml', 'text/plain'], true)) {
			return 'image/svg+xml';
		}

		return $mime;
	}
}
