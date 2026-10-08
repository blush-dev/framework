<?php

/**
 * Manifest file.
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
 * Finds and reads an extension's manifest file, for every kind: JSON, as
 * every data file is (D-631). A manifest file is optional when the folder's `composer.json` has the kind's `type`
 * (D-432); `load()` reads a folder either way.
 */
final readonly class ManifestFile
{
	/**
	 * Returns a folder's manifest file of a kind, or `null` when it has
	 * none.
	 */
	public static function find(string $folder, ExtensionKind $kind): ?string
	{
		$file = "{$folder}/{$kind->manifest()}.json";

		return is_file($file) ? $file : null;
	}

	/**
	 * Reads a folder's manifest of a kind, if it has one, filled in from
	 * its `composer.json` (`extra.blush`, then the shared keys; D-418,
	 * D-432).
	 *
	 * @return array<string, mixed>
	 * @throws ExtensionException When the manifest can't be parsed or isn't a map.
	 */
	public static function load(string $folder, ExtensionKind $kind): array
	{
		$file = self::find($folder, $kind);

		return ComposerJson::fill($file === null ? [] : self::read($file), $folder);
	}

	/**
	 * Reads a manifest file as a map of keys to values. An empty one (`{}`)
	 * is a map with nothing in it, since a manifest may leave every key to
	 * its `composer.json` or a default (D-424).
	 *
	 * @return array<string, mixed>
	 * @throws ExtensionException When it can't be parsed or isn't a map.
	 */
	public static function read(string $file): array
	{
		$contents = (string) file_get_contents($file);

		try {
			$data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new ExtensionException(sprintf('The manifest %s is invalid: %s', $file, $e->getMessage()), previous: $e);
		}

		if (! is_array($data) || ($data !== [] && array_is_list($data))) {
			throw new ExtensionException(sprintf('The manifest %s must be a map of keys to values.', $file));
		}

		/** @var array<string, mixed> Not a list, so its keys are strings. */
		return $data;
	}
}
