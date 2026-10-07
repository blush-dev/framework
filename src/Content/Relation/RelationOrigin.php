<?php

/**
 * Relation origin.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * Where a relation definition comes from (D-593). Only data relations
 * (`user/data/relations`) are edited in the admin.
 */
enum RelationOrigin: string
{
	case Extension = 'extension';
	case Config    = 'config';
	case Data      = 'data';
}
