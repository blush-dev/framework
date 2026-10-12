<?php

/**
 * Extracted message.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

/**
 * A message found in code (D-784): a `t()` call's key and the text
 * written beside it, its note for translators, and where it was first
 * found (relative to the folder read, and its line).
 */
final readonly class ExtractedMessage
{
	public function __construct(
		public string $key,
		public string $text,
		public ?string $context,
		public string $file,
		public int $line
	) {}

	/**
	 * Returns where it was found, as `file:line`.
	 */
	public function where(): string
	{
		return "{$this->file}:{$this->line}";
	}
}
