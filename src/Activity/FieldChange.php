<?php

/**
 * Activity field change.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

/**
 * One field a change changed (D-902): an entry's front matter key or a
 * media file's detail, with its value before and after, `null` where it
 * had none.
 */
final readonly class FieldChange
{
	public function __construct(
		public string $name,
		public mixed $before,
		public mixed $after
	) {}
}
