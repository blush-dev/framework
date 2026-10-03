<?php

/**
 * Media upload rule.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * One kind's upload rules (D-406), under `MediaUploads`: whether files of
 * the kind may be uploaded, and its own largest file (in megabytes) and
 * path, each `null` to take the one every kind has.
 */
final readonly class MediaUploadRule
{
	public ?string $path;

	/**
	 * @throws InvalidConfig
	 */
	public function __construct(
		public bool $enabled = true,
		public ?int $maxSize = null,
		?string $path = null
	) {
		MediaUploads::assertSize($maxSize);

		$this->path = $path === null || trim($path, '/ ') === '' ? null : MediaUploads::checkPath($path);
	}

	/**
	 * Whether it says nothing of its own, so the kind follows every kind.
	 */
	public function isDefault(): bool
	{
		return $this->enabled && $this->maxSize === null && $this->path === null;
	}

	/**
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidConfig
	 */
	public static function fromArray(array $data): self
	{
		$values = new ConfigValues($data, 'MediaUploadRule');
		$values->assertKnownKeys(['enabled', 'maxSize', 'path']);

		$size = $data['maxSize'] ?? null;

		return new self(
			enabled: $values->bool('enabled', true),
			maxSize: $size === null || is_int($size) ? $size : throw new InvalidConfig('MediaUploadRule "maxSize" must be a whole number of megabytes, or null.'),
			path: $values->nullableString('path')
		);
	}

	/**
	 * @return array{enabled: bool, maxSize: ?int, path: ?string}
	 */
	public function toArray(): array
	{
		return ['enabled' => $this->enabled, 'maxSize' => $this->maxSize, 'path' => $this->path];
	}
}
