<?php

/**
 * Plugin discovery.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

use Blush\Core\Paths;
use Blush\Extension\ExtensionException;

/**
 * Finds every installed plugin across all sources, Composer first, then
 * local. Two plugins with the same name, or the same namespace (D-378),
 * are an error.
 */
final readonly class PluginDiscovery
{
	/**
	 * @param list<PluginFinder> $finders
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
			new ComposerPluginFinder($paths->vendor),
			new LocalPluginFinder($paths->plugins)
		]);
	}

	/**
	 * Returns every discovered manifest, sorted by name.
	 *
	 * @return list<PluginManifest>
	 * @throws ExtensionException When two plugins share a name or a namespace.
	 */
	public function discover(): array
	{
		$manifests  = [];
		$namespaces = [];

		foreach ($this->finders as $finder) {
			foreach ($finder->find() as $manifest) {
				if (isset($manifests[$manifest->name])) {
					throw new ExtensionException(sprintf(
						'Two plugins are named "%s": %s and %s.',
						$manifest->name,
						$manifests[$manifest->name]->path,
						$manifest->path
					));
				}

				if (isset($namespaces[$manifest->namespace])) {
					throw new ExtensionException(sprintf(
						'Two plugins have the namespace "%s": %s and %s.',
						$manifest->namespace,
						$namespaces[$manifest->namespace],
						$manifest->name
					));
				}

				$manifests[$manifest->name]       = $manifest;
				$namespaces[$manifest->namespace] = $manifest->name;
			}
		}

		ksort($manifests);

		return array_values($manifests);
	}
}
