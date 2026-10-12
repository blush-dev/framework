<?php

/**
 * Core-key directive fixture.
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
 * Reads a key core's catalog has too, and one only its text names.
 */
final class CoreKey extends Directive
{
	public const ?DirectiveKind KIND = DirectiveKind::Leaf;

	#[Override]
	public function render(): string
	{
		return Escaper::html($this->t('progress.label') . '|' . $this->t('hello.new', 'Brand new, {name}', name: 'Ada'));
	}
}
