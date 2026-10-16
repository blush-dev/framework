<?php

/**
 * Backup config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * Where the site's backups are kept (D-885), from `config/backups.php`:
 *
 *     return new BackupConfig(path: '/srv/backups/example');
 *
 * - `path`: the folder, from the site's root unless it's absolute;
 *   `storage/backups/site` by default, beside the versions extension
 *   replacements keep (D-393). It's never served.
 * - `schedule`: when the site backs itself up (`BackupSchedule`, D-887),
 *   off by default; Tools → Backups saves it as a setting over this.
 */
final readonly class BackupConfig implements Config
{
	public const string PATH = 'storage/backups/site';

	public function __construct(
		public string $path = self::PATH,
		public BackupSchedule $schedule = new BackupSchedule()
	) {
		if (trim($path) === '') {
			throw new InvalidConfig('BackupConfig "path" can\'t be empty.');
		}
	}

	/**
	 * Returns the folder, from the site's root unless it's absolute.
	 */
	public function folder(string $root): string
	{
		return rtrim(str_starts_with($this->path, '/') ? $this->path : "{$root}/{$this->path}", '/');
	}

	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['path', 'schedule']);

		$schedule = $data['schedule'] ?? null;

		return new static(
			path: $values->string('path', self::PATH),
			schedule: match (true) {
				$schedule instanceof BackupSchedule => $schedule,
				is_array($schedule)                 => BackupSchedule::fromArray($schedule),
				$schedule === null                  => new BackupSchedule(),
				default                             => throw new InvalidConfig('BackupConfig "schedule" must be a BackupSchedule or an array.')
			}
		);
	}

	#[Override]
	public function toArray(): array
	{
		return ['path' => $this->path, 'schedule' => $this->schedule->toArray()];
	}
}
