<?php

/**
 * Local extension autoloader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Closure;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeSource;

/**
 * A PSR-4 autoloader for local extensions and themes (Composer packages
 * are autoloaded by Composer). Each prefix maps to a directory inside its extension, and a
 * class file is only loaded from inside that directory.
 */
final class LocalAutoloader
{
	/**
	 * Namespace prefix => base directories, longest prefix first.
	 *
	 * @var array<string, list<string>>
	 */
	private array $prefixes = [];

	/**
	 * The registered loader, kept so it can be unregistered.
	 *
	 * @var ?Closure(string): void
	 */
	private ?Closure $loader = null;

	/**
	 * Adds the PSR-4 maps of every local extension.
	 */
	public function addExtensions(Extensions $extensions): void
	{
		foreach ($extensions->all() as $manifest) {
			if ($manifest->source === ExtensionSource::Local) {
				$this->addMap($manifest->path, $manifest->autoload);
			}
		}
	}

	/**
	 * Adds the PSR-4 maps of a theme chain's site and local themes
	 * (Composer autoloads its own themes).
	 */
	public function addThemes(ThemeChain $chain): void
	{
		foreach ($chain as $theme) {
			if ($theme->source === ThemeSource::Local || $theme->source === ThemeSource::Site) {
				$this->addMap($theme->path, $theme->autoload);
			}
		}
	}

	/**
	 * Adds a PSR-4 map whose folders are relative to a base path.
	 *
	 * @param array<string, string> $map
	 */
	private function addMap(string $base, array $map): void
	{
		foreach ($map as $prefix => $directory) {
			$this->prefixes[$prefix][] = rtrim($base, '/') . '/' . trim($directory, '/');
		}

		uksort($this->prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
	}

	/**
	 * Registers the autoloader with PHP. Does nothing when there's nothing
	 * to load.
	 */
	public function register(): void
	{
		if ($this->loader !== null || $this->prefixes === []) {
			return;
		}

		$this->loader = $this->load(...);

		spl_autoload_register($this->loader);
	}

	/**
	 * Unregisters the autoloader.
	 */
	public function unregister(): void
	{
		if ($this->loader !== null) {
			spl_autoload_unregister($this->loader);
			$this->loader = null;
		}
	}

	/**
	 * Loads a class if it belongs to a registered prefix.
	 */
	public function load(string $class): void
	{
		foreach ($this->prefixes as $prefix => $directories) {
			if (! str_starts_with($class, $prefix)) {
				continue;
			}

			$relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

			// A class name can't contain `..`, but guard anyway so a
			// crafted name can never load a file outside its directory.
			if (str_contains($relative, '..')) {
				return;
			}

			foreach ($directories as $directory) {
				$file = "{$directory}/{$relative}";

				if (is_file($file)) {
					(static function (string $__file): void {
						require $__file;
					})($file);

					return;
				}
			}
		}
	}
}
