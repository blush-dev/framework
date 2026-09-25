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
 * The framework's class-backed components, keyed by name (the "Type
 * enum" of D-019). The other core content components (`gallery`,
 * `figure`, `callout`) are template-only, in the default theme (D-033).
 */
enum ComponentType: string
{
	case Embed = 'embed';

	/**
	 * Returns the component's class.
	 *
	 * @return class-string<Component>
	 */
	public function className(): string
	{
		return match ($this) {
			self::Embed => Embed::class
		};
	}
}
