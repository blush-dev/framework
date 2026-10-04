<?php

/**
 * Local autoloader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Closure;
use Blush\Plugin\Plugins;
use Blush\Plugin\PluginSource;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeSource;

/**
 * The autoloader for local plugins and themes (Composer packages are
 * autoloaded by Composer), from each one's `autoload` (D-418): `psr-4`
 * prefixes, each mapped to a directory inside its extension, with a class
 * file only loaded from inside that directory; and `files`, loaded once
 * when it's registered.
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
	 * Files to load when registered.
	 *
	 * @var list<string>
	 */
	private array $files = [];

	/**
	 * The registered loader, kept so it can be unregistered.
	 *
	 * @var ?Closure(string): void
	 */
	private ?Closure $loader = null;

	/**
	 * Adds the autoloads of every local plugin that runs.
	 */
	public function addPlugins(Plugins $plugins): void
	{
		foreach ($plugins->all() as $manifest) {
			if ($manifest->source === PluginSource::Local) {
				$this->addMap($manifest->path, $manifest->autoload);
			}
		}
	}

	/**
	 * Adds the autoloads of a theme chain's local themes (Composer
	 * autoloads its own themes).
	 */
	public function addThemes(ThemeChain $chain): void
	{
		foreach ($chain as $theme) {
			if ($theme->source === ThemeSource::Local) {
				$this->addMap($theme->path, $theme->autoload);
			}
		}
	}

	/**
	 * Adds an autoload whose paths are relative to a base path.
	 */
	private function addMap(string $base, Autoload $autoload): void
	{
		$base = rtrim($base, '/');

		foreach ($autoload->psr4 as $prefix => $directory) {
			$this->prefixes[$prefix][] = rtrim("{$base}/" . trim($directory, '/'), '/');
		}

		foreach ($autoload->files as $file) {
			$this->files[] = "{$base}/{$file}";
		}

		uksort($this->prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
	}

	/**
	 * Registers the autoloader with PHP, then loads the `files` (once per
	 * process, so a file is never loaded twice). Does nothing when there's
	 * nothing to load.
	 */
	public function register(): void
	{
		if ($this->loader !== null || ($this->prefixes === [] && $this->files === [])) {
			return;
		}

		$this->loader = $this->load(...);

		spl_autoload_register($this->loader);

		foreach ($this->files as $file) {
			if (is_file($file)) {
				(static function (string $__file): void {
					require_once $__file;
				})($file);
			}
		}
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
