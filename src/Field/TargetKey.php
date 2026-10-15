<?php

/**
 * Target keys.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * The keys places are named by (D-337): a kind, a colon, and a name, such
 * as `type:post`, or `*` for every place of the kind (`type:*`, D-786).
 * Fields code registers name the places they're on this way.
 */
final class TargetKey
{
	/**
	 * What a target key may be.
	 */
	public const string PATTERN = '/^[a-z]+:(?:\*|[a-z0-9][a-z0-9_-]*)$/';

	/**
	 * A target's name for every place of its kind.
	 */
	public const string EVERY = '*';

	/**
	 * Returns whether a list of targets takes in a place, by its key:
	 * named, or as one of every place of its kind.
	 *
	 * @param list<string> $targets
	 */
	public static function matches(array $targets, string $target): bool
	{
		if (in_array($target, $targets, true)) {
			return true;
		}

		$kind = strstr($target, ':', true);

		return $kind !== false && in_array("{$kind}:" . self::EVERY, $targets, true);
	}

	/**
	 * Returns a target's kind, the part before the colon.
	 */
	public static function kindOf(string $target): string
	{
		return strstr($target, ':', true) ?: $target;
	}
}
