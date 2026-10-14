<?php

/**
 * Shadow style.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Theme;

/**
 * The admin's shadows, as an admin theme picks them (`boxShadow.style`,
 * D-844). Core keeps each style's geometry, so a theme can choose how
 * things stand off the page but can't draw a shadow of its own; it sets
 * only the color (`boxShadow.color`). Whichever it picks holds for every
 * shadow in the admin, at rest and above the page.
 */
enum ShadowStyle: string
{
	/**
	 * Flat at rest, soft layers under what floats: Neutral's, in
	 * `tokens.css`.
	 */
	case Soft = 'soft';

	/**
	 * A solid offset in the shadow's color, with no blur, on everything
	 * that has a shadow: resting surfaces (panels, cards, buttons) and
	 * menus by 4px, modals and drawers by 8px.
	 */
	case Hard = 'hard';

	/**
	 * Returns the shadows a theme's stylesheet sets for it, by custom
	 * property: none for Soft, which is the admin's own.
	 *
	 * @return array<string, string>
	 */
	public function shadows(): array
	{
		return match ($this) {
			self::Soft => [],
			self::Hard => [
				'shadow-1' => '4px 4px 0 var(--box-shadow-color)',
				'shadow-2' => '4px 4px 0 var(--box-shadow-color)',
				'shadow-3' => '8px 8px 0 var(--box-shadow-color)'
			]
		};
	}
}
