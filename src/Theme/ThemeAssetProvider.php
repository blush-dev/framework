<?php

/**
 * Theme asset provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Override;
use Blush\Asset\AssetRegistry;
use Blush\Container\Container;
use Blush\Core\ServiceProvider;

/**
 * Registers the assets the running chain's themes declare in their
 * manifests' `assets` (D-574), ancestors first, so a child theme's
 * handle wins. `Bootstrap` registers it after the plugins' providers and
 * before the themes' own, so a theme's provider (the preferred place to
 * register a theme's assets) wins over any manifest, and the site's over
 * both.
 */
final class ThemeAssetProvider extends ServiceProvider
{
	public function __construct(Container $container, private readonly ThemeChain $chain)
	{
		parent::__construct($container);
	}

	/**
	 * Registers the chain's manifest assets.
	 */
	#[Override]
	public function boot(): void
	{
		$registry = $this->container->make(AssetRegistry::class);

		foreach ($this->chain->assets() as $asset) {
			$registry->register($asset);
		}
	}
}
