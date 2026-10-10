<?php

/**
 * Embed provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use Uri\Rfc3986\Uri;

/**
 * An oEmbed provider (D-184): the URLs it embeds, as oembed.com-style
 * schemes with `*` wildcards (`https://vimeo.com/*`), and its oEmbed
 * endpoint, which must be HTTPS, or `null` for a provider whose frame is
 * built from the link alone, which is never asked (D-633). Only URLs a
 * registered provider matches are embedded, so content never frames an
 * unknown site (D-113).
 *
 * A subclass adjusts what the provider gives: the URL it asks about
 * (`request()`), and the frame it embeds (`frame()`), such as YouTube's
 * no-cookie host. The base embeds the iframe in the provider's HTML.
 * Built-in providers build their frame from the link (`link()` reads
 * it), so a frame is only ever on the provider's own host.
 *
 * A rich embed (D-691), a post that the provider's script draws from a
 * quote, such as X's, names its script's registered asset (`asset()`)
 * and shows the quote from its answer (`quote()`), cleaned. The script
 * is never taken from the answer.
 */
abstract class EmbedProvider
{
	/**
	 * @param  list<string> $schemes
	 * @throws EmbedException When the endpoint isn't HTTPS.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $label,
		public readonly array $schemes,
		public readonly ?string $endpoint
	) {
		if ($endpoint !== null && ! str_starts_with($endpoint, 'https://')) {
			throw new EmbedException(sprintf('The "%s" embed provider\'s endpoint must be an HTTPS URL; "%s" given.', $name, $endpoint));
		}
	}

	/**
	 * Returns whether the provider embeds a URL. An `http://` URL matches
	 * as its `https://` form.
	 */
	public function matches(string $url): bool
	{
		$url = preg_replace('#^http://#i', 'https://', trim($url)) ?? $url;

		return array_any(
			$this->schemes,
			static fn (string $scheme): bool => preg_match('#^' . str_replace('\*', '.*', preg_quote($scheme, '#')) . '$#i', $url) === 1
		);
	}

	/**
	 * Returns whether the provider is asked about its URLs over oEmbed.
	 */
	public function asks(): bool
	{
		return $this->endpoint !== null;
	}

	/**
	 * Returns the oEmbed request URL for a URL, or `''` for a provider
	 * that isn't asked.
	 */
	public function request(string $url): string
	{
		if ($this->endpoint === null) {
			return '';
		}

		return $this->endpoint . (str_contains($this->endpoint, '?') ? '&' : '?') . http_build_query(['url' => $url, 'format' => 'json']);
	}

	/**
	 * Returns the URL to frame for a URL, given what the provider said
	 * (`null` when it said nothing usable), or `null` when it can't be
	 * framed and should render as a link.
	 */
	public function frame(string $url, ?EmbedData $data): ?string
	{
		return $data?->frame();
	}

	/**
	 * Returns the image to show for a URL, for a provider that answers
	 * with a photo rather than a frame (D-635), or `null`. By default
	 * it's a photo answer's image, when nothing is framed.
	 */
	public function photo(string $url, ?EmbedData $data): ?string
	{
		return $data?->type === EmbedType::Photo ? $data->url : null;
	}

	/**
	 * Returns the embed's size as width and height, which sets its
	 * aspect ratio (D-635), or `null` for 16:9. By default it's the
	 * answer's.
	 *
	 * @return ?array{int, int}
	 */
	public function size(string $url, ?EmbedData $data): ?array
	{
		return $data?->width !== null && $data->height !== null ? [$data->width, $data->height] : null;
	}

	/**
	 * Returns the frame's fixed height in pixels, for a player that fills
	 * its column at a set height rather than keeping a shape (an audio
	 * player, D-634), or `null` for one that keeps its aspect ratio. By
	 * default, a player is fixed when the provider gives its width as a
	 * percentage and its height in pixels.
	 */
	public function fixedHeight(string $url, ?EmbedData $data): ?int
	{
		return $data !== null && $data->fullWidth ? $data->height : null;
	}

	/**
	 * Returns the handle of the registered asset whose script draws the
	 * provider's quotes as posts (D-691), such as `blush/embed-x`, or
	 * `null` for a provider without rich embeds.
	 */
	public function asset(): ?string
	{
		return null;
	}

	/**
	 * Returns the quote to show for a URL, as HTML, for a provider with
	 * rich embeds (D-691), or `null`. By default it's a rich answer's
	 * blockquote, cleaned (`RichQuote`), when the provider has an asset.
	 */
	public function quote(string $url, ?EmbedData $data): ?string
	{
		return $this->asset() !== null && $data?->type === EmbedType::Rich ? RichQuote::clean($data->html) : null;
	}

	/**
	 * Returns the groups a link's path matches, when the link is on one
	 * of the hosts (`*.` allows any subdomain) and its path matches the
	 * pattern; else `null`. Each group is letters, digits, `_`, and `-`
	 * only where the pattern says so, so it's safe in a frame's URL.
	 *
	 * @param  list<string> $hosts
	 * @return ?list<string>
	 */
	protected static function link(string $url, array $hosts, string $pattern): ?array
	{
		$uri  = Uri::parse(preg_replace('#^http://#i', 'https://', trim($url)) ?? $url);
		$host = strtolower((string) $uri?->getHost());

		$known = array_any($hosts, static fn (string $allowed): bool => str_starts_with($allowed, '*.')
			? str_ends_with($host, substr($allowed, 1)) && strlen($host) > strlen($allowed) - 1
			: $host === $allowed);

		if ($uri === null || $uri->getScheme() !== 'https' || ! $known || preg_match($pattern, $uri->getPath(), $match) !== 1) {
			return null;
		}

		return array_values(array_slice($match, 1));
	}

	/**
	 * Returns a start time in seconds, from `90`, `90s`, or `1m30s` (and
	 * `1h2m3s`), or `null` for none, zero, or anything else.
	 */
	protected static function seconds(mixed $value): ?int
	{
		if (! is_string($value) || preg_match('/^(?:(\d+)|(?=\d)(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?)$/', $value, $match, PREG_UNMATCHED_AS_NULL) !== 1) {
			return null;
		}

		$seconds = $match[1] !== null
			? (int) $match[1]
			: (int) $match[2] * 3600 + (int) $match[3] * 60 + (int) $match[4];

		return $seconds > 0 ? $seconds : null;
	}
}
