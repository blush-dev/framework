<?php

/**
 * Admin languages.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Locale;
use Blush\Auth\Preferences;
use Blush\Core\AppConfig;
use Blush\Data\InvalidData;
use Blush\Translation\Translator;

/**
 * The languages the admin can be shown in (D-831), an account's choice
 * on Your Account: English, the language its text is written in, and
 * each language core has an `admin` catalog for, shipped
 * (`resources/lang/admin/{locale}.json`) or the site's own
 * (`user/lang/{locale}/admin/core.json`). An account that hasn't chosen,
 * or chose one that's no longer offered, sees the site's language.
 */
final readonly class AdminLanguages
{
	/**
	 * Core's named catalog for the admin app.
	 */
	public const string CATALOG = 'admin';

	public function __construct(
		private Translator $translator,
		private AppConfig $app
	) {}

	/**
	 * Returns the languages offered, by locale, each named in its own
	 * language, English first.
	 *
	 * @return list<array{code: string, label: string}>
	 * @throws InvalidData When a catalog's name is unsafe.
	 */
	public function offered(): array
	{
		$codes = array_values(array_unique([Translator::LAST, ...$this->translator->catalogLocales(Translator::CORE, self::CATALOG)]));

		return array_map(
			static fn (string $code): array => ['code' => $code, 'label' => mb_ucfirst(Locale::getDisplayName($code, $code) ?: $code)],
			$codes
		);
	}

	/**
	 * Returns whether a language is offered.
	 *
	 * @throws InvalidData
	 */
	public function isOffered(string $code): bool
	{
		return array_any($this->offered(), static fn (array $language): bool => $language['code'] === $code);
	}

	/**
	 * Returns the locale the admin is shown in for an account's
	 * preferences (`null` when no one is signed in): its language when
	 * it's offered, else the site's.
	 *
	 * @throws InvalidData
	 */
	public function for(?Preferences $preferences): string
	{
		$language = $preferences?->language;

		return $language !== null && $this->isOffered($language) ? $language : $this->app->locale;
	}
}
