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
use InvalidArgumentException;
use Override;
use ReflectionClass;
use Blush\Content\Schema\Field;
use Blush\Support\RegistrationException;

/**
 * The registered components, by full name (D-171, D-172). A theme, site,
 * or extension provider registers a component in `boot()`, with a class
 * or as template-only:
 *
 * ```php
 * $components->register('acme/tabs', Tabs::class);
 * $components->register('acme/note', content: ComponentContent::Blocks, props: [new TextField('title')], variants: ['wide']);
 * ```
 *
 * Registering makes a component known to the admin's inserter and to
 * `component:list` with its text; a template-only component renders
 * without it. Third-party names need their namespace, and the `blush`
 * namespace holds only the core components, which a provider may replace
 * (`register('callout', …)` is `blush/callout`). `register()` overwrites;
 * the core components are seeded with `registerIf()`.
 *
 * Unlike the other registries (D-019), this one holds definitions rather
 * than classes, since a component needn't have one.
 */
final class ComponentRegistry implements Countable
{
	/**
	 * The registered components, by full name.
	 *
	 * @var array<string, ComponentDefinition>
	 */
	private array $definitions = [];

	/**
	 * Registers a component, replacing any registered under its name.
	 * `$content`, `$props`, and `$variants` default to what the class
	 * says (D-266: a variant given by name has the component's namespace
	 * as its registrant).
	 *
	 * @param  ?class-string<Component> $class
	 * @param  ?list<Field>             $props
	 * @param  ?list<Variant|string>    $variants
	 * @throws RegistrationException When the name, class, or a variant isn't valid.
	 */
	public function register(string $name, ?string $class = null, ?ComponentContent $content = null, ?array $props = null, ?array $variants = null): void
	{
		$parsed = self::name($name);

		if ($class !== null) {
			if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
				throw RegistrationException::notSubclassOf($class, Component::class);
			}

			if (! new ReflectionClass($class)->isInstantiable()) {
				throw RegistrationException::notInstantiable($class);
			}
		}

		$definition = new ComponentDefinition($parsed, $class, $content, $props, $variants);

		try {
			$definition->variants();
		} catch (InvalidArgumentException $error) {
			throw new RegistrationException(sprintf('The "%s" component: %s', $parsed, $error->getMessage()), 0, $error);
		}

		$this->definitions[(string) $parsed] = $definition;
	}

	/**
	 * Registers a component only when nothing is registered under its
	 * name yet, so the core components never replace a provider's.
	 *
	 * @param  ?class-string<Component> $class
	 * @param  ?list<Field>             $props
	 * @param  ?list<Variant|string>    $variants
	 * @throws RegistrationException
	 */
	public function registerIf(string $name, ?string $class = null, ?ComponentContent $content = null, ?array $props = null, ?array $variants = null): void
	{
		if (! $this->isRegistered($name)) {
			$this->register($name, $class, $content, $props, $variants);
		}
	}

	/**
	 * Removes a component, if it's registered.
	 */
	public function unregister(string $name): void
	{
		$parsed = ComponentName::parse($name);

		if ($parsed !== null) {
			unset($this->definitions[(string) $parsed]);
		}
	}

	/**
	 * Returns whether a component is registered under a name.
	 */
	public function isRegistered(string $name): bool
	{
		return $this->get($name) !== null;
	}

	/**
	 * Returns the component registered under a name (full, or a core
	 * component's short name), or `null`.
	 */
	public function get(string $name): ?ComponentDefinition
	{
		$parsed = ComponentName::parse($name);

		return $parsed === null ? null : $this->definitions[(string) $parsed] ?? null;
	}

	/**
	 * Returns every registered component, by full name.
	 *
	 * @return array<string, ComponentDefinition>
	 */
	public function all(): array
	{
		return $this->definitions;
	}

	/**
	 * Returns the registered namespaces.
	 *
	 * @return list<string>
	 */
	public function namespaces(): array
	{
		return array_values(array_unique(array_map(
			static fn (ComponentDefinition $definition): string => $definition->name->namespace,
			$this->definitions
		)));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->definitions);
	}

	/**
	 * Returns the name a registration is for.
	 *
	 * @throws RegistrationException When it isn't a name a provider may register.
	 */
	private static function name(string $name): ComponentName
	{
		$parsed = ComponentName::parse($name);

		if ($parsed === null) {
			throw new RegistrationException(str_contains($name, '/') || preg_match('#^' . ComponentName::SYNTAX . '$#', $name) !== 1
				? sprintf('"%s" is not a valid component name.', $name)
				: sprintf('"%s" needs its namespace, such as "vendor/%s"; only core components have short names.', $name, $name));
		}

		if ($parsed->isCore() && ComponentType::tryFrom($parsed->name) === null) {
			throw new RegistrationException(sprintf('"%s" is in the "%s" namespace, which only core components use.', $name, ComponentName::CORE));
		}

		return $parsed;
	}
}
