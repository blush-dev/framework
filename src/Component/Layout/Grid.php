<?php

/**
 * Grid component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Layout;

use Override;
use Blush\Component\ComponentView;
use Blush\Core\Framework;

/**
 * Lays blocks out in columns (D-175, D-177): `:::grid{columns=3}` … `:::`.
 * Each block inside is a cell.
 *
 * `columns` is the most columns (1 to 12). A column is never narrower
 * than `min` (a CSS length, `12rem` by default), so on a narrow screen
 * the grid drops to fewer columns; `min=0` keeps every column. `gap` sets
 * the space between cells; otherwise the theme's `--layout-gap` applies,
 * or `1.5rem`.
 *
 * `tag` makes it a `section` or `aside`, named by the label (D-298).
 *
 * The layout is inline styles, so it works in any theme; themes style
 * the `component-grid` class for the rest.
 */
final class Grid extends Layout
{
	/**
	 * The gap when neither the component nor the theme sets one.
	 */
	private const string GAP = '1.5rem';

	/**
	 * The minimum column width when none (or an invalid one) is given.
	 */
	private const string MIN = '12rem';

	/**
	 * The inline styles.
	 */
	public readonly string $style;

	public function __construct(
		public readonly int $columns = 2,
		public readonly string $min = self::MIN,
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
		$columns = max(1, min(12, $this->columns));
		$min     = CssLength::sanitize($this->min) ?? self::MIN;
		$gap     = CssLength::sanitize($this->gap);
		$space   = 'var(--layout-gap, ' . self::GAP . ')';

		$template = match (true) {
			$columns === 1       => 'minmax(0, 1fr)',
			(float) $min === 0.0 => "repeat({$columns}, minmax(0, 1fr))",
			default              => "repeat(auto-fill, minmax(max(min({$min}, 100%), calc((100% - ({$columns} - 1) * {$space}) / {$columns})), 1fr))"
		};

		return ($gap === null ? '' : "--layout-gap: {$gap}; ")
			. "display: grid; gap: {$space}; grid-template-columns: {$template};";
	}

	/**
	 * Renders the framework's template for it, `resources/components/grid.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): ComponentView
	{
		return $this->view(Framework::path('resources/components/grid.php'));
	}
}
