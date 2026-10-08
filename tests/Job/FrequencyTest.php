<?php

/**
 * Frequency tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Job;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Job\Frequency;
use Blush\Job\InvalidFrequency;

#[CoversClass(Frequency::class)]
final class FrequencyTest extends TestCase
{
	private static function at(string $time): DateTimeImmutable
	{
		return new DateTimeImmutable($time, new DateTimeZone('America/Chicago'));
	}

	/**
	 * @return iterable<string, array{Frequency, string, string}>
	 */
	public static function nextTimes(): iterable
	{
		yield 'every minute' => [Frequency::everyMinute(), '2026-06-01 12:00:30', '2026-06-01 12:01'];
		yield 'every 15 minutes' => [Frequency::everyMinutes(15), '2026-06-01 12:01', '2026-06-01 12:15'];
		yield 'hourly at :30' => [Frequency::hourly(30), '2026-06-01 12:30', '2026-06-01 13:30'];
		yield 'daily, tomorrow' => [Frequency::daily('03:00'), '2026-06-01 12:00', '2026-06-02 03:00'];
		yield 'daily, the next month' => [Frequency::daily('03:00'), '2026-06-30 04:00', '2026-07-01 03:00'];
		yield 'weekly on Monday' => [Frequency::weekly(1, '06:00'), '2026-06-03 12:00', '2026-06-08 06:00'];
		yield 'yearly' => [Frequency::cron('@yearly'), '2026-06-01 12:00', '2027-01-01 00:00'];
		yield 'either day field' => [Frequency::cron('0 9 15 * 3'), '2026-06-09 12:00', '2026-06-10 09:00'];
		yield 'a list and a range' => [Frequency::cron('5,10 8-9 * * *'), '2026-06-01 09:06', '2026-06-01 09:10'];
		yield 'Sunday as 7' => [Frequency::cron('0 0 * * 7'), '2026-06-01 12:00', '2026-06-07 00:00'];
		yield 'past the hour that repeats' => [Frequency::cron('30 2 * * *'), '2026-11-01 01:59', '2026-11-01 02:30'];
	}

	#[DataProvider('nextTimes')]
	public function testFindsTheNextTime(Frequency $frequency, string $after, string $next): void
	{
		$found = $frequency->next(self::at($after));

		$this->assertSame($next, $found->format('Y-m-d H:i'));
		$this->assertTrue($frequency->matches($found));
	}

	public function testDescribesItselfInWords(): void
	{
		$this->assertSame('Every minute', Frequency::everyMinute()->describe());
		$this->assertSame('Every 15 minutes', Frequency::everyMinutes(15)->describe());
		$this->assertSame('Hourly at :05', Frequency::hourly(5)->describe());
		$this->assertSame('Daily at 03:00', Frequency::daily('03:00')->describe());
		$this->assertSame('Mondays at 06:00', Frequency::weekly(1, '06:00')->describe());
		$this->assertSame('Cron: 0 9 1 * *', Frequency::cron('0 9 1 * *')->describe());
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function invalidExpressions(): iterable
	{
		yield 'too few fields' => ['* * * *'];
		yield 'out of range' => ['60 * * * *'];
		yield 'not a field' => ['a * * * *'];
		yield 'a backwards range' => ['* 9-8 * * *'];
		yield 'a zero step' => ['*/0 * * * *'];
	}

	#[DataProvider('invalidExpressions')]
	public function testRejectsWhatIsNotCron(string $expression): void
	{
		$this->expectException(InvalidFrequency::class);

		Frequency::cron($expression);
	}

	public function testSaysWhenATimeNeverComes(): void
	{
		$this->expectException(InvalidFrequency::class);
		$this->expectExceptionMessage('never comes');

		Frequency::cron('0 0 30 2 *')->next(self::at('2026-06-01 12:00'));
	}

	public function testRejectsABadTimeOfDay(): void
	{
		$this->expectException(InvalidFrequency::class);

		Frequency::daily('25:00');
	}
}
