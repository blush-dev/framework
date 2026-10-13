<?php

/**
 * Media item.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Blush\Media\Index\MediaRecord;
use Blush\View\Escaper;
use Blush\View\HtmlAttributes;
use Blush\View\InvalidHtmlAttribute;
use Blush\View\SafeHtml;
use Blush\View\TrustedHtml;

/**
 * A file in the media library, as templates, plugins, and core print it
 * (D-814): its `url`, `width`, `height`, `mime`, its details
 * (`metadata()`, with the alt text and caption), and, for an image,
 * `img()`. The library's record stays at hand as `record` for whatever
 * needs more. `MediaItems` builds one, and keeps it for the request.
 */
final readonly class MediaItem
{
	public string $url;

	public ?int $width;

	public ?int $height;

	public string $mime;

	/**
	 * @param MediaRecord       $record The library's record.
	 * @param list<MediaRecord> $sizes  An image's sizes (D-488), smallest first.
	 */
	public function __construct(
		public MediaRecord $record,
		private array $sizes = []
	) {
		$this->url    = $record->url;
		$this->width  = $record->width;
		$this->height = $record->height;
		$this->mime   = $record->mime;
	}

	/**
	 * Returns its id, or `null` for a file without one yet.
	 */
	public function id(): ?string
	{
		return $this->record->id();
	}

	/**
	 * Returns which kind of file it is: an image, a video, a sound, a
	 * document, or another file.
	 */
	public function kind(): MediaKind
	{
		return $this->record->kind();
	}

	/**
	 * Returns its details: title, alt text, caption, and the rest.
	 */
	public function metadata(): MediaMetadata
	{
		return $this->record->metadata();
	}

	/**
	 * Returns its alt text from the library, or `''`.
	 */
	public function alt(): string
	{
		return $this->metadata()->alt;
	}

	/**
	 * Returns an image's sizes (D-488), smallest first.
	 *
	 * @return list<MediaRecord>
	 */
	public function sizes(): array
	{
		return $this->sizes;
	}

	/**
	 * Returns an image as an `<img>`, to print: its `src`, `width`,
	 * `height`, and alt text (the library's, unless `alt:` says
	 * otherwise), a `srcset` of its sizes when it has some, and
	 * `loading="lazy"` and `decoding="async"`, each with attributes by
	 * name as `link()` takes them (`img(class: 'hero', loading: 'eager',
	 * sizes: '100vw')`). Empty for a file that isn't an image.
	 *
	 * @throws InvalidHtmlAttribute When an attribute isn't named.
	 */
	public function img(string|int|bool|null ...$attributes): SafeHtml
	{
		if ($this->kind() !== MediaKind::Image) {
			return new TrustedHtml('');
		}

		$attributes = [
			'width'    => $this->width,
			'height'   => $this->height,
			'alt'      => $this->alt(),
			'srcset'   => $this->srcset(),
			'loading'  => 'lazy',
			'decoding' => 'async',
			...$attributes
		];

		unset($attributes['src']);

		return new TrustedHtml('<img src="' . Escaper::url($this->url) . '"' . HtmlAttributes::render($attributes, 'img()') . '>');
	}

	/**
	 * Returns a `srcset` of an image's sizes and the image itself, by
	 * width, or `null` when it has no sizes to offer.
	 */
	private function srcset(): ?string
	{
		$candidates = [];

		foreach ([...$this->sizes, $this->record] as $size) {
			if ($size->width !== null && $size->width > 0) {
				$candidates[$size->width] ??= "{$size->url} {$size->width}w";
			}
		}

		return count($candidates) > 1 ? implode(', ', $candidates) : null;
	}
}
