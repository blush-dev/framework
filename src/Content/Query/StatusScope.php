<?php

/**
 * Status scope.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Query;

/**
 * Which statuses a query finds when it names none (D-850), worked out
 * from what's registered when it's compiled (`Statuses`), so a query
 * needn't know.
 */
enum StatusScope
{
	/**
	 * Entries on the site: a live status whose time has come.
	 */
	case Live;

	/**
	 * Every active status (what "any status" means): all but the trash
	 * and statuses that aren't active.
	 */
	case Active;

	/**
	 * Every status, the trash included.
	 */
	case All;
}
