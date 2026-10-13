<?php

/**
 * Media field values.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Blush\Core\Paths;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaRecord;
use Blush\Support\RequestMemo;
use Blush\Support\Uuid;

/**
 * Reads what a media field holds (D-773): the id of a media original.
 * Values written before ids, a path such as `/media/2026/10/a.jpg` (or
 * a size's path), are still read, so nothing breaks while they're
 * rewritten (`content:media-ids`, Site Health); everything that writes a
 * media value writes the id.
 *
 * - `record()`: the library's record a value names, which for an old
 *   path may be one of an image's sizes.
 * - `original()`: the original it names, a size's original for a path.
 * - `url()`: where it's served, for a page, a meta tag, or the admin.
 * - `id()`: the original's id, what the value should be.
 */
final readonly class MediaValues
{
	public function __construct(
		private MediaLibrary $library,
		private MediaResolver $resolver,
		private Paths $paths,
		private RequestMemo $memo
	) {}

	/**
	 * Returns whether a value is an id, not a path.
	 */
	public static function isId(mixed $value): bool
	{
		return is_string($value) && Uuid::isValid(trim($value));
	}

	/**
	 * Returns the library's record a value names, or `null` when it names
	 * no file in the library.
	 *
	 * @throws MediaException
	 */
	public function record(mixed $value): ?MediaRecord
	{
		if (! is_string($value) || trim($value) === '') {
			return null;
		}

		// Kept for the request (D-807, D-814); the library drops it when
		// it writes its index.
		return $this->memo->remember("media|record|{$value}", function () use ($value): ?MediaRecord {
			if (self::isId($value)) {
				return $this->library->findId($value);
			}

			$file = $this->resolver->resolve(trim($value));

			if ($file === null || ! str_starts_with($file->path, $this->paths->media . '/')) {
				return null;
			}

			return $this->library->find(substr($file->path, strlen($this->paths->media) + 1));
		});
	}

	/**
	 * Returns the original a value names: the file itself, or the image
	 * an old path to one of its sizes belongs to.
	 *
	 * @throws MediaException
	 */
	public function original(mixed $value): ?MediaRecord
	{
		$record = $this->record($value);

		return $record?->original === null ? $record : $this->library->find($record->original);
	}

	/**
	 * Returns the URL path a value is served at, or `null`.
	 *
	 * @throws MediaException
	 */
	public function url(mixed $value): ?string
	{
		return $this->record($value)?->url;
	}

	/**
	 * Returns where to show a value from, for an `<img>` or a line of
	 * text: its file's URL path; an older value naming no library file
	 * (an address elsewhere), as written; or `null` for nothing, or an id
	 * no file has. A broken library reads as no file.
	 */
	public function address(mixed $value): ?string
	{
		if (! is_string($value) || trim($value) === '') {
			return null;
		}

		try {
			$url = $this->url($value);
		} catch (MediaException) {
			$url = null;
		}

		return $url ?? (self::isId($value) ? null : trim($value));
	}

	/**
	 * Returns the id a value should be: its original's, or `null` when it
	 * names no file, or one without an id yet (`media:ids`).
	 *
	 * @throws MediaException
	 */
	public function id(mixed $value): ?string
	{
		return $this->original($value)?->id();
	}
}
