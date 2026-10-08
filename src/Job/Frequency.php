<?php

/**
 * Frequency.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use DateTimeImmutable;

/**
 * How often a scheduled task runs (D-621): a five-field cron expression
 * (minute, hour, day of the month, month, day of the week), read in the
 * site's time zone, or one of the named shapes that build one:
 *
 *     Frequency::everyMinute();
 *     Frequency::everyMinutes(15);
 *     Frequency::hourly(30);
 *     Frequency::daily('03:00');
 *     Frequency::weekly(1, '06:00');
 *     Frequency::cron('0 9 1 * *');
 *
 * A field is `*`, a number, a range (`1-5`), a step (`*\/15`, `0-30/10`),
 * or a list of those (`1,15`). The day of the week runs 0 (Sunday) to 6,
 * and 7 is Sunday too. As in cron, when both day fields are limited, a
 * day matching either one counts. `@hourly`, `@daily`, `@weekly`,
 * `@monthly`, and `@yearly` are understood.
 */
final readonly class Frequency
{
	/**
	 * Each field's smallest and largest value.
	 *
	 * @var list<array{int, int}>
	 */
	private const array RANGES = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]];

	/**
	 * The shorthand expressions.
	 *
	 * @var array<string, string>
	 */
	private const array MACROS = [
		'@hourly'  => '0 * * * *',
		'@daily'   => '0 0 * * *',
		'@weekly'  => '0 0 * * 0',
		'@monthly' => '0 0 1 * *',
		'@yearly'  => '0 0 1 1 *'
	];

	/**
	 * The days of the week, from Sunday.
	 *
	 * @var list<string>
	 */
	private const array DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

	/**
	 * @param list<array<int, true>> $fields Each field's matching values.
	 */
	private function __construct(
		public string $expression,
		private array $fields
	) {}

	/**
	 * Reads a cron expression.
	 *
	 * @throws InvalidFrequency
	 */
	public static function cron(string $expression): self
	{
		$expression = trim(self::MACROS[strtolower(trim($expression))] ?? $expression);
		$parts      = preg_split('/\s+/', $expression) ?: [];

		if (count($parts) !== 5) {
			throw new InvalidFrequency(sprintf('A frequency is five fields (minute, hour, day, month, day of the week); "%s" given.', $expression));
		}

		$fields = [];

		foreach ($parts as $index => $part) {
			$fields[] = self::field($part, ...self::RANGES[$index]);
		}

		// Sunday is 0 and 7.
		if (isset($fields[4][7])) {
			$fields[4][0] = true;
			unset($fields[4][7]);
		}

		return new self(implode(' ', $parts), $fields);
	}

	/**
	 * Every minute.
	 */
	public static function everyMinute(): self
	{
		return self::cron('* * * * *');
	}

	/**
	 * Every so many minutes, from the top of the hour.
	 *
	 * @throws InvalidFrequency
	 */
	public static function everyMinutes(int $minutes): self
	{
		if ($minutes < 1 || $minutes > 59) {
			throw new InvalidFrequency(sprintf('Every so many minutes must be 1 to 59; %d given.', $minutes));
		}

		return self::cron("*/{$minutes} * * * *");
	}

	/**
	 * Every hour, at a minute past it.
	 *
	 * @throws InvalidFrequency
	 */
	public static function hourly(int $minute = 0): self
	{
		return self::cron("{$minute} * * * *");
	}

	/**
	 * Every day, at a time (`HH:MM`).
	 *
	 * @throws InvalidFrequency
	 */
	public static function daily(string $at = '00:00'): self
	{
		[$hour, $minute] = self::time($at);

		return self::cron("{$minute} {$hour} * * *");
	}

	/**
	 * Every week, on a day (0 for Sunday to 6) at a time (`HH:MM`).
	 *
	 * @throws InvalidFrequency
	 */
	public static function weekly(int $day = 0, string $at = '00:00'): self
	{
		[$hour, $minute] = self::time($at);

		return self::cron("{$minute} {$hour} * * {$day}");
	}

	/**
	 * Whether a time's minute is one the frequency runs in.
	 */
	public function matches(DateTimeImmutable $time): bool
	{
		return isset($this->fields[0][(int) $time->format('i')])
			&& isset($this->fields[1][(int) $time->format('G')])
			&& isset($this->fields[3][(int) $time->format('n')])
			&& $this->dayMatches($time);
	}

	/**
	 * Returns the first minute after a time that the frequency runs in.
	 * Months and days that don't match are skipped whole, so even a
	 * yearly frequency is found in a few hundred steps.
	 *
	 * @throws InvalidFrequency When no time ever matches (February 30th).
	 */
	public function next(DateTimeImmutable $after): DateTimeImmutable
	{
		// The start of the next minute.
		$time = $after->setTimestamp($after->getTimestamp() - $after->getTimestamp() % 60 + 60);

		for ($step = 0; $step < 5000; $step++) {
			if (! isset($this->fields[3][(int) $time->format('n')])) {
				$time = $time->modify('first day of next month')->setTime(0, 0);
			} elseif (! $this->dayMatches($time)) {
				$time = $time->modify('+1 day')->setTime(0, 0);
			} elseif (! isset($this->fields[1][(int) $time->format('G')])) {
				// By the clock's seconds, so a repeated hour (when daylight
				// saving time ends) is still moved past.
				$time = $time->setTimestamp($time->getTimestamp() - (int) $time->format('i') * 60 + 3600);
			} elseif (! isset($this->fields[0][(int) $time->format('i')])) {
				$time = $time->setTimestamp($time->getTimestamp() + 60);
			} else {
				return $time;
			}
		}

		throw new InvalidFrequency(sprintf('The frequency "%s" never comes.', $this->expression));
	}

	/**
	 * Describes the frequency in words, where it's one of the named
	 * shapes, else as its expression.
	 */
	public function describe(): string
	{
		$parts = explode(' ', $this->expression);

		[$minute, $hour, $day, $month, $weekday] = $parts;

		$rest = "{$day} {$month} {$weekday}";

		return match (true) {
			$this->expression === '* * * * *'                                          => 'Every minute',
			$hour === '*' && $rest === '* * *' && preg_match('#^\*/(\d+)$#', $minute, $step) === 1 => sprintf('Every %d minutes', (int) $step[1]),
			$hour === '*' && $rest === '* * *' && ctype_digit($minute)                  => sprintf('Hourly at :%02d', (int) $minute),
			ctype_digit($hour) && ctype_digit($minute) && $rest === '* * *'             => sprintf('Daily at %02d:%02d', (int) $hour, (int) $minute),
			ctype_digit($hour) && ctype_digit($minute) && $day === '*' && $month === '*' && ctype_digit($weekday) && (int) $weekday <= 7
				=> sprintf('%ss at %02d:%02d', self::DAYS[(int) $weekday % 7], (int) $hour, (int) $minute),
			default                                                                     => "Cron: {$this->expression}"
		};
	}

	/**
	 * Whether a time's day matches both day fields, or either one when
	 * both are limited, as cron has it.
	 */
	private function dayMatches(DateTimeImmutable $time): bool
	{
		$day     = isset($this->fields[2][(int) $time->format('j')]);
		$weekday = isset($this->fields[4][(int) $time->format('w')]);

		$days     = count($this->fields[2]) < 31;
		$weekdays = count($this->fields[4]) < 7;

		return $days && $weekdays ? $day || $weekday : $day && $weekday;
	}

	/**
	 * Reads one field into the values it matches.
	 *
	 * @return array<int, true>
	 * @throws InvalidFrequency
	 */
	private static function field(string $field, int $min, int $max): array
	{
		$values = [];

		foreach (explode(',', $field) as $part) {
			if (preg_match('#^(\*|\d+(?:-\d+)?)(?:/(\d+))?$#', $part, $match) !== 1) {
				throw new InvalidFrequency(sprintf('"%s" isn\'t a cron field.', $field));
			}

			$step = isset($match[2]) ? (int) $match[2] : 1;

			if ($match[1] === '*') {
				[$from, $to] = [$min, $max === 7 ? 6 : $max];
			} elseif (str_contains($match[1], '-')) {
				[$from, $to] = array_map(intval(...), explode('-', $match[1]));
			} else {
				$from = (int) $match[1];
				$to   = isset($match[2]) ? $max : $from;
			}

			if ($step < 1 || $from < $min || $to > $max || $from > $to) {
				throw new InvalidFrequency(sprintf('"%s" is outside %d to %d.', $part, $min, $max));
			}

			for ($value = $from; $value <= $to; $value += $step) {
				$values[$value] = true;
			}
		}

		return $values;
	}

	/**
	 * Reads a time of day (`HH:MM`).
	 *
	 * @return array{int, int}
	 * @throws InvalidFrequency
	 */
	private static function time(string $at): array
	{
		if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $at, $match) !== 1) {
			throw new InvalidFrequency(sprintf('A time of day is "HH:MM"; "%s" given.', $at));
		}

		return [(int) $match[1], (int) $match[2]];
	}
}
