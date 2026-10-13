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
use Blush\View\Escaper;
use Blush\View\SafeHtml;

/**
 * The CMS-wide translator (D-028). Messages are ICU MessageFormat
 * patterns (plurals, select, and number and date arguments), looked up
 * by key in catalogs grouped by domain (D-451): `core` for the
 * framework, and each extension's `vendor/name` (`acme/hello`,
 * `blush/default`). An extension's namespace maps to its
 * domain (`domainOf()`), for directive and icon labels.
 *
 * A domain's catalogs are data files named by locale
 * (`lang/en_US.json`, `lang/en.json`) in an ordered list of directories.
 * Ahead of them, the site's own catalogs in `user/lang` win (D-451),
 * arranged by language: `user/lang/fr/core.json`, `user/lang/fr/app.json`,
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
	 * Core's own domain, the framework's text (D-714).
	 */
	public const string CORE = 'core';

	/**
	 * The domains whose overrides sit directly in a language's folder;
	 * every other domain's are under `extensions/`.
	 *
	 * @var list<string>
	 */
	public const array OWN = [self::CORE];

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
	 * extension's `vendor/name`, or the namespace itself (`core`).
	 */
	public function domainOf(string $namespace): string
	{
		return $this->namespaces[$namespace] ?? $namespace;
	}

	/**
	 * Returns the domain of the extension a file is in (D-784), by the
	 * folder its `lang/` sits in (the deepest wins), or `null` for a file
	 * in none.
	 */
	public function domainAt(string $file): ?string
	{
		$found = null;
		$depth = -1;

		foreach ($this->directories as $domain => $directories) {
			foreach ($directories as $directory) {
				$root = dirname($directory);

				if (str_starts_with($file, $root . '/') && strlen($root) > $depth) {
					$found = (string) $domain;
					$depth = strlen($root);
				}
			}
		}

		return $found;
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
	 * of domains is searched in order for each locale. When no catalog
	 * has the key, the text written beside it in the code is used (D-784),
	 * else the key itself.
	 *
	 * @param  array<string, mixed>  $params
	 * @param  string|list<string>   $domain
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function translate(string $key, array $params = [], string|array $domain = self::CORE, ?string $locale = null, ?string $text = null): string
	{
		$locale ??= $this->locale;

		foreach (self::fallbacks($locale, $this->locale) as $candidate) {
			$message = $this->find($domain, $candidate, $key);

			if ($message !== null) {
				return self::format($message, $params, $candidate);
			}
		}

		return self::format($text ?? $key, $params, $locale);
	}

	/**
	 * Translates a message written to be translated when it's shown
	 * (D-784): from its own domain, then core's.
	 *
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function text(Text $text, ?string $locale = null): string
	{
		$locale ??= $this->locale;

		foreach (self::fallbacks($locale, $this->locale) as $candidate) {
			$message = $this->find($text->domains, $candidate, $text->key);

			if ($message !== null) {
				return self::render($text, $message, $candidate);
			}
		}

		return self::render($text, $text->text ?? $text->key, $locale);
	}

	/**
	 * Formats a message's pattern with its parameters (D-816): one that's
	 * a message itself is translated in the same locale, and when one is
	 * HTML (`raw()`, D-559), the whole comes back as HTML, that parameter
	 * as given and the rest escaped. Each is held by a private-use token
	 * while the pattern is formatted and escaped, then put back.
	 */
	public static function render(Text $text, string $pattern, string $locale): string
	{
		$params = [];
		$html   = [];

		foreach ($text->params as $name => $value) {
			if ($value instanceof SafeHtml) {
				$token         = "\u{E000}" . count($html) . "\u{E001}";
				$html[$token]  = (string) $value;
				$params[$name] = $token;
			} else {
				$params[$name] = $value instanceof Text ? (string) $value : $value;
			}
		}

		$message = self::format($pattern, $params, $locale);

		return $html === [] ? $message : strtr(Escaper::html($message), $html);
	}

	/**
	 * Returns the domains a domain's own text is looked up in (D-784):
	 * its own, then core's.
	 *
	 * @return list<string>
	 */
	public static function domainsOf(string $domain): array
	{
		return $domain === self::CORE ? [self::CORE] : [$domain, self::CORE];
	}

	/**
	 * Returns whether a key has a message in any fallback locale.
	 *
	 * @param  string|list<string> $domain
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function has(string $key, string|array $domain = self::CORE, ?string $locale = null): bool
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
			if (Catalog::isAnnotation($key)) {
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
	public static function format(string $pattern, array $params, string $locale): string
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
