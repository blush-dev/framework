<?php

/**
 * Container block start parser.
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
use Blush\Block\BlockKind;
use Blush\Block\BlockRules;

/**
 * Opens a container block on a `:::name[label]{attrs}` line (three
 * or more colons). Containers nest with `:::` throughout: a closing
 * fence closes the innermost open one (D-320). A `:::` line for a
 * block registered as something else (`:::audio{…}`, D-530) is a
 * line of its own that renders as an unknown block: opened, it would
 * take the fence closing a container around it.
 */
final class ContainerBlockStartParser implements BlockStartParserInterface
{
	public function __construct(private readonly ?BlockRules $rules = null)
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

		if (preg_match('/^(:{3,})\s*' . BlockAttributes::SYNTAX . '\s*$/', $line, $match, PREG_UNMATCHED_AS_NULL) !== 1) {
			return BlockStart::none();
		}

		$cursor->advanceToEnd();

		$forms = $this->rules?->forms((string) $match[2]);

		if ($forms !== null && ! in_array(BlockKind::Container, $forms, true)) {
			$leaf = new LeafBlock((string) $match[2], $match[3] ?? '', BlockAttributes::parse($match[4] ?? ''));
			$leaf->misplaced = true;

			return BlockStart::of(new LeafBlockParser($leaf))->at($cursor);
		}

		return BlockStart::of(new ContainerBlockParser(new ContainerBlock(
			(string) $match[2],
			$match[3] ?? '',
			BlockAttributes::parse($match[4] ?? ''),
			strlen((string) $match[1])
		)))->at($cursor);
	}
}
