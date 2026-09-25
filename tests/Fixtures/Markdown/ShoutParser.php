<?php

/**
 * Shout inline parser fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Markdown;

use Override;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Turns `!!word` into `WORD`, standing in for a site's inline parser.
 */
final class ShoutParser implements InlineParserInterface
{
	#[Override]
	public function getMatchDefinition(): InlineParserMatch
	{
		return InlineParserMatch::regex('!!([a-z]+)');
	}

	#[Override]
	public function parse(InlineParserContext $inlineContext): bool
	{
		[$word] = $inlineContext->getSubMatches();
		$inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
		$inlineContext->getContainer()->appendChild(new Text(strtoupper((string) $word)));

		return true;
	}
}
