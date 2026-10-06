<?php

/**
 * File name pattern tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Type;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Type\FileName;
use Blush\Content\Type\InvalidContentType;

#[CoversClass(FileName::class)]
final class FileNameTest extends TestCase
{
	public function testNamesFilesFromADateAndSlug(): void
	{
		$date = new DateTimeImmutable('2026-10-05 09:03:07');

		$this->assertSame('hello', new FileName('{slug}')->name('hello', $date));
		$this->assertSame('2026-10-05.hello', new FileName('{date}.{slug}')->name('hello', $date));
		$this->assertSame('2026-10-05-090307.hello', new FileName('{date}-{time}.{slug}')->name('hello', $date));
		$this->assertSame('2026.10.post-05.hello', new FileName('{year}.{month}.post-{day}.{slug}')->name('hello', $date));
		$this->assertSame('09-03-07.', new FileName('{hour}-{minute}-{second}.{slug}')->prefix($date));
		$this->assertSame('', new FileName('{slug}')->prefix($date));
	}

	public function testDefaultsToTheSlugAlone(): void
	{
		$this->assertSame(FileName::PLAIN, FileName::byDefault()->pattern, 'D-515');
		$this->assertFalse(FileName::byDefault()->isDated());
		$this->assertTrue(new FileName(FileName::DATED)->isDated());
	}

	public function testRejectsPatternsThatWouldChangeTheSlug(): void
	{
		$cases = [
			'{date}'               => 'must end in {slug}.',
			'{slug}.md'            => 'must end in {slug}.',
			'{date}{slug}'         => 'needs a "." before {slug}',
			'{date}.{slug}.{slug}' => 'uses {slug}; the tokens are',
			'{week}.{slug}'        => 'uses {week}; the tokens are',
			'_{date}.{slug}'       => 'may use only the tokens',
			'.{slug}'              => 'may use only the tokens',
			'{date}/x.{slug}'      => 'may use only the tokens'
		];

		foreach ($cases as $pattern => $message) {
			try {
				new FileName($pattern);
				$this->fail($pattern);
			} catch (InvalidContentType $e) {
				$this->assertStringContainsString($message, $e->getMessage(), $pattern);
			}
		}
	}
}
