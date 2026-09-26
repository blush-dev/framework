<?php

/**
 * Cache store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Closure;
use DateInterval;
use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * The base of every cache driver: an in-house PSR-16 store for one
 * namespace (`pages`, `bodies`, and so on), so clearing one never touches
 * another. Drivers implement reading, writing, deleting, and clearing
 * single entries; this class validates keys, turns TTLs into expiry
 * times, and provides the multiple-key methods, `remember()`, and
 * `prune()`.
 *
 * Values are plain data: scalars, `null`, and arrays of them. Objects are
 * refused, so a file-backed store never unserializes one (D-127).
 */
abstract class Store implements CacheInterface
{
	/**
	 * Characters PSR-16 reserves in keys.
	 */
	private const string RESERVED = '{}()/\@:';

	public function __construct(
		public readonly string $namespace,
		protected readonly ClockInterface $clock
	) {
		if (preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $namespace) !== 1) {
			throw new CacheException(sprintf('Cache namespace "%s" must be lowercase letters, digits, "_", ".", and "-".', $namespace));
		}
	}

	/**
	 * Reads an entry: its value, or `null` when it's missing or expired.
	 * An entry that holds `null` is indistinguishable from a missing one,
	 * so `has()` reports it as missing.
	 */
	abstract protected function read(string $key): ?Item;

	/**
	 * Writes an entry, returning whether it was stored.
	 */
	abstract protected function write(string $key, Item $item): bool;

	/**
	 * Removes an entry, returning whether it's gone.
	 */
	abstract protected function remove(string $key): bool;

	/**
	 * Removes every entry in the namespace.
	 */
	abstract protected function flush(): bool;

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function get(string $key, mixed $default = null): mixed
	{
		$item = $this->read(self::assertKey($key));

		return $item === null || $item->isExpired($this->now()) ? $default : $item->value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
	{
		self::assertKey($key);
		self::assertValue($value);

		$expires = $this->expiry($ttl);

		if ($expires !== 0 && $expires <= $this->now()) {
			return $this->remove($key);
		}

		return $this->write($key, new Item($value, $expires));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(string $key): bool
	{
		return $this->remove(self::assertKey($key));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function clear(): bool
	{
		return $this->flush();
	}

	/**
	 * @inheritDoc
	 *
	 * @param  iterable<string> $keys
	 * @return array<string, mixed>
	 */
	#[Override]
	public function getMultiple(iterable $keys, mixed $default = null): iterable
	{
		$values = [];

		foreach ($keys as $key) {
			$values[$key] = $this->get($key, $default);
		}

		return $values;
	}

	/**
	 * @inheritDoc
	 *
	 * @param iterable<string, mixed> $values
	 */
	#[Override]
	public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
	{
		$stored = true;

		foreach ($values as $key => $value) {
			$stored = $this->set((string) $key, $value, $ttl) && $stored;
		}

		return $stored;
	}

	/**
	 * @inheritDoc
	 *
	 * @param iterable<string> $keys
	 */
	#[Override]
	public function deleteMultiple(iterable $keys): bool
	{
		$deleted = true;

		foreach ($keys as $key) {
			$deleted = $this->delete($key) && $deleted;
		}

		return $deleted;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function has(string $key): bool
	{
		return $this->get($key) !== null;
	}

	/**
	 * Returns a cached value, or computes, stores, and returns it. A
	 * `null` result isn't stored, so it's computed again next time.
	 *
	 * @template T
	 * @param  Closure(): T $callback
	 * @return T
	 */
	public function remember(string $key, null|int|DateInterval $ttl, Closure $callback): mixed
	{
		$value = $this->get($key);

		if ($value !== null) {
			/** @var T $value Stored by this method. */
			return $value;
		}

		$value = $callback();

		if ($value !== null) {
			$this->set($key, $value, $ttl);
		}

		return $value;
	}

	/**
	 * Removes expired entries, for stores that keep them until they're
	 * read. Returns how many were removed.
	 */
	public function prune(): int
	{
		return 0;
	}

	/**
	 * Returns the current Unix time.
	 */
	protected function now(): int
	{
		return $this->clock->now()->getTimestamp();
	}

	/**
	 * Returns a TTL as an expiry time, or `0` for never.
	 */
	private function expiry(null|int|DateInterval $ttl): int
	{
		if ($ttl === null) {
			return 0;
		}

		if ($ttl instanceof DateInterval) {
			$now = DateTimeImmutable::createFromTimestamp($this->now());

			return $now->add($ttl)->getTimestamp();
		}

		return $ttl <= 0 ? -1 : $this->now() + $ttl;
	}

	/**
	 * Checks a key against PSR-16's rules.
	 *
	 * @throws InvalidCacheKey
	 */
	private static function assertKey(string $key): string
	{
		if ($key === '' || strpbrk($key, self::RESERVED) !== false) {
			throw new InvalidCacheKey(sprintf('Cache key "%s" is empty or has one of "%s".', $key, self::RESERVED));
		}

		return $key;
	}

	/**
	 * Checks that a value is plain data.
	 *
	 * @throws InvalidCacheValue
	 */
	private static function assertValue(mixed $value): void
	{
		if (is_object($value) || is_resource($value)) {
			throw new InvalidCacheValue(sprintf('Cache values must be plain data; %s given.', get_debug_type($value)));
		}

		if (is_array($value)) {
			array_walk_recursive($value, static function (mixed $item): void {
				self::assertValue($item);
			});
		}
	}
}
