<?php

/**
 * Data loader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use DirectoryIterator;
use JsonException;

/**
 * Reads data files by name, without their extension. Data is JSON, and
 * only JSON (D-631): `load($paths->data, 'menus')` reads `menus.json`.
 * The one YAML Blush reads is front matter.
 *
 * A top-level `$schema` key, which points an editor at a file's JSON
 * Schema, is never part of the data (D-491).
 *
 * Names may contain `/` to reach into subdirectories (`types/post`), but
 * never `..`.
 */
final class DataLoader
{
	/**
	 * The data file extension.
	 */
	public const string EXTENSION = 'json';

	/**
	 * The key that points an editor at a file's JSON Schema.
	 */
	public const string SCHEMA = '$schema';

	/**
	 * Returns whether `$path` is a data file by its extension.
	 */
	public static function isDataFile(string $path): bool
	{
		return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === self::EXTENSION;
	}

	/**
	 * Returns the path of the file for a name, or `null` when there is
	 * none.
	 *
	 * @throws InvalidData When the name is unsafe.
	 */
	public function find(string $directory, string $name): ?string
	{
		$path = $this->path($directory, $name);

		return is_file($path) ? $path : null;
	}

	/**
	 * Returns the path a name's file has, or would have.
	 *
	 * @throws InvalidData When the name is unsafe.
	 */
	public function path(string $directory, string $name): string
	{
		if ($name === '' || str_starts_with($name, '/') || in_array('..', explode('/', $name), true)) {
			throw new InvalidData(sprintf('Invalid data file name "%s".', $name));
		}

		return rtrim($directory, '/') . '/' . $name . '.' . self::EXTENSION;
	}

	/**
	 * Parses the file for a name. Returns `null` when no file exists, so
	 * callers can tell "missing" from "empty".
	 *
	 * @return ?array<array-key, mixed>
	 * @throws InvalidData
	 */
	public function load(string $directory, string $name): ?array
	{
		$path = $this->find($directory, $name);

		return $path === null ? null : $this->loadFile($path);
	}

	/**
	 * Parses every data file directly inside a directory, keyed by name.
	 * Returns an empty array for a missing directory.
	 *
	 * @return array<string, array<array-key, mixed>>
	 * @throws InvalidData
	 */
	public function loadAll(string $directory): array
	{
		if (! is_dir($directory)) {
			return [];
		}

		$names = [];

		foreach (new DirectoryIterator($directory) as $file) {
			if ($file->isFile() && ! str_starts_with($file->getFilename(), '.') && self::isDataFile($file->getFilename())) {
				$names[] = $file->getBasename('.' . $file->getExtension());
			}
		}

		sort($names);

		$data = [];

		foreach ($names as $name) {
			$data[$name] = $this->load($directory, $name) ?? [];
		}

		return $data;
	}

	/**
	 * Parses one file, leaving out a top-level `$schema` key.
	 *
	 * @return array<array-key, mixed>
	 * @throws InvalidData
	 */
	public function loadFile(string $path): array
	{
		$contents = @file_get_contents($path);

		if ($contents === false) {
			throw new InvalidData(sprintf('Unable to read data file "%s".', $path));
		}

		try {
			$data = self::parse($contents);
		} catch (InvalidData $e) {
			throw InvalidData::inFile($path, $e);
		}

		unset($data[self::SCHEMA]);

		return $data;
	}

	/**
	 * Parses JSON data. Objects become associative arrays, and an empty
	 * string is an empty array.
	 *
	 * @return array<array-key, mixed>
	 * @throws InvalidData
	 */
	public static function parse(string $contents): array
	{
		if (trim($contents) === '') {
			return [];
		}

		try {
			$data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new InvalidData(sprintf('Invalid JSON: %s', $e->getMessage()), previous: $e);
		}

		if (! is_array($data)) {
			throw new InvalidData('A data file must hold an object or an array.');
		}

		return $data;
	}
}
