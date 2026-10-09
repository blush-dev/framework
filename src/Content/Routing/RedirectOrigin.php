<?php

/**
 * Redirect origin.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

/**
 * How a row in the `redirects` table came to be, when it wasn't added
 * by hand (D-686): kept in its `via`, and said in the admin's Added
 * column.
 */
enum RedirectOrigin: string
{
	// An entry's slug changed in the admin.
	case Rename = 'rename';

	// An entry moved under another parent, or with a page above it.
	case Move = 'move';

	// Added with many others at once, by a tool or plugin.
	case Import = 'import';
}
