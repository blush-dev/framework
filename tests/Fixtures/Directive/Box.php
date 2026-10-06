<?php

/**
 * Box directive fixture.
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
use Blush\Directive\DirectiveContent;
use Blush\Directive\DirectiveKind;

/**
 * A container drawn only by a template in the chain, with a `wide`
 * variant of its own.
 */
final class Box extends Directive
{
	public const DirectiveContent CONTENT = DirectiveContent::Blocks;

	public const ?DirectiveKind KIND = DirectiveKind::Container;

	public const array VARIANTS = ['wide'];

	#[Override]
	public function render(): null
	{
		return null;
	}
}
