<?php

/**
 * Figure component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Layout;

use Override;
use Blush\Component\Component;
use Blush\Component\ComponentContent;
use Blush\View\Escaper;

/**
 * A figure (D-113, D-195, D-267): a container for anything that should
 * be set apart with a caption (the label), such as an image, a table, a
 * code block, or a quote.
 *
 * ```md
 * :::figure[Visitors by month]
 * | Month | Visitors |
 * | ----- | -------- |
 * | May   | 1,204    |
 * :::
 * ```
 *
 * An image alone in a paragraph inside it is the image itself, not a
 * figure of its own (`Markdown\CommonMark\FigureRenderer`). Without
 * content, nothing renders.
 */
final class Figure extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Blocks;

	public function __construct(public readonly string $label = '')
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return trim($this->content()) !== '';
	}

	/**
	 * Returns the caption, as HTML: the label, escaped, or `''`.
	 */
	public function caption(): string
	{
		return Escaper::html(trim($this->label));
	}
}
