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
 * Every other language's files carry its code as a suffix and its URLs
 * sit under `/{code}`.
 */
final readonly class Languages
{
	/**
	 * @param array<string, Language> $others The other languages, by code.
	 * @param bool                    $listed Whether the config lists the default language.
	 */
	private function __construct(
		public Language $default,
		private array $others,
		private bool $listed = false
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

		$listed  = $default !== null;
		$default ??= new Language(Language::codeOf($locale), $locale);

		$locales = array_count_values(array_map(static fn (Language $language): string => $language->locale, [$default, ...array_values($others)]));
		$shared  = array_keys(array_filter($locales, static fn (int $count): bool => $count > 1));

		if ($shared !== []) {
			throw new InvalidConfig(sprintf('Two languages have the locale "%s"; each language needs its own.', $shared[0]));
		}

		if (isset($others[$default->code])) {
			throw new InvalidConfig(sprintf(
				'The language "%s" is the site\'s default language (from its locale, "%s"); give it the site\'s locale or another code.',
				$default->code,
				$locale
			));
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
	 * with that locale, or `null` (an entry with its own `locale` front
	 * matter, which is in the default language).
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
	 * Returns the config's form: codes to a locale and label.
	 *
	 * @return array<string, array{locale: string, label: string}>
	 */
	public function toArray(): array
	{
		return array_map(
			static fn (Language $language): array => ['locale' => $language->locale, 'label' => $language->label],
			$this->listed ? $this->all() : $this->others
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
