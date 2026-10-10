<?php

/**
 * Key.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * A key pressed in a prompt, with the text it types when it's a
 * character (D-719).
 */
final readonly class Key
{
	public function __construct(
		public KeyType $type,
		public string $char = ''
	) {}
}
