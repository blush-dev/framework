<?php

/**
 * Locales.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use Collator;
use Locale;
use ResourceBundle;

/**
 * The languages and regions a site can be written in, for the admin's
 * language menu (D-441, D-442): every locale ICU knows, each named in
 * its own language (`Deutsch (Österreich)`, its first letter capital),
 * with its name in the admin's language beside it (`German (Austria)`)
 * when that's different. They're grouped by language: the language
 * first, then its regions and scripts under it (`depth` 1), in
 * alphabetical order of the admin-language names, so the order holds
 * across scripts (`Chinese (Simplified)` before `Chinese (Simplified,
 * China)`). Variants (`en_US_POSIX`) are left out; a site may still use
 * any code by typing it.
 */
final class Locales
{
	/**
	 * Returns the locales as menu options, each named in its own language
	 * (`label`) and in another (`hint`, `null` when it's the same).
	 *
	 * @return list<array{value: string, label: string, hint: ?string, depth: int}>
	 */
	public static function options(string $in = 'en'): array
	{
		$collator  = new Collator($in);
		$languages = [];

		foreach (ResourceBundle::getLocales('') ?: [] as $locale) {
			if (! is_string($locale) || array_key_exists('variant0', Locale::parseLocale($locale) ?? [])) {
				continue;
			}

			$language = Locale::getPrimaryLanguage($locale) ?? $locale;
			$native   = mb_ucfirst(Locale::getDisplayName($locale, $locale) ?: $locale);
			$name     = Locale::getDisplayName($locale, $in) ?: $locale;

			$languages[$language][] = [
				'value' => $locale,
				'label' => $native,
				'hint'  => $name === $native ? null : $name,
				'depth' => $locale === $language ? 0 : 1,
				'name'  => $name
			];
		}

		// `Chinese (Simplified)` before `Chinese (Simplified, China)`.
		$sortable = static fn (string $name): string => rtrim($name, ')');

		foreach ($languages as $language => $locales) {
			usort($locales, static fn (array $a, array $b): int => $a['depth'] <=> $b['depth'] ?: (int) $collator->compare($sortable($a['name']), $sortable($b['name'])));
			$languages[$language] = $locales;
		}

		uasort($languages, static fn (array $a, array $b): int => (int) $collator->compare($a[0]['name'], $b[0]['name']));

		return array_map(
			static fn (array $option): array => ['value' => $option['value'], 'label' => $option['label'], 'hint' => $option['hint'], 'depth' => $option['depth']],
			array_merge(...array_values($languages))
		);
	}
}
