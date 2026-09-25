<?php

/**
 * Contextual attribute interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Attributes;

use Blush\Container\Container;

/**
 * Defines the contract for parameter attributes that resolve their own value
 * during autowiring. When the container encounters a constructor parameter
 * marked with a contextual attribute, it calls `resolve()` to obtain the value
 * instead of resolving the parameter from its type.
 */
interface ContextualAttribute
{
	/**
	 * Resolves the value to inject for the attributed parameter, using the
	 * resolving container as needed. The return type is `mixed` so an
	 * implementation may narrow it — a closure, an array of services, a
	 * scalar value, and so on.
	 */
	public function resolve(Container $container): mixed;
}
