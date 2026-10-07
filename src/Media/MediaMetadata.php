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

use Blush\Support\Uuid;

/**
 * What the library says about a media file (D-238, D-269, D-287): the
 * values its metadata file holds, by key, for the fields its kind has
 * (`MediaSchemas`) and any others the file keeps. `title` (what the
 * library calls it, D-290), `alt`, and `caption` are read as one line of
 * text each, `''` when there's none, since they're
 * written into Markdown, where a line break would end them. Where a file
 * is used, what's written there wins; the library's fill the gaps.
 *
 * Four keys aren't fields, and the details form doesn't edit them:
 * `owner`, the username of the account that uploaded the file (D-407);
 * `sizes`, an image's other sizes (D-488), their keys in `user/media`
 * mapped to their `width` and `height`; `artwork`, the id of the library
 * image a sound or video shows as its artwork (D-581), which the file's
 * screen sets on its own; and `id`, the file's UUID (D-487), written
 * last, as an entry's is (D-477). `fields()` is the values without them.
 */
final readonly class MediaMetadata
{
	/**
	 * The key the uploader's username is kept under.
	 */
	public const string OWNER = 'owner';

	/**
	 * The key the file's id is kept under (D-487).
	 */
	public const string ID = 'id';

	/**
	 * The key an image's sizes are kept under (D-488).
	 */
	public const string SIZES = 'sizes';

	/**
	 * The key a sound's or video's artwork, a library image's id, is
	 * kept under (D-581).
	 */
	public const string ARTWORK = 'artwork';

	public string $title;

	public string $alt;

	public string $caption;

	/**
	 * The uploader's username, or `''` for a file with none (one added by
	 * hand, or before uploads were recorded).
	 */
	public string $owner;

	/**
	 * The file's id, lowercase, or `''` for a file with none, or with one
	 * that isn't a UUID.
	 */
	public string $id;

	/**
	 * The image's recorded sizes (D-488), by key: each one's width and
	 * height, `null` when it doesn't say. Entries that aren't a key and a
	 * map are left out (`content:lint` reports them).
	 *
	 * @var array<string, array{width: ?int, height: ?int}>
	 */
	public array $sizes;

	/**
	 * The id of the library image a sound or video shows as its artwork
	 * (D-581), lowercase, or `''` for none, or one that isn't a UUID.
	 */
	public string $artwork;

	/**
	 * @param array<string, mixed> $values
	 */
	public function __construct(public array $values = [])
	{
		$this->title   = self::line($values['title'] ?? null);
		$this->alt     = self::line($values['alt'] ?? null);
		$this->caption = self::line($values['caption'] ?? null);
		$this->owner   = self::line($values[self::OWNER] ?? null);
		$this->id      = Uuid::isValid($values[self::ID] ?? null) ? strtolower(self::line($values[self::ID])) : '';
		$this->sizes   = self::readSizes($values[self::SIZES] ?? null);
		$this->artwork = Uuid::isValid($values[self::ARTWORK] ?? null) ? strtolower(self::line($values[self::ARTWORK])) : '';
	}

	/**
	 * Reads recorded sizes: a map of keys to maps with a `width` and a
	 * `height`.
	 *
	 * @return array<string, array{width: ?int, height: ?int}>
	 */
	public static function readSizes(mixed $value): array
	{
		if (! is_array($value) || array_is_list($value)) {
			return [];
		}

		$sizes = [];

		foreach ($value as $key => $size) {
			$key = trim((string) $key, '/');

			if ($key !== '' && is_array($size) && ($size === [] || ! array_is_list($size))) {
				$sizes[$key] = [
					'width'  => is_int($size['width'] ?? null) ? $size['width'] : null,
					'height' => is_int($size['height'] ?? null) ? $size['height'] : null
				];
			}
		}

		return $sizes;
	}

	/**
	 * The field values: every value but the owner, the sizes, the
	 * artwork, and the id.
	 *
	 * @return array<string, mixed>
	 */
	public function fields(): array
	{
		return array_diff_key($this->values, [self::OWNER => true, self::SIZES => true, self::ARTWORK => true, self::ID => true]);
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
