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

namespace Blush\View\Component;

use ReflectionMethod;

/**
 * A component a theme chain can render, from `Views::components()`: its
 * key, its class (if one is registered), and the `components/{key}.php`
 * files in the chain, winner first.
 */
final readonly class ComponentListing
{
	/**
	 * @param ?class-string<Component> $class
	 * @param list<string>             $files Winner first.
	 */
	public function __construct(
		public string $key,
		public ?string $class = null,
		public array $files = [],
		public bool $isCore = false
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
	 * Returns whether it has no template to render: no file in the chain,
	 * and no class that picks another view (by overriding `template()`).
	 */
	public function isMissingTemplate(): bool
	{
		if ($this->files !== []) {
			return false;
		}

		return $this->class === null
			|| new ReflectionMethod($this->class, 'template')->getDeclaringClass()->getName() === Component::class;
	}
}
