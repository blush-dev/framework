<?php

/**
 * Media variants.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Index;

use Blush\Media\MediaKind;

/**
 * Finds the images that are sizes of another (D-239): resized copies of
 * an image, as media brought from another system often has
 * (`photo-300x200.jpg` of `photo.jpg`), which belong to the original
 * rather than being media of their own.
 * They're still served, so content linking to them keeps working; they
 * just have no id (D-487).
 *
 * **Recorded sizes come first** (D-488): an image whose metadata file
 * lists `sizes` has those files as its sizes, whatever they're named,
 * as an importer writes them or `media:sizes --write` records them. A
 * file two images list is the first's, by key; an image that's listed
 * as a size can't have sizes of its own.
 *
 * **The rest are found by rule** (D-239), never by name alone, so names
 * such as `daisy-3x4.jpg` (an aspect ratio) stay images of their own:
 *
 * - its name ends in `-{w}x{h}`, before the extension;
 * - an image of the same name without that ending, and the same
 *   extension, is beside it, and isn't a size itself;
 * - it's really `w`×`h` pixels, no larger than that image;
 * - it has no id of its own, which keeps a file someone gave its own
 *   details (or uploaded under such a name) an original.
 *
 * This naming rule is the only one for now; D-239 plans rules as a
 * registry, for other importers' conventions.
 */
final class MediaVariants
{
	/**
	 * The originals of the records that are sizes, by key.
	 *
	 * @param  array<string, MediaRecord> $records By key.
	 * @return array<string, string>
	 */
	public static function find(array $records): array
	{
		$found  = [];
		$listed = [];

		foreach ($records as $key => $record) {
			foreach (array_keys($record->metadata()->sizes) as $size) {
				$listed[$size] ??= (string) $key;
			}
		}

		foreach ($listed as $size => $original) {
			if ($size !== $original && isset($records[$size]) && ! isset($listed[$original])) {
				$found[$size] = $original;
			}
		}

		foreach ($records as $key => $record) {
			$key = (string) $key;

			if (isset($found[$key]) || isset($listed[$key])) {
				continue;
			}

			$original = self::originalOf($key, $record, $records);

			if ($original !== null && ! isset($found[$original])) {
				$found[$key] = $original;
			}
		}

		return $found;
	}

	/**
	 * The key of the image a record is a size of, or `null`.
	 *
	 * @param array<string, MediaRecord> $records
	 */
	private static function originalOf(string $key, MediaRecord $record, array $records): ?string
	{
		if ($record->kind() !== MediaKind::Image || $record->id() !== null || preg_match('/^(.+)-(\d+)x(\d+)(\.[^.\/]+)$/', $key, $match) !== 1) {
			return null;
		}

		[, $base, $width, $height, $extension] = $match;

		$original = $records[$base . $extension] ?? null;

		if ($original === null || $original->kind() !== MediaKind::Image || (preg_match('/-\d+x\d+$/', $base) === 1 && self::originalOf($original->key, $original, $records) !== null)) {
			return null;
		}

		return $record->width === (int) $width
			&& $record->height === (int) $height
			&& $original->width !== null && $original->width >= $record->width
			&& $original->height !== null && $original->height >= $record->height
			? $original->key
			: null;
	}
}
