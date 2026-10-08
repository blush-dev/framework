<?php

/**
 * Media snapshot.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Index;

/**
 * The media index's contents (D-288): every media file's record, by key,
 * the metadata files whose media file is gone (`orphans`, by key), when
 * it was built, and what it was built with (`fingerprint`: the media
 * URL and allowed types), so a change to those rebuilds it. A rebuild
 * can take more than one run (D-626): `pending` are the files it has
 * still to read, whose records are the last index's until then.
 * `rejected` are files named for a type the library takes whose
 * contents aren't one, by key, with their size and modified time, so
 * they aren't read again until they change.
 */
final readonly class MediaSnapshot
{
	/**
	 * The stored format's version; another version is rebuilt.
	 */
	public const int VERSION = 5;

	/**
	 * @param array<string, MediaRecord> $records
	 * @param list<string>               $orphans
	 * @param list<string>               $pending
	 * @param array<string, array{int, int}> $rejected
	 */
	public function __construct(
		public string $fingerprint = '',
		public int $built = 0,
		public array $records = [],
		public array $orphans = [],
		public array $pending = [],
		public array $rejected = []
	) {}

	/**
	 * @param array<array-key, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		if (($data['version'] ?? null) !== self::VERSION) {
			return new self();
		}

		$records = [];

		foreach (is_array($data['records'] ?? null) ? $data['records'] : [] as $record) {
			if (is_array($record)) {
				$record                 = MediaRecord::fromArray($record);
				$records[$record->key] = $record;
			}
		}

		return new self(
			is_string($data['fingerprint'] ?? null) ? $data['fingerprint'] : '',
			is_int($data['built'] ?? null) ? $data['built'] : 0,
			$records,
			array_values(array_filter(is_array($data['orphans'] ?? null) ? $data['orphans'] : [], is_string(...))),
			array_values(array_filter(is_array($data['pending'] ?? null) ? $data['pending'] : [], is_string(...))),
			self::rejected($data['rejected'] ?? null)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'version'     => self::VERSION,
			'fingerprint' => $this->fingerprint,
			'built'       => $this->built,
			'records'     => array_values(array_map(static fn (MediaRecord $record): array => $record->toArray(), $this->records)),
			'orphans'     => $this->orphans,
			'pending'     => $this->pending,
			'rejected'    => $this->rejected === [] ? (object) [] : $this->rejected
		];
	}

	/**
	 * Reads the rejected files' sizes and modified times.
	 *
	 * @return array<string, array{int, int}>
	 */
	private static function rejected(mixed $data): array
	{
		$rejected = [];

		foreach (is_array($data) ? $data : [] as $key => $stat) {
			if (is_array($stat) && is_int($stat[0] ?? null) && is_int($stat[1] ?? null)) {
				$rejected[(string) $key] = [$stat[0], $stat[1]];
			}
		}

		return $rejected;
	}
}
