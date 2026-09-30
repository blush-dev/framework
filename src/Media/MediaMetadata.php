<?php

/**
 * Media metadata.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

/**
 * What the library says about a media file (D-238, D-269): its alt text,
 * which describes it for anyone who can't see it, and its caption. Each
 * is `''` when it has none. Where a file is used, what's written there
 * wins; the library's are what the editor fills in on insert.
 */
final readonly class MediaMetadata
{
	public string $alt;

	public string $caption;

	public function __construct(string $alt = '', string $caption = '')
	{
		$this->alt     = self::line($alt);
		$this->caption = self::line($caption);
	}

	/**
	 * Reads a metadata file's data: `alt` and `caption`, when they're text.
	 *
	 * @param array<array-key, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			is_string($data['alt'] ?? null) ? $data['alt'] : '',
			is_string($data['caption'] ?? null) ? $data['caption'] : ''
		);
	}

	/**
	 * Returns whether it says nothing.
	 */
	public function isEmpty(): bool
	{
		return $this->alt === '' && $this->caption === '';
	}

	/**
	 * @return array{alt: string, caption: string}
	 */
	public function toArray(): array
	{
		return ['alt' => $this->alt, 'caption' => $this->caption];
	}

	/**
	 * Text on one line, trimmed: both are written into Markdown, where a
	 * line break would end them.
	 */
	private static function line(string $text): string
	{
		return trim((string) preg_replace('/\s+/u', ' ', $text));
	}
}
