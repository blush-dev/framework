<?php

/**
 * Orphan block fixture.
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
use Blush\Block\BlockKind;

final class Orphan extends Block
{
	public const ?BlockKind KIND = BlockKind::Leaf;

	/**
	 * No markup of its own, and no template.
	 */
	#[Override]
	public function render(): null
	{
		return null;
	}
}
