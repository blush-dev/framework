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
use Blush\Container\Container;

/**
 * Reads data files by name, without their extension, through the parser
 * registered for each extension (D-032). `load($paths->data, 'menus')`
 * reads `menus.json`, `menus.yaml`, or `menus.yml`, whichever exists
 * first in precedence order: the built-in formats in `DataFormat` order
 * (JSON wins), then any formats extensions registered. The files a winner
 * hides are reported by `shadowed()`, for `doctor` to warn about.
 *
 * Names may contain `/` to reach into subdirectories (`types/post`), but
 * never `..`. Parsers are built through the container on first use.
 */
final class DataLoader
{
	/**
	 * Parsers built so far, keyed by extension.
	 *
	 * @var array<string, DataParser>
	 */
	private array $parsers = [];

	public function __construct(
		private readonly DataParserRegistry $registry,
		private readonly Container $container
	) {}

	/**
	 * Returns the registered extensions in precedence order.
	 *
	 * @return list<string>
	 */
	public function extensions(): array
	{
		$builtIn = array_map(static fn (DataFormat $format): string => $format->value, DataFormat::cases());
		$known   = array_keys($this->registry->all());

		return array_values(array_unique([
			...array_intersect($builtIn, $known),
			...array_diff($known, $builtIn)
		]));
	}

	/**
	 * Returns whether `$path`'s extension has a parser.
	 */
	public function supports(string $path): bool
	{
		return $this->registry->isRegistered(strtolower(pathinfo($path, PATHINFO_EXTENSION)));
	}

	/**
	 * Returns the path of the winning file for a name, or `null` when there
	 * is none.
	 *
	 * @throws InvalidData When the name is unsafe.
	 */
	public function find(string $directory, string $name): ?string
	{
		return array_first($this->candidates($directory, $name));
	}

	/**
	 * Returns the files a name's winning file hides.
	 *
	 * @return list<string>
	 * @throws InvalidData When the name is unsafe.
	 */
	public function shadowed(string $directory, string $name): array
	{
		return array_slice($this->candidates($directory, $name), 1);
	}

	/**
	 * Parses the winning file for a name. Returns `null` when no file
	 * exists, so callers can tell "missing" from "empty".
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
	 * When a name exists in several formats, only the winner is read.
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
			if ($file->isFile() && ! str_starts_with($file->getFilename(), '.') && $this->supports($file->getFilename())) {
				$names[$file->getBasename('.' . $file->getExtension())] = true;
			}
		}

		$names = array_keys($names);
		sort($names);

		$data = [];

		foreach ($names as $name) {
			$data[(string) $name] = $this->load($directory, (string) $name) ?? [];
		}

		return $data;
	}

	/**
	 * Parses one file with the parser for its extension.
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
			return $this->parser(pathinfo($path, PATHINFO_EXTENSION))->parse($contents);
		} catch (InvalidData $e) {
			throw InvalidData::inFile($path, $e);
		}
	}

	/**
	 * Returns the parser for a file extension.
	 *
	 * @throws InvalidData When no parser is registered for it.
	 */
	public function parser(string $extension): DataParser
	{
		$extension = strtolower($extension);

		if (isset($this->parsers[$extension])) {
			return $this->parsers[$extension];
		}

		$class = $this->registry->get($extension);

		if ($class === null) {
			throw new InvalidData(sprintf('No data parser is registered for ".%s" files.', $extension));
		}

		return $this->parsers[$extension] = $this->container->make($class);
	}

	/**
	 * Returns the existing files for a name, in precedence order.
	 *
	 * @return list<string>
	 * @throws InvalidData When the name is unsafe.
	 */
	private function candidates(string $directory, string $name): array
	{
		if ($name === '' || str_starts_with($name, '/') || in_array('..', explode('/', $name), true)) {
			throw new InvalidData(sprintf('Invalid data file name "%s".', $name));
		}

		$base = rtrim($directory, '/') . '/' . $name;

		return array_values(array_filter(
			array_map(static fn (string $extension): string => "{$base}.{$extension}", $this->extensions()),
			is_file(...)
		));
	}
}
