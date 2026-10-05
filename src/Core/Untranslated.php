<?php

/**
 * Untranslated enum.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

/**
 * What a multilingual site does with an entry that has no translation in
 * a language (D-467), from `config/app.php`'s `untranslated`. Each level
 * builds on the one before:
 *
 * - `Hide`: the language's URL for it (`/fr/about` without
 *   `about.fr.md`) is a 404, and the language's lists show only its own
 *   entries.
 * - `Redirect` (the default): that URL is a temporary redirect to the
 *   original (`/about`); lists as `Hide`.
 * - `Include`: redirects as `Redirect`, and the language's lists show
 *   the originals of entries without a translation too, linking to the
 *   originals' URLs.
 *
 * The original's text is never served at the language's URL: that's the
 * same page at two addresses (the author's call, D-467).
 */
enum Untranslated: string
{
	case Hide     = 'hide';
	case Redirect = 'redirect';
	case Include  = 'include';

	/**
	 * Returns whether the language's URL redirects to the original.
	 */
	public function redirects(): bool
	{
		return $this !== self::Hide;
	}

	/**
	 * Returns whether the language's lists show the originals of entries
	 * without a translation.
	 */
	public function lists(): bool
	{
		return $this === self::Include;
	}

	/**
	 * Returns the admin's name for it (D-468).
	 */
	public function label(): string
	{
		return match ($this) {
			self::Hide     => 'Show "Page not found"',
			self::Redirect => 'Redirect to the original',
			self::Include  => 'Redirect, and list originals too'
		};
	}

	/**
	 * Returns what it does, in a sentence, for the admin.
	 */
	public function description(): string
	{
		return match ($this) {
			self::Hide     => 'A page without a translation isn\'t found in that language, and lists show only translated entries.',
			self::Redirect => 'A page without a translation sends readers to the original. Lists show only translated entries.',
			self::Include  => 'A page without a translation sends readers to the original, and lists show the originals of untranslated entries too.'
		};
	}
}
