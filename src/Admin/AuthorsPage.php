<?php

/**
 * Authors page.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Content\Entry\Entry;
use Blush\Content\Http\AuthorsController;
use Blush\Content\Type\TypeKind;

/**
 * A type's **authors page** (D-329): `_authors` in its folder, which
 * introduces the type's list of authors. The admin pins it in its type's
 * list below the index page; it has no address of its own, so it's
 * edited like any entry but not duplicated.
 */
final class AuthorsPage
{
	/**
	 * Whether an entry is its type's authors page.
	 */
	public static function is(Entry $entry): bool
	{
		return $entry->key === AuthorsController::PAGE && $entry->type->kind() !== TypeKind::Pages;
	}
}
