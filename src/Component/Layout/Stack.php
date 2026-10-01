<?php

/**
 * Stack component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Layout;

use Override;

/**
 * Puts blocks one above another with an even space between them
 * (D-318): `:::stack{gap=2rem}` … `:::`. Each block inside is an item,
 * and the gap takes the place of its margins.
 *
 * `align` lines the items up across the column (`stretch` by default, so
 * each is as wide as the stack; `start`, `center`, or `end`). `gap` sets
 * the space between items; otherwise the theme's `--layout-gap` applies,
 * or `1rem`.
 *
 * `tag` makes it a `section` or `aside`, named by the label (D-298).
 *
 * The layout is inline styles, so it works in any theme; themes style
 * the `component-stack` class for the rest.
 */
final class Stack extends Layout
{
	/**
	 * The gap when neither the component nor the theme sets one.
	 */
	private const string GAP = '1rem';

	/**
	 * The inline styles.
	 */
	public readonly string $style;

	public function __construct(
		public readonly StackAlign $align = StackAlign::Stretch,
		public readonly string $gap = '',
		public readonly LayoutTag $tag = LayoutTag::Div,
		public readonly string $label = ''
	) {
		$this->style = $this->styles();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function rootAttributes(): array
	{
		return [...parent::rootAttributes(), 'style' => $this->style];
	}

	/**
	 * Returns the inline styles.
	 */
	private function styles(): string
	{
		$gap = CssLength::sanitize($this->gap);

		return ($gap === null ? '' : "--layout-gap: {$gap}; ")
			. 'display: flex; flex-direction: column; '
			. 'gap: var(--layout-gap, ' . self::GAP . '); '
			. "align-items: {$this->align->css()};";
	}
}
