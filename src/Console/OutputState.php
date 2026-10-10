<?php

/**
 * Output state.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * What `Output` has written so far, shared by its copies, so a section
 * or a summary knows whether a blank line goes before it.
 *
 * @internal
 */
final class OutputState
{
	/**
	 * The last two characters written to standard output, without escape
	 * codes.
	 */
	public string $tail = '';

	/**
	 * What `$tail` was when the current line started, for when a line
	 * drawn in place (a progress bar) is cleared.
	 */
	public string $line = '';

	/**
	 * Notes text written: a line cleared (`\r\033[2K`) takes the tail
	 * back to the line's start, and escape codes take no room.
	 */
	public function remember(string $text): void
	{
		$cleared = strrpos($text, "\r\033[2K");

		if ($cleared !== false) {
			$this->tail = $this->line;
			$text       = substr($text, $cleared + 5);
		}

		$text     = (string) preg_replace('/\033\[[\d;?]*[A-Za-z]/', '', $text);
		$combined = $this->tail . $text;
		$newline  = strrpos($combined, PHP_EOL);

		if ($newline !== false) {
			$this->line = substr(substr($combined, 0, $newline + 1), -2);
		}

		$this->tail = substr($combined, -2);
	}
}
