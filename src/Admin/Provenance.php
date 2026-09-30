<?php

/**
 * Where a namespace's components or icons come from.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Extension\ExtensionManifest;
use Blush\Extension\Extensions;
use Blush\Theme\ThemeChain;

/**
 * Names where a component or icon that isn't core comes from, for the
 * editor's inserters to group by (D-243, D-265): a theme in the chain,
 * the site (`app`), or an extension (by its vendor).
 */
final readonly class Provenance
{
	public function __construct(private Extensions $extensions)
	{}

	/**
	 * Describes a namespace's source as its `kind` (`theme`, `site`, or
	 * `extension`) and a `label` to show.
	 *
	 * @return array{kind: string, label: string}
	 */
	public function of(string $namespace, ThemeChain $chain): array
	{
		foreach ($chain->themes as $theme) {
			if ($theme->slug === $namespace) {
				return ['kind' => 'theme', 'label' => $theme->name];
			}
		}

		if ($namespace === 'app') {
			return ['kind' => 'site', 'label' => 'This site'];
		}

		$extensions = array_values(array_filter(
			$this->extensions->all(),
			static fn (ExtensionManifest $extension): bool => $extension->name === $namespace || str_starts_with($extension->name, "{$namespace}/")
		));

		return ['kind' => 'extension', 'label' => count($extensions) === 1 ? $extensions[0]->name : $namespace];
	}
}
