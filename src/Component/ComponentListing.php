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
 * name, its class (if one is registered), and the template files in the
 * chain for it, winner first.
 */
final readonly class ComponentListing
{
	/**
	 * @param ?class-string<Component> $class
	 * @param list<string>             $files Winner first.
	 */
	public function __construct(
		public ComponentName $name,
		public ?string $class = null,
		public array $files = []
	) {}

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
		if ($this->class === null) {
			return false;
		}

		$type = new ReflectionMethod($this->class, 'render')->getReturnType();

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

		return $this->class === null
			|| ! new ReflectionMethod($this->class, 'template')->getDeclaringClass()->isSubclassOf(Component::class);
	}
}
