<?php
/**
 * Application contract.
 *
 * The Application class should be the be the primary class for working with and
 * launching the app. It extends the `Container` contract.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Core;

use Blush\Contracts\Container\Container;

/**
 * Application interface.
 *
 * @since 1.0.0
 */
interface Application extends Container
{
	/**
	 * Adds a service provider. Developers can pass in an object or a fully-
	 * qualified class name.
	 *
	 * @since  1.0.0
	 * @param  string|object  $provider
	 */
	public function provider( $provider ): void;

	/**
	 * Adds a static proxy alias. Developers must pass in fully-qualified
	 * class name and alias class name.
	 *
	 * @since 1.0.0
	 */
	public function proxy( string $class_name, string $alias ): void;
}
