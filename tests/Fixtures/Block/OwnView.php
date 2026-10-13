<?php

/**
 * Own-view block fixture.
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
use Blush\Block\BlockView;

/**
 * Renders the template file it's given, as a plugin's block renders
 * the one it ships with.
 */
final class OwnView extends Block
{
	public const ?BlockKind KIND = BlockKind::Leaf;

	public function __construct(public readonly string $file = '')
	{}

	#[Override]
	public function render(): BlockView
	{
		return $this->view($this->file);
	}
}
