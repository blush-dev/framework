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

use Blush\Core\AppConfig;
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
 * - A relative path is read from the site root, so `user/media/a.mp3`
 *   is `/user/media/a.mp3` (D-190). Media is only ever in `user/media`,
 *   never beside entries (D-294).
 * - A full URL on the site's own origin (`https://example.com/media/a.jpg`)
 *   is its path (D-190), so a URL that was made absolute resolves again.
 * - Anything else (other sites' URLs, other site paths) isn't local media.
 *
 * Only existing files of an allowed MIME type resolve, never hidden ones,
 * and every path is confined to its root.
 */
final readonly class MediaResolver
{
	/**
	 * The library's media, by extension: what the media index and the
	 * admin's library look for, before reading a file to check its type.
	 * Caption tracks (`.vtt`) are served, but aren't library files.
	 *
	 * @var array<string, string>
	 */
	public const array EXTENSIONS = [
		'apng' => 'image/apng',
		'avif' => 'image/avif',
		'gif'  => 'image/gif',
		'jpeg' => 'image/jpeg',
		'jpg'  => 'image/jpeg',
		'png'  => 'image/png',
		'svg'  => 'image/svg+xml',
		'webp' => 'image/webp',
		'mp3'  => 'audio/mpeg',
		'oga'  => 'audio/ogg',
		'ogg'  => 'audio/ogg',
		'wav'  => 'audio/wav',
		'm4v'  => 'video/mp4',
		'mp4'  => 'video/mp4',
		'ogv'  => 'video/ogg',
		'webm' => 'video/webm'
	];

	private Filesystem $filesystem;

	public function __construct(
		private Paths $paths,
		private MediaConfig $config,
		private ?AppConfig $app = null
	) {
		$this->filesystem = new Filesystem();
	}

	/**
	 * Resolves a reference.
	 */
	public function resolve(string $reference): ?MediaFile
	{
		$reference = trim($reference);
		$origin    = $this->app?->origin();

		if ($origin !== null && $origin !== '' && str_starts_with($reference, "{$origin}/")) {
			$reference = substr($reference, strlen($origin));
		}

		if ($reference === '' || str_starts_with($reference, '//') || str_starts_with($reference, '#') || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $reference) === 1) {
			return null;
		}

		$path = rawurldecode((string) preg_replace('/[?#].*$/s', '', $reference));

		if (! str_starts_with($path, '/')) {
			return $this->resolve('/' . $reference);
		}

		foreach ($this->prefixes() as $prefix) {
			if (str_starts_with($path, "{$prefix}/")) {
				return $this->file($this->paths->media, substr($path, strlen($prefix) + 1), $this->config->url);
			}
		}

		return null;
	}

	/**
	 * Resolves a media key, as the media index and metadata files name
	 * files: its path under `user/media` (`2024/sunset.jpg`).
	 */
	public function fromKey(string $key): ?MediaFile
	{
		return $this->file($this->paths->media, $key, $this->config->url);
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

		$mime = self::mimeOf($path);

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
	 * sniff as XML or text) and WebVTT tracks (text, until they have a
	 * cue) by their extension, and calling a WAV `audio/wav` whatever
	 * alias the system's magic database gives it (D-291).
	 */
	public static function mimeOf(string $path): string
	{
		$mime      = strtolower(mime_content_type($path) ?: 'application/octet-stream');
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		return match (true) {
			$extension === 'svg' && in_array($mime, ['image/svg', 'text/xml', 'application/xml', 'text/plain'], true) => 'image/svg+xml',
			$extension === 'vtt' && $mime === 'text/plain'                                                          => 'text/vtt',
			in_array($mime, ['audio/x-wav', 'audio/wave', 'audio/vnd.wave'], true)                                  => 'audio/wav',
			default                                                                                                  => $mime
		};
	}
}
