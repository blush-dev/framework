<?php

/**
 * Theme source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

/**
 * Where an installed theme came from (D-034).
 */
enum ThemeSource: string
{
	/**
	 * The framework default theme.
	 */
	case Framework = 'framework';

	/**
	 * A folder in `user/themes`.
	 */
	case Local = 'local';

	/**
	 * A folder in the site's `resources/themes` (D-144).
	 */
	case Site = 'site';

	/**
	 * A Composer package of type `blush-theme`.
	 */
	case Composer = 'composer';
}
