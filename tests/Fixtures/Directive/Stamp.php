<?php

/**
 * Stamp directive fixture.
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
use Blush\View\Escaper;

/**
 * A directive that renders itself as a string (D-382).
 */
final class Stamp extends Directive
{
	public const ?DirectiveKind KIND = DirectiveKind::Leaf;

	public function __construct(public readonly string $text = '')
	{}

	#[Override]
	public function render(): string
	{
		return '<span ' . $this->attributes() . '>' . Escaper::html($this->text) . '</span>';
	}
}
