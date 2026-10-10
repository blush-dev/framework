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
 * A one-line progress bar that redraws itself in place, after what it's
 * doing when it's named:
 *
 *      Writing upload dates  [==============>               ]  512/1183  43%
 *
 * Until its total is known, a spinner with the count so far:
 *
 *      ⠹ Reading the media index  512
 *
 * It draws only on an ANSI terminal at normal verbosity or above, so
 * piped output, logs, and tests stay clean. A bar redraws only when its
 * percentage changes, and a spinner at most ten times a second.
 * `finish()` clears the line for the summary that follows; `done()`
 * leaves a line saying the step is done, for one of several
 * (`ProgressSteps`), unless it counted nothing.
 */
final class ProgressBar
{
	/**
	 * The spinner's frames.
	 */
	private const array FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

	/**
	 * How long a spinner's frame lasts, in nanoseconds.
	 */
	private const int FRAME = 100_000_000;

	private int $current = 0;

	private int $drawn = -1;

	private int $frame = 0;

	private int $spun = 0;

	public function __construct(
		private readonly Output $output,
		private int $total = 0,
		private readonly int $width = 30,
		public readonly string $label = ''
	) {}

	/**
	 * Moves the bar forward.
	 */
	public function advance(int $steps = 1): void
	{
		$this->update($this->current + $steps);
	}

	/**
	 * Sets how far along the bar is, and optionally a new total. Without
	 * a total, it's a count so far.
	 */
	public function update(int $current, ?int $total = null): void
	{
		$this->total   = max(0, $total ?? $this->total);
		$this->current = max(0, $this->total === 0 ? $current : min($current, $this->total));

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
	 * Replaces the bar with a line saying its step is done, with its
	 * count, when it was drawn; one that counted nothing is cleared.
	 */
	public function done(): void
	{
		$count = $this->total > 0 ? $this->total : $this->current;

		if ($count === 0) {
			$this->finish();

			return;
		}

		if ($this->isVisible() && $this->drawn >= 0) {
			$this->output->write("\r\033[2K" . $this->output->style('✓', Style::Green) . ' ' . $this->label . ($count > 0 ? '  ' . $this->output->style((string) $count, Style::Dim) : '') . PHP_EOL);
		}

		$this->drawn = -1;
	}

	/**
	 * Returns the bar as text, for the current position.
	 */
	public function render(): string
	{
		if ($this->total === 0 && $this->label !== '') {
			return sprintf('%s %s', self::FRAMES[$this->frame % count(self::FRAMES)], $this->label) . ($this->current > 0 ? "  {$this->current}" : '');
		}

		$percent = $this->percent();
		$filled  = intdiv($this->width * $percent, 100);
		$bar     = str_repeat('=', $filled) . ($filled < $this->width ? '>' . str_repeat(' ', $this->width - $filled - 1) : '');
		$digits  = strlen((string) $this->total);

		return ($this->label === '' ? '' : "{$this->label}  ") . sprintf('[%s] %*d/%d %3d%%', $bar, $digits, $this->current, $this->total, $percent);
	}

	/**
	 * Redraws the bar when its percentage changed, or a spinner when its
	 * frame is over.
	 */
	private function draw(): void
	{
		if (! $this->isVisible()) {
			return;
		}

		if ($this->total === 0 && $this->label !== '') {
			$now = hrtime(true);

			if ($this->drawn >= 0 && $now - $this->spun < self::FRAME) {
				return;
			}

			$this->frame = $this->drawn >= 0 ? $this->frame + 1 : 0;
			$this->spun  = $now;
			$this->drawn = 0;
			$this->output->write("\r\033[2K" . $this->render());

			return;
		}

		$percent = $this->percent();

		if ($percent === $this->drawn) {
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
