<?php

/**
 * Built-in component types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component;

/**
 * The core content components (the "Type enum" of D-019): the ones
 * content can use in any theme (D-033), since the default theme, at the
 * end of every chain, has their templates. Most are template-only; a
 * case with a class has it seeded into the `ComponentRegistry`.
 */
enum ComponentType: string
{
	case Callout = 'callout';
	case Embed   = 'embed';
	case Figure  = 'figure';
	case Gallery = 'gallery';

	/**
	 * Returns the component's class, or `null` for a template-only one.
	 *
	 * @return ?class-string<Component>
	 */
	public function className(): ?string
	{
		return match ($this) {
			self::Embed => Embed::class,
			default     => null
		};
	}
}
