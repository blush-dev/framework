<?php

/**
 * Prompt frame.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * Draws a prompt on a terminal and redraws it in place as keys are
 * pressed, then leaves one line with its answer (D-719).
 */
final class Frame
{
	/**
	 * The line the cursor was left on, counted from the frame's first.
	 */
	private int $row = 0;

	public function __construct(
		private readonly Output $output
	) {}

	/**
	 * Draws lines over the last ones drawn, leaving the cursor at a row
	 * and column when given; hidden otherwise.
	 *
	 * @param list<string>     $lines
	 * @param ?array{int, int} $cursor
	 */
	public function draw(array $lines, ?array $cursor = null): void
	{
		$text = $this->erase() . implode(PHP_EOL, $lines);
		$last = count($lines) - 1;

		if ($cursor === null) {
			$this->row = $last;
			$this->output->write("\033[?25l" . $text, Verbosity::Quiet);

			return;
		}

		[$row, $column] = $cursor;

		$this->row = $row;
		$this->output->write(
			$text . ($last > $row ? "\033[" . ($last - $row) . 'A' : '') . "\r" . ($column > 0 ? "\033[{$column}C" : '') . "\033[?25h",
			Verbosity::Quiet
		);
	}

	/**
	 * Replaces the frame with its last line and shows the cursor again.
	 */
	public function finish(string $line): void
	{
		$this->output->write($this->erase() . $line . PHP_EOL . "\033[?25h", Verbosity::Quiet);
		$this->row = 0;
	}

	/**
	 * Returns what moves the cursor back to the frame's first line and
	 * clears from there down.
	 */
	private function erase(): string
	{
		return ($this->row > 0 ? "\033[{$this->row}A" : '') . "\r\033[J";
	}
}
