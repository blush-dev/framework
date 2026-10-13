<?php

/**
 * Languages.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use Blush\Config\InvalidConfig;

/**
 * The languages a site's content is written in (D-455): the default
 * language, which is the site's locale, and any others from
 * `config/app.php`'s `languages`, by code:
 *
 * ```php
 * 'locale'    => 'en_US',
 * 'languages' => [
 *     'fr'    => ['locale' => 'fr_FR', 'label' => 'Français'],
 *     'pt-br' => 'pt_BR'
 * ]
 * ```
 *
 * The default language's content has no suffix and its URLs no prefix.
 * Its code is the site locale's language (`en`), unless a listed
 * language has the site's locale, which makes that entry the default.
 * When none has it, a listed language whose code is the locale's
 * language becomes the default with the site's locale (D-825): a locale
 * of `es` or `es_MX` makes a listed `es` the default, keeping its label.
 * Every other language's files carry its code as a suffix and its URLs
 * sit under `/{code}`.
 */
final readonly class Languages
{
	/**
	 * @param array<string, Language> $others The other languages, by code.
	 * @param ?Language                $listed The config's entry for the default language, if it lists one.
	 */
	private function __construct(
		public Language $default,
		private array $others,
		private ?Language $listed = null
	) {}

	/**
	 * Builds the languages from the site locale and the config's map of
	 * codes to a locale or `['locale' => …, 'label' => …]`.
	 *
	 * @param  array<array-key, mixed> $languages
	 * @throws InvalidConfig
	 */
	public static function fromArray(string $locale, array $languages): self
	{
		$default = null;
		$others  = [];

		foreach ($languages as $code => $value) {
			$language = self::language((string) $code, $value);

			if ($language->locale === $locale && $default === null) {
				$default = $language;
			} else {
				$others[$language->code] = $language;
			}
		}

		$listed = $default;
		$code   = Language::codeOf($locale);

		if ($default === null && isset($others[$code])) {
			$listed  = $others[$code];
			$default = new Language($code, $locale, $listed->label);
			unset($others[$code]);
		}

		$default ??= new Language($code, $locale);

		$locales = array_count_values(array_map(static fn (Language $language): string => $language->locale, [$default, ...array_values($others)]));
		$shared  = array_keys(array_filter($locales, static fn (int $count): bool => $count > 1));

		if ($shared !== []) {
			throw new InvalidConfig(sprintf('Two languages have the locale "%s"; each language needs its own.', $shared[0]));
		}

		return new self($default, $others, $listed);
	}

	/**
	 * Returns every language, the default first.
	 *
	 * @return array<string, Language>
	 */
	public function all(): array
	{
		return [$this->default->code => $this->default, ...$this->others];
	}

	/**
	 * Returns the languages other than the default, by code.
	 *
	 * @return array<string, Language>
	 */
	public function others(): array
	{
		return $this->others;
	}

	/**
	 * Returns whether the site has languages other than its default.
	 */
	public function isMultilingual(): bool
	{
		return $this->others !== [];
	}

	/**
	 * Returns a language by code.
	 */
	public function find(string $code): ?Language
	{
		return $code === $this->default->code ? $this->default : $this->others[$code] ?? null;
	}

	/**
	 * Returns the language a page in a locale is in (D-463): the language
	 * with that locale, or `null`.
	 */
	public function forLocale(string $locale): ?Language
	{
		return array_find($this->all(), static fn (Language $language): bool => $language->locale === $locale);
	}

	/**
	 * Returns whether a code is a language other than the default: one a
	 * file name's suffix or a URL's prefix may carry.
	 */
	public function isOther(string $code): bool
	{
		return isset($this->others[$code]);
	}

	/**
	 * Returns whether a code is the default language's.
	 */
	public function isDefault(string $code): bool
	{
		return $code === $this->default->code;
	}

	/**
	 * Returns the config's form: codes to a locale and label, with the
	 * default language's entry as the config listed it.
	 *
	 * @return array<string, array{locale: string, label: string}>
	 */
	public function toArray(): array
	{
		return array_map(
			static fn (Language $language): array => ['locale' => $language->locale, 'label' => $language->label],
			$this->listed === null ? $this->others : [$this->listed->code => $this->listed, ...$this->others]
		);
	}

	/**
	 * Builds one language from its config value.
	 *
	 * @throws InvalidConfig
	 */
	private static function language(string $code, mixed $value): Language
	{
		if (is_string($value)) {
			return new Language($code, $value);
		}

		$locale = is_array($value) ? $value['locale'] ?? null : null;
		$label  = is_array($value) ? $value['label'] ?? '' : '';

		if (! is_string($locale) || ! is_string($label)) {
			throw new InvalidConfig(sprintf('The language "%s" needs a locale: a string, or an array with a "locale" and an optional "label".', $code));
		}

		return new Language($code, $locale, $label);
	}
}
