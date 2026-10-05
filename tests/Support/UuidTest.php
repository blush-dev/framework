<?php

/**
 * UUID tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Support;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Support\Uuid;

#[CoversClass(Uuid::class)]
final class UuidTest extends TestCase
{
	public function testMakesVersionSevenUuids(): void
	{
		$time = new DateTimeImmutable('2026-10-05 12:34:56.789 UTC');
		$uuid = Uuid::v7($time);

		$this->assertMatchesRegularExpression('/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $uuid, 'Version 7, variant 10xx, lowercase.');
		$this->assertSame(sprintf('%012x', 1791203696789), str_replace('-', '', substr($uuid, 0, 13)), 'It starts with the time in milliseconds.');
		$this->assertNotSame($uuid, Uuid::v7($time), 'The rest is random.');
	}

	public function testSortsByTime(): void
	{
		$earlier = Uuid::v7(new DateTimeImmutable('2026-10-05 12:00:00.000 UTC'));
		$later   = Uuid::v7(new DateTimeImmutable('2026-10-05 12:00:00.001 UTC'));

		$this->assertLessThan(0, strcmp($earlier, $later));
	}

	/**
	 * @return array<string, array{mixed, bool}>
	 */
	public static function values(): array
	{
		return [
			'version 7'         => ['0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74', true],
			'version 4'         => ['9f8b1c2e-4d5a-4b6c-8d7e-0f1a2b3c4d5e', true],
			'uppercase'         => ['0199B6E2-7F3A-7C41-9D2E-5A8F0C3B1E74', true],
			'nil'               => ['00000000-0000-0000-0000-000000000000', false],
			'max'               => ['FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF', false],
			'without hyphens'   => ['0199b6e27f3a7c419d2e5a8f0c3b1e74', false],
			'braces'            => ['{0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74}', false],
			'a trailing line'   => ["0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74\n", false],
			'not hex'           => ['0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e7g', false],
			'a number'          => [42, false],
			'nothing'           => [null, false]
		];
	}

	#[DataProvider('values')]
	public function testChecksValues(mixed $value, bool $valid): void
	{
		$this->assertSame($valid, Uuid::isValid($value));
	}
}
