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
 * What the library says about a media file (D-238, D-269, D-287): the
 * values its metadata file holds, by key, for the fields its kind has
 * (`MediaSchemas`) and any others the file keeps. `title` (what the
 * library calls it, D-290), `alt`, and `caption` are read as one line of
 * text each, `''` when there's none, since they're
 * written into Markdown, where a line break would end them. Where a file
 * is used, what's written there wins; the library's fill the gaps.
 */
final readonly class MediaMetadata
{
	public string $title;

	public string $alt;

	public string $caption;

	/**
	 * @param array<string, mixed> $values
	 */
	public function __construct(public array $values = [])
	{
		$this->title   = self::line($values['title'] ?? null);
		$this->alt     = self::line($values['alt'] ?? null);
		$this->caption = self::line($values['caption'] ?? null);
	}

	/**
	 * Reads a metadata file's data.
	 *
	 * @param array<array-key, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$values = [];

		foreach ($data as $key => $value) {
			$values[(string) $key] = $value;
		}

		return new self($values);
	}

	/**
	 * Returns a copy with values set and keys removed. A value that's
	 * empty (`null`, `''`, or `[]`) removes its key.
	 *
	 * @param array<string, mixed> $set
	 * @param list<string>         $remove
	 */
	public function with(array $set = [], array $remove = []): self
	{
		$values = array_diff_key($this->values, array_flip($remove));

		foreach ($set as $key => $value) {
			if ($value === null || $value === '' || $value === []) {
				unset($values[$key]);
			} else {
				$values[$key] = $value;
			}
		}

		return new self($values);
	}

	/**
	 * Returns whether it says nothing.
	 */
	public function isEmpty(): bool
	{
		return $this->values === [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return $this->values;
	}

	/**
	 * Text on one line, trimmed.
	 */
	public static function line(mixed $text): string
	{
		return is_string($text) ? trim((string) preg_replace('/\s+/u', ' ', $text)) : '';
	}
}
