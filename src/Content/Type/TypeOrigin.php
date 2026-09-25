<?php

/**
 * Content type origin.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

/**
 * Where a content type was defined. Types from code (the framework,
 * extensions, and `config/content.php`) are locked in the admin; data types
 * from `user/data/types` are editable (D-042).
 */
enum TypeOrigin: string
{
	case BuiltIn   = 'built-in';
	case Extension = 'extension';
	case Config    = 'config';
	case Data      = 'data';

	/**
	 * Returns whether the admin may edit types from this origin.
	 */
	public function isEditable(): bool
	{
		return $this === self::Data;
	}
}
