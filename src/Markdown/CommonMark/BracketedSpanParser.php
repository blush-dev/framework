<?php

/**
 * Bracketed span parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark;

use Override;
use League\CommonMark\Environment\EnvironmentAwareInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\Attributes\Util\AttributesHelper;
use League\CommonMark\Node\Inline\AdjacentTextMerger;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Closes a bracket that's followed straight away by attributes,
 * `[text]{.class}`, as a `BracketedSpan` (D-305), instead of leaving it as
 * text that loses its attributes. It runs before league/commonmark's
 * closing bracket parser, and only when the next character is `{` and
 * what follows is a valid attribute list, which it leaves for the
 * attributes extension to parse and give the span. Links win: an image
 * (`![…]`), an inactive opener, and a label with a link reference
 * definition (`[docs]{.x}` with `[docs]: /docs`) are left alone.
 */
final class BracketedSpanParser implements InlineParserInterface, EnvironmentAwareInterface
{
	private EnvironmentInterface $environment;

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getMatchDefinition(): InlineParserMatch
	{
		return InlineParserMatch::string(']');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function setEnvironment(EnvironmentInterface $environment): void
	{
		$this->environment = $environment;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parse(InlineParserContext $inlineContext): bool
	{
		$stack  = $inlineContext->getDelimiterStack();
		$opener = $stack->getLastBracket();
		$cursor = $inlineContext->getCursor();

		if ($opener === null || $opener->isImage() || ! $opener->isActive() || $cursor->peek() !== '{') {
			return false;
		}

		$label = $cursor->getSubstring($opener->getPosition(), $cursor->getPosition() - $opener->getPosition());

		if ($inlineContext->getReferenceMap()->contains($label)) {
			return false;
		}

		$state = $cursor->saveState();
		$cursor->advanceBy(1);
		$attributes = AttributesHelper::parseAttributes($cursor);
		$cursor->restoreState($state);

		if ($attributes === []) {
			return false;
		}

		$cursor->advanceBy(1);

		$span = new BracketedSpan();
		$opener->getNode()->replaceWith($span);

		while (($child = $span->next()) !== null) {
			$span->appendChild($child);
		}

		// Emphasis and other delimiters inside the brackets close here.
		$bottom = $opener->getPosition();
		$stack->processDelimiters($bottom, $this->environment->getDelimiterProcessors());
		$stack->removeBracket();
		$stack->removeAll($bottom);

		AdjacentTextMerger::mergeChildNodes($span);

		return true;
	}
}
