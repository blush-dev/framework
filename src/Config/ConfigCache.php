<?php

/**
 * Config cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Config;

use Blush\Support\PhpArrayFile;

/**
 * Compiles the merged config to a PHP file (D-017, D-044), so production
 * requests skip running the config files and reading `.env`. Env values are
 * baked in at compile time; recompile after changing either.
 */
final readonly class ConfigCache
{
	public function __construct(private PhpArrayFile $file)
	{
	}

	/**
	 * Returns the compiled repository, or `null` when nothing is compiled.
	 *
	 * @throws InvalidConfig When the file holds an unknown or invalid config.
	 */
	public function read(): ?ConfigRepository
	{
		$data = $this->file->read();

		if ($data === null) {
			return null;
		}

		$configs = [];

		foreach ($data as $class => $values) {
			if (! is_string($class) || ! is_subclass_of($class, Config::class) || ! is_array($values)) {
				throw new InvalidConfig(sprintf(
					'The compiled config "%s" is invalid; clear it and compile again.',
					$this->file->path
				));
			}

			$configs[] = $class::fromArray($values);
		}

		return new ConfigRepository(...$configs);
	}

	/**
	 * Compiles the repository to the file.
	 */
	public function write(ConfigRepository $repository): void
	{
		$data = [];

		foreach ($repository->all() as $config) {
			$data[$config::class] = $config->toArray();
		}

		$this->file->write($data);
	}

	/**
	 * Deletes the compiled file.
	 */
	public function clear(): void
	{
		$this->file->delete();
	}
}
