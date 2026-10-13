<?php

/**
 * Leaf block start parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Block;

use Override;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Reads a `::name[label]{attrs}` line (exactly two colons) as a leaf
 * block.
 */
final class LeafBlockStartParser implements BlockStartParserInterface
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
	{
		if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== ':') {
			return BlockStart::none();
		}

		$line = ltrim($cursor->getRemainder(), " \t");

		if (preg_match('/^::' . BlockAttributes::SYNTAX . '\s*$/', $line, $match, PREG_UNMATCHED_AS_NULL) !== 1) {
			return BlockStart::none();
		}

		$cursor->advanceToEnd();

		return BlockStart::of(new LeafBlockParser(new LeafBlock(
			(string) $match[1],
			$match[2] ?? '',
			BlockAttributes::parse($match[3] ?? '')
		)))->at($cursor);
	}
}
