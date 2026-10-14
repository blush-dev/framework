<?php

/**
 * Extension catalogs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Blush\Translation\Catalog;
use Blush\Translation\Messages;

/**
 * Reads a plugin's or theme's `catalogs` (D-831): which of its folders'
 * scripts (`.ts`, `.js`, and `.vue` files) write to which named catalog,
 * each folder relative to the extension's own.
 *
 *     "catalogs": {"site": ["resources/site", "resources/player"]}
 *
 * Without it, each folder under `resources/` is a catalog of its name
 * (`resources/admin/**` writes to `admin`). A folder may be under two
 * catalogs, and writes to both.
 */
final readonly class ExtensionCatalogs
{
	/**
	 * Where scripts are kept unless `catalogs` says otherwise.
	 */
	public const string RESOURCES = 'resources';

	/**
	 * Reads a manifest's `catalogs`, which may be left out (`[]`, the
	 * defaults).
	 *
	 * @return array<string, list<string>>
	 * @throws ExtensionException When it isn't a map of catalog names to lists of folders.
	 */
	public static function fromManifest(mixed $value, Messages $messages = new Messages()): array
	{
		$value ??= [];
		$error   = static fn (): ExtensionException => new ExtensionException((string) $messages->t('extension.extension_catalogs.invalid', '"catalogs" must map catalog names (lowercase letters, digits, hyphens, and underscores) to lists of folders in the extension, such as \'{"site": ["resources/site"]}\'.'));

		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw $error();
		}

		$catalogs = [];

		foreach ($value as $name => $folders) {
			if (! is_string($name) || ! Catalog::isName($name) || ! is_array($folders) || ! array_is_list($folders) || $folders === []) {
				throw $error();
			}

			foreach ($folders as $folder) {
				if (! is_string($folder) || ! self::isFolder($folder)) {
					throw $error();
				}
			}

			/** @var list<string> $folders */
			$catalogs[$name] = array_values(array_unique(array_map(static fn (string $folder): string => trim($folder, '/'), $folders)));
		}

		return $catalogs;
	}

	/**
	 * Returns the catalogs an extension's scripts write to: its
	 * manifest's, else a catalog for each folder under `resources/`.
	 *
	 * @param  array<string, list<string>> $declared
	 * @return array<string, list<string>>
	 */
	public static function resolve(string $root, array $declared): array
	{
		if ($declared !== []) {
			return $declared;
		}

		$catalogs = [];

		foreach (glob("{$root}/" . self::RESOURCES . '/*', GLOB_ONLYDIR) ?: [] as $folder) {
			$name = basename($folder);

			if (Catalog::isName($name)) {
				$catalogs[$name] = [self::RESOURCES . "/{$name}"];
			}
		}

		return $catalogs;
	}

	/**
	 * Returns whether a path is a folder inside an extension: relative,
	 * with no `..` or empty parts.
	 */
	private static function isFolder(string $folder): bool
	{
		$folder = trim($folder, '/');

		return $folder !== '' && ! str_starts_with($folder, '/') && ! array_any(explode('/', $folder), static fn (string $part): bool => $part === '' || $part === '.' || $part === '..');
	}
}
