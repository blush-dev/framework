<?php

/**
 * Field set origin.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * Where a field set was defined. Sets from code (extensions and
 * `config/fields.php`) are locked in the admin; data sets from
 * `user/data/fields` are editable (D-337).
 */
enum FieldSetOrigin: string
{
	case Extension = 'extension';
	case Config    = 'config';
	case Data      = 'data';
}
