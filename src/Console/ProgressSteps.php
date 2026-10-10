<?php

/**
 * Console progress steps.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * Shows a longer task's steps as they happen, a `ProgressBar` for each:
 * the service doing the task calls it with the step it's on and how far
 * along it is (the progress callback services take, a
 * `Closure(string $step, int $done, int $total): void`), and a new step
 * leaves the last one as a line saying it's done:
 *
 *      ✓ Writing upload dates  1903
 *      Updating the media index  [=========>          ]  640/1903  33%
 *
 * A total of 0 is a step whose size isn't known yet, shown as a spinner.
 * `finish()` ends the last step before the command's summary. Like a bar,
 * it draws only on a terminal.
 */
final class ProgressSteps
{
	private ?ProgressBar $bar = null;

	public function __construct(private readonly Output $output)
	{}

	/**
	 * Shows how far along a step is, starting it when it's new.
	 */
	public function __invoke(string $step, int $done, int $total): void
	{
		if ($this->bar === null || $this->bar->label !== $step) {
			$this->bar?->done();
			$this->bar = $this->output->progress($total, $step);
		}

		$this->bar->update($done, $total);
	}

	/**
	 * Ends the last step.
	 */
	public function finish(): void
	{
		$this->bar?->done();
		$this->bar = null;
	}
}
