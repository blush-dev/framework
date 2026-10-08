<?php

/**
 * Storage config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Env\Env;

/**
 * Where the site keeps its data (D-486), from `config/storage.php`. When
 * a site has none, `fromEnv()` reads `STORAGE_DRIVER`:
 *
 *     return new StorageConfig(driver: 'filesystem', areas: ['sessions' => 'filesystem']);
 *
 * - `driver` is the storage every area uses. `filesystem`, files as a
 *   flat-file site keeps them, is the default and, for now, the only one;
 *   database drivers are planned.
 * - `areas` picks another driver per `StorageArea` (`content`, `data`,
 *   `accounts`, `sessions`, `jobs`).
 *
 * Media files aren't an area: they stay files whatever the driver.
 */
final readonly class StorageConfig implements Config
{
	/**
	 * The default driver.
	 */
	public const string FILESYSTEM = 'filesystem';

	/**
	 * @param  array<string, string> $areas Drivers by area.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public string $driver = self::FILESYSTEM,
		public array $areas = []
	) {
		foreach ([$driver, ...array_values($areas)] as $name) {
			if (preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $name) !== 1) {
				throw new InvalidConfig(sprintf('StorageConfig driver names must be lowercase letters, digits, "_", ".", and "-"; "%s" given.', $name));
			}
		}

		foreach (array_keys($areas) as $area) {
			if (StorageArea::tryFrom($area) === null) {
				throw new InvalidConfig(sprintf(
					'StorageConfig "areas" keys must be %s; "%s" given.',
					implode(', ', array_map(static fn (StorageArea $case): string => $case->value, StorageArea::cases())),
					$area
				));
			}
		}
	}

	/**
	 * Builds the config from `STORAGE_DRIVER`. An empty value means the
	 * default.
	 *
	 * @throws InvalidConfig
	 */
	public static function fromEnv(Env $env): self
	{
		return new self($env->get('STORAGE_DRIVER') ?: self::FILESYSTEM);
	}

	/**
	 * Returns the driver an area uses.
	 */
	public function driverFor(StorageArea $area): string
	{
		return $this->areas[$area->value] ?? $this->driver;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['driver', 'areas']);

		$areas = $data['areas'] ?? [];

		if (! is_array($areas) || array_any($areas, static fn (mixed $driver, mixed $area): bool => ! is_string($driver) || ! is_string($area))) {
			throw new InvalidConfig('StorageConfig "areas" must map area names to driver names.');
		}

		/** @var array<string, string> $areas */
		return new static(
			driver: $values->string('driver', self::FILESYSTEM),
			areas: $areas
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'driver' => $this->driver,
			'areas'  => $this->areas
		];
	}
}
