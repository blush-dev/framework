<?php

/**
 * Salutation block fixture.
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
 * A block that renders translated text of its own (D-451).
 */
final class Salutation extends Block
{
	public const ?BlockKind KIND = BlockKind::Leaf;

	#[Override]
	public function render(): string
	{
		return '<p>' . Escaper::html((string) $this->messages->t('greeting', name: 'Ada')) . '</p>';
	}
}
