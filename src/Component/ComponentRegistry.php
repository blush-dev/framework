<?php

/**
 * Component registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Countable;
use Override;
use ReflectionClass;
use Blush\Support\RegistrationException;

/**
 * The components with a class, by full name (D-532). A component needn't
 * be registered: a template in `components/` is one. A theme, site, or
 * plugin provider registers a class in `boot()`:
 *
 * ```php
 * $components->register('acme/card', Card::class);
 * ```
 *
 * `register()` overwrites, so the site can replace a theme's class.
 */
final class ComponentRegistry implements Countable
{
	/**
	 * The registered classes, by full name.
	 *
	 * @var array<string, class-string<Component>>
	 */
	private array $classes = [];

	/**
	 * Registers a component's class, replacing any registered under its
	 * name.
	 *
	 * @param  class-string<Component> $class
	 * @throws RegistrationException When the name or class isn't valid.
	 */
	public function register(string $name, string $class): void
	{
		$parsed = ComponentName::parse($name)
			?? throw new RegistrationException(sprintf('"%s" is not a valid component name; a component is always "{namespace}/{name}", such as "acme/card".', $name));

		if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
			throw RegistrationException::notSubclassOf($class, Component::class);
		}

		if (! new ReflectionClass($class)->isInstantiable()) {
			throw RegistrationException::notInstantiable($class);
		}

		$this->classes[(string) $parsed] = $class;
	}

	/**
	 * Removes a component's class, if it's registered.
	 */
	public function unregister(string $name): void
	{
		unset($this->classes[$name]);
	}

	/**
	 * Returns whether a class is registered under a name.
	 */
	public function isRegistered(string $name): bool
	{
		return isset($this->classes[$name]);
	}

	/**
	 * Returns the class registered under a name, or `null`.
	 *
	 * @return ?class-string<Component>
	 */
	public function get(string $name): ?string
	{
		return $this->classes[$name] ?? null;
	}

	/**
	 * Returns every registered class, by full name.
	 *
	 * @return array<string, class-string<Component>>
	 */
	public function all(): array
	{
		return $this->classes;
	}

	/**
	 * Returns the registered namespaces.
	 *
	 * @return list<string>
	 */
	public function namespaces(): array
	{
		return array_values(array_unique(array_map(
			static fn (string $name): string => explode('/', $name, 2)[0],
			array_keys($this->classes)
		)));
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
