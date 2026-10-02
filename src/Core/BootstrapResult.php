<?php

/**
 * Bootstrap result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use Blush\Config\ConfigRepository;
use Blush\Container\ServiceContainer;
use Blush\Extension\LocalAutoloader;
use Blush\Icon\IconPacks;
use Blush\Plugin\Plugins;
use Blush\Theme\Themes;

/**
 * What `Bootstrap` builds, kept together so `compile()` can reach the pieces
 * it writes to cache.
 *
 * @internal
 */
final readonly class BootstrapResult
{
	public function __construct(
		public Application $application,
		public ServiceContainer $container,
		public ConfigRepository $config,
		public Plugins $plugins,
		public LocalAutoloader $autoloader,
		public Themes $themes,
		public IconPacks $iconPacks
	) {
	}
}
