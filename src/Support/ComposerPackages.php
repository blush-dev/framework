<?php

/**
 * Composer packages.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

use JsonException;

/**
 * Reads the packages Composer installed, from
 * `vendor/composer/installed.json`, for discovering extensions by
 * package type (`blush-plugin`, `blush-theme`, `blush-icons`; D-378).
 */
final readonly class ComposerPackages
{
	public function __construct(private string $vendorPath)
	{}

	/**
	 * Returns the installed packages of a type, each with its absolute
	 * install path as `path`.
	 *
	 * @return list<array<array-key, mixed>>
	 * @throws FilesystemException When `installed.json` can't be read.
	 */
	public function ofType(string $type): array
	{
		$packages = [];

		foreach ($this->all() as $package) {
			if (($package['type'] ?? null) === $type) {
				$packages[] = [...$package, 'path' => $this->path($package)];
			}
		}

		return $packages;
	}

	/**
	 * Returns every installed package entry.
	 *
	 * @return list<array<array-key, mixed>>
	 * @throws FilesystemException
	 */
	private function all(): array
	{
		$file = "{$this->vendorPath}/composer/installed.json";

		if (! is_file($file)) {
			return [];
		}

		try {
			$installed = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new FilesystemException(sprintf('Unable to read "%s": %s', $file, $e->getMessage()), previous: $e);
		}

		// Composer 2 wraps the list in `packages`; Composer 1 did not.
		$packages = is_array($installed) && isset($installed['packages']) ? $installed['packages'] : $installed;

		return is_array($packages) ? array_values(array_filter($packages, is_array(...))) : [];
	}

	/**
	 * Returns a package's absolute install path.
	 *
	 * @param array<array-key, mixed> $package
	 */
	private function path(array $package): string
	{
		$name        = is_string($package['name'] ?? null) ? $package['name'] : '';
		$installPath = is_string($package['install-path'] ?? null) ? $package['install-path'] : '../' . $name;

		return realpath("{$this->vendorPath}/composer/{$installPath}")
			?: new Filesystem()->normalize("{$this->vendorPath}/composer/{$installPath}");
	}
}
