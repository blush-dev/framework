<?php

/**
 * Fixture: a block renderer that describes what it gets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Markdown;

use Override;
use Blush\Block\ParsedBlock;
use Blush\Block\BlockRenderer;

final class EchoBlocks implements BlockRenderer
{
	/**
	 * @var list<ParsedBlock>
	 */
	public array $seen = [];

	#[Override]
	public function render(ParsedBlock $block): ?string
	{
		$this->seen[] = $block;

		return $block->name === 'unknown'
			? null
			: sprintf('<%s-%s>%s</%1$s-%2$s>', $block->kind->value, $block->name, $block->content);
	}
}
