<?php

/**
 * Core-key block fixture.
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
use Blush\View\Escaper;

/**
 * Reads a key core's catalog has too, and one only its text names.
 */
final class CoreKey extends Block
{
	public const ?BlockKind KIND = BlockKind::Leaf;

	#[Override]
	public function render(): string
	{
		return Escaper::html((string) $this->messages->t('progress.label') . '|' . (string) $this->messages->t('hello.new', 'Brand new, {name}', name: 'Ada'));
	}
}
