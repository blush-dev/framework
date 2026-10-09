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
 * - `driver` is the storage every area uses: `filesystem`, files as a
 *   flat-file site keeps them, the default, or `sqlite`, one database
 *   file (D-662; being built, content reads first).
 * - `areas` picks another driver per `StorageArea` (`content`, `data`,
 *   `accounts`, `sessions`, `jobs`).
 * - `sqlite` is the SQLite driver's database file, from the site's root
 *   (or absolute): `user/site.sqlite` by default.
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
	 * The SQLite driver's name.
	 */
	public const string SQLITE = 'sqlite';

	/**
	 * The SQLite database's default file, from the site's root.
	 */
	public const string SQLITE_FILE = 'user/site.sqlite';

	/**
	 * @param  array<string, string> $areas  Drivers by area.
	 * @param  string                $sqlite The SQLite database file, from the site's root or absolute.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public string $driver = self::FILESYSTEM,
		public array $areas = [],
		public string $sqlite = self::SQLITE_FILE
	) {
		if (trim($sqlite) === '') {
			throw new InvalidConfig('StorageConfig "sqlite" must name a database file.');
		}

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
	 * Returns whether any area uses a driver.
	 */
	public function uses(string $driver): bool
	{
		return array_any(StorageArea::cases(), fn (StorageArea $area): bool => $this->driverFor($area) === $driver);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['driver', 'areas', 'sqlite']);

		$areas = $data['areas'] ?? [];

		if (! is_array($areas) || array_any($areas, static fn (mixed $driver, mixed $area): bool => ! is_string($driver) || ! is_string($area))) {
			throw new InvalidConfig('StorageConfig "areas" must map area names to driver names.');
		}

		/** @var array<string, string> $areas */
		return new static(
			driver: $values->string('driver', self::FILESYSTEM),
			areas: $areas,
			sqlite: $values->string('sqlite', self::SQLITE_FILE)
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
			'areas'  => $this->areas,
			'sqlite' => $this->sqlite
		];
	}
}
