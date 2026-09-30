<?php

/**
 * Component variants collecting event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Events;

use Blush\Component\ComponentName;
use Blush\Component\Variant;

/**
 * Dispatched once per component, the first time its variants are needed
 * (D-266), with the ones it declared itself. Listeners add variants, or
 * remove any but Default, which is always there:
 *
 * ```php
 * $listeners->listen(ComponentVariantsCollecting::class, function (ComponentVariantsCollecting $event): void {
 *     if ($event->is('blush/callout')) {
 *         $event->add('bordered', 'nova');
 *     }
 * });
 * ```
 *
 * Firing when variants are first asked for, not when the component is
 * registered, means a listener sees every component, whatever order the
 * providers booted in. A variant from a theme applies only while that
 * theme is in the chain.
 */
final class ComponentVariantsCollecting
{
	/**
	 * The variants so far, by name.
	 *
	 * @var array<string, Variant>
	 */
	private array $variants = [];

	/**
	 * @param list<Variant> $variants What the component declared.
	 */
	public function __construct(public readonly ComponentName $component, array $variants = [])
	{
		foreach ($variants as $variant) {
			$this->variants[$variant->name] = $variant;
		}
	}

	/**
	 * Returns whether the event is for a component: a full name, or a core
	 * component's short name.
	 */
	public function is(string $name): bool
	{
		return (string) ComponentName::parse($name) === (string) $this->component;
	}

	/**
	 * Adds a variant, or replaces one of the same name. `$registrant` is
	 * the namespace whose catalog has its text.
	 */
	public function add(Variant|string $variant, string $registrant = '', ?string $modifier = null): void
	{
		$variant = $variant instanceof Variant ? $variant : new Variant($variant, $registrant, $modifier);

		$this->variants[$variant->name] = $variant;
	}

	/**
	 * Removes a variant, if it's there.
	 */
	public function remove(string $name): void
	{
		unset($this->variants[$name]);
	}

	/**
	 * Returns the variants, in the order they were added.
	 *
	 * @return list<Variant>
	 */
	public function variants(): array
	{
		return array_values($this->variants);
	}
}
