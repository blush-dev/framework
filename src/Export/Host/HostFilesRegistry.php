<?php

/**
 * Host files registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Host;

use Blush\Support\Registry;

/**
 * Maps host format names to classes. An extension adds a format in a
 * provider's `boot()`:
 *
 *     $this->container->make(HostFilesRegistry::class)->register('caddy', CaddyFiles::class);
 *
 * @extends Registry<HostFiles>
 */
final class HostFilesRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = HostFiles::class;
}
