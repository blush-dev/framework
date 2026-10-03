<?php

/**
 * Discovered plugins.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

/**
 * What discovery found: the plugins it could read, and the broken ones
 * (D-394).
 */
final readonly class DiscoveredPlugins
{
	/**
	 * @param list<PluginManifest> $manifests
	 * @param list<BrokenPlugin>   $broken
	 */
	public function __construct(
		public array $manifests = [],
		public array $broken = []
	) {}

	/**
	 * The plugins it could read, keyed by name.
	 *
	 * @return array<string, PluginManifest>
	 */
	public function keyed(): array
	{
		$keyed = [];

		foreach ($this->manifests as $manifest) {
			$keyed[$manifest->name] = $manifest;
		}

		return $keyed;
	}

	/**
	 * How many plugins are installed, broken ones included.
	 */
	public function count(): int
	{
		return count($this->manifests) + count($this->broken);
	}
}
