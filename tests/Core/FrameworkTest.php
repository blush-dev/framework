<?php

/**
 * Framework identity tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Framework;

#[CoversClass(Framework::class)]
final class FrameworkTest extends TestCase
{
	public function testVersionIsSemantic(): void
	{
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/',
			Framework::VERSION
		);
	}

	public function testRunsOnSupportedPhp(): void
	{
		$this->assertTrue(version_compare(PHP_VERSION, '8.5.0', '>='));
	}
}
