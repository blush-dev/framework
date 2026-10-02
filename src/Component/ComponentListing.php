<?php

/**
 * Component listing.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use ReflectionMethod;

/**
 * A component a theme chain can render, from `Views::components()`: its
 * name, its registration (if any), the template files in the chain for
 * it, winner first, its translated label and description (`null` when
 * no catalog has them), and its variants under the chain (D-266).
 */
final readonly class ComponentListing
{
	/**
	 * @param list<string>  $files    Winner first.
	 * @param list<Variant> $variants Default not included.
	 */
	public function __construct(
		public ComponentName $name,
		public ?ComponentDefinition $definition = null,
		public array $files = [],
		public ?string $label = null,
		public ?string $description = null,
		public array $variants = []
	) {}

	/**
	 * Returns whether it's a core component.
	 */
	public function isCore(): bool
	{
		return $this->name->isCore();
	}

	/**
	 * Returns whether a provider registered it (the core components are
	 * registered too), which puts it in the admin's inserter.
	 */
	public function isRegistered(): bool
	{
		return $this->definition !== null;
	}

	/**
	 * Returns its class, or `null` for a template-only component.
	 *
	 * @return ?class-string<Component>
	 */
	public function className(): ?string
	{
		return $this->definition?->class;
	}

	/**
	 * Returns its label: the translated one, or one made from its name.
	 */
	public function displayLabel(): string
	{
		return $this->label ?? $this->name->label();
	}

	/**
	 * Returns the file that renders it, or `null` when the chain has
	 * none.
	 */
	public function file(): ?string
	{
		return array_first($this->files);
	}

	/**
	 * Returns whether its class always renders itself (D-382): its
	 * `render()` can't return `null`, so it has markup of its own when the
	 * chain has no template for it.
	 */
	public function rendersItself(): bool
	{
		$class = $this->className();

		if ($class === null) {
			return false;
		}

		$type = new ReflectionMethod($class, 'render')->getReturnType();

		return $type !== null && ! $type->allowsNull();
	}

	/**
	 * Returns whether it has nothing to render with: no file in the chain,
	 * no class that picks another view (by overriding `template()`), and
	 * no markup of its own (`rendersItself()`).
	 */
	public function isMissingTemplate(): bool
	{
		if ($this->files !== [] || $this->rendersItself()) {
			return false;
		}

		$class = $this->className();

		return $class === null
			|| new ReflectionMethod($class, 'template')->getDeclaringClass()->getName() === Component::class;
	}
}
