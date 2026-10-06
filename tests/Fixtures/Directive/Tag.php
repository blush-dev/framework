<?php

/**
 * Tag directive fixture.
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
 * An inline directive drawn only by a template in the chain.
 */
final class Tag extends Directive
{
	public const DirectiveContent CONTENT = DirectiveContent::Text;

	public const ?DirectiveKind KIND = DirectiveKind::Inline;

	#[Override]
	public function render(): null
	{
		return null;
	}
}
