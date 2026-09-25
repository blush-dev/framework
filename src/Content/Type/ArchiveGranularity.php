<?php

/**
 * Archive granularity.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

/**
 * How finely a type's date archives go. Each level includes the ones above
 * it: `Day` gives year, month, and day archives. 1.x's `date_archives` is
 * `Day`, and its `time_archives` is `Second` (D-078).
 */
enum ArchiveGranularity: string
{
	case None   = 'none';
	case Year   = 'year';
	case Month  = 'month';
	case Day    = 'day';
	case Hour   = 'hour';
	case Minute = 'minute';
	case Second = 'second';

	/**
	 * Returns the granularity for the 1.x `date_archives` and
	 * `time_archives` flags.
	 */
	public static function fromFlags(bool $dateArchives, bool $timeArchives): self
	{
		return match (true) {
			$timeArchives => self::Second,
			$dateArchives => self::Day,
			default       => self::None
		};
	}

	/**
	 * Returns the levels this granularity includes, coarsest first.
	 *
	 * @return list<self>
	 */
	public function levels(): array
	{
		$cases = array_slice(self::cases(), 1);

		return array_slice($cases, 0, (int) array_search($this, $cases, true) + ($this === self::None ? 0 : 1));
	}
}
