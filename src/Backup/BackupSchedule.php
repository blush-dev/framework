<?php

/**
 * Backup schedule.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Blush\Auth\Account;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Job\Frequency;

/**
 * When the site backs itself up (D-887), as `BackupConfig`'s `schedule`,
 * saved from Tools → Backups as the `backups.schedule` setting:
 *
 *     new BackupSchedule(frequency: BackupFrequency::Weekly, day: 0, hour: 3, keep: 14)
 *
 * - `frequency`: off, daily, or weekly; `day` (0 for Sunday to 6) is a
 *   weekly backup's, and `hour` (0 to 23) is in the site's time zone.
 * - `keep`: how many of the schedule's own backups are kept; the oldest
 *   goes when a new one is made. Backups made any other way stay.
 * - `parts`: the parts each holds, or `null` for every part, plugins'
 *   added since included.
 * - `notice`: when it emails (`BackupNotice`), to `to`, an address, or
 *   the site's own (`AppConfig::$email`) when empty.
 */
final readonly class BackupSchedule
{
	/**
	 * The most backups the schedule keeps.
	 */
	public const int KEEP_MAX = 365;

	/**
	 * @param list<string>|null $parts
	 * @throws InvalidConfig When a value doesn't fit.
	 */
	public function __construct(
		public BackupFrequency $frequency = BackupFrequency::Off,
		public int $day = 0,
		public int $hour = 3,
		public int $keep = 14,
		public ?array $parts = null,
		public BackupNotice $notice = BackupNotice::Failures,
		public string $to = ''
	) {
		if ($day < 0 || $day > 6) {
			throw new InvalidConfig(sprintf('BackupSchedule "day" must be 0 (Sunday) to 6; %d given.', $day));
		}

		if ($hour < 0 || $hour > 23) {
			throw new InvalidConfig(sprintf('BackupSchedule "hour" must be 0 to 23; %d given.', $hour));
		}

		if ($keep < 1 || $keep > self::KEEP_MAX) {
			throw new InvalidConfig(sprintf('BackupSchedule "keep" must be 1 to %d; %d given.', self::KEEP_MAX, $keep));
		}

		if ($parts === []) {
			throw new InvalidConfig('BackupSchedule "parts" can\'t be empty; leave it out for every part.');
		}

		if ($to !== '' && ! Account::isValidEmail($to)) {
			throw new InvalidConfig(sprintf('BackupSchedule "to" must be an email address; "%s" given.', $to));
		}
	}

	/**
	 * Returns when the scheduler runs it, or `null` when it's off.
	 */
	public function when(): ?Frequency
	{
		$at = sprintf('%02d:00', $this->hour);

		return match ($this->frequency) {
			BackupFrequency::Off    => null,
			BackupFrequency::Daily  => Frequency::daily($at),
			BackupFrequency::Weekly => Frequency::weekly($this->day, $at)
		};
	}

	/**
	 * @param  array<mixed> $data
	 * @throws InvalidConfig
	 */
	public static function fromArray(array $data): self
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['frequency', 'day', 'hour', 'keep', 'parts', 'notice', 'to']);

		return new self(
			frequency: $values->enum('frequency', BackupFrequency::class, BackupFrequency::Off),
			day: $values->int('day', 0),
			hour: $values->int('hour', 3),
			keep: $values->int('keep', 14),
			parts: $values->nullableStringList('parts'),
			notice: $values->enum('notice', BackupNotice::class, BackupNotice::Failures),
			to: trim($values->string('to', ''))
		);
	}

	/**
	 * @return array{frequency: string, day: int, hour: int, keep: int, parts: list<string>|null, notice: string, to: string}
	 */
	public function toArray(): array
	{
		return [
			'frequency' => $this->frequency->value,
			'day'       => $this->day,
			'hour'      => $this->hour,
			'keep'      => $this->keep,
			'parts'     => $this->parts,
			'notice'    => $this->notice->value,
			'to'        => $this->to
		];
	}
}
