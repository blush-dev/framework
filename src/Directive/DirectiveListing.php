<?php

/**
 * Directive listing.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use ReflectionMethod;

/**
 * A registered directive, from `Views::directives()`: its name, its
 * registration, the template files in the chain for it, winner first, its translated label and description (`null` when
 * no catalog has them), and its variants under the chain (D-266).
 */
final readonly class DirectiveListing
{
	/**
	 * @param list<string>  $files    Winner first.
	 * @param list<Variant> $variants Default not included.
	 */
	public function __construct(
		public DirectiveName $name,
		public DirectiveDefinition $definition,
		public array $files = [],
		public ?string $label = null,
		public ?string $description = null,
		public array $variants = []
	) {}

	/**
	 * Returns whether it's a core directive.
	 */
	public function isCore(): bool
	{
		return $this->name->isCore();
	}

	/**
	 * Returns its class.
	 *
	 * @return class-string<Directive>
	 */
	public function className(): string
	{
		return $this->definition->class;
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
		$type = new ReflectionMethod($this->className(), 'render')->getReturnType();

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

		return ! new ReflectionMethod($this->className(), 'template')->getDeclaringClass()->isSubclassOf(Directive::class);
	}
}
