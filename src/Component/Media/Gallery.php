<?php

/**
 * Gallery component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Media;

use Override;
use Blush\Component\Component;
use Blush\Component\ComponentContent;
use Blush\Component\ComponentView;
use Blush\Core\Framework;

/**
 * Its content (usually images) in up to 1 to 6 columns (D-113, D-195,
 * D-198), laid out as rows that grow to fill the width (`flex`, the
 * default) or as an even grid (`grid`):
 *
 * ```md
 * :::gallery{columns=3 layout=grid}
 * ![](a.jpg)
 * ![](b.jpg)
 * ![](c.jpg)
 * :::
 * ```
 *
 * The layout is a modifier (`component-gallery--grid`), and the columns
 * are `--gallery-columns` on the element.
 */
final class Gallery extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Blocks;

	/**
	 * @inheritDoc
	 */
	public const array HOLDS = ['image'];

	/**
	 * The number of columns, from 1 to 6.
	 */
	public readonly int $columns;

	public function __construct(
		int $columns = 3,
		public readonly GalleryLayout $layout = GalleryLayout::Flex
	) {
		$this->columns = max(1, min(6, $columns));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function modifiers(): array
	{
		return [$this->layout->value];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function rootAttributes(): array
	{
		return ['style' => "--gallery-columns: {$this->columns}"];
	}

	/**
	 * Renders the framework's template for it, `resources/components/gallery.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): ComponentView
	{
		return $this->view(Framework::path('resources/components/gallery.php'));
	}
}
