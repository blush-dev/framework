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
 * Where a content type was defined. Data types from `user/data/types` are
 * editable (D-042). A collection or taxonomy from code (an extension or
 * `config/content.php`) keeps its origin when a data file changes it
 * (D-349; `ContentTypes::isOverridden()`); the built-in types stay as
 * they are unless a data type replaces one whole.
 */
enum TypeOrigin: string
{
	case BuiltIn   = 'built-in';
	case Extension = 'extension';
	case Config    = 'config';
	case Data      = 'data';

	/**
	 * Returns whether the admin edits types from this origin as data
	 * types. `ContentTypes::isEditable()` also counts code types it
	 * changes through a data file.
	 */
	public function isEditable(): bool
	{
		return $this === self::Data;
	}
}
