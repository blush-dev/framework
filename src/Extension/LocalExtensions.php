<?php

/**
 * Local extensions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Blush\Core\Paths;

/**
 * Finds the local extensions of every kind (D-418): folders two levels
 * deep in `extensions/`, at their names (`extensions/acme/hello`), as
 * Composer keeps packages in `vendor/`. A folder's kind is the manifest
 * it holds (`plugin.*`, `theme.*`, or `icons.*`), or its `composer.json`'s
 * `type` (`blush-plugin`, `blush-theme`, or `blush-icons`; D-432), either
 * or both. One claiming more than one kind is broken, for each kind it
 * claims, and one claiming none is skipped. Hidden folders (an install in
 * progress) are skipped too.
 */
final readonly class LocalExtensions
{
	/**
	 * @param string $folder Where the extensions are, `extensions/`.
	 * @param string $root   The site's root, which messages give folders from.
	 */
	public function __construct(
		private string $folder,
		private string $root = ''
	) {}

	/**
	 * Builds the finder for a site.
	 */
	public static function forPaths(Paths $paths): self
	{
		return new self($paths->extensions, $paths->root);
	}

	/**
	 * Returns the folder an extension with a name lives in.
	 */
	public static function path(Paths $paths, string $name): string
	{
		return "{$paths->extensions}/{$name}";
	}

	/**
	 * Whether a path is an extension's folder in `extensions/`.
	 */
	public static function contains(Paths $paths, string $path): bool
	{
		return dirname($path, 2) === $paths->extensions;
	}

	/**
	 * Returns the name a folder in `extensions/`, given from the site's
	 * root (`extensions/acme/hello`, as broken extensions are keyed),
	 * gives, or `null` when it isn't one.
	 */
	public static function nameAt(Paths $paths, string $where): ?string
	{
		$prefix = $paths->relative($paths->extensions) . '/';

		if (! str_starts_with($where, $prefix)) {
			return null;
		}

		$name = substr($where, strlen($prefix));

		return substr_count($name, '/') === 1 && ! str_starts_with($name, '.') && ! str_contains($name, '/.') ? $name : null;
	}

	/**
	 * Removes an extension's vendor folder once it's empty, as after
	 * deleting its last extension.
	 */
	public static function prune(Paths $paths, string $path): void
	{
		$vendor = dirname($path);

		if (dirname($vendor) === $paths->extensions && is_dir($vendor) && (scandir($vendor) ?: []) === ['.', '..']) {
			@rmdir($vendor);
		}
	}

	/**
	 * Returns the kinds a folder claims, each with its winning manifest
	 * file, or `null` when only its `composer.json`'s `type` claims it
	 * (D-432), in the kinds' order.
	 *
	 * @return array<string, ?string>
	 */
	public static function claims(string $folder): array
	{
		$type   = ComposerJson::kind($folder);
		$claims = [];

		foreach (ExtensionKind::cases() as $kind) {
			$file = ManifestFile::find($folder, $kind);

			if ($file !== null || $kind === $type) {
				$claims[$kind->value] = $file;
			}
		}

		return $claims;
	}

	/**
	 * Says what makes a folder claim each of its kinds, for messages:
	 * `plugin.json and a composer.json of type "blush-theme"`.
	 *
	 * @param array<string, ?string> $claims
	 */
	public static function describe(array $claims): string
	{
		$described = [];

		foreach ($claims as $kind => $file) {
			$described[] = $file === null
				? sprintf('a composer.json of type "%s"', ExtensionKind::from($kind)->packageType())
				: basename($file);
		}

		return implode(' and ', $described);
	}

	/**
	 * Returns the local extensions of a kind, in name order.
	 *
	 * @return list<LocalExtension>
	 */
	public function of(ExtensionKind $kind): array
	{
		$found = [];

		foreach ($this->folders() as $path) {
			$claims = self::claims($path);

			if (! array_key_exists($kind->value, $claims)) {
				continue;
			}

			$name    = substr($path, strlen($this->folder) + 1);
			$where   = $this->root !== '' && str_starts_with($path, $this->root . '/') ? substr($path, strlen($this->root) + 1) : $path;
			$problem = count($claims) > 1
				? sprintf('It holds %s, but an extension is one kind.', self::describe($claims))
				: null;

			$found[] = new LocalExtension($kind, $name, $path, $where, $claims[$kind->value], $problem);
		}

		return $found;
	}

	/**
	 * Returns every `{vendor}/{name}` folder, sorted.
	 *
	 * @return list<string>
	 */
	private function folders(): array
	{
		$folders = glob($this->folder . '/*/*', GLOB_ONLYDIR) ?: [];

		sort($folders);

		return $folders;
	}
}
