<?php

/**
 * Plugin source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

/**
 * Where a plugin was found (D-041, D-378).
 */
enum PluginSource: string
{
	/** A Composer package of type `blush-plugin`, autoloaded by Composer. */
	case Composer = 'composer';

	/** A folder in `user/plugins/`, autoloaded by Blush. */
	case Local = 'local';
}
