<?php

/**
 * Fixture: a context provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\View;

use Override;
use Blush\View\ContextProvider;

final class Greeting implements ContextProvider
{
	public int $calls = 0;

	#[Override]
	public function provide(string $view, array $data): array
	{
		$this->calls++;

		return ['greeting' => "Hello from {$view}", 'name' => 'provided'];
	}
}
