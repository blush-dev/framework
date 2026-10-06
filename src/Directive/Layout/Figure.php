<?php

/**
 * Figure directive.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive\Layout;

use Override;
use Blush\Directive\Directive;
use Blush\Directive\DirectiveContent;
use Blush\Directive\DirectiveView;
use Blush\Core\Framework;
use Blush\View\Escaper;
use Blush\Directive\DirectiveKind;

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
final class Figure extends Directive
{
	/**
	 * @inheritDoc
	 */
	public const DirectiveContent CONTENT = DirectiveContent::Blocks;

	/**
	 * @inheritDoc
	 */
	public const ?DirectiveKind KIND = DirectiveKind::Container;

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

	/**
	 * Renders the framework's template for it, `resources/directives/figure.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): DirectiveView
	{
		return $this->view(Framework::path('resources/directives/figure.php'));
	}
}
