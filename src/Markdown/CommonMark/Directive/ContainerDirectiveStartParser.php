<?php

/**
 * Container directive start parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Directive;

use Override;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Opens a container directive on a `:::name[label]{attrs}` line (three
 * or more colons). A container nested in another uses fewer colons than
 * its parent, so the parent's closing fence is longer.
 */
final class ContainerDirectiveStartParser implements BlockStartParserInterface
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

		if (preg_match('/^(:{3,})\s*' . DirectiveAttributes::SYNTAX . '\s*$/', $line, $match, PREG_UNMATCHED_AS_NULL) !== 1) {
			return BlockStart::none();
		}

		$cursor->advanceToEnd();

		return BlockStart::of(new ContainerDirectiveParser(new ContainerDirective(
			(string) $match[2],
			$match[3] ?? '',
			DirectiveAttributes::parse($match[4] ?? ''),
			strlen((string) $match[1])
		)))->at($cursor);
	}
}
