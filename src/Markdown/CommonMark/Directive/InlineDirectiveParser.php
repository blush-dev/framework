<?php

/**
 * Inline directive parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Directive;

use Override;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Reads `:name[text]{attrs}` inside a paragraph. The `[text]` is
 * required, and the colon must not follow a letter, digit, or colon, so
 * `https://…` and times like `10:30` stay text.
 */
final class InlineDirectiveParser implements InlineParserInterface
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getMatchDefinition(): InlineParserMatch
	{
		return InlineParserMatch::regex(':([A-Za-z][A-Za-z0-9_-]*)\[([^\]\n]*)\](?:\{([^}\n]*)\})?');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parse(InlineParserContext $inlineContext): bool
	{
		$cursor   = $inlineContext->getCursor();
		$previous = $cursor->peek(-1);

		if ($previous !== null && preg_match('/[\w:]/u', $previous) === 1) {
			return false;
		}

		$matches = $inlineContext->getSubMatches();

		$cursor->advanceBy($inlineContext->getFullMatchLength());
		$inlineContext->getContainer()->appendChild(new InlineDirective(
			(string) ($matches[0] ?? ''),
			(string) ($matches[1] ?? ''),
			DirectiveAttributes::parse((string) ($matches[2] ?? ''))
		));

		return true;
	}
}
