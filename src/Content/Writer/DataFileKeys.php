<?php

/**
 * Data file keys.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use JsonException;
use Blush\Data\InvalidData;

/**
 * Sets and removes top-level keys in a JSON data file's text (D-631),
 * leaving the object's other keys as they were written. The admin writes
 * data types (D-311) and field sets (D-337) with it.
 */
final readonly class DataFileKeys
{
	/**
	 * Returns a file's text with keys set or (for `null`) removed.
	 *
	 * @param  array<string, mixed>        $sets    Keys to values; `null` removes one.
	 * @param  array<string, list<string>> $renamed Older names each key replaces, removed with it.
	 * @throws InvalidData When the file isn't a JSON object.
	 */
	public static function edit(string $text, array $sets, array $renamed = []): string
	{
		try {
			$data = trim($text) === '' ? [] : json_decode($text, true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			throw new InvalidData('The file isn\'t valid JSON; fix it by hand first.', previous: $error);
		}

		if (! is_array($data)) {
			throw new InvalidData('The file isn\'t a JSON object; fix it by hand first.');
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

		try {
			return json_encode($data === [] ? (object) [] : $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
		} catch (JsonException $error) {
			throw new InvalidData('The values can\'t be written as JSON.', previous: $error);
		}
	}
}
