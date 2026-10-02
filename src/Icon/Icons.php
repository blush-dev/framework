<?php

/**
 * Icons.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Theme\ThemeChain;

/**
 * Finds icons' SVG files for a theme chain (D-187). For `{ns}/{name}`, the
 * first that exists wins:
 *
 * 1. The site's `resources/icons/{ns}/{name}.svg` (or, for its own `app`
 *    icons, `resources/icons/{name}.svg`).
 * 2. Each theme in the chain, the active one first: `icons/{name}.svg`
 *    for its own namespace, or `icons/{ns}/{name}.svg` to restyle another
 *    one's icon (such as `icons/blush/house.svg`).
 * 3. The folders an extension added for the namespace (`IconRegistry`).
 * 4. For `blush`, the core icons in the framework's `resources/icons`.
 */
final class Icons
{
	/**
	 * Files found so far, by chain and name (`false` when there's none).
	 *
	 * @var array<string, string|false>
	 */
	private array $found = [];

	public function __construct(
		private readonly Paths $paths,
		private readonly IconRegistry $registry
	) {}

	/**
	 * Returns an icon's SVG file for a chain, or `null`.
	 */
	public function file(IconName $name, ThemeChain $chain): ?string
	{
		$key = implode(',', $chain->names()) . " {$name}";

		if (! array_key_exists($key, $this->found)) {
			$this->found[$key] = array_find($this->candidates($name, $chain), static fn (string $file): bool => is_file($file)) ?? false;
		}

		return $this->found[$key] === false ? null : $this->found[$key];
	}

	/**
	 * Returns an icon's SVG markup for a chain, or `null`.
	 */
	public function svg(IconName $name, ThemeChain $chain): ?string
	{
		$file = $this->file($name, $chain);
		$svg  = $file === null ? false : file_get_contents($file);

		return is_string($svg) && $svg !== '' ? $svg : null;
	}

	/**
	 * Returns every icon the chain can show, sorted by name, with the file
	 * that wins for each.
	 *
	 * @return array<string, string>
	 */
	public function all(ThemeChain $chain): array
	{
		$names = [];

		foreach ($this->folders($chain) as $namespace => $folders) {
			foreach ($folders as $folder) {
				foreach (glob("{$folder}/*.svg") ?: [] as $file) {
					$name = IconName::parse($namespace . '/' . basename($file, '.svg'));

					if ($name !== null) {
						$names[(string) $name] = $name;
					}
				}
			}
		}

		ksort($names, SORT_STRING);

		$files = [];

		foreach ($names as $key => $name) {
			$files[$key] = (string) $this->file($name, $chain);
		}

		return $files;
	}

	/**
	 * Returns the files an icon may be, first wins.
	 *
	 * @return list<string>
	 */
	private function candidates(IconName $name, ThemeChain $chain): array
	{
		$icons = "{$this->paths->resources}/icons";
		$files = [$name->namespace === IconName::SITE ? "{$icons}/{$name->name}.svg" : "{$icons}/{$name->namespace}/{$name->name}.svg"];

		foreach ($chain as $theme) {
			$files[] = $theme->namespace === $name->namespace
				? "{$theme->path}/icons/{$name->name}.svg"
				: "{$theme->path}/icons/{$name->namespace}/{$name->name}.svg";
		}

		foreach ($this->registry->folders($name->namespace) as $folder) {
			$files[] = "{$folder}/{$name->name}.svg";
		}

		if ($name->isCore()) {
			$files[] = Framework::path("resources/icons/blush/{$name->name}.svg");
		}

		return $files;
	}

	/**
	 * Returns the folders whose `*.svg` files are a namespace's own icons.
	 *
	 * @return array<string, list<string>>
	 */
	private function folders(ThemeChain $chain): array
	{
		$folders = [IconName::CORE => [Framework::path('resources/icons/blush')], IconName::SITE => ["{$this->paths->resources}/icons"]];

		foreach ($chain as $theme) {
			$folders[$theme->namespace][] = "{$theme->path}/icons";
		}

		foreach ($this->registry->all() as $namespace => $added) {
			$folders[$namespace] = [...$folders[$namespace] ?? [], ...$added];
		}

		return $folders;
	}
}
