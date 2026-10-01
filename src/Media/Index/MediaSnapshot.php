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
 * URL and allowed types), so a change to those rebuilds it.
 */
final readonly class MediaSnapshot
{
	/**
	 * The stored format's version; another version is rebuilt.
	 */
	public const int VERSION = 3;

	/**
	 * @param array<string, MediaRecord> $records
	 * @param list<string>               $orphans
	 */
	public function __construct(
		public string $fingerprint = '',
		public int $built = 0,
		public array $records = [],
		public array $orphans = []
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
			array_values(array_filter(is_array($data['orphans'] ?? null) ? $data['orphans'] : [], is_string(...)))
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
			'orphans'     => $this->orphans
		];
	}
}
