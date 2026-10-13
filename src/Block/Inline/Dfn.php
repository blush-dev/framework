<?php

/**
 * Definition block.
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
 * The defining instance of a term, in the sentence that defines it
 * (D-305): `:dfn[Blush] is a flat-file CMS.` When the text isn't the term
 * as written, `title` is: `:dfn[flat files]{title="flat-file storage"}`.
 */
final class Dfn extends Block
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
		public readonly string $title = '',
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
	 * @inheritDoc
	 */
	#[Override]
	protected function rootAttributes(): array
	{
		return ['title' => trim($this->title)];
	}

	/**
	 * Renders the framework's template for it, `resources/blocks/dfn.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): BlockView
	{
		return $this->view(Framework::path('resources/blocks/dfn.php'));
	}
}
