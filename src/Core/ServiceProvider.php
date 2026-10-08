<?php

/**
 * Abstract service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use Override;
use Blush\Container\Container;
use Blush\Container\ContainerException;
use Blush\Container\ServiceResolver;
use Blush\Core\Bootable;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageResolver;

/**
 * Service providers allow you to connect services to the application container.
 * This base class can be extended with a `register()` method for registering
 * services and a `BOOTABLE` constant (or a custom `boot()` method) for booting
 * them.
 */
abstract class ServiceProvider implements Bootable
{
	/**
	 * An array of abstracts to resolve from the container and boot. Each
	 * entry must resolve to a `Bootable` instance. Services boot in the order
	 * listed, so a service that depends on another having booted should be
	 * declared after it.
	 *
	 * @var  array<string> Bootable service abstracts.
	 */
	protected const array BOOTABLE = [];

	/**
	 * A map of singletons to register. Numeric-keyed entries are self-bound
	 * (the abstract is its own concrete); string-keyed entries bind an
	 * abstract to a concrete class name. Bindings that require a closure
	 * factory should be registered in an overridden `register()` method.
	 *
	 * @var  array<int|string, string> Singleton abstracts/concretes.
	 */
	protected const array SINGLETONS = [];

	/**
	 * A map of singletons to register as overridable defaults, following
	 * the same key conventions as `SINGLETONS`. Each is registered only if
	 * the abstract is not already bound, so an extension may replace it by
	 * binding its own concrete (via `singleton()`) regardless of load order.
	 *
	 * @var  array<int|string, string> Default singleton abstracts/concretes.
	 */
	protected const array SINGLETONS_IF = [];

	/**
	 * A map of transients to register (a new instance on each resolution),
	 * following the same key conventions as `SINGLETONS`. Bindings that
	 * require a closure factory should be registered in an overridden
	 * `register()` method.
	 *
	 * @var  array<int|string, string> Transient abstracts/concretes.
	 */
	protected const array TRANSIENTS = [];

	/**
	 * A map of transients to register as overridable defaults, following
	 * the same key conventions as `SINGLETONS`. Each is registered only if
	 * the abstract is not already bound, so an extension may replace it by
	 * binding its own concrete (via `transient()`) regardless of load order.
	 *
	 * @var  array<int|string, string> Default transient abstracts/concretes.
	 */
	protected const array TRANSIENTS_IF = [];

	/**
	 * A map of storage contracts to the area that keeps them (D-642).
	 * Each is bound as an overridable default singleton (as in
	 * `SINGLETONS_IF`) to the class the area's storage driver gives:
	 *
	 *     protected const array STORAGE = [AccountStore::class => StorageArea::Accounts];
	 *
	 * @var  array<class-string, StorageArea> Contracts mapped to areas.
	 */
	protected const array STORAGE = [];

	/**
	 * A map of alias names to the abstracts they resolve to. Resolving an
	 * alias resolves its abstract instead, returning the same instance and
	 * lifetime, mirroring the container's `alias()` method.
	 *
	 * @var  array<string, string> Alias names mapped to abstracts.
	 */
	protected const array ALIASES = [];

	/**
	 * A map of tag names to the list of abstracts assigned to each tag. The
	 * tagged abstracts are resolvable together via the container's `tagged()`
	 * method.
	 *
	 * @var  array<string, array<string>> Tag names mapped to abstracts.
	 */
	protected const array TAGS = [];

	/**
	 * Accepts a container implementation for registering services.
	 */
	public function __construct(protected readonly Container $container)
	{
	}

	/**
	 * Registers the bindings listed in the `SINGLETONS`, `SINGLETONS_IF`,
	 * `TRANSIENTS`, `TRANSIENTS_IF`, and `STORAGE` constants, assigns each tag listed
	 * in the `TAGS` constant, and registers each alias in the `ALIASES`
	 * constant. The application calls this before `register()`, so the
	 * declared bindings are always processed regardless of what a subclass
	 * does in `register()`.
	 *
	 * @throws ContainerException
	 */
	final public function registerDeclarations(): void
	{
		$this->registerBindings(static::SINGLETONS, $this->container->singleton(...));
		$this->registerBindings(static::SINGLETONS_IF, $this->container->singletonIf(...));
		$this->registerBindings(static::TRANSIENTS, $this->container->transient(...));
		$this->registerBindings(static::TRANSIENTS_IF, $this->container->transientIf(...));

		foreach (static::STORAGE as $contract => $area) {
			$this->container->singletonIf(
				$contract,
				static fn (ServiceResolver $resolver): object => $resolver->make(StorageResolver::class)->resolve($area, $contract)
			);
		}

		foreach (static::ALIASES as $alias => $abstract) {
			$this->container->alias($alias, $abstract);
		}

		foreach (static::TAGS as $tag => $abstracts) {
			$this->container->tag($abstracts, $tag);
		}
	}

	/**
	 * Override to register custom bindings that cannot be expressed with
	 * the class constants (such as closure factories). Runs after the
	 * declared bindings.
	 */
	public function register(): void
	{
	}

	/**
	 * Registers a map of bindings with the given container binder. A
	 * numeric-keyed entry is self-bound (the abstract is its own concrete);
	 * a string-keyed entry binds the abstract to its concrete class name.
	 *
	 * @param array<int|string, string>       $bindings
	 * @param callable(string, string=): void $bind
	 */
	private function registerBindings(array $bindings, callable $bind): void
	{
		foreach ($bindings as $abstract => $concrete) {
			is_int($abstract) ? $bind($concrete) : $bind($abstract, $concrete);
		}
	}

	/**
	 * Resolves and boots each abstract listed in the `BOOTABLE` constant.
	 * The application calls this before `boot()`, so the declared services
	 * are always booted regardless of what a subclass does in `boot()`.
	 *
	 * @throws UnbootableServiceException If a `BOOTABLE` entry does not
	 *         implement `Bootable`.
	 */
	final public function bootDeclarations(): void
	{
		foreach (static::BOOTABLE as $abstract) {
			$service = $this->container->get($abstract);

			if (! $service instanceof Bootable) {
				throw new UnbootableServiceException(sprintf(
					'%s is listed in %s::BOOTABLE but does not implement %s.',
					$abstract,
					static::class,
					Bootable::class
				));
			}

			$service->boot();
		}
	}

	/**
	 * Override to add custom boot logic. Runs after the services listed in
	 * the `BOOTABLE` constant.
	 */
	#[Override]
	public function boot(): void
	{
	}
}
