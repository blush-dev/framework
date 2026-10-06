<?php

/**
 * Defaulted directive fixture.
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

/**
 * A directive that declares a variant named `default`, which can't be
 * registered.
 */
final class Defaulted extends Directive
{
	public const ?DirectiveKind KIND = DirectiveKind::Leaf;

	public const array VARIANTS = ['default'];

	#[Override]
	public function render(): string
	{
		return '';
	}
}
