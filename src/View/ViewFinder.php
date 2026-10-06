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
 * A view name is a path without its extension (`single-post`,
 * `layouts/base`, `parts/header`): letters, digits, `_`, and `-`, in
 * segments split by `/`. Nothing else is accepted, so names from front
 * matter can't leave the view directories. A name's file is in any view
 * engine's extension (D-502): `single.php`, `single.twig`. Within a
 * directory, the engine registered first wins.
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
	 * `nearest()` results so far, keyed by the names joined with `|`.
	 *
	 * @var array<string, array{string, string}|false>
	 */
	private array $nearest = [];

	/**
	 * @param list<string> $directories Absolute directories, highest precedence first.
	 * @param list<string> $extensions  View engines' extensions, without the dot, in precedence order.
	 */
	public function __construct(
		private readonly array $directories,
		private readonly array $extensions = ['php']
	) {}

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
	 * Returns the extensions searched, in precedence order.
	 *
	 * @return list<string>
	 */
	public function extensions(): array
	{
		return $this->extensions;
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
	 * Returns the file of whichever name is in the highest-precedence
	 * directory, with that name, or `null`. Within a directory, earlier
	 * names win. This is how a view with more than one allowed name
	 * resolves (a core directive's `directives/callout` or
	 * `directives/blush-callout`): the directory decides, not the name.
	 *
	 * @param  list<string> $names
	 * @return ?array{string, string} The name and the file.
	 * @throws ViewException When a name isn't valid.
	 */
	public function nearest(array $names): ?array
	{
		$key = implode('|', $names);

		if (! array_key_exists($key, $this->nearest)) {
			$files               = $this->allOf($names);
			$this->nearest[$key] = $files === [] ? false : array_first($files);
		}

		return $this->nearest[$key] === false ? null : $this->nearest[$key];
	}

	/**
	 * Returns every file for any of the names, by directory precedence,
	 * then name order, then extension order, winner first, with each
	 * file's name.
	 *
	 * @param  list<string> $names
	 * @return list<array{string, string}> Names and files.
	 * @throws ViewException When a name isn't valid.
	 */
	public function allOf(array $names): array
	{
		foreach ($names as $name) {
			if (! self::isValidName($name)) {
				throw new ViewException(sprintf('"%s" is not a valid view name.', $name));
			}
		}

		$files = [];

		foreach ($this->directories as $directory) {
			foreach ($names as $name) {
				foreach ($this->files($directory, $name) as $file) {
					$files[] = [$name, $file];
				}
			}
		}

		return $files;
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

		return array_merge(...array_map(fn (string $directory): array => $this->files($directory, $name), $this->directories));
	}

	/**
	 * Returns a name's files in one directory, in extension order.
	 *
	 * @return list<string>
	 */
	private function files(string $directory, string $name): array
	{
		return array_values(array_filter(
			array_map(static fn (string $extension): string => "{$directory}/{$name}.{$extension}", $this->extensions),
			is_file(...)
		));
	}
}
