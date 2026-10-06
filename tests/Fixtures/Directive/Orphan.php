<?php

/**
 * Orphan directive fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Directive;

use Override;
use Blush\Directive\Directive;
use Blush\Directive\DirectiveKind;

final class Orphan extends Directive
{
	public const ?DirectiveKind KIND = DirectiveKind::Leaf;

	/**
	 * No markup of its own, and no template.
	 */
	#[Override]
	public function render(): null
	{
		return null;
	}
}
