<?php

/**
 * Fixture: a component with markup of its own.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Component;

use Override;
use Blush\Component\Component;
use Blush\View\Escaper;

/**
 * A component that renders itself (D-382) unless the chain has a template
 * for it.
 */
final class Chip extends Component
{
	public function __construct(public readonly string $text = '')
	{}

	#[Override]
	public function render(): string
	{
		return '<span ' . $this->attributes() . '>' . Escaper::html($this->text) . '</span>';
	}
}
