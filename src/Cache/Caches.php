<?php

/**
 * Caches.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Psr\Clock\ClockInterface;
use Blush\Cache\Events\CacheCleared;
use Blush\Core\AppConfig;
use Blush\Event\Dispatcher;

/**
 * The site's cache stores, one per namespace, built on first use with the
 * driver `CacheConfig` picks for it. When caching is off (in development,
 * by default), `store()` hands out a `NullStore`, so callers never check
 * whether caching is on; `persistent()` always uses the real driver, for
 * records that must hold even then.
 *
 *     $html = $caches->store('fragments')->remember("menu.{$version}", null, fn (): string => $this->menu());
 */
final class Caches
{
	/**
	 * Stores built so far, by namespace.
	 *
	 * @var array<string, Store>
	 */
	private array $stores = [];

	/**
	 * The null stores handed out while caching is off, by namespace.
	 *
	 * @var array<string, NullStore>
	 */
	private array $nulls = [];

	/**
	 * The namespaces `store()` has handed out.
	 *
	 * @var array<string, true>
	 */
	private array $used = [];

	public function __construct(
		private readonly CacheConfig $config,
		private readonly AppConfig $app,
		private readonly CacheDriverFactory $factory,
		private readonly ClockInterface $clock,
		private readonly Dispatcher $events
	) {}

	/**
	 * Returns whether caching is on.
	 */
	public function enabled(): bool
	{
		return $this->config->isEnabled($this->app->environment);
	}

	/**
	 * Returns a namespace's store, or a null store when caching is off.
	 *
	 * @throws CacheException When the namespace's driver can't be built.
	 */
	public function store(string|CacheNamespace $namespace): Store
	{
		$namespace = $namespace instanceof CacheNamespace ? $namespace->value : $namespace;

		$this->used[$namespace] = true;

		return $this->enabled()
			? $this->persistent($namespace)
			: $this->nulls[$namespace] ??= new NullStore($namespace, $this->clock);
	}

	/**
	 * Returns a namespace's store with its driver, even when caching is
	 * off.
	 *
	 * @throws CacheException
	 */
	public function persistent(string|CacheNamespace $namespace): Store
	{
		$namespace = $namespace instanceof CacheNamespace ? $namespace->value : $namespace;

		return $this->stores[$namespace] ??= $this->factory->make($this->config->driverFor($namespace), $namespace);
	}

	/**
	 * Clears the framework's derived namespaces, every namespace
	 * `CacheConfig` names, and any other namespace `store()` handed out
	 * in this process, whether or not caching is on. `webhooks` and
	 * namespaces used only through `persistent()` are left alone.
	 * Dispatches `CacheCleared`, and returns the namespaces cleared.
	 *
	 * @return list<string>
	 * @throws CacheException
	 */
	public function clear(): array
	{
		$namespaces = $this->namespaces();

		foreach ($namespaces as $namespace) {
			$this->persistent($namespace)->clear();
		}

		$this->events->dispatch(new CacheCleared($namespaces));

		return $namespaces;
	}

	/**
	 * Removes expired entries from the same namespaces `clear()` clears.
	 * Returns how many were removed.
	 *
	 * @throws CacheException
	 */
	public function prune(): int
	{
		$removed = 0;

		foreach ($this->namespaces() as $namespace) {
			$removed += $this->persistent($namespace)->prune();
		}

		return $removed;
	}

	/**
	 * Returns the namespaces `clear()` and `prune()` work on.
	 *
	 * @return list<string>
	 */
	private function namespaces(): array
	{
		$namespaces = array_unique([
			...array_map(static fn (CacheNamespace $namespace): string => $namespace->value, CacheNamespace::cases()),
			...array_map(strval(...), array_keys($this->config->stores)),
			...array_map(strval(...), array_keys($this->used))
		]);

		return array_values(array_filter(
			$namespaces,
			static fn (string $namespace): bool => CacheNamespace::tryFrom($namespace)?->isDerived() ?? true
		));
	}
}
