<?php

/**
 * A BEM class.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

use NoDiscard;
use Override;
use Stringable;

/**
 * A BEM block's or element's class, with its modifiers and any utility
 * classes (D-821, D-822), printed as one class list. `Bem` makes them;
 * each method returns a new one, so they chain:
 *
 * ```php
 * $bem->element('link')->modifier('current'); // nav__link nav__link--current
 * $bem->element('link')->when($current, 'current');
 * $bem->block()->modifier('wide')->with('alignwide'); // nav nav--wide alignwide
 * ```
 *
 * It prints wherever a string does: `attr()`, `Renderable::html()`, and
 * `.` all take it.
 */
final readonly class BemClass implements Stringable
{
	/**
	 * @param string       $base      The block's or element's class.
	 * @param list<string> $modifiers Its modifiers, each checked.
	 * @param list<string> $utilities Classes that aren't the block's own.
	 */
	public function __construct(
		public string $base,
		public array $modifiers = [],
		public array $utilities = []
	) {}

	/**
	 * Returns it with modifiers added: `nav__link--current`. Empty ones
	 * are left out, and each is added once.
	 *
	 * @throws InvalidClassName When a modifier isn't a valid name.
	 */
	#[NoDiscard]
	public function modifier(string ...$modifiers): self
	{
		$added = array_map(
			static fn (string $modifier): string => Bem::check('modifier', $modifier),
			array_values(array_filter($modifiers, static fn (string $modifier): bool => $modifier !== ''))
		);

		return clone($this, ['modifiers' => array_values(array_unique([...$this->modifiers, ...$added]))]);
	}

	/**
	 * Returns it with `$modifier` added when `$condition` holds, else as
	 * it is.
	 *
	 * @throws InvalidClassName When `$modifier` isn't a valid name.
	 */
	#[NoDiscard]
	public function when(bool $condition, string $modifier): self
	{
		return $condition ? $this->modifier($modifier) : $this;
	}

	/**
	 * Returns it with classes added after its own: utility classes (a
	 * `class` prop, `alignwide`), or another block's class on the same
	 * element (a BEM mix, `$playlist->element('card')`). A string may
	 * hold several, separated by spaces.
	 *
	 * @param Stringable|string|list<Stringable|string> $utilities
	 */
	#[NoDiscard]
	public function with(Stringable|string|array $utilities): self
	{
		$added = is_array($utilities) ? $utilities : [$utilities];
		$added = array_map(static fn (Stringable|string $class): string => trim((string) $class), $added);

		return clone($this, ['utilities' => [...$this->utilities, ...array_values(array_filter($added, static fn (string $class): bool => $class !== ''))]]);
	}

	/**
	 * Returns the class list: the base, a class for each modifier, then
	 * the utilities.
	 */
	#[Override]
	public function __toString(): string
	{
		return implode(' ', [
			$this->base,
			...array_map(fn (string $modifier): string => "{$this->base}--{$modifier}", $this->modifiers),
			...$this->utilities
		]);
	}
}
