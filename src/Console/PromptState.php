<?php

/**
 * Prompt state.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * Where a prompt drawn on a terminal is while keys are pressed (D-719):
 * the text typed (an answer, or a list's filter) and the cursor in it,
 * the error shown, the option highlighted, the options picked, and the
 * toggle's side.
 *
 * @internal
 */
final class PromptState
{
	/**
	 * @param list<string> $picked
	 */
	public function __construct(
		public string $text = '',
		public int $cursor = 0,
		public ?string $error = null,
		public bool $tried = false,
		public int $index = 0,
		public array $picked = [],
		public bool $yes = false
	) {}
}
