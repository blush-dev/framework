<?php

/**
 * Admin theme source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\AdminTheme;

/**
 * Where an admin theme was found (D-827).
 */
enum AdminThemeSource: string
{
	/** Bundled with the framework, in `resources/admin-themes`. */
	case Framework = 'framework';

	/** A folder in `extensions/`. */
	case Local = 'local';

	/** A Composer package of type `blush-admin-theme`. */
	case Composer = 'composer';
}
