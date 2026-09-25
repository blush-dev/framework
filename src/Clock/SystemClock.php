<?php

/**
 * System clock.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Clock;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Psr\Clock\ClockInterface;

/**
 * The real clock: returns the current time in the site timezone. Depend on
 * `Psr\Clock\ClockInterface` rather than calling `new DateTimeImmutable()`, so
 * scheduled content and cache lifetimes can be tested with `FrozenClock`.
 */
final readonly class SystemClock implements ClockInterface
{
	public function __construct(private DateTimeZone $timezone = new DateTimeZone('UTC'))
	{
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function now(): DateTimeImmutable
	{
		return new DateTimeImmutable('now', $this->timezone);
	}
}
