<?php

/**
 * Region loader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region;

use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;

/**
 * Reads the site's regions: one data file per region in
 * `user/data/regions/` (D-201), JSON (D-631), named for the region. A
 * file holds its `items`, or is the list of items itself:
 *
 * ```json
 * {
 *     "items": [
 *         {"directive": "menu", "name": "social"},
 *         {"markdown": "Powered by **Blush**."}
 *     ]
 * }
 * ```
 *
 * A `$schema` key, for editors (D-207), is allowed. The files are read
 * once. A file with the wrong shape loads as an empty region, with its
 * problems kept.
 */
final class RegionLoader
{
	/**
	 * The regions' folder, under the data folder.
	 */
	public const string FOLDER = 'regions';

	/**
	 * The region files, by name, once read.
	 *
	 * @var ?array<string, RegionFile>
	 */
	private ?array $files = null;

	public function __construct(
		private readonly Paths $paths,
		private readonly DataLoader $loader
	) {}

	/**
	 * Returns every region, by name.
	 *
	 * @return array<string, RegionFile>
	 * @throws InvalidData When a file can't be parsed.
	 */
	public function all(): array
	{
		if ($this->files !== null) {
			return $this->files;
		}

		$directory = $this->directory();
		$files     = [];

		foreach ($this->loader->loadAll($directory) as $name => $data) {
			$files[$name] = self::file($name, $this->loader->find($directory, $name) ?? '', $data);
		}

		return $this->files = $files;
	}

	/**
	 * Returns a region, or `null` when the site has none by that name.
	 *
	 * @throws InvalidData
	 */
	public function get(string $name): ?RegionFile
	{
		return $this->all()[$name] ?? null;
	}

	/**
	 * Returns the regions' folder.
	 */
	public function directory(): string
	{
		return "{$this->paths->data}/" . self::FOLDER;
	}

	/**
	 * Builds a region from its parsed file.
	 *
	 * @param array<array-key, mixed> $data
	 */
	private static function file(string $name, string $path, array $data): RegionFile
	{
		if (array_is_list($data)) {
			return new RegionFile($name, $path, $data);
		}

		$problems = [];

		foreach (array_keys(array_diff_key($data, ['items' => true, '$schema' => true])) as $key) {
			$problems[] = sprintf('"%s" isn\'t a region key; a region has "items".', $key);
		}

		$items = $data['items'] ?? [];

		if (! is_array($items) || ! array_is_list($items)) {
			$problems[] = '"items" must be a list.';
			$items      = [];
		}

		return new RegionFile($name, $path, $items, $problems);
	}
}
