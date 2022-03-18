<?php
/**
 * Content query static proxy class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Proxies;

use Blush\Core\Proxy;
use Blush\Cache\Registry;

class Cache extends Proxy
{
	/**
	 * Returns the name of the accessor for object registered in the container.
	 *
	 * @since 1.0.0
	 */
	protected static function accessor(): string
	{
		return 'cache.registry';
	}
}
