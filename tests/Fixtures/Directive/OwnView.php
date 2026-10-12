<?php

/**
 * Own-view directive fixture.
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
use Blush\Directive\DirectiveView;

/**
 * Renders the template file it's given, as a plugin's directive renders
 * the one it ships with.
 */
final class OwnView extends Directive
{
	public const ?DirectiveKind KIND = DirectiveKind::Leaf;

	public function __construct(public readonly string $file = '')
	{}

	#[Override]
	public function render(): DirectiveView
	{
		return $this->view($this->file);
	}
}
