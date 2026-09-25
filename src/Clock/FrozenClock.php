<?php

/**
 * Frozen clock.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Clock;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock that only moves when told to, for tests and for rendering a site "as
 * of" a given moment (previewing scheduled content, say).
 */
final class FrozenClock implements ClockInterface
{
	private DateTimeImmutable $now;

	public function __construct(DateTimeInterface|string $now = 'now')
	{
		$this->now = $this->toImmutable($now);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function now(): DateTimeImmutable
	{
		return $this->now;
	}

	/**
	 * Moves the clock to the given moment.
	 */
	public function set(DateTimeInterface|string $now): void
	{
		$this->now = $this->toImmutable($now);
	}

	/**
	 * Moves the clock forward by an interval (`PT1H`, say, or a
	 * `DateInterval`).
	 */
	public function advance(DateInterval|string $interval): void
	{
		$this->now = $this->now->add(is_string($interval) ? new DateInterval($interval) : $interval);
	}

	/**
	 * Converts a moment to an immutable date.
	 */
	private function toImmutable(DateTimeInterface|string $moment): DateTimeImmutable
	{
		return is_string($moment)
			? new DateTimeImmutable($moment)
			: DateTimeImmutable::createFromInterface($moment);
	}
}
