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
 * A one-line progress bar that redraws itself in place, after a spinner
 * and what it's doing when it's named (D-722):
 *
 *      ⠹ Writing upload dates  ━━━━━━━━━━━━╸─────────────────  512 / 1,183  43%
 *
 * The part done is drawn in the accent and the rest dim, and the line
 * is at most 40 columns, at least 20, as the terminal's width allows.
 * Until its total is known, a spinner with the count so far:
 *
 *      ⠹ Reading the media index  512
 *
 * It draws on a terminal at normal verbosity or above, in color only
 * with ANSI (`NO_COLOR` keeps the bar), so piped output, logs, and tests
 * stay clean. It redraws when its percentage changes, and its spinner at
 * most ten times a second. `finish()` clears the line for the summary
 * that follows; `done()` leaves a line saying the step is done, with
 * its count and how long it took, for one of several
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

	/**
	 * The narrowest and widest a bar is drawn, and its width off a
	 * terminal's known width.
	 */
	private const int NARROWEST = 20;

	private const int WIDEST = 40;

	private const int WIDTH = 30;

	private int $current = 0;

	private int $drawn = -1;

	private int $frame = 0;

	private int $spun = 0;

	private readonly int $started;

	public function __construct(
		private readonly Output $output,
		private int $total = 0,
		public readonly string $label = ''
	) {
		$this->started = hrtime(true);
	}

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
	 * count and how long it took, when it was drawn; one that counted
	 * nothing is cleared.
	 */
	public function done(): void
	{
		$count = $this->total > 0 ? $this->total : $this->current;

		if ($count === 0) {
			$this->finish();

			return;
		}

		if ($this->isVisible() && $this->drawn >= 0) {
			$this->output->write("\r\033[2K" . $this->output->tone('✓', Tone::Pass) . ' ' . $this->label . '  ' . $this->output->tone($this->output->join(number_format($count), self::elapsed(hrtime(true) - $this->started)), Tone::Muted) . PHP_EOL);
		}

		$this->drawn = -1;
	}

	/**
	 * Returns the bar as text, for the current position.
	 */
	public function render(): string
	{
		$spinner = $this->label === '' ? '' : self::FRAMES[$this->frame % count(self::FRAMES)] . " {$this->label}  ";

		if ($this->total === 0 && $this->label !== '') {
			return rtrim($spinner) . ($this->current > 0 ? '  ' . number_format($this->current) : '');
		}

		$percent = $this->percent();
		$total   = number_format($this->total);
		$counts  = sprintf('  %s / %s  %3d%%', str_pad(number_format($this->current), strlen($total), ' ', STR_PAD_LEFT), $total, $percent);
		$width   = $this->output->width === null ? self::WIDTH : max(self::NARROWEST, min(self::WIDEST, $this->output->width - mb_strwidth($spinner . $counts) - 1));
		$filled  = intdiv($width * $percent, 100);
		$bar     = $this->output->tone(str_repeat('━', $filled) . ($filled < $width ? '╸' : ''), Tone::Code)
			. $this->output->tone(str_repeat('─', max(0, $width - $filled - 1)), Tone::Muted);

		return $spinner . $bar . $counts;
	}

	/**
	 * Redraws the bar when its percentage changed or its spinner's frame
	 * is over.
	 */
	private function draw(): void
	{
		if (! $this->isVisible()) {
			return;
		}

		$now     = hrtime(true);
		$spin    = $this->label !== '' && ($this->drawn < 0 || $now - $this->spun >= self::FRAME);
		$percent = $this->total === 0 ? 0 : $this->percent();

		if (! $spin && $percent === $this->drawn) {
			return;
		}

		if ($spin) {
			$this->frame = $this->drawn >= 0 ? $this->frame + 1 : 0;
			$this->spun  = $now;
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
	 * Returns whether the bar is drawn at all: on a terminal, or with ANSI
	 * forced on.
	 */
	private function isVisible(): bool
	{
		return ($this->output->ansi || $this->output->width !== null) && $this->output->shows(Verbosity::Normal);
	}

	/**
	 * Returns how long something took, as people read it: `340 ms`,
	 * `2.4s`, or `1m 05s`.
	 */
	private static function elapsed(int $nanoseconds): string
	{
		$milliseconds = intdiv($nanoseconds, 1_000_000);

		return match (true) {
			$milliseconds < 1000  => "{$milliseconds} ms",
			$milliseconds < 60000 => sprintf('%.1fs', $milliseconds / 1000),
			default               => sprintf('%dm %02ds', intdiv($milliseconds, 60000), intdiv($milliseconds % 60000, 1000))
		};
	}
}
