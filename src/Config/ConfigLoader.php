<?php

/**
 * Config loader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Config;

use Blush\Core\Paths;
use Blush\Env\Env;

/**
 * Loads the site's `config/*.php` files. Each file runs in an isolated scope
 * with `$env` (an `Env`) and `$paths` (a `Paths`) available, and returns one
 * config object or a list of them. Files load in name order.
 */
final readonly class ConfigLoader
{
	public function __construct(
		private Env $env,
		private Paths $paths
	) {
	}

	/**
	 * Loads every config file in the directory. A missing directory yields
	 * an empty repository.
	 *
	 * @throws InvalidConfig When a file returns anything but config objects,
	 *                       or two files configure the same class.
	 */
	public function load(string $directory): ConfigRepository
	{
		$files = is_dir($directory) ? glob(rtrim($directory, '/') . '/*.php') : [];

		if ($files === false) {
			$files = [];
		}

		sort($files);

		$configs = [];

		foreach ($files as $file) {
			array_push($configs, ...$this->loadFile($file));
		}

		return new ConfigRepository(...$configs);
	}

	/**
	 * Loads one config file.
	 *
	 * @return list<Config>
	 * @throws InvalidConfig
	 */
	public function loadFile(string $file): array
	{
		$result = (static function (string $__file, Env $env, Paths $paths): mixed {
			return require $__file;
		})($file, $this->env, $this->paths);

		$configs = is_array($result) ? $result : [$result];

		if ($configs === [] || ! array_is_list($configs)) {
			throw $this->invalid($file, $result);
		}

		$list = [];

		foreach ($configs as $config) {
			$list[] = $config instanceof Config ? $config : throw $this->invalid($file, $config);
		}

		return $list;
	}

	/**
	 * Builds an invalid-file exception.
	 */
	private function invalid(string $file, mixed $result): InvalidConfig
	{
		return new InvalidConfig(sprintf(
			'Config file "%s" must return a %s object or a list of them; %s given.',
			$this->paths->relative($file),
			Config::class,
			get_debug_type($result)
		));
	}
}
