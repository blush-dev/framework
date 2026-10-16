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
use Blush\Translation\Messages;
use Blush\Translation\Text;

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
	 * @throws ExtensionException When the manifest can't be parsed, isn't a map, or is of a schema version Blush can't read.
	 */
	public static function load(string $folder, ExtensionKind $kind, Messages $messages = new Messages()): array
	{
		return self::compose(self::find($folder, $kind), $folder, $kind, $messages);
	}

	/**
	 * Reads a manifest file, or none, filled in from the `composer.json`
	 * in its folder, each in the current schema version's shape (D-908):
	 * the file by its own `schemaVersion`, and `extra.blush` by its own,
	 * never the file's taken from `extra.blush`.
	 *
	 * @return array<string, mixed>
	 * @throws ExtensionException When the manifest can't be parsed, isn't a map, or is of a schema version Blush can't read.
	 */
	public static function compose(?string $file, string $folder, ExtensionKind $kind, Messages $messages = new Messages()): array
	{
		$schema = ExtensionSchema::of($kind);
		$blush  = ComposerJson::blush(ComposerJson::read($folder));
		$data   = $file === null ? [] : $schema->upgrade(self::read($file, $messages), $file, $messages);

		return ComposerJson::fill($data, $folder, $blush === [] ? [] : $schema->upgrade($blush, "{$folder}/composer.json", $messages));
	}

	/**
	 * Returns a warning for a folder's manifest, or its `composer.json`'s
	 * `extra.blush` without one, when it's written to an older schema
	 * version than the current (D-908), showing it upgraded.
	 */
	public static function outdated(string $folder, ExtensionKind $kind, Messages $messages = new Messages()): ?Text
	{
		$file = self::find($folder, $kind);

		try {
			$data = $file === null ? ComposerJson::blush(ComposerJson::read($folder)) : self::read($file, $messages);
		} catch (ExtensionException) {
			return null;
		}

		return ExtensionSchema::of($kind)->outdated($data, $file ?? "{$folder}/composer.json", $messages);
	}

	/**
	 * Reads a manifest file as a map of keys to values. An empty one (`{}`)
	 * is a map with nothing in it, since a manifest may leave every key to
	 * its `composer.json` or a default (D-424).
	 *
	 * @return array<string, mixed>
	 * @throws ExtensionException When it can't be parsed or isn't a map.
	 */
	public static function read(string $file, Messages $messages = new Messages()): array
	{
		$contents = (string) file_get_contents($file);

		try {
			$data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new ExtensionException((string) $messages->t('extension.manifest_file.invalid', 'The manifest {file} is invalid: {error}', file: $file, error: $e->getMessage()), previous: $e);
		}

		if (! is_array($data) || ($data !== [] && array_is_list($data))) {
			throw new ExtensionException((string) $messages->t('extension.manifest_file.not_map', 'The manifest {file} must be a map of keys to values.', file: $file));
		}

		/** @var array<string, mixed> Not a list, so its keys are strings. */
		return $data;
	}
}
