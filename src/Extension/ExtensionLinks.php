<?php

/**
 * Extension links.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Where to learn about, get help with, and fund an extension (D-428), as
 * `composer.json` has them (https://getcomposer.org/doc/04-schema.md):
 * its `homepage`, its `support` (`email`, `issues`, `forum`, `wiki`,
 * `irc`, `source`, `docs`, `rss`, `chat`, `security`), and its
 * `funding`, a list of `{"type", "url"}`.
 *
 * Every URL is `http` or `https`, except `support.irc` (`irc` or
 * `ircs`); `support.email` is an email address. A manifest's are checked
 * strictly; `composer.json`'s are read leniently, keeping only what fits,
 * since it isn't the extension's manifest (as `ExtensionAuthor` does).
 */
final readonly class ExtensionLinks
{
	/**
	 * The `support` keys, in the order the admin lists them.
	 *
	 * @var list<string>
	 */
	public const array SUPPORT = ['docs', 'source', 'issues', 'forum', 'chat', 'wiki', 'irc', 'rss', 'security', 'email'];

	/**
	 * @param array<string, string>                     $support Support key => URL (or email address).
	 * @param list<array{type: string, url: string}> $funding Where to fund it.
	 */
	public function __construct(
		public string $homepage = '',
		public array $support = [],
		public array $funding = []
	) {}

	/**
	 * Reads a manifest's `homepage`, `support`, and `funding`.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ExtensionException When one doesn't fit.
	 */
	public static function fromArray(array $data): self
	{
		$homepage = $data['homepage'] ?? '';
		$support  = $data['support'] ?? [];
		$funding  = $data['funding'] ?? [];

		if (! is_string($homepage) || ($homepage !== '' && ! self::isUrl($homepage))) {
			throw new ExtensionException('"homepage" must be an http or https URL.');
		}

		if (! is_array($support) || ($support !== [] && array_is_list($support))) {
			throw new ExtensionException(sprintf('"support" must be an object with any of %s.', implode(', ', self::SUPPORT)));
		}

		$links = [];

		foreach ($support as $key => $value) {
			$links[(string) $key] = self::support((string) $key, $value);
		}

		if (! is_array($funding) || ! array_is_list($funding)) {
			throw new ExtensionException('"funding" must be a list, each an object with a "url" and an optional "type".');
		}

		return new self(trim($homepage), self::ordered($links), array_map(self::funding(...), $funding));
	}

	/**
	 * Reads `homepage`, `support`, and `funding` leniently, as a package's
	 * `composer.json` or Composer's `installed.json` has them, keeping
	 * only what fits.
	 *
	 * @param array<array-key, mixed> $data
	 */
	public static function lenient(array $data): self
	{
		$homepage = $data['homepage'] ?? '';
		$support  = is_array($data['support'] ?? null) ? $data['support'] : [];
		$funding  = is_array($data['funding'] ?? null) && array_is_list($data['funding']) ? $data['funding'] : [];
		$links    = [];
		$funds    = [];

		foreach ($support as $key => $value) {
			try {
				$links[(string) $key] = self::support((string) $key, $value);
			} catch (ExtensionException) {
				continue;
			}
		}

		foreach ($funding as $entry) {
			try {
				$funds[] = self::funding($entry);
			} catch (ExtensionException) {
				continue;
			}
		}

		return new self(
			is_string($homepage) && self::isUrl($homepage) ? trim($homepage) : '',
			self::ordered($links),
			$funds
		);
	}

	/**
	 * Returns the links as `composer.json` has them, leaving out what's
	 * empty, for a manifest's cached form.
	 *
	 * @return array{homepage?: string, support?: array<string, string>, funding?: list<array{type: string, url: string}>}
	 */
	public function toArray(): array
	{
		return array_filter(
			['homepage' => $this->homepage, 'support' => $this->support, 'funding' => $this->funding],
			static fn (string|array $value): bool => $value !== '' && $value !== []
		);
	}

	/**
	 * Returns the homepage and support links, in order, each with the URL
	 * to follow: `support.email` as a `mailto:` link.
	 *
	 * @return list<array{kind: string, url: string}>
	 */
	public function links(): array
	{
		$links = $this->homepage === '' ? [] : [['kind' => 'homepage', 'url' => $this->homepage]];

		foreach ($this->support as $kind => $url) {
			$links[] = ['kind' => $kind, 'url' => $kind === 'email' ? "mailto:{$url}" : $url];
		}

		return $links;
	}

	/**
	 * Checks one `support` entry.
	 *
	 * @throws ExtensionException
	 */
	private static function support(string $key, mixed $value): string
	{
		if (! in_array($key, self::SUPPORT, true)) {
			throw new ExtensionException(sprintf('"support" has %s; "%s" isn\'t one.', implode(', ', self::SUPPORT), $key));
		}

		$value = is_string($value) ? trim($value) : '';
		$fits  = match ($key) {
			'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
			'irc'   => self::isUrl($value, ['irc', 'ircs']),
			default => self::isUrl($value)
		};

		if (! $fits) {
			throw new ExtensionException(sprintf('"support.%s" must be %s.', $key, match ($key) {
				'email' => 'an email address',
				'irc'   => 'an irc or ircs URL',
				default => 'an http or https URL'
			}));
		}

		return $value;
	}

	/**
	 * Checks one `funding` entry.
	 *
	 * @return array{type: string, url: string}
	 * @throws ExtensionException
	 */
	private static function funding(mixed $entry): array
	{
		if (! is_array($entry) || array_diff(array_map(strval(...), array_keys($entry)), ['type', 'url']) !== []) {
			throw new ExtensionException('Each of "funding" must be an object with a "url" and an optional "type".');
		}

		$type = $entry['type'] ?? '';
		$url  = $entry['url'] ?? '';

		if (! is_string($type) || ! is_string($url) || ! self::isUrl($url)) {
			throw new ExtensionException('Each of "funding" needs a "url", an http or https URL, and its "type" must be a string.');
		}

		return ['type' => trim($type), 'url' => trim($url)];
	}

	/**
	 * Puts support links in the admin's order.
	 *
	 * @param  array<string, string> $links
	 * @return array<string, string>
	 */
	private static function ordered(array $links): array
	{
		return array_intersect_key(array_merge(array_fill_keys(self::SUPPORT, ''), $links), $links);
	}

	/**
	 * Returns whether a string is a URL with one of the schemes.
	 *
	 * @param list<string> $schemes
	 */
	private static function isUrl(string $url, array $schemes = ['http', 'https']): bool
	{
		$url    = trim($url);
		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

		return in_array($scheme, $schemes, true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
	}
}
