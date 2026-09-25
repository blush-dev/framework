<?php

/**
 * URL generator tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Routing\RouteConfig;
use Blush\Routing\UrlGenerationException;
use Blush\Routing\UrlGenerator;
use Blush\Tests\Fixtures\Routing\Color;

#[CoversClass(UrlGenerator::class)]
#[CoversClass(RouteConfig::class)]
final class UrlGeneratorTest extends TestCase
{
	use BootsRoutedSite;

	protected function tearDown(): void
	{
		$this->unregisterAutoloader();
	}

	private function urls(string $trailingSlash = 'false'): UrlGenerator
	{
		return $this->boot($this->routesConfig($trailingSlash))->container()->make(UrlGenerator::class);
	}

	public function testGeneratesPaths(): void
	{
		$urls = $this->urls();

		$this->assertSame('/about', $urls->to('about'));
		$this->assertSame('/archives/2024', $urls->to('archive.year', ['year' => 2024]));
		$this->assertSame('/archives/color/red', $urls->to('archive.color', ['color' => Color::Red]));
		$this->assertSame('/files/a%20b/c.txt', $urls->to('file', ['name' => 'a b/c.txt']));
		$this->assertTrue($urls->has('feed'));
	}

	public function testExtraValuesBecomeTheQuery(): void
	{
		$this->assertSame(
			'/archives/2024/05?page=2&q=a%20b',
			$this->urls()->to('archive.month', ['year' => 2024, 'month' => '05', 'page' => 2, 'q' => 'a b', 'skip' => null])
		);
	}

	public function testGeneratesAbsoluteUrls(): void
	{
		$this->assertSame('https://fixture.test/about', $this->urls()->to('about', absolute: true));
	}

	public function testFollowsTheTrailingSlashSetting(): void
	{
		$urls = $this->urls('true');

		$this->assertSame('/about/', $urls->to('about'));
		$this->assertSame('/files/c.txt', $urls->to('file', ['name' => 'c.txt']));
	}

	public function testRejectsUnknownNames(): void
	{
		$this->expectException(UrlGenerationException::class);

		$this->urls()->to('nope');
	}

	public function testRejectsValuesOutsideTheConstraint(): void
	{
		$this->expectException(UrlGenerationException::class);

		$this->urls()->to('archive.year', ['year' => 'latest']);
	}
}
