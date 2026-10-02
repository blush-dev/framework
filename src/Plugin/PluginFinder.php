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
 * Finds plugins from one source.
 */
interface PluginFinder
{
	/**
	 * Returns the manifests of every plugin this source provides.
	 *
	 * @return list<PluginManifest>
	 * @throws ExtensionException
	 */
	public function find(): array;
}
