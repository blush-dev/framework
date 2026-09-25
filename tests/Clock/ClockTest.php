<?php

/**
 * Clock tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Clock;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Clock\FrozenClock;
use Blush\Clock\SystemClock;

#[CoversClass(SystemClock::class)]
#[CoversClass(FrozenClock::class)]
final class ClockTest extends TestCase
{
	public function testSystemClockUsesItsTimezone(): void
	{
		$clock = new SystemClock(new DateTimeZone('America/Chicago'));

		$this->assertSame('America/Chicago', $clock->now()->getTimezone()->getName());
		$this->assertEqualsWithDelta(time(), $clock->now()->getTimestamp(), 2);
	}

	public function testFrozenClockOnlyMovesWhenTold(): void
	{
		$clock = new FrozenClock('2026-09-25T10:00:00+00:00');

		$this->assertSame($clock->now(), $clock->now());

		$clock->advance('PT90M');
		$this->assertSame('2026-09-25T11:30:00+00:00', $clock->now()->format(DATE_ATOM));

		$clock->set(new DateTimeImmutable('2027-01-01T00:00:00+00:00'));
		$this->assertSame('2027-01-01T00:00:00+00:00', $clock->now()->format(DATE_ATOM));
	}
}
