<?php

/**
 * Composer installed.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * The packages Composer installed, for a `require` or `conflict` naming
 * a library rather than an extension (D-438): read from
 * `vendor/composer/installed.php`, the file Composer's
 * `InstalledVersions` reads (a PHP array, so opcache keeps it), only
 * when one is asked about, and once.
 *
 * A package's versions are what `InstalledVersions::getVersionRanges()`
 * gives: its own version, its aliases, and what packages that replace or
 * provide it stand in for, joined with `||`. Without the file (no
 * `vendor/`, or Composer 1), nothing is installed.
 */
final class ComposerInstalled
{
	/**
	 * Each installed package's entry, by name, once read.
	 *
	 * @var ?array<string, array<array-key, mixed>>
	 */
	private ?array $versions = null;

	/**
	 * @param ?string $vendor The site's `vendor/` folder, or `null` for none.
	 */
	public function __construct(private readonly ?string $vendor = null)
	{}

	/**
	 * The versions a package is installed at, as a constraint (`3.0.3 ||
	 * 1.0|2.0`), or `null` when Composer didn't install it.
	 */
	public function ranges(string $name): ?string
	{
		$entry = $this->versions()[$name] ?? null;

		if ($entry === null) {
			return null;
		}

		$ranges = [];

		if (is_string($entry['pretty_version'] ?? null)) {
			$ranges[] = $entry['pretty_version'];
		}

		foreach (['aliases', 'replaced', 'provided'] as $key) {
			foreach (is_array($entry[$key] ?? null) ? $entry[$key] : [] as $range) {
				if (is_string($range)) {
					$ranges[] = $range;
				}
			}
		}

		return $ranges === [] ? null : implode(' || ', $ranges);
	}

	/**
	 * A package's own version, as Composer gives it (`v3.0.3`), or `null`
	 * when it has none (one only replaced or provided by another).
	 */
	public function version(string $name): ?string
	{
		$version = $this->versions()[$name]['pretty_version'] ?? null;

		return is_string($version) ? $version : null;
	}

	/**
	 * Each installed package's entry, by name.
	 *
	 * @return array<string, array<array-key, mixed>>
	 */
	private function versions(): array
	{
		if ($this->versions !== null) {
			return $this->versions;
		}

		$file      = $this->vendor === null ? null : "{$this->vendor}/composer/installed.php";
		$installed = $file !== null && is_file($file) ? (static fn (string $file): mixed => require $file)($file) : null;
		$versions  = is_array($installed) && is_array($installed['versions'] ?? null) ? $installed['versions'] : [];

		return $this->versions = array_filter($versions, static fn (mixed $entry, mixed $name): bool => is_string($name) && is_array($entry), ARRAY_FILTER_USE_BOTH);
	}
}
