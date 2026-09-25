<?php

/**
 * Class registry base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use ReflectionClass;

/**
 * Base class for type registries, the "Registry" in the Type enum + Registry +
 * Factory + Registrar pattern (D-019). Stores `key => class name` mappings
 * that a factory later resolves through the container, and guards
 * registration so only instantiable subclasses of the registry's contract
 * can be stored. Subclasses declare the contract with the `CONTRACT` constant
 * or by overriding `contract()`. Registries are iterable (`key =>
 * class-string`) and countable.
 *
 * @template   T of object
 * @implements IteratorAggregate<string, class-string<T>>
 */
abstract class Registry implements IteratorAggregate, Countable
{
	/**
	 * The class or interface every registered class must extend or
	 * implement. Subclasses override this constant, or override
	 * `contract()` to compute it.
	 */
	protected const string CONTRACT = '';

	/**
	 * Maps each registered key to its class name.
	 *
	 * @var array<string, class-string<T>>
	 */
	private array $classes = [];

	/**
	 * Optionally seeds the registry with an initial `key => class` map.
	 *
	 * @param  array<string, class-string<T>> $classes
	 * @throws RegistrationException
	 */
	public function __construct(array $classes = [])
	{
		foreach ($classes as $key => $className) {
			$this->register($key, $className);
		}
	}

	/**
	 * Returns the class or interface every registered class must extend or
	 * implement. Defaults to the `CONTRACT` constant.
	 *
	 * @return class-string<T>
	 * @throws RegistrationException When no valid contract is declared.
	 */
	protected function contract(): string
	{
		$contract = static::CONTRACT;

		if ($contract === '') {
			throw RegistrationException::missingContract(static::class);
		}

		if (! class_exists($contract) && ! interface_exists($contract)) {
			throw RegistrationException::invalidContract(static::class, $contract);
		}

		/** @var class-string<T> */
		return $contract;
	}

	/**
	 * Maps `$key` to `$className`, overwriting any existing mapping. Throws
	 * if `$className` doesn't satisfy the contract or can't be
	 * instantiated, so the factory can always build what is stored.
	 *
	 * @param  class-string<T> $className
	 * @throws RegistrationException
	 */
	public function register(string $key, string $className): void
	{
		$contract = $this->contract();

		if (! class_exists($className) || ! is_subclass_of($className, $contract)) {
			throw RegistrationException::notSubclassOf($className, $contract);
		}

		if (! new ReflectionClass($className)->isInstantiable()) {
			throw RegistrationException::notInstantiable($className);
		}

		$this->classes[$key] = $className;
	}

	/**
	 * Maps `$key` to `$className` only when nothing is registered under it
	 * yet, so a registrar can seed built-ins without overwriting an
	 * extension's registration.
	 *
	 * @param  class-string<T> $className
	 * @throws RegistrationException
	 */
	public function registerIf(string $key, string $className): void
	{
		if (! $this->isRegistered($key)) {
			$this->register($key, $className);
		}
	}

	/**
	 * Removes the mapping for `$key`, if any.
	 */
	public function unregister(string $key): void
	{
		unset($this->classes[$key]);
	}

	/**
	 * Returns whether a class is registered under `$key`.
	 */
	public function isRegistered(string $key): bool
	{
		return array_key_exists($key, $this->classes);
	}

	/**
	 * Returns the class name registered under `$key`, or `null` if none.
	 *
	 * @return ?class-string<T>
	 */
	public function get(string $key): ?string
	{
		return $this->classes[$key] ?? null;
	}

	/**
	 * Returns every registered key mapped to its class name.
	 *
	 * @return array<string, class-string<T>>
	 */
	public function all(): array
	{
		return $this->classes;
	}

	/**
	 * @inheritDoc
	 *
	 * @return ArrayIterator<string, class-string<T>>
	 */
	#[Override]
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->classes);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->classes);
	}
}
