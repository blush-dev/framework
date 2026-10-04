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
 * a manifest may leave to it: Blush's own keys, under `extra.blush`
 * (D-432), and the keys a manifest shares with Composer's schema (D-418;
 * `authors` since D-384, `license` since D-385, and `homepage`,
 * `support`, and `funding` since D-428, `abandoned` since D-433, `suggest` since D-434, and `conflict` since
 * D-435). Its `type` may say the folder's
 * kind (D-432). A missing or unreadable file is nothing, and so is a key
 * of the wrong shape.
 */
final readonly class ComposerJson
{
	/**
	 * The keys a manifest shares with Composer's schema, which a manifest
	 * may leave to its `composer.json`.
	 *
	 * @var list<string>
	 */
	public const array SHARED = ['name', 'description', 'version', 'license', 'authors', 'autoload', 'require', 'conflict', 'homepage', 'support', 'funding', 'abandoned', 'suggest'];

	/**
	 * Fills in what a manifest leaves out from the `composer.json` in its
	 * folder: first the keys in its `extra.blush`, then the shared keys,
	 * but for the ones to skip (D-432). So a manifest's key wins over
	 * `extra.blush`'s, which wins over `composer.json`'s own, and a folder
	 * with no manifest file at all is described by its `composer.json`.
	 *
	 * A Composer package's theme or icon pack skips `require` (D-431) and
	 * `conflict` (D-435): Composer has met its `composer.json`'s, whose
	 * packages aren't extensions, so only its manifest's (or
	 * `extra.blush`'s) are Blush's to check.
	 *
	 * @param  array<string, mixed> $data
	 * @param  list<string>         $skip
	 * @return array<string, mixed>
	 */
	public static function fill(array $data, string $folder, array $skip = []): array
	{
		$composer = self::read($folder);

		foreach (self::blush($composer) as $key => $value) {
			if (! array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		foreach (array_diff(self::SHARED, $skip) as $key) {
			if (array_key_exists($key, $data) || ! array_key_exists($key, $composer)) {
				continue;
			}

			$value = match ($key) {
				'authors' => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), ExtensionAuthor::lenient($composer['authors'])),
				'license' => self::license($composer['license']),
				'abandoned' => ExtensionAbandoned::lenient($composer['abandoned']),
				'suggest'   => ExtensionSuggest::lenient($composer['suggest']) ?: null,
				'autoload', 'require', 'conflict' => is_array($composer[$key]) ? $composer[$key] : null,
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
	 * Returns the kind the `composer.json` in a folder says it is, by its
	 * `type` (`blush-plugin`, `blush-theme`, `blush-icons`), or `null`.
	 */
	public static function kind(string $folder): ?ExtensionKind
	{
		return ExtensionKind::fromPackageType(self::read($folder)['type'] ?? null);
	}

	/**
	 * Returns the map under a decoded file's `extra.blush`, or an empty
	 * array when there's none.
	 *
	 * @param  array<array-key, mixed> $composer
	 * @return array<string, mixed>
	 */
	public static function blush(array $composer): array
	{
		$extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
		$blush = $extra['blush'] ?? null;

		if (! is_array($blush) || array_is_list($blush)) {
			return [];
		}

		return array_filter($blush, is_string(...), ARRAY_FILTER_USE_KEY);
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
