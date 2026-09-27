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
 * by key in catalogs grouped by domain: `blush` for the framework,
 * `theme` for the active theme chain, and more for extensions and sites
 * later.
 *
 * A domain's catalogs are data files named by locale
 * (`lang/en_US.json`, `lang/en.yaml`) in an ordered list of directories;
 * for each key the first directory that has it wins, so a child theme
 * overrides its parent message by message. Nested objects flatten into
 * dotted keys.
 *
 * Locales fall back from the most specific: `en_US`, then `en`, then the
 * site locale and its language. A key found nowhere comes back as the
 * key itself (formatted with its parameters), so a missing translation
 * shows up without breaking the page.
 */
final class Translator
{
	/**
	 * Loaded catalogs, keyed by `{domain}|{locale}`.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $catalogs = [];

	/**
	 * @param array<string, list<string>> $directories Catalog directories by domain, highest precedence first.
	 */
	public function __construct(
		private readonly DataLoader $loader,
		private readonly string $locale = 'en_US',
		private readonly array $directories = []
	) {}

	/**
	 * Returns a copy that reads a domain from other directories.
	 *
	 * @param list<string> $directories Highest precedence first.
	 */
	#[NoDiscard]
	public function withDirectories(string $domain, array $directories): self
	{
		return new self($this->loader, $this->locale, [...$this->directories, $domain => $directories]);
	}

	/**
	 * Returns the default locale.
	 */
	public function locale(): string
	{
		return $this->locale;
	}

	/**
	 * Translates a key, formatting it with named parameters:
	 * `translate('reading_time', ['minutes' => 5], 'theme')`.
	 *
	 * @param  array<string, mixed> $params
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function translate(string $key, array $params = [], string $domain = 'blush', ?string $locale = null): string
	{
		$locale ??= $this->locale;

		foreach (self::fallbacks($locale, $this->locale) as $candidate) {
			$message = $this->catalog($domain, $candidate)[$key] ?? null;

			if ($message !== null) {
				return self::format($message, $params, $candidate);
			}
		}

		return self::format($key, $params, $locale);
	}

	/**
	 * Returns whether a key has a message in any fallback locale.
	 *
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function has(string $key, string $domain = 'blush', ?string $locale = null): bool
	{
		return array_any(
			self::fallbacks($locale ?? $this->locale, $this->locale),
			fn (string $candidate): bool => isset($this->catalog($domain, $candidate)[$key])
		);
	}

	/**
	 * Returns the locales to look in, most specific first: the locale,
	 * its language, then the default locale and its language.
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

		return array_filter($locales, static fn (string $name): bool => $name !== '')
			|> array_unique(...)
			|> array_values(...);
	}

	/**
	 * Returns a domain's merged catalog for one locale.
	 *
	 * @return array<string, string>
	 * @throws InvalidData
	 */
	private function catalog(string $domain, string $locale): array
	{
		$id = "{$domain}|{$locale}";

		if (isset($this->catalogs[$id])) {
			return $this->catalogs[$id];
		}

		$catalog = [];

		foreach ($this->directories[$domain] ?? [] as $directory) {
			$catalog += self::flatten($this->loader->load($directory, $locale) ?? []);
		}

		return $this->catalogs[$id] = $catalog;
	}

	/**
	 * Flattens nested messages into dotted keys, keeping only strings.
	 *
	 * @param  array<array-key, mixed> $messages
	 * @return array<string, string>
	 */
	private static function flatten(array $messages, string $prefix = ''): array
	{
		$flat = [];

		foreach ($messages as $key => $message) {
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
