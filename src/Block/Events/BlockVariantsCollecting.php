<?php

/**
 * Block variants collecting event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block\Events;

use Blush\Block\BlockName;
use Blush\Block\Variant;

/**
 * Dispatched once per block, the first time its variants are needed
 * (D-266), with the ones it declared itself. Listeners add variants, or
 * remove any but Default, which is always there:
 *
 * ```php
 * $listeners->listen(BlockVariantsCollecting::class, function (BlockVariantsCollecting $event): void {
 *     if ($event->is('core/callout')) {
 *         $event->add('bordered', 'nova');
 *     }
 * });
 * ```
 *
 * Firing when variants are first asked for, not when the block is
 * registered, means a listener sees every block, whatever order the
 * providers booted in. A variant from a theme applies only while that
 * theme is in the chain.
 */
final class BlockVariantsCollecting
{
	/**
	 * The variants so far, by name.
	 *
	 * @var array<string, Variant>
	 */
	private array $variants = [];

	/**
	 * @param list<Variant> $variants What the block declared.
	 */
	public function __construct(public readonly BlockName $block, array $variants = [])
	{
		foreach ($variants as $variant) {
			$this->variants[$variant->name] = $variant;
		}
	}

	/**
	 * Returns whether the event is for a block: a full name, or a core
	 * block's short name.
	 */
	public function is(string $name): bool
	{
		return (string) BlockName::parse($name) === (string) $this->block;
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
