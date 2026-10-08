<?php

/**
 * Job config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * Background jobs and the scheduler (D-621), from `config/jobs.php`:
 *
 *     return new JobConfig(runner: RunnerMode::Cron);
 *
 * - `runner`: when jobs run besides the command line (`RunnerMode`):
 *   `auto` (also after a page is served while cron hasn't run lately),
 *   `cron`, or `sync`.
 * - `budget`: the seconds `schedule:run` works the queue for, under
 *   cron's minute.
 * - `webBudget`: the seconds of work after a page is served.
 * - `timeout`: the seconds after which a job still running is taken for
 *   dead (its runner stopped) and queued again.
 * - `keepDone` and `keepFailed`: the seconds finished jobs are kept, for
 *   their results, before `blush/prune-jobs` removes them. Done jobs the
 *   scheduler queued aren't kept at all.
 */
final readonly class JobConfig implements Config
{
	/**
	 * @throws InvalidConfig
	 */
	public function __construct(
		public RunnerMode $runner = RunnerMode::Auto,
		public int $budget = 50,
		public int $webBudget = 10,
		public int $timeout = 900,
		public int $keepDone = 86400,
		public int $keepFailed = 604800
	) {
		foreach (['budget' => $budget, 'webBudget' => $webBudget, 'timeout' => $timeout, 'keepDone' => $keepDone, 'keepFailed' => $keepFailed] as $name => $value) {
			if ($value < 1) {
				throw new InvalidConfig(sprintf('JobConfig "%s" must be at least 1 second; %d given.', $name, $value));
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['runner', 'budget', 'webBudget', 'timeout', 'keepDone', 'keepFailed']);

		return new static(
			runner: $values->enum('runner', RunnerMode::class, RunnerMode::Auto),
			budget: $values->int('budget', 50),
			webBudget: $values->int('webBudget', 10),
			timeout: $values->int('timeout', 900),
			keepDone: $values->int('keepDone', 86400),
			keepFailed: $values->int('keepFailed', 604800)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'runner'     => $this->runner->value,
			'budget'     => $this->budget,
			'webBudget'  => $this->webBudget,
			'timeout'    => $this->timeout,
			'keepDone'   => $this->keepDone,
			'keepFailed' => $this->keepFailed
		];
	}
}
