<?php

/**
 * Extension cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Blush\Support\PhpArrayFile;

/**
 * Compiles discovered extension manifests to a PHP file, so production
 * requests don't scan `installed.json` or `user/extensions` (D-041, D-044).
 */
final readonly class ExtensionCache
{
	public function __construct(private PhpArrayFile $file)
	{
	}

	/**
	 * Returns the cached manifests, or `null` when nothing is cached.
	 *
	 * @return ?list<ExtensionManifest>
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
				throw new ExtensionException(sprintf('The extension cache "%s" is invalid.', $this->file->path));
			}

			$manifests[] = ExtensionManifest::fromArray($manifest);
		}

		return $manifests;
	}

	/**
	 * Writes the manifests to the cache.
	 *
	 * @param list<ExtensionManifest> $manifests
	 */
	public function write(array $manifests): void
	{
		$this->file->write(array_map(
			static fn (ExtensionManifest $manifest): array => $manifest->toArray(),
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
