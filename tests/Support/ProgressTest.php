<?php

/**
 * Progress tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Support\Progress;

#[CoversClass(Progress::class)]
final class ProgressTest extends TestCase
{
	public function testReportsStepsAndCounts(): void
	{
		$seen     = [];
		$progress = new Progress(static function (string $step, int $done, int $total) use (&$seen): void {
			$seen[] = "{$step} {$done}/{$total}";
		});

		$progress->step('Writing', 1, 2);
		$counts = $progress->of('Indexing');

		$this->assertNotNull($counts);

		$counts(3, 4);

		$this->assertSame(['Writing 1/2', 'Indexing 0/0', 'Indexing 3/4'], $seen);
	}

	public function testSaysNothingWithoutACallback(): void
	{
		$progress = new Progress();
		$progress->step('Writing', 1, 2);

		$this->assertNull($progress->of('Indexing'));
	}
}
