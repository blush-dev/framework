<?php

/**
 * Translation rule.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * How a translation's links in a relation relate to its original's
 * (D-587). Targets always show in the entry's language when they have a
 * translation in it, whatever the rule.
 *
 * - `Fallback` (the default): its own links when it has any, else its
 *   original's, so its own replace the original's.
 * - `Add`: the original's, then its own (a translation crediting its
 *   translator beside the original's authors).
 * - `Own`: only its own.
 */
enum TranslationRule: string
{
	case Fallback = 'fallback';
	case Add      = 'add';
	case Own      = 'own';

	/**
	 * Returns the target ids a translation shows, from its own and its
	 * original's, in order and without repeats.
	 *
	 * @param  list<string> $own
	 * @param  list<string> $original
	 * @return list<string>
	 */
	public function apply(array $own, array $original): array
	{
		return match ($this) {
			self::Fallback => $own === [] ? $original : $own,
			self::Add      => array_values(array_unique([...$original, ...$own])),
			self::Own      => $own
		};
	}
}
