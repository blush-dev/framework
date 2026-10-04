<?php

/**
 * Export fingerprint.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Support\Filesystem;

/**
 * Sums up everything but content that a rendered page depends on, for
 * `build --incremental` (D-139). The content version (D-128) covers
 * content; this covers the rest, by each file's path, size, and
 * modification time (a stat, never a read):
 *
 * - the export's origin and options, and the framework version;
 * - `.env`, `config/`, `user/data`, `user/media` (images give Markdown
 *   their dimensions), `extensions/`, `public/`, `resources/`, and the
 *   site's `src/`;
 * - installed Composer packages (extensions from Composer),
 *   and the framework's own `src/` and `resources/`, so framework
 *   development is covered too.
 */
final readonly class ExportFingerprint
{
	public function __construct(
		private Paths $paths,
		private ExportConfig $config,
		private Filesystem $filesystem
	) {}

	/**
	 * Returns the fingerprint for an export to an origin.
	 */
	public function compute(string $url, bool $crawl): string
	{
		$hash = hash_init('xxh128');

		hash_update($hash, json_encode([Framework::VERSION, $url, $crawl, $this->config->toArray()], JSON_THROW_ON_ERROR) . "\n");

		$folders = [
			$this->paths->config,
			$this->paths->data,
			$this->paths->media,
			$this->paths->extensions,
			$this->paths->public,
			$this->paths->resources,
			$this->paths->root . '/src',
			Framework::path('src'),
			Framework::path('resources')
		];

		foreach (array_unique($folders) as $folder) {
			hash_update($hash, "{$folder}\n");

			foreach ($this->filesystem->files($folder, links: false) as $relative => $file) {
				hash_update($hash, sprintf("%s\0%d\0%d\n", $relative, $file->getSize(), $file->getMTime()));
			}
		}

		foreach ([$this->paths->root . '/.env', $this->paths->vendor . '/composer/installed.json'] as $file) {
			hash_update($hash, sprintf("%s\0%d\0%d\n", $file, is_file($file) ? (int) filesize($file) : -1, is_file($file) ? (int) filemtime($file) : -1));
		}

		return hash_final($hash);
	}
}
