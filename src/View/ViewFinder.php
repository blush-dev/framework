<?php

/**
 * View finder.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

/**
 * Finds view files by name through an ordered list of directories: the
 * site's overrides, then the active theme, its ancestors, and the
 * framework default theme (D-024). The first directory with a file wins.
 *
 * A view name is a path without the `.php` extension (`single-post`,
 * `layouts/base`, `parts/header`): letters, digits, `_`, and `-`, in
 * segments split by `/`. Nothing else is accepted, so names from front
 * matter can't leave the view directories.
 */
final class ViewFinder
{
	/**
	 * Paths found so far, keyed by name (`false` when there is none).
	 *
	 * @var array<string, string|false>
	 */
	private array $found = [];

	/**
	 * @param list<string> $directories Absolute directories, highest precedence first.
	 */
	public function __construct(private readonly array $directories)
	{}

	/**
	 * Returns the directories searched, highest precedence first.
	 *
	 * @return list<string>
	 */
	public function directories(): array
	{
		return $this->directories;
	}

	/**
	 * Returns whether a string is a valid view name.
	 */
	public static function isValidName(string $name): bool
	{
		return preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$#', $name) === 1;
	}

	/**
	 * Returns the winning file for a view name, or `null`.
	 *
	 * @throws ViewException When the name isn't valid.
	 */
	public function find(string $name): ?string
	{
		if (! array_key_exists($name, $this->found)) {
			$this->found[$name] = array_first($this->all($name)) ?? false;
		}

		return $this->found[$name] === false ? null : $this->found[$name];
	}

	/**
	 * Returns the first name in a list that has a view, with its file, or
	 * `null`. Invalid names are skipped, since hierarchies include names
	 * from front matter.
	 *
	 * @param  list<string> $names
	 * @return ?array{string, string} The name and the file.
	 */
	public function first(array $names): ?array
	{
		foreach ($names as $name) {
			if (self::isValidName($name) && ($file = $this->find($name)) !== null) {
				return [$name, $file];
			}
		}

		return null;
	}

	/**
	 * Returns every file for a view name, winner first, for explaining
	 * what shadows what (`theme:why`).
	 *
	 * @return list<string>
	 * @throws ViewException When the name isn't valid.
	 */
	public function all(string $name): array
	{
		if (! self::isValidName($name)) {
			throw new ViewException(sprintf('"%s" is not a valid view name.', $name));
		}

		return array_values(array_filter(
			array_map(static fn (string $directory): string => "{$directory}/{$name}.php", $this->directories),
			is_file(...)
		));
	}
}
