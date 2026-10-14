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
use Blush\Extension\LocalExtensions;
use Blush\Translation\Messages;

/**
 * Finds every installed plugin across all sources, Composer first, then
 * local. A plugin whose manifest doesn't hold is kept as broken instead
 * of failing discovery (D-394), so it can be listed and fixed; two
 * plugins with the same name, or the same namespace (D-378), are still
 * an error.
 */
final readonly class PluginDiscovery
{
	/**
	 * @param list<PluginFinder> $finders
	 */
	public function __construct(
		private array $finders,
		private Messages $messages = new Messages()
	) {}

	/**
	 * Builds discovery over the standard sources for a site.
	 */
	public static function forPaths(Paths $paths, Messages $messages = new Messages()): self
	{
		return new self([
			new ComposerPluginFinder($paths->vendor, $messages),
			new LocalPluginFinder(LocalExtensions::forPaths($paths, $messages), $paths->root, $messages)
		], $messages);
	}

	/**
	 * Returns every discovered plugin: the manifests, sorted by name, and
	 * the broken ones, in the order found.
	 *
	 * @throws ExtensionException When two plugins share a name or a namespace, or a source can't be read.
	 */
	public function discover(): DiscoveredPlugins
	{
		$manifests  = [];
		$namespaces = [];
		$broken     = [];

		foreach ($this->finders as $finder) {
			$found  = $finder->find();
			$broken = [...$broken, ...$found->broken];

			foreach ($found->manifests as $manifest) {
				if (isset($manifests[$manifest->name])) {
					throw new ExtensionException((string) $this->messages->t(
						'plugin.plugin_discovery.same_name',
						'Two plugins are named "{name}": {first} and {second}.',
						name: $manifest->name,
						first: $manifests[$manifest->name]->path,
						second: $manifest->path
					));
				}

				if (isset($namespaces[$manifest->namespace])) {
					throw new ExtensionException((string) $this->messages->t(
						'plugin.plugin_discovery.same_namespace',
						'Two plugins have the namespace "{namespace}": {first} and {second}.',
						namespace: $manifest->namespace,
						first: $namespaces[$manifest->namespace],
						second: $manifest->name
					));
				}

				$manifests[$manifest->name]       = $manifest;
				$namespaces[$manifest->namespace] = $manifest->name;
			}
		}

		ksort($manifests);

		return new DiscoveredPlugins(array_values($manifests), $broken);
	}
}
