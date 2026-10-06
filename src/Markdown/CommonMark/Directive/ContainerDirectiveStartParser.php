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
use Blush\Markdown\DirectiveKind;
use Blush\Markdown\DirectiveRules;

/**
 * Opens a container directive on a `:::name[label]{attrs}` line (three
 * or more colons). Containers nest with `:::` throughout: a closing
 * fence closes the innermost open one (D-320). A `:::` line for a
 * directive registered as something else (`:::audio{…}`, D-530) is a
 * line of its own that renders as an unknown directive: opened, it would
 * take the fence closing a container around it.
 */
final class ContainerDirectiveStartParser implements BlockStartParserInterface
{
	public function __construct(private readonly ?DirectiveRules $rules = null)
	{}

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

		$forms = $this->rules?->forms((string) $match[2]);

		if ($forms !== null && ! in_array(DirectiveKind::Container, $forms, true)) {
			$leaf = new LeafDirective((string) $match[2], $match[3] ?? '', DirectiveAttributes::parse($match[4] ?? ''));
			$leaf->misplaced = true;

			return BlockStart::of(new LeafDirectiveParser($leaf))->at($cursor);
		}

		return BlockStart::of(new ContainerDirectiveParser(new ContainerDirective(
			(string) $match[2],
			$match[3] ?? '',
			DirectiveAttributes::parse($match[4] ?? ''),
			strlen((string) $match[1])
		)))->at($cursor);
	}
}
