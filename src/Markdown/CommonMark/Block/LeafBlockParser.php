<?php

/**
 * Leaf block parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Block;

use Override;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * A leaf block is one line, so it never continues.
 */
final class LeafBlockParser extends AbstractBlockContinueParser
{
	public function __construct(private readonly LeafBlock $block)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getBlock(): LeafBlock
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
