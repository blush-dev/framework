<?php

/**
 * Framework identity.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

/**
 * Holds the framework's product name and version. This is the one place code
 * should read the product name from, so a rename (D-038) stays mechanical.
 */
final class Framework
{
	/**
	 * The product name.
	 */
	public const string NAME = 'Blush Framework';

	/**
	 * The command-line executable's name, as shown in usage lines.
	 */
	public const string BINARY = 'blush';

	/**
	 * The framework version.
	 */
	public const string VERSION = '2.0.0-dev';
}
