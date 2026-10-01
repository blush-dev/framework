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
 * Sets and removes top-level keys in a JSON or YAML data file's text,
 * leaving everything else as it was written: a JSON object's other keys,
 * and a YAML file's other lines, comments included (`YamlMap`). The admin
 * writes data types (D-311) and field sets (D-337) with it.
 */
final readonly class DataFileKeys
{
	/**
	 * Returns a file's text with keys set or (for `null`) removed, as JSON
	 * or YAML by its extension.
	 *
	 * @param  array<string, mixed>        $sets    Keys to values; `null` removes one.
	 * @param  array<string, list<string>> $renamed Older names each key replaces, removed with it.
	 * @param  array<string, int>          $inline  For YAML, the depth each key's value turns inline at (2 by default).
	 * @throws InvalidData When a JSON file isn't a JSON object.
	 */
	public static function edit(string $path, string $text, array $sets, array $renamed = [], array $inline = []): string
	{
		return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json'
			? self::json($text, $sets, $renamed)
			: self::yaml($text, $sets, $renamed, $inline);
	}

	/**
	 * JSON with keys set or removed.
	 *
	 * @param  array<string, mixed>        $sets
	 * @param  array<string, list<string>> $renamed
	 * @throws InvalidData
	 */
	public static function json(string $text, array $sets, array $renamed = []): string
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

	/**
	 * YAML with top-level keys set or removed, each list or map written as
	 * a block down to its inline depth.
	 *
	 * @param array<string, mixed>        $sets
	 * @param array<string, list<string>> $renamed
	 * @param array<string, int>          $inline
	 */
	public static function yaml(string $text, array $sets, array $renamed = [], array $inline = []): string
	{
		// An empty file is written as `{}`, which keys can't follow.
		$map = YamlMap::fromText(trim($text) === '{}' ? '' : $text);

		foreach ($sets as $key => $value) {
			$map = $map->without($renamed[$key] ?? []);
			$map = $value === null ? $map->without([$key]) : $map->with([$key], $value, $inline[$key] ?? 2);
		}

		$yaml = $map->text();

		return trim($yaml) === '' ? "{}\n" : $yaml;
	}
}
