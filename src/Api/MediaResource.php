<?php

/**
 * Media in the API.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use Blush\Core\AppConfig;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaDates;
use Blush\Media\MediaException;
use Blush\Media\MediaMetadata;
use Blush\Media\MediaValues;

/**
 * Media files as the API sends them (D-736, D-738): public, all of
 * them, each by its id (D-487). A file without an id isn't one.
 *
 * - **A stub,** where something refers to a file: `{ id, url, alt,
 *   width, height }`, the size of an image it names (D-488), with its
 *   original's id and alt text.
 * - **A record:** its kind, type, URL, size in bytes, dimensions,
 *   duration, title, alt text, caption, description, when it was
 *   uploaded, an image's other sizes, a sound's or video's artwork as a
 *   stub, and the library's other fields for it.
 */
final readonly class MediaResource
{
	public function __construct(
		private MediaLibrary $library,
		private MediaValues $values,
		private AppConfig $app
	) {}

	/**
	 * Returns a file by its id, or `null`.
	 *
	 * @throws MediaException
	 */
	public function find(string $id): ?MediaRecord
	{
		$record = $this->library->findId($id);

		return $record?->id() === null ? null : $record;
	}

	/**
	 * Returns the library file a stored reference names (an id, or an
	 * older path, which may be one of an image's sizes; D-773), or `null`
	 * when the library has no such file.
	 *
	 * @throws MediaException
	 */
	public function referenced(mixed $reference): ?MediaRecord
	{
		return $this->values->record($reference);
	}

	/**
	 * Returns a stub for a stored reference, the file itself when
	 * `$expand`, or `null` when the library has no such file.
	 *
	 * @return ?array<string, mixed>
	 * @throws MediaException
	 */
	public function reference(mixed $reference, bool $expand): ?array
	{
		$record   = $this->referenced($reference);
		$original = $record?->original === null ? $record : $this->library->find($record->original);

		if ($record === null || $original?->id() === null) {
			return null;
		}

		return $expand ? $this->item($original) : $this->stub($record, $original);
	}

	/**
	 * Returns a file's stub: a size's URL and dimensions, with its
	 * original's id and alt text.
	 *
	 * @return array{id: ?string, url: string, alt: string, width: ?int, height: ?int}
	 */
	public function stub(MediaRecord $record, ?MediaRecord $original = null): array
	{
		$original ??= $record;

		return [
			'id'     => $original->id(),
			'url'    => ApiValues::url($this->app, $record->url),
			'alt'    => $original->metadata()->alt,
			'width'  => $record->width,
			'height' => $record->height
		];
	}

	/**
	 * Returns a file's record.
	 *
	 * @return array<string, mixed>
	 * @throws MediaException
	 */
	public function item(MediaRecord $record): array
	{
		$metadata = $record->metadata();
		$artwork  = $metadata->artwork === '' ? null : $this->find($metadata->artwork);
		$content  = $metadata->values[MediaMetadata::CONTENT] ?? null;

		return [
			'id'          => $record->id(),
			'kind'        => $record->kind()->value,
			'mime'        => $record->mime,
			'url'         => ApiValues::url($this->app, $record->url),
			'size'        => $record->size,
			'width'       => $record->width,
			'height'      => $record->height,
			'duration'    => $record->duration(),
			'title'       => $metadata->title,
			'alt'         => $metadata->alt,
			'caption'     => $metadata->caption,
			'description' => is_string($content) ? $content : '',
			'uploaded'    => ApiValues::date(MediaDates::of($record, $this->app->timezone())),
			'sizes'       => array_map(fn (MediaRecord $size): array => [
				'url'    => ApiValues::url($this->app, $size->url),
				'width'  => $size->width,
				'height' => $size->height
			], $this->library->sizes($record->key)),
			'artwork'     => $artwork === null ? null : $this->stub($artwork),
			'fields'      => ApiValues::object(array_map(
				ApiValues::value(...),
				array_diff_key($metadata->fields(), ['title' => true, 'alt' => true, 'caption' => true, MediaMetadata::CONTENT => true])
			))
		];
	}
}
