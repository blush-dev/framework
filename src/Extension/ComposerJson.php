<?php

/**
 * Composer JSON.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use JsonException;

/**
 * Reads the `composer.json` in an extension's folder leniently, for what
 * a manifest may leave to it (its `authors`, D-384, and a plugin's
 * `license`, D-385). It isn't the manifest, so a missing or unreadable
 * file is nothing.
 */
final readonly class ComposerJson
{
	/**
	 * Returns the decoded file, or an empty array.
	 *
	 * @return array<array-key, mixed>
	 */
	public static function read(string $folder): array
	{
		$file = "{$folder}/composer.json";
		$json = is_file($file) ? @file_get_contents($file) : false;

		try {
			$data = $json === false ? null : json_decode($json, true, 32, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($data) ? $data : [];
	}

	/**
	 * Reads a `license` as Composer has it, a string or a list of them
	 * (any of which applies), as one string: `MIT`, `MIT or GPL-2.0`; an
	 * empty string for anything else.
	 */
	public static function license(mixed $license): string
	{
		if (is_string($license)) {
			return trim($license);
		}

		if (! is_array($license) || ! array_is_list($license)) {
			return '';
		}

		return implode(' or ', array_filter(array_map(static fn (mixed $item): string => is_string($item) ? trim($item) : '', $license), static fn (string $item): bool => $item !== ''));
	}
}
