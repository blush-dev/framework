<?php

/**
 * Backup.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use NoDiscard;

/**
 * A backup in the backups folder (D-885): its `.zip` (`blush-{Y-m-d-Hi}.zip`)
 * and the `{name}.json` beside it. What's fixed when it's made is also the
 * archive's own `backup.json` (`manifest()`): its `formatVersion`, the Blush
 * version, when, by whom, how, its note then, each area's driver, and
 * each part's tables with its count and bytes. The file beside it adds
 * what changes later: its status, size, note, a failure's reason, and the
 * job making it.
 */
final readonly class Backup
{
	/**
	 * The archive's format version (`formatVersion`), raised when what's
	 * in it changes; `blushVersion` is the Blush that made it (D-889).
	 */
	public const int FORMAT_VERSION = 1;

	/**
	 * The archive's own description of itself.
	 */
	public const string MANIFEST = 'backup.json';

	/**
	 * The longest note.
	 */
	public const int NOTE_LENGTH = 60;

	/**
	 * A backup's name: what's left of its file name.
	 */
	public const string NAME = '/\A[a-z0-9][a-z0-9-]{0,99}\z/';

	/**
	 * @param array<string, array{count: int, bytes: int, tables: list<string>}> $parts   By part name.
	 * @param array<string, string>                                               $drivers Each area's driver, by area.
	 */
	public function __construct(
		public string $name,
		public BackupStatus $status,
		public BackupKind $kind,
		public int $created,
		public ?string $account = null,
		public string $note = '',
		public array $parts = [],
		public array $drivers = [],
		public int $size = 0,
		public ?string $reason = null,
		public ?string $job = null,
		public int $formatVersion = self::FORMAT_VERSION,
		public string $blushVersion = '',
		public bool $canceled = false
	) {}

	/**
	 * Returns whether a path in an archive is one a backup holds and
	 * lands inside the folder it's unzipped in: `backup.json`, or a file
	 * under a driver's folder (`filesystem/`, `sqlite/`), with no `..`,
	 * absolute path, backslash, or empty part. Restoring and uploading
	 * check every path with it.
	 */
	public static function isSafePath(string $name): bool
	{
		if ($name === self::MANIFEST) {
			return true;
		}

		if (str_contains($name, '\\') || str_contains($name, "\0") || str_starts_with($name, '/')) {
			return false;
		}

		$segments = explode('/', $name);

		if (! in_array($segments[0], [FilesystemTableBackup::DRIVER, SqliteTableBackup::DRIVER], true) || count($segments) < 2) {
			return false;
		}

		return array_all($segments, static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..');
	}

	/**
	 * Returns a copy with other values.
	 *
	 * @param array<string, mixed> $values
	 */
	#[NoDiscard]
	public function with(array $values): self
	{
		return clone($this, $values);
	}

	/**
	 * Returns the parts' names.
	 *
	 * @return list<string>
	 */
	public function partNames(): array
	{
		return array_keys($this->parts);
	}

	/**
	 * Returns what the archive says of itself.
	 *
	 * @return array<string, mixed>
	 */
	public function manifest(): array
	{
		return [
			'formatVersion' => $this->formatVersion,
			'blushVersion'  => $this->blushVersion,
			'name'          => $this->name,
			'created'       => gmdate('c', $this->created),
			'account'       => $this->account,
			'kind'          => $this->kind->value,
			'note'          => $this->note,
			'drivers'       => $this->drivers,
			'parts'         => $this->parts
		];
	}

	/**
	 * Returns what the file beside the archive holds.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			...$this->manifest(),
			'status'   => $this->status->value,
			'size'     => $this->size,
			'reason'   => $this->reason,
			'job'      => $this->job,
			'canceled' => $this->canceled
		];
	}

	/**
	 * Builds a backup from the file beside it, or from an archive's
	 * `backup.json` (a done backup, its `size` given), or `null` when the
	 * data isn't one.
	 *
	 * @param array<mixed> $data
	 */
	public static function fromArray(array $data, ?int $size = null): ?self
	{
		$name    = $data['name'] ?? null;
		$created = is_string($data['created'] ?? null) ? strtotime($data['created']) : false;
		$kind    = is_string($data['kind'] ?? null) ? BackupKind::tryFrom($data['kind']) : null;

		if (! is_string($name) || preg_match(self::NAME, $name) !== 1 || $created === false || $kind === null) {
			return null;
		}

		$status = $size !== null ? BackupStatus::Done : (is_string($data['status'] ?? null) ? BackupStatus::tryFrom($data['status']) : null);

		if ($status === null) {
			return null;
		}

		return new self(
			name: $name,
			status: $status,
			kind: $kind,
			created: $created,
			account: is_string($data['account'] ?? null) ? $data['account'] : null,
			note: is_string($data['note'] ?? null) ? $data['note'] : '',
			parts: self::parts($data['parts'] ?? null),
			drivers: self::drivers($data['drivers'] ?? null),
			size: $size ?? (is_int($data['size'] ?? null) ? $data['size'] : 0),
			reason: is_string($data['reason'] ?? null) ? $data['reason'] : null,
			job: is_string($data['job'] ?? null) ? $data['job'] : null,
			formatVersion: is_int($data['formatVersion'] ?? null) ? $data['formatVersion'] : 0,
			blushVersion: is_string($data['blushVersion'] ?? null) ? $data['blushVersion'] : '',
			canceled: ($data['canceled'] ?? false) === true
		);
	}

	/**
	 * Reads each area's driver from stored data.
	 *
	 * @return array<string, string>
	 */
	private static function drivers(mixed $drivers): array
	{
		$read = [];

		foreach (is_array($drivers) ? $drivers : [] as $area => $driver) {
			if (is_string($area) && is_string($driver)) {
				$read[$area] = $driver;
			}
		}

		return $read;
	}

	/**
	 * Reads the parts from stored data, dropping what isn't one.
	 *
	 * @return array<string, array{count: int, bytes: int, tables: list<string>}>
	 */
	private static function parts(mixed $parts): array
	{
		if (! is_array($parts)) {
			return [];
		}

		$read = [];

		foreach ($parts as $name => $part) {
			if (is_string($name) && is_array($part)) {
				$read[$name] = [
					'count'  => is_int($part['count'] ?? null) ? $part['count'] : 0,
					'bytes'  => is_int($part['bytes'] ?? null) ? $part['bytes'] : 0,
					'tables' => is_array($part['tables'] ?? null) ? array_values(array_filter($part['tables'], is_string(...))) : []
				];
			}
		}

		return $read;
	}
}
