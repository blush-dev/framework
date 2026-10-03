<?php

/**
 * Plugin finder interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

use Blush\Extension\ExtensionException;

/**
 * Finds plugins from one source. A plugin whose manifest can't be read
 * is returned as broken (D-394); only a source that can't be read at all
 * throws.
 */
interface PluginFinder
{
	/**
	 * Returns every plugin this source provides.
	 *
	 * @throws ExtensionException
	 */
	public function find(): DiscoveredPlugins;
}
