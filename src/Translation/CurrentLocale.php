<?php

/**
 * Current locale.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use Closure;

/**
 * The language text is shown in right now (D-816), which every `t()`
 * reads when its text is printed: the site's, until something says
 * otherwise. A page render sets its page's (a translation's, D-455) for
 * as long as it renders, with `in()`; each request starts over in the
 * site's.
 */
final class CurrentLocale
{
	/**
	 * The locale set for now, or `null` for the site's.
	 */
	private ?string $locale = null;

	/**
	 * @param string $site The site's locale.
	 */
	public function __construct(public readonly string $site = 'en_US')
	{}

	/**
	 * Returns the locale text is shown in now.
	 */
	public function get(): string
	{
		return $this->locale ?? $this->site;
	}

	/**
	 * Runs a callback in a locale (`null` or `''` for the site's), then
	 * goes back to the one before.
	 *
	 * @template T
	 * @param  Closure(): T $callback
	 * @return T
	 */
	public function in(?string $locale, Closure $callback): mixed
	{
		$before       = $this->locale;
		$this->locale = $locale === null || $locale === '' ? null : $locale;

		try {
			return $callback();
		} finally {
			$this->locale = $before;
		}
	}

	/**
	 * Goes back to the site's locale, as each request starts.
	 */
	public function reset(): void
	{
		$this->locale = null;
	}
}
