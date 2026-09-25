<?php

/**
 * Extension discovery.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Blush\Core\Paths;

/**
 * Finds every installed extension across all sources, Composer first, then
 * local. Two extensions with the same name are an error.
 */
final readonly class ExtensionDiscovery
{
	/**
	 * @param list<ExtensionFinder> $finders
	 */
	public function __construct(private array $finders)
	{
	}

	/**
	 * Builds discovery over the standard sources for a site.
	 */
	public static function forPaths(Paths $paths): self
	{
		return new self([
			new ComposerExtensionFinder($paths->vendor),
			new LocalExtensionFinder($paths->extensions)
		]);
	}

	/**
	 * Returns every discovered manifest, sorted by name.
	 *
	 * @return list<ExtensionManifest>
	 * @throws ExtensionException When two extensions share a name.
	 */
	public function discover(): array
	{
		$manifests = [];

		foreach ($this->finders as $finder) {
			foreach ($finder->find() as $manifest) {
				if (isset($manifests[$manifest->name])) {
					throw new ExtensionException(sprintf(
						'Two extensions are named "%s": %s and %s.',
						$manifest->name,
						$manifests[$manifest->name]->path,
						$manifest->path
					));
				}

				$manifests[$manifest->name] = $manifest;
			}
		}

		ksort($manifests);

		return array_values($manifests);
	}
}
