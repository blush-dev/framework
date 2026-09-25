<?php

/**
 * Extension source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Where an extension was found (D-041).
 */
enum ExtensionSource: string
{
	/** A Composer package of type `blush-extension`, autoloaded by Composer. */
	case Composer = 'composer';

	/** A folder in `user/extensions/`, autoloaded by Blush. */
	case Local = 'local';
}
