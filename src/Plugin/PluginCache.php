<?php

/**
 * Plugin cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

use Blush\Extension\ExtensionException;
use Blush\Support\PhpArrayFile;

/**
 * Compiles discovered plugin manifests to a PHP file, so production
 * requests don't scan `installed.json` or `user/plugins` (D-041, D-044).
 */
final readonly class PluginCache
{
	public function __construct(private PhpArrayFile $file)
	{
	}

	/**
	 * Returns the cached manifests, or `null` when nothing is cached.
	 *
	 * @return ?list<PluginManifest>
	 * @throws ExtensionException When the cache is invalid.
	 */
	public function read(): ?array
	{
		$data = $this->file->read();

		if ($data === null) {
			return null;
		}

		$manifests = [];

		foreach ($data as $manifest) {
			if (! is_array($manifest)) {
				throw new ExtensionException(sprintf('The plugin cache "%s" is invalid.', $this->file->path));
			}

			$manifests[] = PluginManifest::fromArray($manifest);
		}

		return $manifests;
	}

	/**
	 * Writes the manifests to the cache.
	 *
	 * @param list<PluginManifest> $manifests
	 */
	public function write(array $manifests): void
	{
		$this->file->write(array_map(
			static fn (PluginManifest $manifest): array => $manifest->toArray(),
			$manifests
		));
	}

	/**
	 * Deletes the cache.
	 */
	public function clear(): void
	{
		$this->file->delete();
	}
}
