<?php

/**
 * Console progress bar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * A one-line progress bar that redraws itself in place:
 *
 *      [==============>               ]  512/1183  43%
 *
 * It draws only on an ANSI terminal at normal verbosity or above, so
 * piped output, logs, and tests stay clean, and it redraws only when the
 * percentage changes. `finish()` clears the line for the summary that
 * follows.
 */
final class ProgressBar
{
	private int $current = 0;

	private int $drawn = -1;

	public function __construct(
		private readonly Output $output,
		private int $total = 0,
		private readonly int $width = 30
	) {}

	/**
	 * Moves the bar forward.
	 */
	public function advance(int $steps = 1): void
	{
		$this->update($this->current + $steps);
	}

	/**
	 * Sets how far along the bar is, and optionally a new total.
	 */
	public function update(int $current, ?int $total = null): void
	{
		$this->total   = max(0, $total ?? $this->total);
		$this->current = max(0, min($current, $this->total));

		$this->draw();
	}

	/**
	 * Clears the bar.
	 */
	public function finish(): void
	{
		if ($this->isVisible() && $this->drawn >= 0) {
			$this->output->write("\r\033[2K");
		}

		$this->drawn = -1;
	}

	/**
	 * Returns the bar as text, for the current position.
	 */
	public function render(): string
	{
		$percent = $this->percent();
		$filled  = intdiv($this->width * $percent, 100);
		$bar     = str_repeat('=', $filled) . ($filled < $this->width ? '>' . str_repeat(' ', $this->width - $filled - 1) : '');
		$digits  = strlen((string) $this->total);

		return sprintf('[%s] %*d/%d %3d%%', $bar, $digits, $this->current, $this->total, $percent);
	}

	/**
	 * Redraws the bar when its percentage changed.
	 */
	private function draw(): void
	{
		$percent = $this->percent();

		if (! $this->isVisible() || $percent === $this->drawn) {
			return;
		}

		$this->drawn = $percent;
		$this->output->write("\r\033[2K" . $this->render());
	}

	/**
	 * Returns how far along the bar is, from 0 to 100.
	 */
	private function percent(): int
	{
		return $this->total === 0 ? 0 : intdiv($this->current * 100, $this->total);
	}

	/**
	 * Returns whether the bar is drawn at all.
	 */
	private function isVisible(): bool
	{
		return $this->output->ansi && $this->output->shows(Verbosity::Normal);
	}
}
