<?php

/**
 * Translator.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use Locale;
use MessageFormatter;
use NoDiscard;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;

/**
 * The CMS-wide translator (D-028). Messages are ICU MessageFormat
 * patterns (plurals, select, and number and date arguments), looked up
 * by key in catalogs grouped by domain (D-451): `blush` for the
 * framework, `app` for the site, and each extension's `vendor/name`
 * (`acme/hello`, `blush/default`). An extension's namespace maps to its
 * domain (`domainOf()`), for component and icon labels.
 *
 * A domain's catalogs are data files named by locale
 * (`lang/en_US.json`, `lang/en.yaml`) in an ordered list of directories.
 * Ahead of them, the site's own catalogs in `user/lang` win (D-451),
 * arranged by language: `user/lang/fr/blush.json`, `user/lang/fr/app.json`,
 * and `user/lang/fr/extensions/acme/hello.json`. A lookup can take a list
 * of domains, such as a theme chain's, child first; for each key the
 * first that has it wins, so a child theme overrides its parent message
 * by message. Nested objects flatten into dotted keys. Keys starting with
 * `@@` are metadata, not messages: a catalog says what it translates with
 * `@@locale` and `@@domain` (D-452).
 *
 * Locales fall back from the most specific: `fr_CA`, then `fr`, then the
 * site locale and its language, and last `en` (D-453), the language
 * packages ship first, so a package with only `en.json` shows English
 * rather than its keys on a French site. A key found nowhere comes back as the
 * key itself (formatted with its parameters), so a missing translation
 * shows up without breaking the page.
 */
final class Translator
{
	/**
	 * The locale every lookup falls back to last.
	 */
	public const string LAST = 'en';

	/**
	 * The domains whose overrides sit directly in a language's folder;
	 * every other domain's are under `extensions/`.
	 *
	 * @var list<string>
	 */
	public const array OWN = ['blush', 'app'];

	/**
	 * Loaded layers, keyed by `{domain}|{locale}`: the site's override,
	 * then the domain's own catalogs merged.
	 *
	 * @var array<string, array{array<string, string>, array<string, string>}>
	 */
	private array $layers = [];

	/**
	 * @param array<string, list<string>> $directories Catalog directories by domain, highest precedence first.
	 * @param ?string                     $overrides   The site's override folder (`user/lang`), or `null` for none.
	 * @param array<string, string>       $namespaces  Extension namespaces' domains.
	 */
	public function __construct(
		private readonly DataLoader $loader,
		private readonly string $locale = 'en_US',
		private readonly array $directories = [],
		private readonly ?string $overrides = null,
		private readonly array $namespaces = []
	) {}

	/**
	 * Returns a copy with more domains, replacing any with the same name,
	 * and their namespaces.
	 *
	 * @param array<string, list<string>> $directories Catalog directories by domain, highest precedence first.
	 * @param array<string, string>       $namespaces  Namespaces' domains.
	 */
	#[NoDiscard]
	public function withDomains(array $directories, array $namespaces = []): self
	{
		return new self($this->loader, $this->locale, [...$this->directories, ...$directories], $this->overrides, [...$this->namespaces, ...$namespaces]);
	}

	/**
	 * Returns the default locale.
	 */
	public function locale(): string
	{
		return $this->locale;
	}

	/**
	 * Returns the domain an extension namespace's text is in: its
	 * extension's `vendor/name`, or the namespace itself (`blush`, `app`).
	 */
	public function domainOf(string $namespace): string
	{
		return $this->namespaces[$namespace] ?? $namespace;
	}

	/**
	 * Returns the override catalog's path for a domain and locale, without
	 * its extension, or `null` when the site has no override folder.
	 */
	public function overridePath(string $domain, string $locale): ?string
	{
		if ($this->overrides === null) {
			return null;
		}

		return in_array($domain, self::OWN, true) || ! str_contains($domain, '/')
			? "{$this->overrides}/{$locale}/{$domain}"
			: "{$this->overrides}/{$locale}/extensions/{$domain}";
	}

