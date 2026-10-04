<?php

/**
 * Salutation component fixture.
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
 * A component that renders translated text of its own (D-451).
 */
final class Salutation extends Component
{
	#[Override]
	public function render(): string
	{
		return '<p>' . Escaper::html($this->t('greeting', name: 'Ada')) . '</p>';
	}
}
