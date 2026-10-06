<?php

/**
 * Fixture: a component class with no template.
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

/**
 * A component class that relies on a template in the chain and has none.
 */
final class Orphan extends Component
{
	#[Override]
	public function render(): null
	{
		return null;
	}
}
