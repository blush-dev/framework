<?php

/**
 * Media record.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Index;

use Blush\Core\Paths;
use Blush\Media\Embedded\EmbeddedMetadata;
use Blush\Media\MediaFile;
use Blush\Media\MediaKind;
use Blush\Media\MediaMetadata;

/**
 * One media file in the media index (D-288), in `user/media`. Its `key`
 * is where it is there (`2026/09/lake.jpg`), which is also where its
 * metadata file is under `user/data/media`.
 * What the file says about itself is `embedded` (D-289), read when the
 * file changes.
 */
final readonly class MediaRecord
{
	/**
	 * @param array<string, mixed> $metadata  Its metadata file's values.
	 * @param ?int                 $described When its metadata file was written, or `null` without one.
	 */
	public function __construct(
		public string $key,
		public string $url,
		public string $mime,
		public int $size,
		public int $modified,
		public ?int $width = null,
		public ?int $height = null,
		public array $metadata = [],
		public ?int $described = null,
		public ?EmbeddedMetadata $embedded = null
	) {}

	/**
	 * How long a sound or video lasts, in seconds, when it says (D-291).
	 */
	public function duration(): ?float
	{
		$duration = $this->embedded->values['duration'] ?? null;

		return is_float($duration) ? $duration : null;
	}

	public function kind(): MediaKind
	{
		return MediaKind::fromMime($this->mime);
	}

	public function metadata(): MediaMetadata
	{
		return new MediaMetadata($this->metadata);
	}

	/**
	 * The file it records, as the resolver describes one.
	 */
	public function file(Paths $paths): MediaFile
	{
		return new MediaFile($paths->media . '/' . $this->key, $this->url, $this->mime, $this->size, $this->width, $this->height);
	}

	/**
	 * @param array<array-key, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$int      = static fn (string $key): ?int => is_int($data[$key] ?? null) ? $data[$key] : null;
		$metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

		return new self(
			is_string($data['key'] ?? null) ? $data['key'] : '',
			is_string($data['url'] ?? null) ? $data['url'] : '',
			is_string($data['mime'] ?? null) ? $data['mime'] : '',
			$int('size') ?? 0,
			$int('modified') ?? 0,
			$int('width'),
			$int('height'),
			array_combine(array_map(strval(...), array_keys($metadata)), array_values($metadata)),
			$int('described'),
			is_array($data['embedded'] ?? null) ? EmbeddedMetadata::fromArray($data['embedded']) : null
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'key'       => $this->key,
			'url'       => $this->url,
			'mime'      => $this->mime,
			'size'      => $this->size,
			'modified'  => $this->modified,
			'width'     => $this->width,
			'height'    => $this->height,
			'metadata'  => $this->metadata,
			'described' => $this->described,
			'embedded'  => $this->embedded?->toArray()
		];
	}
}
