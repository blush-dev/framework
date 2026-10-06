<?php

/**
 * Container directive parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Directive;

use Override;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * Holds a container directive's blocks until a closing fence of at least
 * as many colons (`:::`), or the end of the document. A closing fence
 * closes the innermost open container it's long enough for (D-320), so
 * nested containers can all use `:::`; one that's still open inside this
 * one takes the fence first.
 */
final class ContainerDirectiveParser extends AbstractBlockContinueParser
{
	public function __construct(private readonly ContainerDirective $block)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getBlock(): ContainerDirective
	{
		return $this->block;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function isContainer(): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function canContain(AbstractBlock $childBlock): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): BlockContinue
	{
		if (
			! $cursor->isIndented()
			&& preg_match('/^(:{3,})\s*$/', ltrim($cursor->getRemainder(), " \t"), $match) === 1
			&& strlen($match[1]) >= $this->block->fence
			&& ! $this->closesInside($activeBlockParser->getBlock(), strlen($match[1]))
		) {
			$this->block->closed = true;

			return BlockContinue::finished();
		}

		return BlockContinue::at($cursor);
	}

	/**
	 * Whether a container still open inside this one takes a closing
	 * fence of `$length` colons. The innermost open block's parents, up
	 * to this one, are the blocks open inside it.
	 */
	private function closesInside(Node $tip, int $length): bool
	{
		for ($node = $tip; $node !== null && $node !== $this->block; $node = $node->parent()) {
			if ($node instanceof ContainerDirective && $node->fence <= $length) {
				return true;
			}
		}

		return false;
	}
}