	/**
	 * Translates a key, formatting it with named parameters:
	 * `translate('reading_time', ['minutes' => 5], 'acme/hello')`. A list
	 * of domains is searched in order for each locale.
	 *
	 * @param  array<string, mixed>  $params
	 * @param  string|list<string>   $domain
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function translate(string $key, array $params = [], string|array $domain = 'blush', ?string $locale = null): string
	{
		$locale ??= $this->locale;

		foreach (self::fallbacks($locale, $this->locale) as $candidate) {
			$message = $this->find($domain, $candidate, $key);

			if ($message !== null) {
				return self::format($message, $params, $candidate);
			}
		}

		return self::format($key, $params, $locale);
	}

	/**
	 * Returns the messages nested under a key, formatted and keyed by
	 * their last segment, from the first fallback locale that has any
	 * (D-450): a group comes whole from one locale, so a translation can
	 * have more or fewer messages in it than the default. Within a domain,
	 * the site's override replaces the package's group; across a list of
	 * domains, the groups add up, earlier ones winning, so a child theme
	 * adds to its parent's. Empty when no locale has the group.
	 *
	 * @param  array<string, mixed> $params
	 * @param  string|list<string>  $domain
	 * @return array<string, string>
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function group(string $key, array $params = [], string|array $domain = 'blush', ?string $locale = null): array
	{
		$prefix = "{$key}.";

		foreach (self::fallbacks($locale ?? $this->locale, $this->locale) as $candidate) {
			$messages = [];

			foreach ((array) $domain as $name) {
				foreach ($this->layers($name, $candidate) as $layer) {
					$found = [];

					foreach ($layer as $id => $message) {
						if (str_starts_with($id, $prefix)) {
							$found[substr($id, strlen($prefix))] = self::format($message, $params, $candidate);
						}
					}

					if ($found !== []) {
						$messages += $found;

						break;
					}
				}
			}

			if ($messages !== []) {
				return $messages;
			}
		}

		return [];
	}

	/**
	 * Returns whether a key has a message in any fallback locale.
	 *
	 * @param  string|list<string> $domain
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function has(string $key, string|array $domain = 'blush', ?string $locale = null): bool
	{
		return array_any(
			self::fallbacks($locale ?? $this->locale, $this->locale),
			fn (string $candidate): bool => $this->find($domain, $candidate, $key) !== null
		);
	}

	/**
	 * Returns the locales to look in, most specific first: the locale,
	 * its language, the default locale and its language, then `en`.
	 *
	 * @return list<string>
	 */
	public static function fallbacks(string $locale, string $default): array
	{
		$locales = [];

		foreach ([$locale, $default] as $name) {
			$name      = self::normalize($name);
			$locales[] = $name;
			$locales[] = Locale::getPrimaryLanguage($name) ?? $name;
		}

		$locales[] = self::LAST;

		return array_filter($locales, static fn (string $name): bool => $name !== '')
			|> array_unique(...)
			|> array_values(...);
	}

	/**
	 * Returns a key's message in one locale from the first of the domains
	 * (and, within each, the override first) that has it, or `null`.
	 *
	 * @param  string|list<string> $domain
	 * @throws InvalidData
	 */
	private function find(string|array $domain, string $locale, string $key): ?string
	{
		foreach ((array) $domain as $name) {
			foreach ($this->layers($name, $locale) as $layer) {
				if (isset($layer[$key])) {
					return $layer[$key];
				}
			}
		}

		return null;
	}

	/**
	 * Returns a domain's layers for one locale: the site's override, then
	 * its own catalogs merged, highest precedence first.
	 *
	 * @return array{array<string, string>, array<string, string>}
	 * @throws InvalidData
	 */
	private function layers(string $domain, string $locale): array
	{
		$id = "{$domain}|{$locale}";

		if (isset($this->layers[$id])) {
			return $this->layers[$id];
		}

		$override = $this->overridePath($domain, $locale);
		$own      = [];

		foreach ($this->directories[$domain] ?? [] as $directory) {
			$own += self::flatten($this->loader->load($directory, $locale) ?? []);
		}

		return $this->layers[$id] = [
			$override === null ? [] : self::flatten($this->loader->load(dirname($override), basename($override)) ?? []),
			$own
		];
	}

	/**
	 * Flattens nested messages into dotted keys, keeping only strings and
	 * skipping `@@` metadata keys.
	 *
	 * @param  array<array-key, mixed> $messages
	 * @return array<string, string>
	 */
	private static function flatten(array $messages, string $prefix = ''): array
	{
		$flat = [];

		foreach ($messages as $key => $message) {
			if (Catalog::isMeta($key)) {
				continue;
			}

			$key = $prefix . $key;

			if (is_array($message)) {
				$flat += self::flatten($message, "{$key}.");
			} elseif (is_string($message)) {
				$flat[$key] = $message;
			}
		}

		return $flat;
	}

	/**
	 * Formats a message pattern. Patterns without arguments skip ICU, and
	 * a pattern ICU can't format comes back as written.
	 *
	 * @param array<string, mixed> $params
	 */
	private static function format(string $pattern, array $params, string $locale): string
	{
		if (! str_contains($pattern, '{')) {
			return $pattern;
		}

		$formatted = MessageFormatter::formatMessage($locale, $pattern, $params);

		return $formatted === false ? $pattern : $formatted;
	}

	/**
	 * Normalizes a locale name to `ll_RR` form (`en-us` → `en_US`).
	 */
	private static function normalize(string $locale): string
	{
		return Locale::canonicalize(str_replace('-', '_', $locale)) ?? $locale;
	}
}
