<?php

/**
 * Legacy type folder.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

/**
 * Turns a data type that names its folder (`folder`, or 1.x's `path`)
 * into one kept in `_` and its name (D-683). The migration tool writes it
 * and moves the files (`content:type-folders`, Site Health); until it
 * has, the loader reads a data type in this form through it, so the
 * admin keeps working, though its files aren't found until they move.
 *
 * - **The folder pattern** after the folder (`_posts/{year}`) becomes
 *   the type's `folders`, unless it gives its own.
 * - **Addresses stay:** a collection whose URL prefix came from its
 *   folder (`recipes`, or `_posts` for `posts`) is given that prefix, and
 *   a tree the path its pages were served under, unless it already has
 *   one or it's the type's name. A file changing a type from code
 *   (D-349) is given neither, since its `urls` would replace the code's
 *   whole; the code says its prefix.
 */
final class LegacyFolder
{
	/**
	 * Returns whether a definition names its folder.
	 *
	 * @param array<array-key, mixed> $definition
	 */
	public static function is(array $definition): bool
	{
		return array_key_exists('folder', $definition) || array_key_exists('path', $definition);
	}

	/**
	 * Returns the folder a definition names, without its folder pattern,
	 * or `''` for none.
	 *
	 * @param array<array-key, mixed> $definition
	 */
	public static function folderOf(array $definition): string
	{
		$folder = $definition['folder'] ?? $definition['path'] ?? '';

		return FolderPattern::split(trim(is_string($folder) ? str_replace('\\', '/', $folder) : '', '/'))[0];
	}

	/**
	 * Returns a type's definition without its folder. `$kind` is the
	 * type's kind; `$addresses` is whether the definition may be given a
	 * prefix (not over a code type, and only with URLs allowed).
	 *
	 * @param  array<array-key, mixed> $definition
	 * @return array<array-key, mixed>
	 */
	public static function convert(string $name, array $definition, TypeKind $kind, bool $addresses = true): array
	{
		$folder  = $definition['folder'] ?? $definition['path'] ?? '';
		$pattern = FolderPattern::split(trim(is_string($folder) ? str_replace('\\', '/', $folder) : '', '/'))[1];
		$public  = self::publicPath(self::folderOf($definition));

		unset($definition['folder'], $definition['path']);

		if ($pattern !== null && $kind !== TypeKind::Tree) {
			$definition['folders'] ??= $pattern;
		}

		if (! $addresses || $public === '' || $public === $name) {
			return $definition;
		}

		if ($kind === TypeKind::Tree) {
			$definition['prefix'] ??= $public;
		} elseif ($kind === TypeKind::Collection) {
			$key  = array_key_exists('routing', $definition) && ! array_key_exists('urls', $definition) ? 'routing' : 'urls';
			$urls = $definition[$key] ?? [];

			if (is_array($urls) && ! isset($urls['prefix'])) {
				$definition[$key] = [...$urls, 'prefix' => $public];
			}
		}

		return $definition;
	}

	/**
	 * Returns a folder path without the `_` that starts its folder names,
	 * as the path its addresses were made from: `_posts` is `posts`.
	 */
	private static function publicPath(string $folder): string
	{
		return implode('/', array_map(static fn (string $segment): string => ltrim($segment, '_'), explode('/', $folder)));
	}
}
