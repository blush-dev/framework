<?php

/**
 * BEM class names.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

use NoDiscard;

/**
 * The class names of one BEM block (D-821, D-822): the block, its
 * elements, and their modifiers, so no one writes `__` or `--` by hand.
 * Blocks and components have one (`Renderable::bem()`); anything else
 * that prints HTML, a theme's partial or a plugin's output, makes its
 * own. Each method returns a `BemClass`, which chains and prints as the
 * full class list:
 *
 * ```php
 * $bem = new Bem('pagination');
 *
 * $bem->block();                                // pagination
 * $bem->modifier('wide');                       // pagination pagination--wide
 * $bem->element('item');                        // pagination__item
 * $bem->element('item')->modifier('current');   // pagination__item pagination__item--current
 * $bem->element('item')->when($current, 'current');
 * $bem->block()->modifier('wide')->with('alignwide'); // pagination pagination--wide alignwide
 * ```
 *
 * Every name is letters, digits, hyphens, and underscores, starting with
 * a letter or digit, so a class can't carry a space or a quote.
 */
final readonly class Bem
{
	/**
	 * What a block, element, or modifier name may be.
	 */
	private const string NAME = '/^[A-Za-z0-9][A-Za-z0-9_-]*$/';

	/**
	 * @throws InvalidClassName When `$name` isn't a valid name.
	 */
	public function __construct(public string $name)
	{
		self::check('block', $name);
	}

	/**
	 * Returns the block's class: `pagination`.
	 */
	#[NoDiscard]
	public function block(): BemClass
	{
		return new BemClass($this->name);
	}

	/**
	 * Returns an element's class: `pagination__item`.
	 *
	 * @throws InvalidClassName When `$element` isn't a valid name.
	 */
	#[NoDiscard]
	public function element(string $element): BemClass
	{
		return new BemClass($this->name . '__' . self::check('element', $element));
	}

	/**
	 * Returns the block's class with a modifier:
	 * `pagination pagination--next`. The same as
	 * `block()->modifier($modifier)`.
	 *
	 * @throws InvalidClassName When `$modifier` isn't a valid name.
	 */
	#[NoDiscard]
	public function modifier(string $modifier): BemClass
	{
		return $this->block()->modifier($modifier);
	}

	/**
	 * Returns `$name` once it's known to be valid.
	 *
	 * @param string $part `block`, `element`, or `modifier`.
	 *
	 * @throws InvalidClassName When it isn't.
	 */
	public static function check(string $part, string $name): string
	{
		return preg_match(self::NAME, $name) === 1 ? $name : throw InvalidClassName::for($part, $name);
	}
}
