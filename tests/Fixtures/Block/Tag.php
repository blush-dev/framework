<?php

/**
 * Tag block fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Block;

use Override;
use Blush\Block\Block;
use Blush\Block\BlockContent;
use Blush\Block\BlockKind;

/**
 * An inline block drawn only by a template in the chain.
 */
final class Tag extends Block
{
	public const BlockContent CONTENT = BlockContent::Text;

	public const ?BlockKind KIND = BlockKind::Inline;

	#[Override]
	public function render(): null
	{
		return null;
	}
}
