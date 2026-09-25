<?php

/**
 * Fixture: a controller that fails.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\View;

use RuntimeException;
use Psr\Http\Message\ResponseInterface;

final readonly class Explodes
{
	public function __invoke(): ResponseInterface
	{
		throw new RuntimeException('Controller exploded.');
	}
}
