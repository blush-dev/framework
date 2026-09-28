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

/**
 * An oEmbed provider (D-184): the URLs it embeds, as oembed.com-style
 * schemes with `*` wildcards (`https://vimeo.com/*`), and its oEmbed
 * endpoint, which must be HTTPS. Only URLs a registered provider matches
 * are embedded, so content never frames an unknown site (D-113).
 *
 * A subclass adjusts what the provider gives: the URL it asks about
 * (`request()`), and the frame it embeds (`frame()`), such as YouTube's
 * no-cookie host. The base embeds the iframe in the provider's HTML.
 * `allowsScripts()` is for rich embeds that need the provider's script
 * (X, Instagram); none do yet, so their URLs render as links.
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
		public readonly string $endpoint
	) {
		if (! str_starts_with($endpoint, 'https://')) {
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
	 * Returns the oEmbed request URL for a URL.
	 */
	public function request(string $url): string
	{
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
	 * Returns whether the provider's own HTML, script and all, may be
	 * output for rich embeds. Not supported yet (D-184).
	 */
	public function allowsScripts(): bool
	{
		return false;
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
