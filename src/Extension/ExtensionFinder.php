<?php

/**
 * Extension finder interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Finds extensions from one source.
 */
interface ExtensionFinder
{
	/**
	 * Returns the manifests of every extension this source provides.
	 *
	 * @return list<ExtensionManifest>
	 * @throws ExtensionException
	 */
	public function find(): array;
}
