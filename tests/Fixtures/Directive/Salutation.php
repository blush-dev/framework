<?php

/**
 * Salutation directive fixture.
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
 * A directive that renders translated text of its own (D-451).
 */
final class Salutation extends Directive
{
	public const ?DirectiveKind KIND = DirectiveKind::Leaf;

	#[Override]
	public function render(): string
	{
		return '<p>' . Escaper::html($this->t('greeting', name: 'Ada')) . '</p>';
	}
}
