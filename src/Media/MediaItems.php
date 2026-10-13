<?php

/**
 * Media items.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Blush\Media\Index\MediaLibrary;
use Blush\Support\RequestMemo;

/**
 * The media library to read from (D-814): every template has it as
 * `$media`, and plugins and core ask the container for it, so a file
 * reached from a template, an entry's field, or code is one lookup and
 * one `MediaItem`, kept for the request (D-807).
 *
 * ```php
 * <?php if ($logo = $media->find($site->setting('logo'))) : ?>
 * 	<?= $logo->img(class: 'site-logo') ?>
 * <?php endif ?>
 * ```
 */
final readonly class MediaItems
{
	public function __construct(
		private MediaValues $values,
		private MediaLibrary $library,
		private RequestMemo $memo
	) {}

	/**
	 * Returns the file a value names (a file's id, or an older path), or
	 * `null` when it names none, or the library can't be read.
	 */
	public function find(mixed $value): ?MediaItem
	{
		if (! is_string($value) || trim($value) === '') {
			return null;
		}

		return $this->memo->remember("media|item|{$value}", function () use ($value): ?MediaItem {
			try {
				$record = $this->values->record($value);

				return $record === null ? null : new MediaItem($record, $record->kind() === MediaKind::Image ? $this->library->sizes($record->key) : []);
			} catch (MediaException) {
				return null;
			}
		});
	}
}
