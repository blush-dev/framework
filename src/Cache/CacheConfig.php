<?php

/**
 * Cache config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Core\Environment;

/**
 * The site's cache settings, from `config/cache.php`:
 *
 *     return new CacheConfig(driver: 'apcu', stores: ['pages' => 'file']);
 *
 * - `enabled` turns the caches on (pages, rendered bodies, and
 *   fragments). `null`, the default, means on everywhere but
 *   development, where templates and content change constantly.
 * - `driver` is the store every namespace uses (`file`, `php`, `apcu`,
 *   `array`, `null`, or an extension's), and `stores` picks another per
 *   namespace.
 * - `pages` turns the full-page cache (`PageCache`) on or off within
 *   that.
 * - `maxAge` is the `Cache-Control` max-age, in seconds, for pages the
 *   page cache stores. `0` (the default) lets browsers and proxies keep a
 *   page but check it on every use, which the ETag makes cheap (a 304).
 */
final readonly class CacheConfig implements Config
{
	/**
	 * @param  array<string, string> $stores Drivers by namespace.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public ?bool $enabled = null,
		public string $driver = CacheDriver::File->value,
		public array $stores = [],
		public bool $pages = true,
		public int $maxAge = 0
	) {
		foreach ([$driver, ...array_keys($stores), ...array_values($stores)] as $name) {
			if (preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $name) !== 1) {
				throw new InvalidConfig(sprintf('CacheConfig driver and namespace names must be lowercase letters, digits, "_", ".", and "-"; "%s" given.', $name));
			}
		}

		if ($maxAge < 0) {
			throw new InvalidConfig(sprintf('CacheConfig "maxAge" must be 0 or more; %d given.', $maxAge));
		}
	}

	/**
	 * Returns whether caching is on in an environment.
	 */
	public function isEnabled(Environment $environment): bool
	{
		return $this->enabled ?? ! $environment->isDevelopment();
	}

	/**
	 * Returns the driver a namespace uses.
	 */
	public function driverFor(string $namespace): string
	{
		return $this->stores[$namespace] ?? $this->driver;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['enabled', 'driver', 'stores', 'pages', 'maxAge']);

		$enabled = $data['enabled'] ?? null;
		$stores  = $data['stores'] ?? [];

		if ($enabled !== null && ! is_bool($enabled)) {
			throw new InvalidConfig('CacheConfig "enabled" must be a boolean or null.');
		}

		if (! is_array($stores) || array_any($stores, static fn (mixed $driver, mixed $namespace): bool => ! is_string($driver) || ! is_string($namespace))) {
			throw new InvalidConfig('CacheConfig "stores" must map namespace names to driver names.');
		}

		/** @var array<string, string> $stores */
		return new static(
			enabled: $enabled,
			driver: $values->string('driver', CacheDriver::File->value),
			stores: $stores,
			pages: $values->bool('pages', true),
			maxAge: $values->int('maxAge', 0)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'enabled' => $this->enabled,
			'driver'  => $this->driver,
			'stores'  => $this->stores,
			'pages'   => $this->pages,
			'maxAge'  => $this->maxAge
		];
	}
}
