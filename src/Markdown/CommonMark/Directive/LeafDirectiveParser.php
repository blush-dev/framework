<?php

/**
 * Leaf directive parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Directive;

use Override;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * A leaf directive is one line, so it never continues.
 */
final class LeafDirectiveParser extends AbstractBlockContinueParser
{
	public function __construct(private readonly LeafDirective $block)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getBlock(): LeafDirective
	{
		return $this->block;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
	{
		return BlockContinue::none();
	}
}
