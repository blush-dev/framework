<?php

/**
 * Entry status.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

/**
 * Where an entry is in its life. Front matter can set `published` or
 * `draft`; `Scheduled` is derived from a `published` date in the future.
 * Visibility is separate (D-082).
 */
enum Status: string
{
	case Published = 'published';
	case Draft     = 'draft';
	case Scheduled = 'scheduled';

	/**
	 * Returns the values front matter may set.
	 *
	 * @return list<string>
	 */
	public static function writable(): array
	{
		return [self::Published->value, self::Draft->value];
	}
}
