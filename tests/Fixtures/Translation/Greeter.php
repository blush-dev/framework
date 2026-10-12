<?php

/**
 * Greeter fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Translation;

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * A plugin's service that writes text, given its own `Messages`.
 */
final readonly class Greeter
{
	public function __construct(public Messages $messages)
	{}

	public function greeting(): Text
	{
		return $this->messages->t('greeting', 'Hello, {name}.', name: 'Ada');
	}
}
