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
 * a manifest may leave to it: the keys a manifest shares with Composer's
 * schema (D-418; `authors` since D-384, `license` since D-385, and
 * `homepage`, `support`, and `funding` since D-428). It isn't
 * the manifest, so a missing or unreadable file is nothing, and so is a
 * key of the wrong shape.
 */
final readonly class ComposerJson
{
	/**
	 * The keys a manifest shares with Composer's schema, which a manifest
	 * may leave to its `composer.json`.
	 *
	 * @var list<string>
	 */
	public const array SHARED = ['name', 'description', 'version', 'license', 'authors', 'autoload', 'require', 'homepage', 'support', 'funding'];

	/**
	 * Fills in the shared keys a manifest leaves out from the
	 * `composer.json` in its folder. Blush's own keys never come from it.
	 *
	 * @param  array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public static function fill(array $data, string $folder): array
	{
		if (array_diff(self::SHARED, array_keys($data)) === []) {
			return $data;
		}

		$composer = self::read($folder);

		foreach (self::SHARED as $key) {
			if (array_key_exists($key, $data) || ! array_key_exists($key, $composer)) {
				continue;
			}

			$value = match ($key) {
				'authors' => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), ExtensionAuthor::lenient($composer['authors'])),
				'license' => self::license($composer['license']),
				'autoload', 'require' => is_array($composer[$key]) ? $composer[$key] : null,
				'homepage', 'support', 'funding' => ExtensionLinks::lenient([$key => $composer[$key]])->toArray()[$key] ?? null,
				default   => is_string($composer[$key]) ? $composer[$key] : null
			};

			if ($value !== null) {
				$data[$key] = $value;
			}
		}

		return $data;
	}

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
