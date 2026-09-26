<?php

/**
 * Export manifest.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use JsonException;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * The record of the last export (D-137): its output folder, origin, the
 * content version and fingerprint it was rendered at (D-139), and every
 * file it wrote with a fingerprint. The next export uses it to leave
 * unchanged files alone, to remove the files it no longer writes, and
 * (with `--incremental`) to skip rendering when nothing changed. It's
 * kept in `storage/cache/export/manifest.json`, outside the output
 * folder, so it's never deployed.
 */
final readonly class ExportManifest
{
	/**
	 * @param array<string, string> $files Path relative to the output folder => fingerprint.
	 */
	public function __construct(
		public string $root,
		public string $url,
		public array $files = [],
		public ?string $version = null,
		public ?string $fingerprint = null
	) {}

	/**
	 * Reads a manifest, or returns `null` when there's none or it's
	 * damaged.
	 */
	public static function read(string $path): ?self
	{
		$json = is_file($path) ? file_get_contents($path) : false;

		if ($json === false) {
			return null;
		}

		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		if (! is_array($data) || ! is_string($data['root'] ?? null) || ! is_string($data['url'] ?? null) || ! is_array($data['files'] ?? null)) {
			return null;
		}

		$files = array_filter($data['files'], static fn (mixed $fingerprint, int|string $file): bool => is_string($file) && is_string($fingerprint), ARRAY_FILTER_USE_BOTH);

		/** @var array<string, string> $files */
		return new self(
			$data['root'],
			$data['url'],
			$files,
			is_string($data['version'] ?? null) ? $data['version'] : null,
			is_string($data['fingerprint'] ?? null) ? $data['fingerprint'] : null
		);
	}

	/**
	 * Writes the manifest.
	 *
	 * @throws ExportException
	 */
	public function write(string $path, Filesystem $filesystem = new Filesystem()): void
	{
		try {
			$filesystem->writeAtomic($path, json_encode(
				['root' => $this->root, 'url' => $this->url, 'version' => $this->version, 'fingerprint' => $this->fingerprint, 'files' => $this->files],
				JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			));
		} catch (FilesystemException | JsonException $error) {
			throw new ExportException(sprintf('Unable to write the export manifest: %s', $error->getMessage()), 0, $error);
		}
	}
}
