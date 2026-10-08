<?php

/**
 * Data keys.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

/**
 * Sets and removes top-level keys in a record, leaving its other keys as
 * they were, in their order. The admin writes data types (D-311) and
 * field sets (D-337) with it.
 */
final readonly class DataKeys
{
	/**
	 * Returns a record with keys set or (for `null`) removed.
	 *
	 * @param  array<array-key, mixed>     $data
	 * @param  array<string, mixed>        $sets    Keys to values; `null` removes one.
	 * @param  array<string, list<string>> $renamed Older names each key replaces, removed with it.
	 * @return array<array-key, mixed>
	 * @throws InvalidData When the record is a list, not a map.
	 */
	public static function apply(array $data, array $sets, array $renamed = []): array
	{
		if ($data !== [] && array_is_list($data)) {
			throw new InvalidData('The record isn\'t a map of keys; fix it by hand first.');
		}

		foreach ($sets as $key => $value) {
			foreach ($renamed[$key] ?? [] as $old) {
				unset($data[$old]);
			}

			if ($value === null) {
				unset($data[$key]);
			} else {
				$data[$key] = $value;
			}
		}

		return $data;
	}
}
