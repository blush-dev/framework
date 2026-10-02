<?php

/**
 * Orphan component fixture.
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

final class Orphan extends Component
{
	/**
	 * No markup of its own, and no template.
	 */
	#[Override]
	public function render(): null
	{
		return null;
	}
}
