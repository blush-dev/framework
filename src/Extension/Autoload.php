<?php

/**
 * Autoload.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * A plugin's or theme's `autoload`, in Composer's shape (D-418): `psr-4`
 * maps namespace prefixes to folders, and `files` lists files loaded once
 * when the extension runs. Every path is relative to the extension, and
 * must stay inside it.
 *
 *     "autoload": {
 *         "psr-4": { "Acme\\Hello\\": "src/" },
 *         "files": ["src/helpers.php"]
 *     }
 */
final readonly class Autoload
{
	/**
	 * @param array<string, string> $psr4  Namespace prefixes (each ending in `\`) and their folders.
	 * @param list<string>          $files Files to load.
	 */
	public function __construct(
		public array $psr4 = [],
		public array $files = []
	) {}

	/**
	 * Reads an `autoload` value; `null` is none.
	 *
	 * @throws ExtensionException When it isn't Composer's shape, or a path leaves the extension.
	 */
	public static function fromArray(mixed $data): self
	{
		if ($data === null) {
			return new self();
		}

		if (! is_array($data) || ($data !== [] && array_is_list($data))) {
			throw new ExtensionException('"autoload" must be an object, such as {"psr-4": {"Acme\\\\Hello\\\\": "src/"}, "files": ["src/helpers.php"]}.');
		}

		$psr4  = $data['psr-4'] ?? [];
		$files = $data['files'] ?? [];

		if (! is_array($psr4) || ($psr4 !== [] && array_is_list($psr4))) {
			throw new ExtensionException('"autoload.psr-4" must map namespace prefixes to folders.');
		}

		if (! is_array($files) || ! array_is_list($files)) {
			throw new ExtensionException('"autoload.files" must be a list of files.');
		}

		$map = [];

		foreach ($psr4 as $prefix => $folder) {
			if (! is_string($prefix) || ! str_ends_with($prefix, '\\')) {
				throw new ExtensionException(sprintf('"autoload.psr-4" prefix "%s" must be a namespace ending in a backslash, as Composer\'s are.', $prefix));
			}

			if (! is_string($folder) || ! self::isInside($folder)) {
				throw new ExtensionException('"autoload.psr-4" must map namespace prefixes to folders inside the extension.');
			}

			$map[$prefix] = trim($folder, '/') === '' ? '' : trim($folder, '/') . '/';
		}

		$list = [];

		foreach ($files as $file) {
			if (! is_string($file) || trim($file, '/') === '' || ! self::isInside($file)) {
				throw new ExtensionException('"autoload.files" must list files inside the extension.');
			}

			$list[] = trim($file, '/');
		}

		return new self($map, $list);
	}

	/**
	 * Returns the autoload in Composer's shape, leaving out what's empty.
	 *
	 * @return array{psr-4?: array<string, string>, files?: list<string>}
	 */
	public function toArray(): array
	{
		return [
			...($this->psr4 === [] ? [] : ['psr-4' => $this->psr4]),
			...($this->files === [] ? [] : ['files' => $this->files])
		];
	}

	/**
	 * Whether a relative path stays inside the extension.
	 */
	private static function isInside(string $path): bool
	{
		return ! str_starts_with($path, '/')
			&& ! str_contains($path, '\\')
			&& ! in_array('..', explode('/', $path), true)
			&& preg_match('#^[A-Za-z]:#', $path) !== 1;
	}
}
