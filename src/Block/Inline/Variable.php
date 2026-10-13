<?php

/**
 * Variable block.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block\Inline;

use Override;
use Blush\Block\Block;
use Blush\Block\BlockContent;
use Blush\Block\BlockView;
use Blush\Core\Framework;
use Blush\Block\BlockKind;

/**
 * A variable in math or code (D-305): `:var[x]`. (`Var` is a reserved
 * word in PHP, so the class has the longer name.)
 */
final class Variable extends Block
{
	/**
	 * @inheritDoc
	 */
	public const BlockContent CONTENT = BlockContent::Text;

	/**
	 * @inheritDoc
	 */
	public const ?BlockKind KIND = BlockKind::Inline;

	public function __construct(
		public readonly string $label = ''
	) {}

	/**
	 * Returns the text, as HTML: the content, else the label escaped.
	 */
	public function text(): string
	{
		return $this->contentOr($this->label);
	}

	/**
	 * Renders the framework's template for it, `resources/blocks/var.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): BlockView
	{
		return $this->view(Framework::path('resources/blocks/var.php'));
	}
}
