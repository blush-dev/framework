<?php

/**
 * Row directive.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive\Layout;

use Override;
use Blush\Directive\DirectiveView;
use Blush\Core\Framework;

/**
 * Puts blocks side by side, wrapping onto more lines when they don't fit
 * (D-175, D-177): `:::row{justify=between}` … `:::`. Each block inside is
 * an item.
 *
 * `justify` spreads the items (`start`, `center`, `end`, or `between`),
 * `align` lines them up (`center` by default; `start`, `end`, `stretch`,
 * or `baseline`), and `wrap=false` keeps them on one line. `gap` sets the
 * space between items; otherwise the theme's `--layout-gap` applies, or
 * `1rem`.
 *
 * `tag` makes it a `section` or `aside`, named by the label (D-298).
 *
 * The layout is inline styles, so it works in any theme; themes style
 * the `directive-row` class for the rest.
 */
final class Row extends Layout
{
	/**
	 * The gap when neither the directive nor the theme sets one.
	 */
	private const string GAP = '1rem';

	/**
	 * The inline styles.
	 */
	public readonly string $style;

	public function __construct(
		public readonly RowJustify $justify = RowJustify::Start,
		public readonly RowAlign $align = RowAlign::Center,
		public readonly bool $wrap = true,
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
			. 'display: flex; flex-wrap: ' . ($this->wrap ? 'wrap' : 'nowrap') . '; '
			. 'gap: var(--layout-gap, ' . self::GAP . '); '
			. "justify-content: {$this->justify->css()}; align-items: {$this->align->css()};";
	}

	/**
	 * Renders the framework's template for it, `resources/directives/row.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): DirectiveView
	{
		return $this->view(Framework::path('resources/directives/row.php'));
	}
}
