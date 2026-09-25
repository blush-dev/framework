<?php

/**
 * Config repository.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Config;

/**
 * The loaded config objects, one per class. Services rarely need this: every
 * config object is also bound in the container under its own class, so a
 * service type-hints `AppConfig` directly.
 */
final readonly class ConfigRepository
{
	/**
	 * Config objects keyed by class.
	 *
	 * @var array<class-string<Config>, Config>
	 */
	private array $configs;

	/**
	 * @throws InvalidConfig When two objects share a class.
	 */
	public function __construct(Config ...$configs)
	{
		$keyed = [];

		foreach ($configs as $config) {
			if (isset($keyed[$config::class])) {
				throw new InvalidConfig(sprintf('%s is configured more than once.', $config::class));
			}

			$keyed[$config::class] = $config;
		}

		$this->configs = $keyed;
	}

	/**
	 * Whether a config of the class is loaded.
	 *
	 * @param class-string<Config> $class
	 */
	public function has(string $class): bool
	{
		return isset($this->configs[$class]);
	}

	/**
	 * Returns the config of the class, or `null` when none is loaded.
	 *
	 * @template T of Config
	 * @param    class-string<T> $class
	 * @return   ?T
	 */
	public function find(string $class): ?Config
	{
		$config = $this->configs[$class] ?? null;

		return $config instanceof $class ? $config : null;
	}

	/**
	 * Returns the config of the class.
	 *
	 * @template T of Config
	 * @param    class-string<T> $class
	 * @return   T
	 * @throws   InvalidConfig When none is loaded.
	 */
	public function get(string $class): Config
	{
		return $this->find($class) ?? throw new InvalidConfig(sprintf('%s is not configured.', $class));
	}

	/**
	 * Returns a copy with the given configs added, replacing any of the
	 * same class.
	 */
	public function with(Config ...$configs): self
	{
		$merged = $this->configs;

		foreach ($configs as $config) {
			$merged[$config::class] = $config;
		}

		return new self(...array_values($merged));
	}

	/**
	 * Returns a copy with defaults added for any class not yet loaded.
	 */
	public function withDefaults(Config ...$defaults): self
	{
		$merged = [];

		foreach ($defaults as $default) {
			$merged[$default::class] = $default;
		}

		return new self(...array_values([...$merged, ...$this->configs]));
	}

	/**
	 * Returns every config object.
	 *
	 * @return list<Config>
	 */
	public function all(): array
	{
		return array_values($this->configs);
	}
}
