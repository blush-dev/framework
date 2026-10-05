<?php

/**
 * Language.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use Locale;
use Blush\Config\InvalidConfig;

/**
 * One language a site's content is written in (D-455): its code, the
 * locale its pages are shown in, and its name in its own language.
 *
 * The code is lowercase and hyphenated (`fr`, `pt-br`). It's the suffix
 * a translation's file name carries (`about.fr.md`) and the prefix its
 * URLs sit under (`/fr/a-propos`). The locale (`fr_FR`) picks messages,
 * dates, and `<html lang>`, and names its `user/lang` folder (D-451).
 */
final readonly class Language
{
	/**
	 * What a language code looks like.
	 */
	public const string CODE = '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/';

	/**
	 * @throws InvalidConfig
	 */
	public function __construct(
		public string $code,
		public string $locale,
		public string $label = ''
	) {
		if (preg_match(self::CODE, $code) !== 1) {
			throw new InvalidConfig(sprintf('The language code "%s" must be lowercase letters and digits joined by hyphens, such as "fr" or "pt-br".', $code));
		}

		if (preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $locale) !== 1) {
			throw new InvalidConfig(sprintf('The language "%s" has an invalid locale "%s".', $code, $locale));
		}
	}

	/**
	 * Returns the language's name: its label, or the locale's name in its
	 * own language (`Français`).
	 */
	public function name(): string
	{
		return $this->label !== '' ? $this->label : mb_ucfirst(Locale::getDisplayName($this->locale, $this->locale) ?: $this->code);
	}

	/**
	 * Returns the locale as a BCP 47 tag (`fr-FR`), for `lang` and
	 * `hreflang` attributes.
	 */
	public function tag(): string
	{
		return str_replace('_', '-', $this->locale);
	}

	/**
	 * Returns the code a locale gets when nothing names one: its language
	 * (`en` for `en_US`).
	 */
	public static function codeOf(string $locale): string
	{
		return strtolower(Locale::getPrimaryLanguage($locale) ?? strtok($locale, '_-') ?: $locale);
	}
}
