<?php

/**
 * Web runner.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Psr\Clock\ClockInterface;

/**
 * Runs due work after a page is served, for sites without cron (D-621):
 * in `auto` mode only, under PHP-FPM (which can finish the response
 * first, so the visitor never waits), at most once a minute, and only
 * while neither cron nor a worker has run in the last two minutes, so a
 * site with cron never does work on visits. It works for
 * `JobConfig::$webBudget` seconds.
 */
final readonly class WebRunner
{
	/**
	 * Seconds without cron or a worker before visits step in.
	 */
	public const int STALE = 120;

	/**
	 * Seconds between runs after visits.
	 */
	public const int EVERY = 60;

	public function __construct(
		private JobConfig $config,
		private JobRunner $runner,
		private Scheduler $scheduler,
		private JobStore $store,
		private ClockInterface $clock
	) {}

	/**
	 * Whether the server can finish a response and keep working.
	 */
	public static function isAvailable(): bool
	{
		return function_exists('fastcgi_finish_request');
	}

	/**
	 * Finishes the response and runs due work, when it's this request's
	 * turn. Call it after the response is sent.
	 */
	public function afterResponse(): void
	{
		if ($this->config->runner !== RunnerMode::Auto || ! self::isAvailable() || ! $this->isDue() || ! $this->take()) {
			return;
		}

		fastcgi_finish_request();
		ignore_user_abort(true);

		$this->scheduler->tick();
		$this->runner->work(RunnerKind::Web, $this->config->webBudget);
	}

	/**
	 * Whether visits should run work now: cron and a worker are quiet,
	 * and the last run after a visit is a minute old.
	 */
	private function isDue(): bool
	{
		$now  = $this->clock->now()->getTimestamp();
		$runs = $this->runner->lastRuns();

		return ($this->runner->lastDependableRun() ?? 0) < $now - self::STALE
			&& ($runs[RunnerKind::Web->value] ?? 0) < $now - self::EVERY;
	}

	/**
	 * Takes this minute's turn, so of the requests finishing at once,
	 * only one works.
	 */
	private function take(): bool
	{
		$taken = false;

		$this->store->locked('web', function () use (&$taken): void {
			if ($this->isDue()) {
				$this->runner->beat(RunnerKind::Web);
				$taken = true;
			}
		});

		return $taken;
	}
}
