<?php

/**
 * Fixture: a directive renderer that describes what it gets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Markdown;

use Override;
use Blush\Markdown\Directive;
use Blush\Markdown\DirectiveRenderer;

final class EchoDirectives implements DirectiveRenderer
{
	/**
	 * @var list<Directive>
	 */
	public array $seen = [];

	#[Override]
	public function render(Directive $directive): ?string
	{
		$this->seen[] = $directive;

		return $directive->name === 'unknown'
			? null
			: sprintf('<%s-%s>%s</%1$s-%2$s>', $directive->kind->value, $directive->name, $directive->content);
	}
}
