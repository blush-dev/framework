<?php

/**
 * Menu loader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu;

use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;

/**
 * Reads the site's menus: one data file per menu in `user/data/menus/`
 * (D-199), JSON (D-631), named for the menu. A file holds the menu's
 * `label` (optional) and its `items`, or is the list of items itself:
 *
 * ```json
 * {
 *     "label": "Primary",
 *     "items": [
 *         {"entry": "page/about"},
 *         {"route": "feed", "label": "Feed"}
 *     ]
 * }
 * ```
 *
 * A `$schema` key, for editors (D-207), is allowed. The files are read
 * once. A file with the wrong shape loads as an empty menu, with its
 * problems kept.
 */
final class MenuLoader
{
	/**
	 * The menus' folder, under the data folder.
	 */
	public const string FOLDER = 'menus';

	/**
	 * What a menu's name looks like.
	 */
	public const string NAME = '/^[a-z0-9][a-z0-9_-]*$/';

	/**
	 * The menu files, by name, once read.
	 *
	 * @var ?array<string, MenuFile>
	 */
	private ?array $files = null;

	public function __construct(
		private readonly Paths $paths,
		private readonly DataLoader $loader
	) {}

	/**
	 * Returns every menu, by name.
	 *
	 * @return array<string, MenuFile>
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
	 * Returns a menu, or `null` when the site has none by that name.
	 *
	 * @throws InvalidData
	 */
	public function get(string $name): ?MenuFile
	{
		return $this->all()[$name] ?? null;
	}

	/**
	 * Returns the menus' folder.
	 */
	public function directory(): string
	{
		return "{$this->paths->data}/" . self::FOLDER;
	}

	/**
	 * Builds a menu from its parsed file.
	 *
	 * @param array<array-key, mixed> $data
	 */
	private static function file(string $name, string $path, array $data): MenuFile
	{
		$problems = [];

		if (preg_match(self::NAME, $name) !== 1) {
			$problems[] = 'Its name isn\'t valid: use lowercase letters, digits, hyphens, and underscores.';
		}

		if (array_is_list($data)) {
			return new MenuFile($name, $path, null, $data, $problems);
		}

		foreach (array_keys(array_diff_key($data, ['label' => true, 'items' => true, '$schema' => true])) as $key) {
			$problems[] = sprintf('"%s" isn\'t a menu key; a menu has "label" and "items".', $key);
		}

		$items = $data['items'] ?? [];

		if (! is_array($items) || ! array_is_list($items)) {
			$problems[] = '"items" must be a list.';
			$items      = [];
		}

		return new MenuFile($name, $path, $data['label'] ?? null, $items, $problems);
	}
}
