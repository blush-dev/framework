<?php

/**
 * Embed data.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use Dom\HTMLDocument;
use Dom\HTMLElement;

/**
 * What an oEmbed provider says about a URL: its type, title, provider,
 * size, thumbnail, and HTML. Only the fields Blush uses are kept, and
 * they're checked: sizes must be positive whole numbers and URLs HTTPS.
 * A width given as a percentage (SoundCloud's `"100%"`) is no size, but
 * is kept as `$fullWidth`: the player fills its column at its height
 * (D-634). Text is one line, without control characters or the
 * direction overrides that can disguise it (Flickr's author names carry
 * them). A photo's `url` is the image (D-635).
 *
 * The HTML is kept as given. Blush doesn't output it: `frame()` reads the
 * iframe URL out of it, and themes build their own markup (D-184). A
 * provider allowed to run scripts (rich embeds, later) would use it.
 */
final readonly class EmbedData
{
	public function __construct(
		public EmbedType $type,
		public string $title = '',
		public string $providerName = '',
		public ?int $width = null,
		public ?int $height = null,
		public ?string $thumbnail = null,
		public string $html = '',
		public ?string $url = null,
		public bool $fullWidth = false,
		public string $author = ''
	) {}

	/**
	 * Builds the data from an oEmbed response, or returns `null` when it
	 * isn't one (no known `type`).
	 *
	 * @param array<array-key, mixed> $response
	 */
	public static function fromResponse(array $response): ?self
	{
		$type = is_string($response['type'] ?? null) ? EmbedType::tryFrom($response['type']) : null;

		if ($type === null) {
			return null;
		}

		return new self(
			$type,
			self::line($response['title'] ?? null),
			self::line($response['provider_name'] ?? null),
			self::size($response['width'] ?? null),
			self::size($response['height'] ?? null),
			self::https($response['thumbnail_url'] ?? null),
			self::text($response['html'] ?? null),
			self::https($response['url'] ?? null),
			is_string($response['width'] ?? null) && preg_match('/^\d+(?:\.\d+)?%$/', $response['width']) === 1,
			self::line($response['author_name'] ?? null)
		);
	}

	/**
	 * Returns the data as a cached oEmbed response.
	 *
	 * @return array<string, mixed>
	 */
	public function toResponse(): array
	{
		return [
			'type'          => $this->type->value,
			'title'         => $this->title,
			'provider_name' => $this->providerName,
			'width'         => $this->fullWidth ? '100%' : $this->width,
			'height'        => $this->height,
			'thumbnail_url' => $this->thumbnail,
			'html'          => $this->html,
			'url'           => $this->url,
			'author_name'   => $this->author
		];
	}

	/**
	 * Returns the HTTPS URL of the first iframe in the HTML, or `null`.
	 */
	public function frame(): ?string
	{
		if (! str_contains($this->html, '<iframe')) {
			return null;
		}

		$document = HTMLDocument::createFromString('<!DOCTYPE html><meta charset="utf-8"><body>' . $this->html . '</body>', LIBXML_NOERROR);
		$iframe   = $document->querySelector('iframe[src]');

		return $iframe instanceof HTMLElement ? self::https($iframe->getAttribute('src')) : null;
	}

	/**
	 * Returns a string value, trimmed, or `''`.
	 */
	private static function text(mixed $value): string
	{
		return is_string($value) ? trim($value) : '';
	}

	/**
	 * Returns a string value as one line of text, or `''`: control
	 * characters become spaces, and direction marks and overrides go.
	 */
	private static function line(mixed $value): string
	{
		$text = is_string($value) ? (preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value) ?? '') : '';

		return trim(preg_replace('/\p{Cc}+/u', ' ', $text) ?? '');
	}

	/**
	 * Returns a positive whole number from an integer or numeric string,
	 * or `null` (some providers give `"100%"`).
	 */
	private static function size(mixed $value): ?int
	{
		$size = is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : null);

		return $size !== null && $size > 0 ? $size : null;
	}

	/**
	 * Returns an HTTPS URL, or `null`.
	 */
	private static function https(mixed $value): ?string
	{
		return is_string($value) && str_starts_with($value, 'https://') ? $value : null;
	}
}
