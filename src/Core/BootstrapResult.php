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
use Blush\Extension\Extensions;
use Blush\Extension\LocalAutoloader;

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
		public ConfigRepository $config,
		public Extensions $extensions,
		public LocalAutoloader $autoloader
	) {
	}
}
