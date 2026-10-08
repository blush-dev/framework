<?php

/**
 * Where a namespace's directives or icons come from.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Icon\IconPacks;
use Blush\Plugin\Plugins;
use Blush\Theme\ThemeChain;

/**
 * Names where a directive or icon that isn't core comes from, for the
 * editor's inserters to group by (D-243, D-265): a theme in the chain,
 * an icon pack, or a plugin (by the namespace each
 * declares, D-378). The Tools screen groups actions by where their class
 * comes from the same way (D-540).
 */
final readonly class Provenance
{
	public function __construct(
		private Plugins $plugins,
		private IconPacks $packs
	) {}

	/**
	 * Describes a namespace's source as its `kind` (`theme`,
	 * `icon-pack`, or `plugin`) and a `label` to show.
	 *
	 * @return array{kind: string, label: string}
	 */
	public function of(string $namespace, ThemeChain $chain): array
	{
		foreach ($chain->themes as $theme) {
			if ($theme->namespace === $namespace) {
				return ['kind' => 'theme', 'label' => $theme->label];
			}
		}

		$pack = $this->packs->byNamespace($namespace);

		if ($pack !== null) {
			return ['kind' => 'icon-pack', 'label' => $pack->label];
		}

		foreach ($this->plugins->all() as $plugin) {
			if ($plugin->namespace === $namespace) {
				return ['kind' => 'plugin', 'label' => $plugin->label];
			}
		}

		return ['kind' => 'plugin', 'label' => $namespace];
	}

	/**
	 * Describes where a class comes from as its `kind` (`core`, `site`,
	 * `plugin`, or `other`) and a `label` to show: Blush's own, the
	 * site's (`App\`), or a plugin's, found by its autoload prefixes or
	 * its provider's namespace.
	 *
	 * @return array{kind: string, label: string}
	 */
	public function ofClass(string $class): array
	{
		$class = ltrim($class, '\\');

		if (str_starts_with($class, 'Blush\\')) {
			return ['kind' => 'core', 'label' => 'Blush'];
		}

		if (str_starts_with($class, 'App\\')) {
			return ['kind' => 'site', 'label' => 'This site'];
		}

		$plugin = $this->plugins->owning($class);

		return $plugin === null ? ['kind' => 'other', 'label' => 'Elsewhere'] : ['kind' => 'plugin', 'label' => $plugin->label];
	}
}
